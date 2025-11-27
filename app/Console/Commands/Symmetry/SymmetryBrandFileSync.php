<?php

namespace App\Console\Commands\Symmetry;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Config;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use Carbon\Carbon;

use Aws\S3\S3Client;
use Aws\Sts\StsClient;

use App\Models\JsonDocument;
use App\Models\Event;
use App\Models\Brand;
use App\Models\ProviderIntegration;

class SymmetryBrandFileSync extends Command
{
    public $cloudfront;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'symmetry:brand:file:sync
            {--ignoreSynced}
            {--confirmation_code=}
            {--debug}
            {--limit=}
            {--noUpload}
            {--startDate=}
            {--endDate=}
            {--noSlack}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transfer files to configured S/FTP server';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        $this->cloudfront = config('services.aws.cloudfront.domain');
        parent::__construct();
    }
    public $startDate;
    public $endDate;
//   public $distroList = ['accountmanagers@answernet.com','DXC_AutoEmails@dxc-inc.com','curt.cadwell@answernet.com'];
    public $distroList = ['curt.cadwell@answernet.com'];
    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Symmetry Brand File Sync';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->brand_id = 'ec9ac17c-1aff-4c20-87fe-211049507bde';
        if ($this->option('startDate') && $this->option('endDate')) {
            // TODO: We're trusting the dates the user is passing. Add validation for:
            // 1) valid dates were provided
            // 2) start date <= end date
            $this->startDate = Carbon::parse($this->option('startDate'));
            $this->endDate = Carbon::parse($this->option('endDate'));
            $this->info('Using custom dates...');
        } else {
            $this->startDate = Carbon::today()->subDays(3);
            $this->endDate = Carbon::today()->addDays(1);  // need this for the 9:30 run and won't hurt for the 2:00am run
            $this->info('Using custom dates 3 days back...');
        }
        DB::enableQueryLog();

        $brands = Brand::select(
            'id',
            'name',
            'recording_transfer_type',
            'recording_transfer_config'
        );

        $brands = $brands->where(
            'id',
            $this->brand_id
        );

       // $brands = $brands->get()->makeVisible('recording_transfer_config');
        $brands = $brands->get();
        if ($brands) {
            foreach ($brands as $brand) {
                try {
                    $this->line('Starting ' . $brand->name . "\n");

                    $dd = null; // Recordings transfer config (including client FTP if FTP upload set)
                    $tpvhubFtpConfig = null; // FTP config for our own server, for any clients we retain copies of files for

                    if (strlen(trim($brand->recording_transfer_config)) > 0) {
                        $dd = (is_string($brand->recording_transfer_config))
                            ? json_decode($brand->recording_transfer_config, true)
                            : $brand->recording_transfer_config;
                        $summary = [];

                        // If Indra, get tpvhub FTP config
                        // if($brand->id == 'eb35e952-04fc-42a9-a47d-715a328125c0' || $brand->id == '4e65aab8-4dae-48ef-98ee-dd97e16cbce6' // Indra prod/stage
                        //     || $brand->id == '6629c585-6cf0-4cdb-8bf9-e76b8a4a2bf5' || $brand->id == '0eacb864-933f-4dfe-b2bb-8de4c5936f18') { // Columbia prod/stage, C

                        //     $tpvhubFtpConfig = ProviderIntegration::select(
                        //         'id',
                        //         'username',
                        //         'password',
                        //         'hostname',
                        //         'notes' // root folder setting in notes field
                        //     )->where(
                        //         'brand_id',
                        //         'eb35e952-04fc-42a9-a47d-715a328125c0' // For Indra/Columbia we're using Indra's prod ID to store the config
                        //     )->where(
                        //         'service_type_id',
                        //         34 // Indra TpvHub FTPS
                        //     )->first()->toArray();
                        // }

                        $events = Event::select(
                            'events.id',
                            'events.created_at',
                            'events.eztpv_id',
                            'events.confirmation_code',
                            'stats_product.channel',
                            'stats_product.market',
                            'stats_product.service_state',
                            'stats_product.btn',
                            'stats_product.commodity',
                            'stats_product.vendor_name'
                        )->where(
                            'events.brand_id',
                            $brand->id
                        )->leftJoin(
                            'stats_product',
                            'events.id',
                            'stats_product.event_id'
                        );

                        // begin tpv result
                        //  determine what tpv result is selected
                        //  if null select all results
                        if (isset($dd['tpv_result'])) {
                            $events = $events->whereIn(
                                'stats_product.result',
                                $dd['tpv_result']
                            );
                        }
                        // end tpv result
                        if ($this->option('confirmation_code')) {
                            $events = $events->where(
                                'events.confirmation_code',
                                $this->option('confirmation_code')
                            );
                        }

                        // if ($this->option('state')) {
                        //     $events = $events->where(
                        //         'stats_product.service_state',
                        //         $this->option('state')
                        //     );
                        // }

                        if (!$this->option('ignoreSynced')) {
                            $events = $events->where(
                                'events.synced',
                                0
                            );
                        }

                        $events = $events->whereNull(
                            'events.deleted_at'
                        );

                        $events = $events->whereBetween(
                            'events.created_at',
                            [
                                $this->startDate->format('Y-m-d'),
                                $this->endDate->format('Y-m-d'),
                            ]
                        );

                        $events = $events->with(
                            [
                                'eztpv',
                                'eztpv.eztpv_docs',
                                'eztpv.eztpv_docs.uploads' => function ($query) {
                                    $query->whereNull(
                                        'deleted_at'
                                    );
                                },
                                'eztpv.eztpv_docs.uploads.type',
                                'interactions',
                                'interactions.interaction_type',
                                'interactions.recordings',
                                'interactions.result',
                            ]
                        )->groupBy(
                            'stats_product.confirmation_code'
                        )->orderBy(
                            'events.created_at',
                            'asc'
                        // )->limit($limit)->get();
                        );
                        // $queryStr = str_replace(array('?'), array('\'%s\''), $events->toSql());
                        // $queryStr = vsprintf($queryStr, $events->getBindings());
            
                        // $this->info("");
                        // $this->info('QUERY:');
                        // $this->info($queryStr);
                        // $this->info("");

                        $events = $events->get();
                        $total_records = $events->count();
                        $this->info('total selected = ' .$total_records);

                        if ($events) {
                            foreach ($events as $event) {
                                $day = $event->created_at->format('Y-m-d');
                                $eventSummary = [];
                                 if (isset($dd) && in_array('recordings', $dd['file_types'])) {
                                    if ($this->option('debug')) {
                                        $this->info('Looking at recordings for ' . $event->confirmation_code);
                                    }

                                    foreach ($event->interactions as $interaction) {
                                        foreach ($interaction->recordings as $recording) {
                                            if (isset($dd['tpv_result'])) {
                                                if (isset($interaction->result->result)) {
                                                    if (in_array(strtolower($interaction->result->result), array_map('strtolower',$dd['tpv_result'])) == false) {
                                                        break;  // don't download recording
                                                    }
                                                } else {
                                                    break;  // don't download recording result not set
                                                }
                                            }
                                            $eventSummary[$day][$event->confirmation_code][] = [
                                                'type_id' => $recording->id,
                                                'created_at' => $interaction->created_at,
                                                'confirmation_code' => $event->confirmation_code,
                                                'name' => ($interaction->interaction_type
                                                    && $interaction->interaction_type->name)
                                                    ? $interaction->interaction_type->name
                                                    : null,
                                                'recording' => $recording->recording,
                                                'brand' => $brand->id,
                                                'channel' => $event->channel,
                                                'state' => $event->service_state,
                                                'market' => $event->market,
                                                'vendor' => $event->vendor_name,
                                                'btn' => $event->btn,
                                                'commodity' => $event->commodity
                                            ];
                                        }
                                    }

                                }

                                if (
                                    isset($dd)
                                    && (in_array('contracts', $dd['file_types'])
                                        || in_array('photos', $dd['file_types']))
                                ) {
                                    if (
                                        isset($event->eztpv)
                                        && $event->eztpv !== null
                                        && isset($event->eztpv->eztpv_docs)
                                        && is_iterable($event->eztpv->eztpv_docs)
                                    ) {
                                        foreach ($event->eztpv->eztpv_docs as $doc) {
                                            if (isset($doc->uploads) && $doc->preview_only === 0) {
                                                if (isset($doc->uploads->filename)) {
                                                    if (
                                                        in_array('contracts', $dd['file_types'])
                                                        && 3 === $doc->uploads->upload_type_id
                                                    ) {
                                                        $eventSummary[$day][$event->confirmation_code][] = [
                                                            'created_at' => $event->created_at,
                                                            'confirmation_code' => $event->confirmation_code,
                                                            'filename' => $doc->uploads->filename,
                                                            'upload_type' => ($doc->uploads
                                                                && $doc->uploads->type->upload_type)
                                                                ? $doc->uploads->type->upload_type
                                                                : null,
                                                            'type_id' => $doc->id,
                                                            'brand' => $brand->id,
                                                            'channel' => $event->channel,
                                                            'state' => $event->service_state,
                                                            'market' => $event->market,
                                                            'vendor' => $event->vendor_name,
                                                            'btn' => $event->btn,
                                                            'commodity' => $event->commodity
                                                        ];
                                                    }

                                                    if (
                                                        in_array('photos', $dd['file_types'])
                                                        && 4 === $doc->uploads->upload_type_id
                                                    ) {
                                                        $eventSummary[$day][$event->confirmation_code][] = [
                                                            'created_at' => $event->created_at,
                                                            'confirmation_code' => $event->confirmation_code,
                                                            'filename' => $doc->uploads->filename,
                                                            'upload_type' => ($doc->uploads
                                                                && $doc->uploads->type->upload_type)
                                                                ? $doc->uploads->type->upload_type
                                                                : null,
                                                            'type_id' => $doc->id,
                                                            'brand' => $brand->id,
                                                            'channel' => $event->channel,
                                                            'state' => $event->service_state,
                                                            'market' => $event->market,
                                                            'vendor' => $event->vendor_name,
                                                            'btn' => $event->btn,
                                                            'commodity' => $event->commodity
                                                        ];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                if ($this->option('debug')) {
                                    $this->info('eventSummary = ');
                                    print_r($eventSummary);
                                }

                                if (!empty($eventSummary)) {
                                    $summary[] = $eventSummary;
                                } else {
                                    if ($this->option('debug')) {
                                        print_r($event->toArray());
                                    }
                                }

                                // if (!$this->option('ignoreSynced')) {
                                //     $event = Event::where(
                                //         'confirmation_code',
                                //         $event->confirmation_code
                                //     )->where(
                                //         'brand_id',
                                //         $brand->id
                                //     )->first();
                                //     if ($event) {
                                //         info('(2) Conf: ' . $event->confirmation_code . ' marked as synced');
                                //         $event->synced = 1;
                                //         $event->save();
                                //     }
                                // }
                            }
                        }
                        $fixSummary = [];
                        foreach ($summary as $index) {
                            foreach ($index as $key => $value) {
                                foreach ($value as $k => $v) {
                                    $fixSummary[$key][$k] = $v;
                                }
                            }
                        }

                        if ($this->option('noUpload')) {
                            print_r(DB::getQueryLog());

                            $this->info('dd = ');
                            print_r($dd);

                            $this->info('events->toArray() = ');
                            print_r($events->toArray());

                            $this->info('fixSummary = ');
                            print_r($fixSummary);
                            exit();
                        }

                        if (!empty($fixSummary)) {
                            $this->line(" -- Starting processing of files.\n");
                            $this->Upload($dd, $tpvhubFtpConfig, $fixSummary, $brand, $dd);
                        }
                    }
                } catch (\Exception $e) {
                    // SendTeamMessage('monitoring', '[SymmetryBrandFileSync][' . $brand->name . '] - general error while processing. Error(' . $e->getLine() . '): ' . $e->getMessage());
                    $this->warn('Error while processing brand ' . $brand->name . ', Message: ' . $e->getMessage() . ' Code: ' . $e->getCode() . ' Line: ' . $e->getLine());
                }
            }
        }
    }

    private function downloadFile(
        $file,
        $date_dir,
        $created_at,
        $confirmation_code,
        $id,
        $type,
        $name = null,
        $channel,  // used for Nordic
        $state,    // used for Nordic
        $market,   // used for Nordic
        $btn,     // used for Nordic
        $vendor,  // used for Nordic
        $dd
    ) {

        // Create download directory if it doesn't exist
        if (!file_exists('/tmp/files')) {
            mkdir('/tmp/files', 0777, true);
        }

        // Extract file name from provided path
        $explode = explode('/', $file);
        $local_filename = $explode[count($explode) - 1];

        // Download the file
        // echo ' -- Fetching '.$file."\n";
        $this->line(' -- Type: ' . $type . ' Confirmation Code: ' . $confirmation_code . ' Date: ' . $date_dir . "\n");
        $content = @file_put_contents(
            '/tmp/files/' . $local_filename,
            file_get_contents($file)
        );

        // No content or 'type' arg not provided. Return with a null remote filename.
        if (!$content || !isset($type)) {
            return [
                'type' => $type,
                'id' => $id,
                'remote_filename' => null,
                'local_filename' => $local_filename,
            ];
        }

        // Create date string to use in filenames
        $file_date = Carbon::parse(
            $created_at,
            'America/Chicago'
        )->format('Y_m_d_H_i_s');

        $file_date2 = Carbon::parse( // YYYYMMDD version
            $created_at,
            'America/Chicago'
        )->format('Ymd');

        $remote_filename = "";            
        
        // By default, the remote path contains a date folder. This can be overridden with the create_date_folder config setting.
        // Determine whether to add the date folder to the remote file path.
        // removed below code so we don't create a date subfolder for Symmetry 
        // if (!isset($dd['create_date_folder']) // No create_date_folder setting in config; add date folder to path
        //     || (isset($dd['create_date_folder']) && $dd['create_date_folder'] === true)) { // create_date_folder setting exists and is set to true; add date folder to path
        //     $remote_filename .= $date_dir . '/';
        // }

        // Use Voicelog file paths and names setting. Overrides any other dir/file settings.
        $useVoiceLogFilePaths = (isset($dd['use_vl_paths']) && $dd['use_vl_paths'] === true);

        switch ($type) {
            case 'recording':
                
                // Determine which recording filename convention to use:

                // Use Voicelog path and naming?
                if($useVoiceLogFilePaths) {

                    // Build file path: incoming/audiofiles/<Voicelog Phone ID for Client>/<channel_state_market>/<yyyymmdd>/<recording_name>.mpe
                    // recording_name: <btn>_<confirmation_code>_<incoming/outgoing>.mp3
                    
                    $csmFolder = $channel . '_' . $state . '_' . $market;
//                    $filename = $dd['btn'] . '_' . $confirmation_code . '_' . $type . '.mp3';
                    $filename = str_replace('+1','',$btn) . '_' . $confirmation_code . '.mp3';

                    $remote_filename = "incoming/audiofiles/" . $dd['vl_phone_id'] . "/$csmFolder/$file_date2/$filename"; // Turn 864260300 into a config, or come up with a generic name

                    break;
                } else {

                    // Not using Voicelog paths and names. Check other file settings

                    // (default) When recordings_file_naming is not set, or is set to false: confirmationCode-recordingType-dateTime.mp3
                    // When recordings_file_naming is set to true: confirmationCode_01_uniqueNumber.mp3
                    if (isset($dd['recordings_file_naming'])) {
                        $dd['recordings_file_naming'] = ($dd['recordings_file_naming'] === true ? '00' : $dd['recordings_file_naming']); // messed up need multiple options for naming conventions
                         switch ($dd['recordings_file_naming']) {
                            case '00':  // Use IDT filenaming convention
                                $remote_filename .= $confirmation_code
                                . '_01_'
                                . strtotime($created_at->format("Ymd"))
                                . '.mp3';
                                break;
                            case '01':  
                                $remote_filename .= str_replace('_','',$file_date)
                                . '_'
                                . str_replace('+1','',$btn)
                                . '_'
                                . $confirmation_code
                                . '.mp3';
                                break;
                            default:
                                $remote_filename .= $confirmation_code
                                . '-' . $name . '-'
                                . $file_date
                                . '.mp3';
                        }
                    } else { // Use default filenaming convention
                        $remote_filename .= $confirmation_code
                        . '-' . $name . '-'
                        . $file_date
                        . '.mp3';
                    }
                }
                break;

            case 'contract':

                // Use Voicelog path and naming?
                if($useVoiceLogFilePaths) {

                    // Build file path: incoming/TEXT_TPV/<Voicelog Phone ID for Client>/<channel_state_market>/<yyyymmdd>/<recording_name>.mpe
                    // recording_name: <btn>_<confirmation_code>_<incoming/outgoing>.mp3

                    $csmFolder = $state . '_' . $vendor;
                    //$filename = $dd['btn'] . '_' . $confirmation_code . '_' . $type . '.pdf';
                    $filename =  str_replace('+1','',$btn) . '_' . $confirmation_code . '.pdf';

                    // MD contracts use a different path
                    $remote_filename = "";
                    if(strtoupper($state) === 'MD' && $channel === 'TM') {
                        $remote_filename = "incoming/TM_MD_ELOA/" . $vendor . "/ELOA/$file_date2/$filename";
                    } else {
                        $remote_filename = "incoming/TEXT_TPV/$csmFolder/ELOA/$file_date2/$filename";
                    }
                    
                    break;
                } else {
                
                    // Not using 
                    // Override default path and name?
                    if (isset($dd['create_date_folder']) && $dd['create_date_folder'] == false) { // Don't create date folder
                        $remote_filename = $confirmation_code
                        . '-' . $file_date . '-'
                        . $local_filename;
                    } else {
                        $remote_filename = $date_dir . '/'
                        . $confirmation_code
                        . '-' . $file_date . '-'
                        . $local_filename;                            
                    }
                }
                break;

            case 'photo':
                // Override default path and name?
                if (isset($dd['create_date_folder']) && $dd['create_date_folder'] == false) { // Don't create date folder.
                    $remote_filename = $confirmation_code
                        . '-' . $file_date . '-'
                        . $local_filename;
                } else {
                    $remote_filename = $date_dir . '/'
                    . $confirmation_code
                    . '-' . $file_date . '-'
                    . $local_filename
                    . '.png';
                }
                break;
        }

        return [
            'type' => $type,
            'id' => $id,
            'remote_filename' => $remote_filename,
            'local_filename' => $local_filename,
        ];
    }

    /**
     * File Transfer Upload.
     *
     * @param array $config  - configuration needed to perform SFTP upload
     * @param array $tpvhubFtpConfig - FTP config for clients that we retain a copy of the uploaded files. Leave null otherwise.
     * @param array $summary - summary of files to upload
     * @param Brand $brand
     *
     * @return string - Status message
     */
    public function Upload($config, $tpvhubFtpConfig, $summary, $brand, $dd)
    {
        $useCurl = (isset($config['use_curl']) && $config['use_curl']);
        
        $useS3 = (isset($config['delivery_method']) && $config['delivery_method'] === 's3');
        $assumeIamRole = (isset($config['aws_assume_iam_role']) && $config['aws_assume_iam_role'] === true);

        $s3Client = null;
        
        $adapter = null; // adapter and filesystem objects for client FTP site
        $filesystem = null;

        $tpvhubFtpAdapter = null; // adapter and filesystem objects for TpvHub SFTP site
        $tpvhubFtpFileSystem = null;

        if ($useCurl) {
            $this->info('Using Curl for FTP');
        }

        // Create S3 client if S3 upload option is set
        if ($useS3) {
            $this->info('Using S3 for file delivery');

            // Check if we need to assume a role
            $iamRole = null;

            if($assumeIamRole) {
                $this->info('Assuming IAM Role for S3 Access...');

                $iamRole = $this->awsAssumeIamRole($brand, $config);
            }

            $this->info('Creating S3 client');
            $s3Client = $this->awsCreateS3Client($brand, $config, $iamRole);
        }

        if (!$useCurl && !$useS3) {
            $this->info('Using Leage/Flysystem for FTP');

            // Set up connection to the client's FTP site
            if (
                isset($config['delivery_method'])
                && 'sftp' === $config['delivery_method']
            ) {
                $config = [
                    'host' => $config['hostname'],
                    'port' => (isset($config['port'])) ? $config['port'] : 22,
                    'username' => $config['username'],
                    'password' => $config['password'],
                    'root' => (isset($config['root'])) ? $config['root'] : '/',
                    'timeout' => 20,
                    'directoryPerm' => 0755,
                ];
                $adapter = new SftpAdapter($config);

            } else {
                $config = [
                    'host' => $config['hostname'],
                    'username' => $config['username'],
                    'password' => $config['password'],
                    'port' => (isset($config['port'])) ? $config['port'] : 21,
                    'root' => (isset($config['root'])) ? $config['root'] : '/',
                    'ssl' => (isset($config['ssl'])) ? $config['ssl'] : false,
                    'passive' => (isset($config['passive'])) ? $config['passive'] : false,
                    'timeout' => 30,
                    'ignorePassiveAddress' => (isset($config['ignorePassiveAddress']))
                        ? $config['ignorePassiveAddress'] : false,
                ];

                $adapter = new Ftp($config);
            }

            try {
                $adapter->getConnection();
            } catch (\Exception $e) {
                if (!$this->option('noSlack')) {
                    // SendTeamMessage('monitoring', '[SymmetryBrandFileSync][' . $brand->name . '] - unable to connect to ' . $config['host'] . ' Error: ' . $e->getMessage());
                }
                $this->line("\n!!! Connection to server failed.\n");
                info('Could not connect to server: ' . json_encode($config));
                return;
            }

            $filesystem = new Filesystem(
                $adapter,
                new Config(
                    [
                        'disable_asserts' => true,
                    ]
                )
            );

            // Set up connection to tpvhub SFTP
            if($tpvhubFtpConfig) {
                $tpvhubFtpConfig = [
                    'host' => $tpvhubFtpConfig['hostname'],
                    'port' => 22,
                    'username' => $tpvhubFtpConfig['username'],
                    'password' => $tpvhubFtpConfig['password'],
                    'root' => (isset($tpvhubFtpConfig['notes'])) ? $tpvhubFtpConfig['notes'] : '/', // root dir stored in notes field
                    'timeout' => 20,
                    'directoryPerm' => 0755,
                ];
                
                $tpvhubFtpAdapter = new SftpAdapter($tpvhubFtpConfig);
            
                try {
                    $tpvhubFtpAdapter->getConnection();
                } catch (\Exception $e) {
                    if (!$this->option('noSlack')) {
                        // SendTeamMessage('monitoring', '[SymmetryBrandFileSync][' . $brand->name . '] - unable to connect to ' . $tpvhubFtpConfig['host'] . ' Error: ' . $e->getMessage());
                    }
                    $this->line("\n!!! Connection to TpvHub SFTP server failed. Continuing without retaining our own copy of the files.\n");
                    info('Could not connect to TpvHub SFTP server: ' . json_encode($tpvhubFtpConfig) . '. Continuing without retaining our own copy of the files.');
                }

                if($tpvhubFtpAdapter->isConnected()) {
                    $tpvhubFtpFileSystem = new FileSystem(
                        $tpvhubFtpAdapter,
                        new Config(
                            [
                                'disable_asserts' => true,
                            ]
                        )
                    );
                }
            }
        }

        $statusMessages = [];

        foreach ($summary as $key => $value) {
            $errors = [];

            foreach ($value as $confirmation_code => $array) {
                $tpvresult = null;
                $confCodeMessages = [];
                try {
                    if (empty($array)) {
                        $confCodeMessages[] = ['error' => 'No Files to Sync'];
                    }

                    $markAsSynced = true;
                    for ($i = 0; $i < count($array); ++$i) {
                        $fileType = null;
                        $return = null;
                        if (
                            isset($array[$i]['upload_type'])
                            && 'Contract' == $array[$i]['upload_type']
                        ) {
                            if (!isset($array[$i]['filename']) || 0 == strlen(trim($array[$i]['filename']))) {
                                continue;
                            }
                            $fileType = 'contract';
                            $file = $this->cloudfront . '/' . $array[$i]['filename'];
                            $return = $this->downloadFile(
                                $file,
                                $key,
                                $array[$i]['created_at'],
                                $array[$i]['confirmation_code'],
                                $array[$i]['type_id'],
                                'contract',
                                null,
                                $array[$i]['channel'],  // used for Nordic
                                $array[$i]['state'],    // used for Nordic
                                $array[$i]['market'],   // used for Nordic
                                $array[$i]['btn'],     // used for Nordic
                                $array[$i]['vendor'],  // used for Nordic
                                $dd
                            );
                        } elseif (
                            isset($array[$i]['upload_type'])
                            && 'EzTPV Photo' == $array[$i]['upload_type']
                        ) {
                            if (!isset($array[$i]['filename']) || 0 == strlen(trim($array[$i]['filename']))) {
                                continue;
                            }
                            $fileType = 'photo';
                            $file = $this->cloudfront . '/' . $array[$i]['filename'];
                            $return = $this->downloadFile(
                                $file,
                                $key,
                                $array[$i]['created_at'],
                                $array[$i]['confirmation_code'],
                                $array[$i]['type_id'],
                                'photo',
                                null,
                                $array[$i]['channel'],  // used for Nordic
                                $array[$i]['state'],    // used for Nordic
                                $array[$i]['market'],   // used for Nordic
                                $array[$i]['btn'],     // used for Nordic
                                $array[$i]['vendor'],  // used for Nordic
                                $dd
                            );
                        } else {
                            if (isset($array[$i]) && isset($array[$i]['recording'])) {
                                $fileType = 'wav';
                                $file = $this->cloudfront . '/' . $array[$i]['recording'];
                                $return = $this->downloadFile(
                                    $file,
                                    $key,
                                    $array[$i]['created_at'],
                                    $array[$i]['confirmation_code'],
                                    $array[$i]['type_id'],
                                    'recording',
                                    $array[$i]['name'],
                                    $array[$i]['channel'],  // used for Nordic
                                    $array[$i]['state'],    // used for Nordic
                                    $array[$i]['market'],   // used for Nordic
                                    $array[$i]['btn'],     // used for Nordic
                                    $array[$i]['vendor'],  // used for Nordic
                                    $dd
                                );
                            }
                        }

                        if ($return === null) {
                            $markAsSynced = false;
                            $confCodeMessages[] = ['file' => $file, 'type' => $fileType, 'error' => 'Could not resolve upload entry to file'];
                            $this->info('Could not resolve upload entry to a file.');
                            info('Could not resolve upload to a file', [$array[$i]]);
                            $errors[] = ['code' => 0, 'message' => 'could not resolve upload entry to a file: ' . json_encode($array[$i]), 'conf' => $confirmation_code];
                            continue;
                        }

                        $local_filename = $return['local_filename'];
                        $remote_filename = $return['remote_filename'];
                        $id = $return['id'];

                        if (!isset($remote_filename) || $remote_filename == null) {
                            $markAsSynced = false;
                            $this->line("\t -- No remote file exists for " . $id);
                            if (file_exists('/tmp/files/' . $local_filename)) {
                                info('Remote file nonexistent, removing local file ' . $local_filename . ' for ' . $confirmation_code);
                                $this->line("\t -- Remote file nonexistent, removing local file " . $local_filename);
                                @unlink('/tmp/files/' . $local_filename);
                            }
                        }

                        if (file_exists('/tmp/files/' . $local_filename)) {
                            if ($stream = fopen('/tmp/files/' . $local_filename, 'r+')) {

                                // Upload file to client's FTP site or S3 bucket.
                                if ($useCurl) {
                                    $success = curlFtpUpload('/tmp/files/' . $local_filename, $config);
                                    $success = (strtolower($success) === "success");
                                    
                                } else if ($useS3) {

                                    // Add root path, if any, to s3 key
                                    if(isset($config['root']) && !empty($config['root']) && trim($config['root']) !== '/') {
                                        $remote_filename = $config['root'] . '/' . $remote_filename;
                                    }

                                    try {
                                        $s3Client->putObject([
                                            'Bucket' => $config['s3_bucket'],
                                            'Key' => $remote_filename,
                                            'Body' => $stream
                                        ]);

                                        $success = true;

                                    } catch (\Exception $e) {
                                        // SendTeamMessage('monitoring', '[SymmetryBrandFileSync][' . $brand->name . '] Error uploading file to ' . $remote_filename . ': ' . $e->getMessage());

                                        $success = false;
                                    }

                                } else {
                                    // Upload to client's FTP site
                                    $success = $filesystem->writeStream(
                                        $remote_filename,
                                        $stream
                                    );
                                }

                                if (!$success) {
                                    $markAsSynced = false;
                                    $confCodeMessages[] = ['file' => $file, 'remote_file' => $remote_filename, 'type' => $fileType, 'error' => 'transfer failed'];
                                    throw new \Exception('Transfer of ' . $fileType . ' file ' . $local_filename . ' not successful');
                                }

                                // Upload to tpvhub FTP, but only if FTP to client succeeded
                                if($success && $tpvhubFtpConfig && $tpvhubFtpAdapter->isConnected()) { // 1) client FTP succeeded, 2) Tpvhub FTP Config provided (aka yes, we want to retain a copy) and 3) successfully connected to tpvhub FTP
                                    $tpvhubSuccess = $tpvhubFtpFileSystem->writeStream(
                                        $remote_filename,
                                        $stream
                                    );

                                    // If tpvhub FTP failed, continue anyway
                                    if(!$tpvhubSuccess) {
                                        $this->line("\n!!! Failed to upload file to tpvhub FTP. Continue since client's copy was successfully uploaded.\n");
                                        info("Failed to upload file to tpvhub FTP. Continue since client's copy was successfully uploaded.");
                                    }                                        
                                }

                                $confCodeMessages[] = ['file' => $file, 'remote_file' => $remote_filename, 'type' => $fileType, 'error' => false];

                                info('Uploaded ' . $fileType . ' file ' . $local_filename . ' to ' . $remote_filename . ' for ' . $confirmation_code);
                                $this->line("\t" . ' -- Uploaded ' . $fileType . ' file: ' . $local_filename . "\n");

                                unlink('/tmp/files/' . $local_filename);

                                fclose($stream);
                            } else {
                                $markAsSynced = false;
                                $confCodeMessages[] = ['file' => $file, 'remote_file' => $remote_filename, 'type' => $fileType, 'error' => 'Could not locate file (1)'];
                                $this->line("\t" . '-- Unable to find ' . $fileType . ' file (1): ' . $local_filename . "\n");
                                info('(1) ' . $fileType . ' file ' . $local_filename . ' was not found during transfer attempt for ' . $confirmation_code);
                            }

                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        } else {
                            $markAsSynced = false;
                            $confCodeMessages[] = ['file' => $file, 'remote_file' => $remote_filename, 'type' => $fileType, 'error' => 'Could not locate file (2)'];
                            $this->line("\t -- Unable to find '.$fileType.' file (2): " . $local_filename . "\n");
                            info('(2) ' . $fileType . ' file ' . $local_filename . ' was not found during transfer attempt for ' . $confirmation_code);
                        }
                    }

                    // Only mark event as synced if all transfers passed.
                    if ($markAsSynced) {
                        $event = Event::where(
                            'confirmation_code',
                            $confirmation_code
                        )->first();
                        if ($event) {
                            info('(1) Conf: ' . $event->confirmation_code . ' marked as synced');
                            $event->synced = 1;
                            $event->save();
                        }
                    }
                } catch (\Exception $e) {
                    $confCodeMessages[] = ['error' => $e->getMessage()];
                    $errors[] = ['code' => $e->getCode(), 'message' => $e->getMessage(), 'conf' => $confirmation_code];
                    $this->error("\t -- Error: " . $e->getCode() . ' -- ' . $e->getMessage() . "\n");
                    info('Conf: ' . $confirmation_code . ' -- Error during ' . $fileType . ' file transfer: ' . $e->getMessage());
                }
                $statusMessages[$confirmation_code] = $confCodeMessages;
            }

            if (!empty($errors)) {
                try {
                    $msg = '';
                    foreach ($errors as $error) {
                        $msg .= $error['conf'] . ': (' . $error['code'] . ') ' . $error['message'] . "\n";
                    }
                    if (!$this->option('noSlack')) {
                        if (count($errors) < 5) {
                            // SendTeamMessage('monitoring', '[SymmetryBrandFileSync][' . $brand->name . '] Errors encountered uploading files: ```' . $msg . '```');
                        } else {
                            // SendTeamMessage('monitoring', '[SymmetryBrandFileSync][' . $brand->name . '] Errors encountered uploading files (details emailed)');
                        }
                    }

                    SendErrorEmail($brand->name . ' SymmetryBrandFileSync Errors', $msg);
                } catch (\Exception $e) {
                    info('Error in error handler: ', [$e]);
                }
            }
        }

        $jd = new JsonDocument();
        $jd->document_type = 'brand_file_sync';
        $jd->document = $statusMessages;
        $jd->ref_id = $brand->id;
        $jd->save();
    }

    /**
     * Assume an IAM role
     */
    private function awsAssumeIamRole($brand, $config)
    {
        try {
            
            $role = null;

            $stsClient = new StsClient([
                'region'  => $config['aws_region'],
                'version' => '2011-06-15',
                'credentials' => [
                    'key'     => $config['aws_key'],
                    'secret'  => $config['aws_secret']
                ]
            ]);

            $role = $stsClient->AssumeRole([
                'RoleArn' => $config['aws_role_arn'],
                'RoleSessionName' => $config['aws_role_session_name']
            ]);

            return $role;

        } catch(\Exception $e) {
            $this->error('Error Assuming IAM Role');
            // SendTeamMessage('monitoring', '[SymmetryBrandFileSync][' . $brand->name . '] Error Assuming AWS IAM Role.');
        }

        return null;
    }

    /**
     * Create S3 client
     */
    private function awsCreateS3Client($brand, $config, $iamRole = null)
    {
        try {

            $client = null;

            // Create via assumed role?
            // Credentials in config are used to generate temporarily credetials and token
            if($iamRole) {

                $client = new S3Client([
                    'version' => '2006-03-01',
                    'region'  => $config['aws_region'],
                    'credentials' => [
                        'key'    => $iamRole['Credentials']['AccessKeyId'],
                        'secret' => $iamRole['Credentials']['SecretAccessKey'],
                        'token'  => $iamRole['Credentials']['SessionToken']
                    ]
                ]);

            } else { // Credentils in config should give us access
                
                $client = new S3Client([
                    'version' => '2006-03-01',
                    'region'  => $config['aws_region'],
                    'credentials' => [
                        'key'    => $config['aws_key'],
                        'secret' => $config['aws_secret']
                    ]
                ]);
            }

            return $client;

        } catch (\Exception $e) {
            $this->error('Error Creating S3 Client');
            // SendTeamMessage('monitoring', '[SymmetryBrandFileSync][' . $brand->name . '] Error Creating S3 Client.');
        }

        return null;
    }

}
