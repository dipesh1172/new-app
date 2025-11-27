<?php

namespace App\Console\Commands;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\OutboundCallQueue;
use App\Models\Interaction;
use App\Models\Event;

class OutboundQueueCompleted extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:outbound-queue
                            {--dryrun : Don\'t make changes to data}
                            {--limit= : To be eligible for retry must be within this many minutes from now}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks Outbound Call Queue items for an actual call';

    private $twilio;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->twilio = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $toCheck = OutboundCallQueue::whereNotNull('sid')->where('sid', '<>', '')->whereNull('interaction_id')->where('sent', '<', 2)->get();
        $this->info('Stage 1: Verify Interactions');
        
        $bar = $this->output->createProgressBar($toCheck->count());
        $bar->start();
        
        $now = Carbon::now('America/Chicago');
        $withLimit = $now->clone()->subMinutes($limit);
        if ($limit > 5) {
            $upperLimit = $now->clone()->subMinutes(5);
        } else {
            $upperLimit = $now->clone();
        }
  
        $cantVerify = $toCheck->filter(function ($item) use ($bar, $withLimit, $upperLimit) {

            $newdate = new Carbon($item->sent_at, 'America/Chicago');
            if ($newdate->lessThan($withLimit) || $newdate->greaterThan($upperLimit)) {
                return false;
            }

            $retVal = true;
            $interactions = Interaction::where('event_id', $item->event_id)
                ->where('created_at', '>=', $newdate)
                ->whereIn('interaction_type_id', [1, 2]) // only look at types that do a call
                ->orderBy('interaction_type_id', 'Asc')
                ->get();

            foreach ($interactions as $interaction) {
                if ($interaction->event_result_id < 3 && in_array($interaction->interaction_type_id, [1,2])) {
                    if (!$this->option('dryrun')) {
                        $item->interaction_id = $interaction->id;
                        $item->save();

                        if ( preg_match("/[0-9]{10}\.[0-9]{1,6}/", $interaction->session_id ) && $interaction->interaction_type_id == 1 ) {
                            $this->info("Deleting The Offensive Record in Motion".$interaction->id);
                            $interaction->delete();
                        }                        
                    }
                    $retVal = false;
                }                
            };

            return $retVal;
        });

        $bar->finish();

        $this->line('');
        $this->info('Stage 2: Verify Tasks');
        
        $bar = $this->output->createProgressBar($cantVerify->count());
        $bar->start();
        
        $retry = $cantVerify->filter(function ($item) use ($bar) {

            try {

                switch(substr($item->sid, 0,2)) {

                    // Handle Twilio requeue
                    case "WT":
                        $task = $this->twilio->taskrouter->v1->workspaces(config('services.twilio.workspace'))->tasks($item->sid)->fetch();
                        $bar->advance();
                        if (($task->assignmentStatus !== null && in_array($task->assignmentStatus, ['pending', 'reserved', 'assigned']))) {
                            return false;
                        }
                        return true;
                    break;

                    // Handle Motion requeue
                    case "CA":
                        $task = $this->twilio->calls($item->sid)->fetch();
                        switch($task->status){
                            case "in-progress":
                            case "queued":
                            case "ringing":
                                $bar->advance();
                                return false;
                                break;

                            case "completed":                         
                            case "failed":
                            case "busy":
                            case "canceled":
                            case "no-answer":
                                $bar->advance();
                                return true;
                                break;
                        }

                    break;
                }

            } catch (\Exception $e) {
                //$this->warn($e->getMessage());

                $bar->advance();
                return true;
            }
        });
        $bar->finish();
        $this->line('');
        $this->info('Potential retries: ' . $retry->count());
        $retry->each(function ($item) {
            $ev = Event::find($item->event_id);
            if ($ev && $ev->brand) {
                $this->info('Retrying... ' . $ev->brand->name . ': ' . $ev->confirmation_code);
                if (!$this->option('dryrun')) {
                    $item->sent = 0;
                    $item->save();
                }
            } else {
                $ev = Event::where('id', $item->event_id)->withTrashed()->first();
                $item->sent = 3;
                if ($ev && $ev->trashed()) {
                    $item->sent = 4;
                    return;
                }
                if (!$this->option('dryrun')) {
                    $item->save();
                }
                $msg = 'Failed to load Outbound Queue for Reinsertion.  Brand: ' . $ev->brand->name . ', Event ID: ' . $item->event_id .
                ', OutboundCallQueue ID: ' . $item->id . ', confirmation_code: ' . $ev->confirmation_code;

                SendTeamMessage('monitoring', $msg);
            }
        });
    }
}
