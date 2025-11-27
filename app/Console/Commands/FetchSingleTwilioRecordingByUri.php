<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use Exception;
use App\Models\Recording;
use App\Models\Interaction;

class FetchSingleTwilioRecordingByUri extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:recording:single {--url=} {--interaction=} {--callid=} {--brand=} {--duration=} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets a recording by url and associates it with a call';

    /**
     * Create a new command instance.
     *
     * @return void
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
        // to locate logs use (staging) cat logs/mgmt | grep "$url"
        $brand = $this->option('brand');
        $url = $this->option('url');
        $interaction = $this->option('interaction');
        $callid = $this->option('callid');
        $duration = $this->option('duration');
        $force = $this->option('force');

        $urlParts = explode('/', $url);
        $filename = $urlParts[count($urlParts) - 1];

        $recording = Recording::where('interaction_id', $interaction)->where('call_id', $callid)->first();
        if ($recording !== null && $recording->remote_status !== 'pending' && !$force) {
            info('Recording ' . $url . ' Interaction already has recording, not updating');
            $this->info('Interaction already has recording');
            return 0;
        }

        if ($recording !== null) {
            info('Recording ' . $url . ' removing old recording.');
            $recording->delete(); // if we get here we're forcing the fetch and don't want the old record
        }

        $recording = new Recording();
        $recording->interaction_id = $interaction;
        $recording->duration = $duration;
        $recording->call_id = $callid;
        $recording->remote_status = 'pending';
        $recording->remote_error_code = $brand;
        $recording->recording = $url;
        $recording->save();

        $i = Interaction::find($interaction);
        if ($i && $i->interaction_type_id == 20) {
            $durationMinutes = $duration / 60;
            if ($durationMinutes > $i->interaction_time) {
                $i->interaction_time = $durationMinutes;
                $i->save();
            }
        }

        try {
            if (!$this->checkIfRecordingReady($url)) {
                info('Recording ' . $url . ' is not ready');
                $cnt = 0;
                $found = $this->checkIfRecordingReady($url);
                while (!$found && $cnt < 5) {
                    usleep(150000);
                    $found = $this->checkIfRecordingReady($url);
                    info('(' . $cnt . ') Recording ' . $url . ' is not ready');
                    $cnt += 1;
                }

                if (!$found) {
                    info('Recording is not available for ' . $url);
                    $this->error('Recording is not available for ' . $url);
                    return 41;
                }
            }

            info('Recording ' . $url . ' grabbing audio');
            $audio = file_get_contents($url);

            $year = date('Y');
            $month = date('m');
            $day = date('d');
            $keyname = "uploads/brands/{$brand}/recordings/{$year}/{$month}/{$day}";
            info('Recording ' . $url . ' saving audio to s3 as ' . $keyname . '/' . $filename);
            Storage::disk('s3')->put("{$keyname}/{$filename}", $audio, 'public');

            info('Recording ' . $url . ' Updating recording record');
            $recording->recording = "{$keyname}/{$filename}";
            $recording->size = strlen($audio);
            $recording->remote_status = 'completed';
            $recording->remote_error_code = null;
            $recording->save();

            info('Recording ' . $url . ' recording fetch complete.');

            $this->line('Ok');
        } catch (Exception $e) {
            info('Recording ' . $url . ' Unable to fetch recording: ' . $e->getMessage(), [$e]);
            $this->error('Unable to fetch recording: ' . $e->getMessage() . ' :: ' . $url);

            return 42;
        }
    }

    private function checkIfRecordingReady(string $url)
    {
        try {
            $newUrl = $url . '.json';
            $raw = file_get_contents($newUrl);
            $jdata = json_decode($raw, true);
            info('Recording ' . $url . ' checking status', [$jdata]);
            return $jdata['status'] === 'completed';
        } catch (Exception $e) {
            info('Recording ' . $url . ' Error checking recording status: ' . $e->getMessage(), [$e]);
            $this->info('Error checking recording status: ' . $e->getMessage());
            return false;
        }
    }
}
