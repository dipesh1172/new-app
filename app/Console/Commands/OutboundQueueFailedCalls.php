<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client as TwilioClient;

use App\Models\Interaction;
use App\Models\OutboundCallQueue;

class OutboundQueueFailedCalls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbound:call:queue:failed-calls
                            {--dry-run : Don\'t make changes to data}
                            {--limit= : To be eligible for retry must be within this many minutes from now}
                            {--debug : Display extra debug messages}
                            {--no-mm : Prevent message posts to Mattermost}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks interactions for outbound call queue TPVs that failed to call out to the customer and requeus them';

    /**
     * Twilio REST client
     */
    private $twilio;

    /**
     * Mattemost message prepend string
     */
    private $mmPrepend = '';

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

        $this->mmPrepend = '[OutboundCallQueueFailedCalls]';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $limit  = $this->option('limit');
        $dryRun = $this->option('dry-run');
        $noMm   = $this->option('no-mm');

        if(!$limit) {
            $this->error('Missing required arg: --limit');
            return -1;
        }

        // Never look for interactions older than this date
        $oldestDate = Carbon::parse('2024-01-29 00:00:00', 'America/Chicago');

        // Build start/end date range
        $now = Carbon::now('America/Chicago');
        $startDate = $now->clone()->subMinutes($limit);

        // If limit is < 5 minutes, grab all interactions between now and 5 minutes ago.
        if ($limit > 5) {
            $endDate = $now->clone()->subMinutes(5);
        } else {
            // else, grab all interactions between now - limit and 5 minutes ago (builds 5 minute gap)
            $endDate = $now;
        }

        //  We don't need the brand filter anymore, this is happening across brands
        /*$brands = [
            '0daf01ac-1ff6-403d-8cbc-3bd08f90c72f', // Solomon, old staging
            'e156442c-3edd-4a15-9ee8-ca2e97ec2e6f'  // Solomon, prod
        ];*/

        // Get the interaction data
        $this->info('Searching for outbound call queue interactions with failed outbound calls.');

        $interactionsToCheck = Interaction::select(
            'events.confirmation_code',
            'brands.name AS brand_name',
            'phone_numbers.phone_number',
            'outbound_call_queue.id AS ocq_id',
            'event_product.auth_first_name',
            'event_product.auth_last_name',
            'interactions.*'
        )->join('events', 'interactions.event_id', 'events.id')
        ->join('event_product', 'events.id', 'event_product.event_id')
        ->join('brands', 'events.brand_id', 'brands.id')
        ->join('outbound_call_queue', 'interactions.id', 'outbound_call_queue.interaction_id')
        ->join('phone_number_lookup', function($join) {
            $join->on('events.id', 'phone_number_lookup.type_id');
            $join->where('phone_number_lookup.phone_number_type_id', 3); // 3 == 'event'
        })
        ->join('phone_numbers', 'phone_number_lookup.phone_number_id', 'phone_numbers.id')
        ->where('interactions.outbound_call_requeued', 0)
        ->where('interactions.interaction_type_id', 2) // call_outbound
        ->whereNull('interactions.tpv_staff_id')
        ->whereNull('interactions.event_result_id')
        ->whereNotNull('outbound_call_queue.sid')
        ->where('outbound_call_queue.sid', '<>', '')
        ->whereNotNull('outbound_call_queue.interaction_id')
        ->where('interactions.created_at', '>' , $oldestDate)
        ->where('interactions.created_at', '>' , $startDate)
        ->where('interactions.created_at', '<', $endDate);

        if($this->option('debug')) {
            $this->info("QUERY:");
            $this->info($interactionsToCheck->toSql());
            echo $interactionsToCheck->toSql();

            $this->info("BINDINGS:");
            print_r($interactionsToCheck->getBindings());
        }

        $interactionsToCheck = $interactionsToCheck->get();

        // Exit early if no records to process
        $interactionCount = count($interactionsToCheck);
        $ctr = 0;

        $this->info('Found ' . count($interactionsToCheck) . ' interaction(s)');
        if(count($interactionsToCheck) == 0) {
            $this->info('No records to process. Exiting.');
            return -1;
        }

        // For each interaction located, check Twilio if an outbound call occurred.
        // If No, requeue
        foreach($interactionsToCheck as $interaction) {

            $ctr++;

            $this->info('------------------------------------------------------------');
            $this->info('[' . $ctr . ' / ' . $interactionCount . ']');
            $this->info('');
            $this->info('OCQ ID: ' . $interaction->ocq_id);
            $this->info('Interaction ID: ' . $interaction->id);
            $this->info('Phone: ' . $interaction->phone_number);

            try {    
                if(!$noMm) {
                    SendTeamMessage('monitoring', $this->mmPrepend . '[Conf #: ' . $interaction->confirmation_code . '][Brand: ' . $interaction->brand_name . '] Found interaction with failed outbound call. Attempting to requeue.');
                }

                $this->info('Searching for outbound call in Twilio to number ' . $interaction->phone_number);

                // Locate outbound call in Twilio
                $startTime = $now->clone()->format('Y-m-d') . 'T00:00:00Z';

                $calls = $this->twilio->calls->read([
                    'to' => $interaction->phone_number,
                    'startTime' => $startTime
                ]);

                // Check if the locate call(s) are for today. If so, do not requeue
                $requeue = true;

                foreach($calls as $call) {
                    $twilioDate  = intval(Carbon::parse($call->startTime)->startOfDay()->format("Ymd"));
                    $currentDate = intval($now->clone()->startOfDay()->format("Ymd"));

                    if($twilioDate >= $currentDate) {
                        $requeue = false;
                        break;
                    }
                }

                // Requeue if no calls in Twilio
                if($requeue) {
                    $this->info('Not found. Requeuing...');

                    $ocq = OutboundCallQueue::where('id', $interaction->ocq_id)->first();

                    if (!$dryRun) {                        
                        $ocq->sent = 0;
                        $ocq->save();

                        $this->info('Outbound call was requeued....sending notification....');

                        $subject = $interaction->brand_name .' : Outbound call Re-Queue for '. $interaction->phone_number;
                        $data = [
                            'subject' => '',
                            'firstName' => $interaction->auth_first_name,
                            'lastName'  => $interaction->auth_last_name,
                            'phone'     => $interaction->phone_number
                        ];

                        $email_address = [
                            'accountmanagers@answernet.com'
                        ];

                        Mail::send(
                            'emails.outbound_call_requeue',
                            $data,
                            function ($message) use ($subject, $email_address) {
                                $message->subject($subject);
                                $message->from('no-reply@tpvhub.com');
                                $message->to($email_address);
                            }
                        );

                        $this->info('Outbound call notification sent....');

                        if(!$noMm) {
                            SendTeamMessage('monitoring', $this->mmPrepend . '[Conf #: ' . $interaction->confirmation_code . '][Brand: ' . $interaction->brand_name . '] Outbound call was requeued.');
                        }

                    } else {
                        $this->info('Dry-run. Outbound call was NOT requeued.');
                    }
                } else {
                    $this->info('Found. Will not requeue.');

                    if(!$noMm) {
                        SendTeamMessage('monitoring', $this->mmPrepend . '[Conf #: ' . $interaction->confirmation_code . '][Brand: ' . $interaction->brand_name . '] Found an outbound call in Twilio. Call will not be requeued.');
                    }
                }

                // Regardless of whether call was requeued, flag this interaction as requeued so that
                // this job doesn't pick it up again
                if(!$dryRun) {
                    $interaction2 = Interaction::where('id', $interaction->id)->first();

                    $interaction2->outbound_call_requeued = 1;
                    $interaction2->save();
                }

            } catch (\Exception $e) {
                if(!$noMm) {
                    SendTeamMessage('monitoring', $this->mmPrepend . '[Conf #: ' . $interaction->confirmation_code . '][Brand: ' . $interaction->brand_name . '] Failed to requeue outbound call. Error: ' . $e->getMessage());
                }
            }
        }
    }
}
