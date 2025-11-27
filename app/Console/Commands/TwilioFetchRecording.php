<?php

// php artisan fetch:recording --confirmation_code=18344124482

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Audit;
use App\Models\Interaction;
use App\Models\Recording;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TwilioFetchRecording extends Command
{
    private $_client;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:recording {--confirmation_code=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch recordings by Confirmation Code';

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
        assert($this->_client !== null);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Starting Recording Fetch');
        $interactions = Interaction::select(
            'interactions.session_call_id',
            'interactions.session_id',
            'brands.id AS brand_id',
            'interactions.id AS interaction_id'
        )->join(
            'events',
            'events.id',
            'interactions.event_id'
        )->join(
            'brands',
            'events.brand_id',
            'brands.id'
        )->whereNotNull(
            'session_call_id'
        )->where(
            'interactions.session_id',
            'NOT LIKE',
            'DEVTASK%'
        )->where(
            'interactions.session_call_id',
            'NOT LIKE',
            'DEVCALL%'
        )->where(
            'events.confirmation_code',
            $this->option('confirmation_code')
        )->get();
        assert($interactions !== null);

        if ($interactions->count() > 0) {
            $this->info('Fetching recordings for ' . $interactions->count() . ' interactions.');
            /*foreach ($interactions->toArray() as $key => $value) {
                $this->info(':'.$key.' => '.print_r($value, true));
            }*/
            foreach ($interactions as $interaction) {
                try {
                    $recording = Recording::where(
                        'interaction_id',
                        $interaction->interaction_id
                    )->first();

                    if ($recording !== null) {
                        $this->info('Skipping ' . $interaction->interaction_id . '...');
                    } else {
                        $this->info('Starting ' . $interaction->interaction_id . '...');
                        // $audit = Audit::where(
                        //     'url',
                        //     'LIKE',
                        //     '%/incoming/update-session-call%'
                        // )->where(
                        //     'auditable_id',
                        //     $interaction->interaction_id
                        // )->latest()->first();

                        // $session_call_id = $interaction->session_call_id;
                        // assert($session_call_id !== null);
                        // // $this->info('SCI = '.$session_call_id);

                        // if ($audit !== null) {
                        //     $array = (is_string($audit->old_values))
                        //         ? json_decode($audit->old_values, true)
                        //         : $audit->old_values;
                        //     // Log::info('audit vals', $array);
                        //     if ($array !== null && isset($array['session_call_id']) && strlen(trim($array['session_call_id'])) > 0) {
                        //         Interaction::find($interaction->interaction_id)->update(['session_call_id' => $array['session_call_id']]);
                        //         Log::info('Updated call session ' . $session_call_id . ' to ' . $array['session_call_id']);
                        //         $session_call_id = $array['session_call_id'];

                        //         $this->info('SCI UPDATED = ' . $session_call_id);
                        //     }
                        // }

                        $this->_getRecordings(
                            $interaction->session_call_id,
                            $interaction->brand_id,
                            $interaction->interaction_id
                        );
                    }
                } catch (\Exception $e) {
                    if ($interaction !== null) {
                        Log::error('There was an issue while processing interaction: {' . $interaction->interaction_id . '}', [$e]);
                    } else {
                        Log::error('The passed interaction does not exist');
                    }
                }
            }
        } else {
            $this->error('Nothing to fetch.');
        }
    }

    private function _getRecordings($callSid, $brand, $interactionID)
    {
        if (
            strlen(trim($callSid)) > 0
            && strlen(trim($brand)) > 0
            && strlen(trim($interactionID)) > 0
        ) {
            Log::info('Fetching Recording for Call SID {' . $callSid . '} for interaction {' . $interactionID . '}');
            $call = $this->_client->calls($callSid)->fetch();
            assert($call !== null);
            assert(is_object($call));

            $recordings = $call->recordings->read();
            assert($recordings !== null);
            $this->info(' - Found ' . count($recordings) . ' recordings.');

            foreach ($recordings as $recording) {
                $year = date('Y');
                $month = date('m');
                $day = date('d');
                $keyname = "uploads/brands/{$brand}/recordings/{$year}/{$month}/{$day}";
                $filename = "{$recording->sid}.mp3";
                $mp3 = str_replace('.json', '.mp3', $recording->uri);
                $path = "https://api.twilio.com{$mp3}?Download=true";

                $content = @file_get_contents($path);
                assert($content !== false);
                if (!$content) {
                    $error = error_get_last();
                    Log::error(
                        "Error fetching recording for {$callSid}
                        -- ERROR: {$error['message']}"
                    );
                } else {
                    $s3 = Storage::disk('s3')
                        ->put("{$keyname}/{$filename}", $content, 'public');

                    $rec = new Recording();
                    $rec->interaction_id = $interactionID;
                    $rec->recording = "{$keyname}/{$filename}";
                    $rec->duration = $recording->duration;
                    $rec->size = strlen($content);
                    $rec->save();

                    $int = Interaction::where(
                        'id',
                        $interactionID
                    )->whereNull(
                        'interaction_time'
                    )->first();
                    if ($int) {
                        if ($int->interaction_type_id === 8) {
                            $int->event_result_id = 1;
                            $int->save();
                        }

                        if ($recording->duration > 0) {
                            $int->interaction_time = $recording->duration / 60;
                            $int->save();
                        }
                    }
                    // Delete recording from twilio
                    // if ($rec) {
                    //     $call->recordings($recording->sid)->delete();
                    // }
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
        }
    }
}
