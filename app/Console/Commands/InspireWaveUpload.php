<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Recording;
use App\Models\ProviderIntegration;
use App\Models\Brand;

class InspireWaveUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inspire:wave_upload {--debug} {--forever} {--hoursAgo=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload waves to Inspire\'s API';

    /**
     * Is this in debug mode
     *
     * @var bool
     */
    protected $isDebug = false;

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
        $isDebug = $this->option('debug');

        $start_time = Carbon::now('America/Chicago')->startOfDay();
        if ($this->option('hoursAgo')) {
            $start_time = $start_time->subHours($this->option('hoursAgo'));
        }
        if ($this->option('forever')) {
            $start_time = "01-01-1980";
        }

        $env_id = App::environment() === 'production' ? 1 : 2;
        $env = ($env_id === 1 ? 'Production' : 'Staging');
        if ($isDebug) {
            $this->info('Running in ' . $env);
        }

        $brand = Brand::where(
            'name',
            'Inspire Energy'
        )->orWhere(
            'name',
            'Inspire Energy Holdings LLC'
        )->first();
        if (!$brand) {
            $this->info("Unable to find brand_id for Inspire");
            exit();
        }

        $pi = ProviderIntegration::where('brand_id', $brand->id)->where('env_id', $env_id)->first();
        if (!$pi) {
            $this->info("Unable to find ProviderIntegration record.");
            exit();
        }

        $events = DB::table('recordings')
            ->leftJoin('interactions', 'recordings.interaction_id', 'interactions.id')
            ->leftJoin('events', 'interactions.event_id', 'events.id')
            ->where('events.brand_id', $brand->id)
            ->where('interactions.created_at', '>=', $start_time)
            ->where('recordings.pushed', '0')
            ->whereNotNull('interactions.session_call_id')
            ->orderBy('recordings.created_at')
            ->select(
                'events.id as event_id',
                'events.confirmation_code',
                'interactions.session_call_id as session_call_id',
                'interactions.created_at',
                'recordings.recording',
                'recordings.pushed',
                'recordings.id as recording_id'
            )->get();

        if ($events->isEmpty()) {
            $this->info("No events to process");
            exit();
        }

        $url = $pi->hostname . '/api/1/clients/DXC3D269/call_recordings';
        $username = $pi->username;
        $password = $pi->password;

        foreach ($events as $event) {
            $contents = Storage::disk('s3')->get($event->recording);
            if (strlen($contents) > 0) {
                file_put_contents(public_path('tmp/output.mp3'), $contents);
                $cfile = curl_file_create(
                    public_path('tmp/output.mp3'),
                    'audio/mpeg',
                    md5($event->created_at . time()) . ".mp3"
                );

                $fields = [
                    'record_locator' => $event->confirmation_code,
                    'recorded_dt' => Carbon::parse($event->created_at)->toIso8601String(),
                    'file' => $cfile
                ];

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $password);
                curl_setopt($curl, CURLOPT_USERAGENT, 'Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12.388 Version/12.15');
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // stop verifying certificate
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POST, true); // enable posting
                curl_setopt($curl, CURLOPT_POSTFIELDS, $fields); // post images
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // if any redirection after upload
                $r = curl_exec($curl);
                $i = curl_getinfo($curl);
                if ($i['http_code'] != '200') {
                    $this->info("Error uploading audio file");
                } else {
                    $rec = Recording::where('id', $event->recording_id)->get()->first();
                    if ($rec) {
                        $rec->pushed = 1;
                        $rec->save();
                        $this->info("Audio file successfully uploaded. " . $rec->recording);
                    } else {
                        $this->info("Unable to find database entry for recording. " . $rec->recording);
                    }
                }

                curl_close($curl);
            }
        }
    }

    function build_data_files($boundary, $fields, $files)
    {
        $data = '';
        $eol = "\r\n";
        $delimiter = '-------------' . $boundary;

        foreach ($fields as $name => $content) {
            $data .= "--" . $delimiter . $eol
                . 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol
                . $content . $eol;
        }

        foreach ($files as $name => $content) {
            $data .= "--" . $delimiter . $eol
                . 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $name . '"' . $eol
                . 'Content-Transfer-Encoding: binary' . $eol . $eol . $content . $eol;
        }
        $data .= "--" . $delimiter . "--" . $eol;

        return $data;
    }
}
