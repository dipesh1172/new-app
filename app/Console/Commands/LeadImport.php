<?php

namespace App\Console\Commands;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Config;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Vendor;
use App\Models\State;
use App\Models\ProviderIntegration;
use App\Models\Lead;
use App\Models\Brand;

class LeadImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lead:import
        {--brand=}
        {--vendor=}
        {--file=}
        {--folder=}
        {--limit=}
        {--append}
        {--stopAfter=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import leads for a specified brand';

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
        if (!$this->option('brand')) {
            $this->error("You must specify a brand id with --brand=");
            exit();
        }

        ini_set('auto_detect_line_endings', true);

        $brand = Brand::find($this->option('brand'));
        if (!$brand) {
            $this->error("Unable to find a brand with that ID.");
            return 42;
        }

        $filename = $this->option('file');

        $header = null;

        switch ($brand->name) {
            case 'Reliant Energy Retail Services LLC':
                ini_set('memory_limit', '-1');
            $brand_id = $brand->id;
            if (!$this->option('append')) {
                $this->info("Timestamp: " . Carbon::now("America/Chicago"));
               $this->info("Starting deletion of current active Reliant leads.");
               Lead::where(
                'brand_id',
                $brand_id
                )->where(
                   'used',
                   0
               )->forceDelete();
               $this->info("Timestamp: " . Carbon::now("America/Chicago"));
               $this->info("Done deleting Reliant leads.");
            }
                $contents = file_get_contents($filename);

                if ($contents) {
                    $lines = explode(PHP_EOL, utf8_encode($contents));
                    $header = null;
                    $csv = [];

                    foreach ($lines as $line) {
                        if (strlen(trim($line)) > 0) {
                            $csv[] = str_getcsv($line);
                        }
                    }
                    $fileInfo = pathinfo($filename);

                    $this->info("Timestamp: " . Carbon::now("America/Chicago"));
                    $this->info("Starting processing: " . $fileInfo['basename']);
        
                    $kntrec = 0;
                    foreach ($csv as $row) {
                        if ($header === null) {
                            $header = str_replace("'", "", $row);
                            continue;
                        }

                        $data = array_combine($header, $row);

                        if (
                            trim($data['PRSPCT_ID']) === 'PRSPCT_ID'
                            || trim($data['PRSPCT_ID']) === ''
                        ) {
                            continue;
                        }
                        $kntrec++;
                        if (fmod($kntrec,1000) == 0) {
                            $this->info("Timestamp: " . Carbon::now("America/Chicago"));
                            $this->info('Records Processed ' . $kntrec);
 //                           return;
                        }
//                        $this->info('Adding ' . trim($data['PRSPCT_ID']));

                            $lead = new Lead();
                            $lead->brand_id = $this->option('brand');
                            $lead->state_id = 44;
                            $lead->lead_type_id = 2;
                            $lead->external_lead_id = mb_strtoupper(trim($data['PRSPCT_ID']));
                            $lead->external_lead_id2 = (isset($data['ESIID']))
                                ? trim($data['ESIID'])
                                : null;
                            $lead->first_name = (isset($data['MATCH_FIRST_NAME']))
                                ? trim($data['MATCH_FIRST_NAME'])
                                : null;
                            $lead->middle_name = (isset($data['MATCH_MIDDLE_NAME']))
                                ? trim($data['MATCH_MIDDLE_NAME'])
                                : null;
                            $lead->last_name = (isset($data['MATCH_LAST_NAME']))
                                ? trim($data['MATCH_LAST_NAME'])
                                : null;
                            $lead->service_address1 = trim($data['PROM_ADDR_LN_1']);
                            $lead->service_address2 = (isset($data['PROM_ADDR_LN_2']))
                                ? trim($data['PROM_ADDR_LN_2'])
                                : null;
                            $lead->service_city = trim($data['PROM_ADDR_CITY_NAME']);
                            $lead->service_state = trim($data['PROM_ADDR_ST']);
                            $lead->service_zip = trim($data['PROM_ADDR_ZIP']);
                            $lead->service_zip4 = trim($data['PROM_ADDR_ZIP4']);
                            $lead->lead_campaign = ltrim(trim($data['TRUNC_SSN4']),'X');
                            $extra = [
                                'last4_ssn' => trim($data['TRUNC_SSN4']),
                                'phone_y_n' => trim($data['PHONE_Y_N']),
                                'prspct_id,' => trim($data['PRSPCT_ID']),
                                'campaign_type' => trim($data['CAMPAIGN_TYPE']),
                                'match_first_name' => trim($data['MATCH_FIRST_NAME']),
                                'match_middle_name' => trim($data['MATCH_MIDDLE_NAME']),
                                'match_last_name' => trim($data['MATCH_LAST_NAME']),
                                'surnsufx' => trim($data['SURNSUFX']),
                                'prom_addr_ln_1' => trim($data['PROM_ADDR_LN_1']),
                                'prom_addr_ln_2' => trim($data['PROM_ADDR_LN_2']),
                                'prom_addr_city_name' => trim($data['PROM_ADDR_CITY_NAME']),
                                'prom_addr_st' => trim($data['PROM_ADDR_ST']),
                                'prom_addr_zip' => trim($data['PROM_ADDR_ZIP']),
                                'prom_addr_zip4' => trim($data['PROM_ADDR_ZIP4']),
                                'mail_decision' => trim($data['MAIL_DECISION']),
                                'anchor_mover_flag' => trim($data['ANCHOR_MOVER_FLAG']),
                                'st_cd' => trim($data['ST_CD']),
                                'us_gout_carrier_route' => trim($data['US_GOUT_CARRIER_ROUTE']),
                                'ln_of_travel_info' => trim($data['LN_OF_TRAVEL_INFO']),
                                'lot_sort_num' => trim($data['LOT_SORT_NUM']),
                                'd2d_campaign_trk_cd' => trim($data['D2D_CAMPAIGN_TRK_CD']),
                                'd2d_promo' => trim($data['D2D_PROMO']),
                                'd2d_agreement_num' => trim($data['D2D_AGREEMENT_NUM']),
                                'outbnd_chan' => trim($data['OUTBND_CHAN']),
                                'customer_segment' => trim($data['CUSTOMER_SEGMENT']),
                                'd2d_unique_promo_cd' => trim($data['D2D_UNIQUE_PROMO_CD']),
                                'esiid' => trim($data['ESIID']),
                                'vendor_id' => trim($data['VENDOR_ID']),
                                'idusa' => trim($data['IDUSA']),
                                'primary_phone' => trim($data['PRIMARY_PHONE']),
                                'srvc_addr_ln1' => trim($data['SRVC_ADDR_LN1']),
                                'srvc_addr_city' => trim($data['SRVC_ADDR_CITY']),
                                'srvc_addr_st' => trim($data['SRVC_ADDR_ST']),
                                'srvc_addr_zip' => trim($data['SRVC_ADDR_ZIP']),
                                'd2d_poc_1' => trim($data['D2D_POC_1']),
                                'd2d_poc_2' => trim($data['D2D_POC_2']),
                                'd2d_poc_3' => trim($data['D2D_POC_3']),
                                'd2d_poc_4' => trim($data['D2D_POC_4']),
                                'd2d_poc_5' => trim($data['D2D_POC_5']),
                                'd2d_poc_6' => trim($data['D2D_POC_6']),
                                'd2d_poc_7' => trim($data['D2D_POC_7']),
                                'd2d_poc_8' => trim($data['D2D_POC_8']),
                                'd2d_poc_9' => trim($data['D2D_POC_9']),
                                'company_cd' => trim($data['COMPANY_CD']),
                                'brand_unq_id' => trim($data['BRAND_UNQ_ID']),
                                'credit_source' => trim($data['CREDIT_SOURCE']),
                                'score_dt' => trim($data['SCORE_DT']),
                                'segment_decision' => trim($data['SEGMENT_DECISION']),
                                'segment_indicator' => trim($data['SEGMENT_INDICATOR']),
                                'bus_prtnr_id' => trim($data['BUS_PRTNR_ID']),
                                'contr_acct_id' => trim($data['CONTR_ACCT_ID']),
                                'start_dt' => trim($data['START_DT']),
                                'end_dt' => trim($data['END_DT']),
                                'tdsp_name' => trim($data['TDSP_NAME']),
                                'trunc_ssn4' => trim($data['TRUNC_SSN4']),
                                'd2d_vendor' => trim($data['D2D_VENDOR']),
                                'former_cmpgn_flag' => trim($data['FORMER_CMPGN_FLAG']),
                                'msa_code' => trim($data['MSA_CODE']),
                                'lang_pref' => trim($data['LANG_PREF']),
                                'rec_id' => trim($data['REC_ID']),
                                'switched_to' => trim($data['SWITCHED_TO']),
                                'move_or_switch_dt' => trim($data['MOVE_OR_SWITCH_DT']),
                                'enroll_dec' => trim($data['ENROLL_DEC']),
                                'd2d_cell_code' => trim($data['D2D_CELL_CODE']),
                                'usage_val_seg' => trim($data['USAGE_VAL_SEG']),
                                'leads_filename' => trim($fileInfo['basename']),
                            ];
                            $lead->extra_fields = $extra;

                            $lead->save();

                            if (
                                isset($data['PRIMARY_PHONE'])
                                && strlen(trim($data['PRIMARY_PHONE'])) > 0
                            ) {
                                generatePhoneNumberRecords($data['PRIMARY_PHONE'], 9, $lead->id);
                            }
                    }
                    $this->info("Timestamp: " . Carbon::now("America/Chicago"));
                    $this->info('Finished');

                    $this->info('Total records Processed ' . $kntrec);
                }

                break;
            case 'Tomorrow Energy':
                $cleanFileAfter = false;
                if (!empty($filename)) {
                    $this->line('Importing from file: ' . $filename);
                } else {
                    // $cleanFileAfter = true;
                    $this->line('Grabbing list from ftp');
                    $pi = ProviderIntegration::where('brand_id', $this->option('brand'))->where('provider_integration_type_id', 1)->first();
                    $adapter = new SftpAdapter(
                        [
                            'host' => $pi->hostname,
                            'port' => 22,
                            'username' => $pi->username,
                            'password' => $pi->password,
                            'root' => $pi->notes !== null && trim($pi->notes) !== '' ? $pi->notes : '/',
                            'timeout' => 10,
                            'directoryPerm' => 0755,
                        ]
                    );
                    $filesystem = new Filesystem(
                        $adapter,
                        new Config(
                            [
                                'disable_asserts' => true,
                            ]
                        )
                    );

                    $file = tempnam(sys_get_temp_dir(), 'tef');
                    $rfiles = $filesystem->listContents();
                    $remoteFile = null;
                    $files = [];
                    foreach ($rfiles as $rfile) {
                        if ($rfile['type'] === 'file' && $rfile['extension'] === 'csv') {
                            // print_r($rfile);
                            $files[$rfile['timestamp']] = $rfile['path'];
                        }
                    }

                    ksort($files);

                    //print_r($files);

                    $remoteFile = end($files);

                    $this->line('Remote file: ' . $remoteFile);

                    $remoteContents = $filesystem->read($remoteFile);
                    if ($remoteContents === false) {
                        $this->error('Unable to read remote file: ' . $remoteFile);
                        break;
                    }

                    file_put_contents($file, $remoteContents);

                    $this->line('Temp file written to ' . $file);
                }

                Lead::where(
                    'brand_id',
                    $brand->id
                )->delete();

                $handle = fopen($filename, 'r');
                $header = null;
                $count = 0;
                while ($row = fgetcsv($handle)) {
                    if ($header === null) {
                        $header = $row;
                        continue;
                    }

                    $data = array_combine($header, $row);

                    if (
                        trim($data['RecordLocatorId']) === 'RecordLocatorId'
                        || trim($data['RecordLocatorId']) === ''
                    ) {
                        continue;
                    }

                    $existing = Lead::where(
                        'brand_id',
                        $this->option('brand')
                    )->where(
                        'lead_type_id',
                        2
                    )->where(
                        'external_lead_id',
                        trim($data['RecordLocatorId'])
                    )->where(
                        'first_name',
                        trim($data['First Name'])
                    );

                    $middle = trim($data['Middle Initial']);
                    if (!empty($middle)) {
                        $existing = $existing->where(
                            'middle_name',
                            $middle
                        );
                    }

                    $existing = $existing->where(
                        'last_name',
                        trim($data['Surname'])
                    )->where(
                        'service_address1',
                        trim($data['Street'])
                    );

                    if (isset($data['Street2']) && trim($data['Street2']) !== '') {
                        $existing = $existing->where('service_address2', trim($data['Street2']));
                    }

                    $existing = $existing->where(
                        'service_city',
                        trim($data['City'])
                    )->where(
                        'service_state',
                        trim($data['State Abbreviation'])
                    )->where(
                        'service_zip',
                        trim($data['Zip Code'])
                    )->withTrashed()->orderBy(
                        'leads.created_at',
                        'desc'
                    )->first();

                    if ($existing !== null) {
                        info('Restoring ' . $existing->external_lead_id);
                        $existing->restore();
                    } else {
                        info('Adding ' . trim($data['RecordLocatorId']));

                        $lead = new Lead();
                        $lead->brand_id = $this->option('brand');
                        $lead->state_id = 44;
                        $lead->lead_type_id = 2;
                        $lead->external_lead_id = mb_strtoupper(trim($data['RecordLocatorId']));
                        $lead->first_name = (trim($data['First Name']))
                            ? trim($data['First Name'])
                            : null;
                        $lead->middle_name = (!empty($middle))
                            ? $middle
                            : null;
                        $lead->last_name = (trim($data['Surname']))
                            ? trim($data['Surname'])
                            : null;
                        $lead->service_address1 = trim($data['Street']);
                        $lead->service_address2 = (trim($data['Street2']))
                            ? trim($data['Street2'])
                            : null;
                        $lead->service_city = trim($data['City']);
                        $lead->service_state = trim($data['State Abbreviation']);
                        $lead->service_zip = trim($data['Zip Code']);
                        $lead->save();
                    }
                    $count = $count + 1;

                    if ($this->option('stopAfter') && $count >= $this->option('stopAfter')) {
                        break;
                    }
                }
                $this->line('Imported ' . $count . ' leads.');
                if ($cleanFileAfter) {
                    unlink($file);
                }
                break;

            case 'TXU Energy':
                $files = [];
                if ($this->option('folder')) {
                    if (!is_dir($this->option('folder'))) {
                        $this->error("Folder (" . $this->option('folder') . ") doesn't exist.");
                        exit();
                    }

                    $files = glob($this->option('folder') . '/*');
                } else {
                    if (!$this->option('file')) {
                        $this->error("Syntax: php artisan lead:import --brand=<brand_id> --file=<path to file>");
                        exit();
                    }

                    if (!file_exists($filename)) {
                        $this->error("File (" . $filename . ") doesn't exist.");
                        exit();
                    }

                    $files[] = $filename;
                }

                info(print_r($files, true));

                if (!$this->option('append')) {
                    $this->info("Timestamp: " . Carbon::now("America/Chicago"));
                    $this->info("Starting deletion of current active TXU leads.");
                    // By default, this will force delete all leads from the table before starting a new import.
                    Lead::where(
                        'brand_id',
                        $brand->id
                    )->where(
                        'used',
                        0
                    )->forceDelete();
                    $this->info("Timestamp: " . Carbon::now("America/Chicago"));
                    $this->info("Done deleting TXU leads.");
                }

                $this->info("Import Start: " . Carbon::now("America/Chicago"));

                foreach ($files as $filename) {
                    if (empty($filename)) {
                        continue;
                    }

                    $fileInfo = pathinfo($filename);

                    $this->info("Timestamp: " . Carbon::now("America/Chicago"));
                    $this->info("Starting file: " . $fileInfo['basename']);

                    $header = null;

                    $count = 0;
                    $handle = fopen($filename, 'r');
                    while ($row = fgetcsv($handle, 0, '|')) {
                        if ($header === null) {
                            $header = $row;
                            continue;
                        }

                        $data = array_combine($header, $row);

                        if (empty(trim($data['TXU_REFERENCE_CODE']))) {
                            // Row was empty, lets skip it.
                            continue;
                        }

                        $lead = new Lead();
                        $lead->brand_id = $this->option('brand');
                        $lead->lead_type_id = 2;
                        $lead->external_lead_id = mb_strtoupper(trim($data['TXU_REFERENCE_CODE']));

                        $state = Cache::remember(
                            'state_lookup' . trim($data['ERCOT_SVC_STATE']),
                            60,
                            function () use ($data) {
                                return State::where(
                                    'state_abbrev',
                                    strtoupper(trim($data['ERCOT_SVC_STATE']))
                                )->first();
                            }
                        );
                        if ($state) {
                            $lead->state_id = $state->id;
                        }

                        $lead->external_lead_id2 = trim($data['POD']);
                        $lead->first_name = (strlen(trim($data['CONTACT_FIRST_NM'])) > 0)
                            ? trim($data['CONTACT_FIRST_NM'])
                            : null;
                        $lead->middle_name = (strlen(trim($data['CONTACT_MIDDLE_I'])) > 0)
                            ? trim($data['CONTACT_MIDDLE_I'])
                            : null;
                        $lead->last_name = (strlen(trim($data['CONTACT_LAST_NM'])) > 0)
                            ? trim($data['CONTACT_LAST_NM'])
                            : null;
                        $lead->service_address1 = trim($data['ERCOT_SVC_ADDR']);
                        $lead->service_city = trim($data['ERCOT_SVC_CITY']);
                        $lead->service_state = trim($data['ERCOT_SVC_STATE']);
                        $lead->service_zip = trim($data['ERCOT_SVC_ZIP']);
                        $lead->credit_pass = (strlen(trim($data['CREDIT_PASS'])) > 0)
                            ? trim($data['CREDIT_PASS'])
                            : null;

                        switch (trim($data['DWELLING_TYPE'])) {
                            case 'SINGLE':
                                $lead->home_type_id = 1;
                                break;
                            case 'MULTI FAMILY':
                                $lead->home_type_id = 2;
                                break;
                            default:
                                $lead->home_type_id = 1;
                        }

                        $lead->lead_campaign = trim($data['TACTIC_CODE']);

                        $extra = [
                            'sub_channel' => trim($data['SUB_CHANNEL']),
                            'ws_campaign_name' => trim($data['WS_CAMPAIGN_NAME']),
                            'account_number' => trim($data['ACCOUNT_NUMBER']),
                            'business_partner' => trim($data['BUSINESS_PARTNER']),
                            'edc' => trim($data['EDC']),
                            'mail_drop_date' => trim($data['MAIL_DROP_DATE']),
                            'meter_read_cycle' => trim($data['METER_READ_CYCLE']),
                            'avg_monthly_kwh' => trim($data['AVG_MONTHLY_KWH']),
                            'leads_filename' => trim($fileInfo['basename']),
                        ];
                        $lead->extra_fields = $extra;
                        $lead->save();

                        $count++;
                        if (
                            $this->option('limit')
                            && $count >= $this->option('limit')
                        ) {
                            break;
                        }

                        if ($count % 5000 == 0) {
                            $this->info("Timestamp: " . Carbon::now("America/Chicago"));
                            $this->info($count . " record(s) processed.");
                        }
                    }

                    fclose($handle);
                }

                $this->info("Import End: " . Carbon::now("America/Chicago"));

                break;
            case 'Clearview Energy':

                // Get FTP info
                $pi = ProviderIntegration::where(
                    'brand_id',
                    $brand->id
                )->where(
                    'provider_integration_type_id',
                    1
                )->first();

                if (!$pi) {
                    $this->error("No credentials were found.");
                }

                if (is_string($pi->notes)) {
                    $pi->notes = json_decode($pi->notes, true);
                }

                // Setup FTP config
                $config = [
                    'hostname' => $pi->hostname,
                    'username' => $pi->username,
                    'password' => $pi->password,
                    'root' => '/',
                    'port' => 22,
                    'timeout' => 10,
                    'directoryPerm' => 0755,
                ];

                // Setup the file system adapter
                $adapter = new SftpAdapter(
                    [
                        'host' => $config['hostname'],
                        'port' => 22,
                        'username' => $config['username'],
                        'password' => $config['password'],
                        'root' => (isset($config['root'])) ? $config['root'] : '/',
                        'timeout' => 10,
                        'directoryPerm' => 0755,
                    ]
                );

                $filesystem = new Filesystem($adapter);

                // Get list of files on FTP server
                $files = $filesystem->listContents($config['root'], true);

                // Find the file to import.
                // If a --file was not specified, we're looking for CE_TPV_YYYYMMDD.csv
                if (!$filename) {
                    $filename = "CE_TPV_" . Carbon::yesterday("America/Chicago")->format("Ymd") . ".csv";
                }
                $fileIndex = -1;
                for ($i = 0; $i < count($files); $i++) {
                    if (strtolower($files[$i]['basename']) == strtolower($filename)) {
                        $fileIndex = $i;
                        break;
                    }
                }

                if ($fileIndex == -1) {
                    $this->info("File " . $filename . " was not found. Ending import.");
                    return -1;
                }

                $contents = $filesystem->read($files[$fileIndex]['path']);
                if (empty(trim($contents))) {
                    $this->error("File (" . $files[$fileIndex]['path'] . ") has no contents.");
                    return 44;
                }

                $count = 0;
                $rows = str_getcsv($contents, "\n");

                // Clear existing leads?
                if (!$this->option('append')) {
                    Lead::where(
                        'brand_id',
                        $brand->id
                    )->where(
                        'used',
                        0
                    )->delete();
                }

                foreach ($rows as $row) {
                    $new_data = str_getcsv($row, ',');
                    if ($header === null) {
                        $header = $new_data;
                        continue;
                    }

                    $data = array_combine($header, $new_data);

                    $state_lookup = Cache::remember(
                        'state_lookup' . $data['STATE'],
                        60,
                        function () use ($data) {
                            return State::where(
                                'state_abbrev',
                                strtoupper($data['STATE'])
                            )->first();
                        }
                    );
                    if (!$state_lookup) {
                        $this->error('State ' . $data['STATE'] . ' was not valid.');
                        return 46;
                    }

                    // Lead lookup. To determine if a deleted lead can be restored or a new one must be created.
                    $lead = Lead::where(
                        'brand_id',
                        $brand->id
                    )->where(
                        'external_lead_id',
                        trim($data['RECORD_LOCATOR'])
                    )->withTrashed()->orderBy(
                        'leads.created_at',
                        'desc'
                    )->first();

                    if ($lead) {
                        if ($lead->trashed()) {
                            $lead->restore();
                        }
                    } else {
                        $lead = new Lead();
                        $lead->brand_id = $brand->id;
                        $lead->vendor_id = null;
                        $lead->lead_type_id = 2;
                        $lead->channel_id = 2;
                        $lead->state_id = $state_lookup->id;
                        $lead->external_lead_id = mb_strtoupper(trim($data['RECORD_LOCATOR']));
                        $lead->first_name = trim($data['FIRST_NAME']);
                        $lead->middle_name = (strlen(trim($data['MIDDLE_NAME'])) > 0)
                            ? trim($data['MIDDLE_NAME'])
                            : null;
                        $lead->last_name = trim($data['LAST_NAME']);
                        $lead->service_address1 = trim($data['ADDRESS_1']);
                        $lead->service_address2 = trim($data['ADDRESS_2']);
                        $lead->service_city = trim($data['CITY']);
                        $lead->service_state = trim($data['STATE']);
                        $lead->service_zip = trim($data['ZIP']);

                        switch (trim($data['DWELL_TYPE'])) {
                            case 'S':
                                $lead->home_type_id = 1;
                                break;
                            case 'M':
                                $lead->home_type_id = 2;
                                break;
                            default:
                                $lead->home_type_id = 1;
                        }

                        $lead->lead_campaign = trim($data['CAMPAIGN_CODE']);
                        $lead->save();

                        if (
                            isset($data['PHONE'])
                            && strlen(trim($data['PHONE'])) > 0
                        ) {
                            generatePhoneNumberRecords($data['PHONE'], 9, $lead->id);
                        }

                        ++$count;
                        if ($this->option('limit')) {
                            if ($count >= $this->option('limit')) {
                                break;
                            }
                        }
                    }
                }

                break;

            case 'Gexa Energy':
                $this->info("Starting Gexa Lead Import...");

                // Pull FTP details from DB
                $pi = ProviderIntegration::where(
                    'brand_id',
                    $brand->id
                )->where(
                    'provider_integration_type_id',
                    1
                )->first();

                // Check DB pull results
                if (empty($pi)) {
                    $this->error("No credentials were found.");
                    return 2;
                }

                // Set up FTP config object we'll use for the FTP connection
                $config = [
                    'host' => $pi->hostname,
                    'username' => $pi->username,
                    'password' => $pi->password,
                    'port' => 990,
                    'root' => '/FTPRecording/Vendor28/ftp_dexr/GEXALEADLISTS/',
                    'ssl' => true,
                    'passive' => true,
                    'timeout' => 30,
                ];

                print_r($config);

                // Create filename to search for, if not provided in command args
                if (empty($filename)) {
                    $filename = sprintf('file_%s.csv', Carbon::today()->format('Ymd'));
                }

                // Download the file
                $this->info("Downloading file: " . $filename);
                $ftpResult = curlFtpDownload($filename, public_path("tmp/") . $filename, $config);

                // 1st Check - Was FTP successful?
                $this->info("FTP Result: " . $ftpResult);
                if (strtolower($ftpResult) !== "success") {
                    $this->error('Error downloading file (or remote file does not exist): ' . public_path("tmp/") . $filename);
                    break;
                }

                // 2nd Check - Does local file exist?
                if (!file_exists(public_path("tmp/") . $filename)) {
                    $this->error('Unable to locate downloaded file: ' . public_path("tmp/") . $filename);
                    break;
                }

                // 3rd Check - Is the file empty?
                $fileContent = file_get_contents(public_path('tmp/') . $filename);
                if (trim($fileContent) == false) {
                    $this->error('File is empty.');
                    break;
                }

                $count = 0;

                // Check if we need to delete existing leads
                if (!$this->option('append')) {
                    Lead::where(
                        'brand_id',
                        $brand->id
                    )->where(
                        'used',
                        0
                    )->delete();
                }

                $handle = fopen(public_path("tmp/" . $filename), 'r');
                while ($new_data = fgetcsv($handle, 0)) {

                    if (isset($new_data[0])) {
                        $first_name = $new_data[0];
                        $middle_name = $new_data[1];
                        $last_name = $new_data[2];
                        $address = $new_data[4];
                        $city = $new_data[5];
                        $state = $new_data[6];
                        $zip = substr($new_data[7], 0, 5);
                        $zip4 = substr($new_data[7], 5, 9);
                        $esiid = $new_data[8];
                        $lead_id = $new_data[9];

                        if ($state !== 'State Abbreviation') {
                            $state_lookup = Cache::remember(
                                'state_lookup' . $state,
                                60,
                                function () use ($state) {
                                    return State::where(
                                        'state_abbrev',
                                        strtoupper($state)
                                    )->first();
                                }
                            );
                            if (!$state_lookup) {
                                $this->error('State ' . $state . " was not valid.");
                                return 3;
                            }

                            $lead = Lead::where(
                                'brand_id',
                                $brand->id
                            )->where(
                                'external_lead_id',
                                trim($lead_id)
                            )->withTrashed()->orderBy(
                                'leads.created_at',
                                'desc'
                            )->first();

                            if ($lead) {
                                if ($lead->trashed()) {
                                    $lead->restore();
                                }
                            } else {
                                Lead::disableAuditing();

                                $lead = new Lead();
                                $lead->brand_id = $brand->id;
                                $lead->lead_type_id = 2;
                                $lead->state_id = $state_lookup->id;
                                $lead->external_lead_id = mb_strtoupper(trim($lead_id));
                                $lead->external_lead_id2 = trim($esiid);
                                $lead->first_name = trim($first_name);
                                $lead->middle_name = (strlen(trim($middle_name)) > 0)
                                    ? trim($middle_name)
                                    : null;
                                $lead->last_name = trim($last_name);
                                $lead->service_address1 = trim($address);
                                $lead->service_city = trim($city);
                                $lead->service_state = trim($state);
                                $lead->service_zip = trim($zip);
                                $lead->service_zip4 = (strlen(trim($zip4)) > 0)
                                    ? trim($zip4)
                                    : null;
                                $lead->home_type_id = 1;
                                $lead->save();

                                Lead::enableAuditing();

                                $count = $count + 1;

                                if ($this->option('stopAfter') && $count >= $this->option('stopAfter')) {
                                    break;
                                }
                            }
                        }
                    }
                }

                fclose($handle);

                // Delete the downloaded file
                if (file_exists(public_path("tmp/") . $filename)) {
                    unlink(public_path("tmp/") . $filename);
                }

                break;

            default:
                $this->error('No Lead Import Process exists for the brand ' . $brand->name);
                return 47;
        }
    }
}
