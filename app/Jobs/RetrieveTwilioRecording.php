<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\Twilio\RecordingCompleted;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RetrieveTwilioRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $recording = null;

    /**
     * Create a new job instance.
     */
    public function __construct(RecordingCompleted $recording)
    {
        $this->recording = $recording;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if ($this->recording === null) {
            throw new \Exception('Tried to handle a null recording...');
        }
        $interaction = Interaction::where('session_call_id', $this->recording->callSid)->first();
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $keyname = "uploads/brands/{$brand}/recordings/{$year}/{$month}/{$day}";
        $filename = "{$this->recording->recordingSid}.mp3";
        $mp3 = str_replace('.json', '.mp3', $this->recording->recordingUri);
        $guzzle = new GuzzleHTTP\Client(
            [
            'base_uri' => 'https://api.twilio.com',
            ]
        );

        try {
            $stream = tmpfile();
            $guzzle->get(
                $mp3, [
                    'query' => [
                        'Download' => 'true',
                    ],
                    'sink' => $stream,
                ]
            );

            $fileSize = ftell($stream);
            rewind($stream);

            $s3 = Storage::disk('s3')
                        ->put("{$keyname}/{$filename}", $stream, 'public');

            $rec = new Recording();
            if ($interaction !== null) {
                $rec->interaction_id = $interaction->id;
            }
            $rec->recording = "{$keyname}/{$filename}";
            $rec->duration = $this->recording->recordingDuration;
            $rec->size = $fileSize;
            $rec->call_id = $this->recording->callSid;
            $rec->save();

            // Delete recording from twilio
            // if ($rec) {
            //     $call->recordings($this->recording->recordingSid)->delete();
            // }
        } catch (\Exception $e) {
            Log::error(
                "Error fetching recording for {$this->response->callSid}",
                $e
            );
        }

        fclose($stream);
    }
}
