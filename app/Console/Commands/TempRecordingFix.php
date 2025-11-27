<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\Interaction;
use App\Models\Recording;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Illuminate\Console\Command;

class TempRecordingFix extends Command
{
    private $_client;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'temp:recording:fix {--confirmation_code=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'A temporary script to fix a wrong session_call_id issue';

    /**
     * Create a new command instance.
     *
     * @return void
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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('confirmation_code')) {
            $interaction = Interaction::select(
                'interactions.*',
                'events.brand_id'
            )->leftJoin(
                'events',
                'interactions.event_id',
                'events.id'
            )->where(
                'events.confirmation_code',
                $this->option('confirmation_code')
            )->where(
                'interactions.interaction_type_id',
                2
            )->orderBy(
                'interactions.created_at',
                'desc'
            )->first();
            if ($interaction) {
                $audit = Audit::where(
                    'auditable_id',
                    $interaction->id
                )->where(
                    'url',
                    'LIKE',
                    '%/interactions/update/%'
                )->orderBy(
                    'created_at',
                    'desc'
                )->first();
                if ($audit) {
                    $data = (is_string($audit->old_values))
                        ? json_decode($audit->old_values, true)
                        : $audit->old_values;
                    if (isset($data['session_call_id'])) {
                        $interaction->session_call_id = $data['session_call_id'];
                        $interaction->save();

                        $recording = Recording::where(
                            'interaction_id',
                            $interaction->id
                        )->first();
                        if ($recording) {
                            $recording->delete();
                        }

                        $this->_getRecordings(
                            $data['session_call_id'],
                            $interaction->brand_id,
                            $interaction->id
                        );
                    } else {
                        echo "Could not find a session_call_id.\n";
                    }
                }
            }
        } else {
            echo "You must pass in a --confirmation_code=\n";
        }
    }
}
