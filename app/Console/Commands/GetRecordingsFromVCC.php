<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Recording;
use App\Models\Interaction;
use App\Models\Event;

class GetRecordingsFromVCC extends Command
{
    private $_client;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:vccrecordings
        {--confirmation_code=}
        {--ignoreExistingRecordings}
        {--forever}
        {--nightly}
        {--dryrun}
        {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets the Interaction recordings from Twilio';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $interactions = Interaction::select(
            'interactions.notes',
            'interactions.session_call_id',
            'interactions.session_id',
            'brands.id AS brand_id',
            'interactions.id AS interaction_id',
            'interactions.interaction_time',
            'events.confirmation_code',
            'recordings.id as recording_id',
            'recordings.remote_status as recording_status'
        )->join(
            'events',
            'events.id',
            'interactions.event_id'
        )->join(
            'brands',
            'events.brand_id',
            'brands.id'
        )->leftJoin(
            'recordings',
            'interactions.id',
            'recordings.interaction_id'
        )->whereNotNull(
            'session_call_id'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2, 5, 8, 20, 21, 25]
        );

        if (!$this->option('ignoreExistingRecordings')) {
            $interactions = $interactions->where(
                function ($query) {
                    $query->whereNull('recordings.id')->orWhere(
                        function ($query2) {
                            $query2->whereNotNull('recordings.id')
                                ->whereNotIn('recordings.remote_status', ['absent', 'completed'])
                                ->whereNull('recordings.recording');
                        }
                    );
                }
            );
        }

        $interactions = $interactions->where(function($query) {
            $query->where(
                'interactions.session_id',
                'NOT LIKE',
                'DEVTASK%')
                ->orWhereNull(
                    'interactions.session_id'
                );
        })->where(
            'interactions.session_call_id',
            'NOT LIKE',
            'DEVCALL%'
        )->whereRaw('interactions.session_call_id regexp "[0-9]*\\\\.[0-9]*"');

        if ($this->option('confirmation_code')) {
            $interactions = $interactions->where(
                'events.confirmation_code',
                $this->option('confirmation_code')
            );
        } else {
            if (!$this->option('forever')) {
                if (!$this->option('nightly')) {
                    $interactions = $interactions->where(
                        'interactions.created_at',
                        '>=',
                        Carbon::now('America/Chicago')->subHours(2)
                    );
                } else {
                    $interactions = $interactions->where(
                        'interactions.created_at',
                        '>=',
                        Carbon::now('America/Chicago')->subDays(7)
                    );
                }
            }
        }

        $interactions = $interactions->get();
        $this->info("AMOUNT ".$interactions->count());
        foreach ($interactions as $interaction) {
            $this->info("Starting ({$interaction->confirmation_code}) {$interaction->interaction_id} {$interaction->call_session_id}...");
            $gotRecording = $this->_getRecordings($interaction->session_call_id, $interaction->brand_id, $interaction->interaction_id);
            if (!$gotRecording) {
                if ($this->option('debug')) {
                    $this->warn('Did not get recording for assigned sid: ' . $interaction->session_call_id);
                }
                $failedSids = [$interaction->session_call_id];
                $interactionNotes = $interaction->notes;
                if ($interactionNotes !== null) {
                    if (!empty($interactionNotes['session_id']) && is_array($interactionNotes['session_id']) && count($interactionNotes['session_id']) > 0) {
                        if ($this->option('debug')) {
                            $this->info('Checking alternate session ids');
                        }
                        foreach ($interactionNotes['session_id'] as $session_id) {
                            if ($this->option('debug')) {
                                $this->info('Checking: ' . $session_id);
                            }
                            if (!$gotRecording && !in_array($session_id, $failedSids)) {
                                $gotRecording = $this->_getRecordings($session_id, $interaction->brand_id, $interaction->interaction_id);
                                if (!$gotRecording) {
                                    if ($this->option('debug')) {
                                        $this->warn('Recording not found for sid: ' . $session_id);
                                    }
                                    $failedSids[] = $session_id;
                                } else {
                                    if ($this->option('debug')) {
                                        $this->info('Recording found with sid: ' . $session_id);
                                    }
                                    break;
                                }
                            }
                        }
                    } else {
                        if ($this->option('debug')) {
                            $this->warn("INTERACTION ITEMS".print_r($interactionNotes, true));
//                            $this->warn('!empty($interactionNotes["session_id"])? ' . (!empty($interactionNotes['session_id']) ? 'yes' : 'no'));
//                            $this->warn('is_array($interactionNotes["session_id"])? ' . (is_array($interactionNotes['session_id']) ? 'yes' : 'no'));
//                            $this->warn('count($interactionNotes["session_id"]) > 0? ' . (count($interactionNotes['session_id']) > 0 ? 'yes' : 'no'));

                        }
                    }
                } else {
                    if ($this->option('debug')) {
                        $this->warn('Interaction Notes is null (' . $interaction->interaction_id . ')');
                    }
                }
            } else {
                if ($this->option('debug')) {
                    $this->info('Recording was found for ' . $interaction->session_call_id);
                }
            }
        }
    }

    private function _getRecordings($callSid, $brand, $interactionID): bool
    {
        if (
            strlen(trim($callSid)) > 0
            && strlen(trim($brand)) > 0
            && strlen(trim($interactionID)) > 0
        ) {
            Log::info(' for Call SID {' . $callSid . '} for interaction {' . $interactionID . '}');

            $filename = "{$callSid}.wav";

            // $mp3 = str_replace('.json', '.wav', $recording->uri);

            $wav = $filename;//str_replace('.json', '.wav', $recording->uri);

            $path = str_replace("https://","http://", config('services.motion.file_url') . "/files/monitor/{$wav}");

            $s3path = config('services.motion.s3_bucket') . "/files/monitor/{$wav}";

            $headers = [];

            if ($this->option('debug')) {
                $this->info('Pulling recording from: ' . $path);
            }

            $int = Interaction::find($interactionID);

            if (!$this->option('dryrun')) {
                $size = 0; $size2 = 0;
                $rec = new Recording();
                $rec->interaction_id = $interactionID;
                $rec->recording = "/files/monitor/{$wav}";
                $rec->duration = $int->duration;

                try {
                    $headers = get_headers($path, true);
                    $size = $headers['Content-Length'];
                } catch (\Exception $ex ){
                    $this->info("GET VCC Recordings: Failed to get from Motion Server... " . $path);
                    try {


                        $s3path = file_get_contents(config('services.motion.signed_url').$filename);

                        $headers = get_headers($s3path, true);
                        $size = $headers['Content-Length']; 
                    }
                    catch(\Exception $ex){
                        $this->info("GET VCC Recordings: Failed to get from S3 Bucket Server..." . print_r($headers, true));
                        $size = 0;
                    }
                }

                $rec->size = $size; 
                $rec->save();

                if ($int) {
                    $int->session_call_id = $callSid;

                    if ($int->interaction_type_id === 8) {
                        $int->event_result_id = 1;
                        $int->disposition_id = null;
                    }

                    $int->save();

                    $event = Event::where(
                        'id',
                        $int->event_id
                    )->where(
                        'synced',
                        1
                    )->first();
                    if ($event) {
                        // If a recording is fetched,
                        //   and brand file sync has already been run,
                        //   reset synced to 0
                        $event->synced = 0;
                        $event->save();
                    }
                }

                return true;
            } else {
                $this->info(" -- would have written $path  to S3 files/monitor/$wav and created a recordings entry.\n");
            }

        }
        return false;
    }
}
