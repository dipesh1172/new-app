<?php

namespace App\Console\Commands;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

use Aws\S3\S3Client;
use Carbon\Carbon;

use App\Models\Recording;
use App\Models\Interaction;
use App\Models\Event;

class GetRecordingsFromTwilio extends Command
{
    private $_client;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:recordings
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

    protected $s3Client = null;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_client = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );

        $this->s3Client = new S3Client([
            'version' => '2006-03-01',
            'region'  => config('filesystems.disks.s3.region'),
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret')
            ]
        ]);
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
        )->where( // Ignore interactions with Motion sids (ex: 123123123123.1231231231)
            'session_call_id',
            'NOT LIKE',
            '%.%'
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

        $interactions = $interactions->where(
            'interactions.session_id',
            'NOT LIKE',
            'DEVTASK%'
        )->where(
            'interactions.session_call_id',
            'NOT LIKE',
            'DEVCALL%'
        );

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

        foreach ($interactions as $interaction) {
            $this->info("Starting ({$interaction->confirmation_code}) {$interaction->interaction_id}...");
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
                            $this->warn('!empty($interactionNotes["session_id"])? ' . (!empty($interactionNotes['session_id']) ? 'yes' : 'no'));
                            $this->warn('is_array($interactionNotes["session_id"])? ' . (is_array($interactionNotes['session_id']) ? 'yes' : 'no'));
                            $this->warn('count($interactionNotes["session_id"]) > 0? ' . (count($interactionNotes['session_id']) > 0 ? 'yes' : 'no'));
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
            Log::info('Fetching Recording for Call SID {' . $callSid . '} for interaction {' . $interactionID . '}');
            $call = $this->_client->calls($callSid)->fetch();

            if (empty($call) || !is_object($call)) {
                if ($this->option('debug')) {
                    $this->warn('$call is empty? ' . (empty($call) ? 'yes' : 'no'));
                    $this->warn('$call is_object?' . (is_object($call) ? 'yes' : 'no'));
                }
                return false;
            }

            $recordings = $call->recordings->read();
            if (count($recordings) > 0) {
                $this->info(' - Found ' . count($recordings) . ' recording entries.');

                foreach ($recordings as $recording) {
                    $year = date('Y');
                    $month = date('m');
                    $day = date('d');
                    $keyname = "uploads/brands/{$brand}/recordings/{$year}/{$month}/{$day}";
                    $filename = "{$recording->sid}.mp3";
                    $mp3 = str_replace('.json', '.mp3', $recording->uri);

                    $path = "https://api.twilio.com{$mp3}?Download=true";
                    if ($this->option('debug')) {
                        $this->info('Pulling recording from: ' . $path);
                    }

                    $content = @file_get_contents($path);

                    if ($content === false) {
                        $error = error_get_last();
                        Log::error(
                            "Error fetching recording for {$callSid}
                        -- ERROR: {$error['message']}"
                        );
                        $this->warn('Error fetching recording for ' . $callSid . ': ' . $error['message']);
                    } else {
                        if (!$this->option('dryrun')) {
                            Storage::disk('s3')
                                ->put("{$keyname}/{$filename}", $content, 'public');

                            $rec = new Recording();
                            $rec->interaction_id = $interactionID;
                            $rec->recording = "{$keyname}/{$filename}";
                            $rec->duration = $recording->duration;
                            $rec->size = strlen($content);
                            $rec->save();

                            $int = Interaction::find($interactionID);
                            if ($int) {
                                $int->session_call_id = $callSid;

                                if ($int->interaction_type_id === 8) {
                                    $int->event_result_id = 1;
                                    $int->disposition_id = null;
                                }

                                if ($int->interaction_time === null && $recording->duration > 0) {
                                    $int->interaction_time = $recording->duration / 60;
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

                            try {
                                $fileInfo = $this->s3Client->headObject([
                                    'Bucket' => config('filesystems.disks.s3.bucket'),
                                    'Key' => "{$keyname}/{$filename}"
                                ]);

                                if($fileInfo) {
                                    $didDelete = $this->_client->recordings($recording->sid)->delete();

                                    if(!$didDelete) {
                                        SendTeamMessage('monitoring', '[GetRecordingsFromTwilio][Interaction: ' . $interactionID . '][Recording: ' . $recording->sid . '] Failed to delete recording from Twilio.');
                                    }
                                } else {
                                    SendTeamMessage('monitoring', '[GetRecordingsFromTwilio][Interaction: ' . $interactionID . '][Recording: ' . $recording->sid . '] Recording NOT found on S3. Will not be deleted from Twilio.');
                                }
                            } catch (\Exception $e) {
                                SendTeamMessage('monitoring', '[GetRecordingsFromTwilio][Interaction: ' . $interactionID . '][Recording: ' . $recording->sid . '] S3 recording check error, or error deleting recording from Twilio: ' . $e->getMessage());
                            }

                            return true;
                            
                            // Delete recording from twilio
                            // if ($rec) {
                            //     $call->recordings($recording->sid)->delete();
                            // }
                        } else {
                            $this->info(" -- would have written " . $path . " to S3 and created a recordings entry.\n");
                        }
                    }
                }
            } else {
                if ($this->option('debug')) {
                    $this->warn('No recordings found for ' . $callSid);
                }
            }
        } else {
            Log::error(
                'Invalid parameters to getRecording',
                [
                    'callSid' => $callSid,
                    'brand' => $brand,
                    'interactionID' => $interactionID,
                ]
            );
            $this->error('Invalid parameters to getRecording');
        }
        return false;
    }
}
