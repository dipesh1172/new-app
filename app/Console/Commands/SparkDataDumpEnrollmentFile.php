<?php

namespace App\Console\Commands;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
//use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\ProviderIntegration;
use App\Models\StatsProduct;
use App\Models\JsonDocument;
use App\Models\Rate;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SparkDataDumpEnrollmentFile extends Command
{

     public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SparkDataDumpEnrollmentFile 
        {--mode=        : Optional. Valid values are "live" and "test". "live" is used by default.} 
        {--noftp        : Optional. If provided, creates the file but does not FTP it.} 
        {--start-date=  : Optional. Start date for data query. Must be paired with --end-date. If omitted, current date is used.} 
        {--end-date=    : Optional. End date for data query. Must be paired with --start-date. If omitted, current date is used.} 
        {--useSchedule  : Optional. This is used for 4:00pm and 11:30pm scheduled runs}
        {--resubmit     : Optional. This should only be used if data needs to be resubmitted. This option will only work if --start-date and --end-date are also provided.} 
        {--no-json-doc  : Optional. This should only be used for tests. If provided, this prevents the program from writing the json_document log record, and allowing this record to be picked up again.}';


    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Spark Data Dump Enrollment File ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates and Emails Spark Energy\'s enrollment file';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '7845a318-09ff-42fa-8072-9b0146b174a5'; //  prod ID 
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [  
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'EnrollmentsandContractManagement1@sparkenergy.com','mharvey@sparkenergy.com','accountmanagers@answernet.com'],
           // 'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            'test' => ['curt.cadwell@answernet.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
           // 'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            'test' => ['curt.cadwell@answernet.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com','curt.cadwell@answernet.com','accountmanagers@answernet.com'],
            //'test' => ['dxcit@tpv.com']
            'test' => ['curt.cadwell@answernet.com']

        ]
    ];

    /**
     * FTP Settings
     *
     * @var array
     */

     protected $ftpSettings = [
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 22,
        'root' => '/Marketing/AnswerNetTPV/To Spark/BI Data Dump',
//        'passive' => true,
//        'ssl' => true,
        'timeout' => 30,
        'directoryPerm' => 0755,
    ];

    /**
     * Report start date
     *
     * @var mixed
     */
    protected $startDate = null;

    /**
     * Report end date
     *
     * @var mixed
     */
    protected $endDate = null;

    /**
     * Report mode: 'live' or 'test'.
     *
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

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
        $this->startDate = Carbon::today('America/Chicago');
        $this->endDate = Carbon::tomorrow('America/Chicago')->add(-1, 'second');

        // Check mode. Leave in 'live' mode if not provided or an invalid value was provided.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            }
        }

        // Check for and validate custom report dates, but only if both start and end dates are provided
        if ($this->option('start-date') && $this->option('end-date')) {
            // TODO: We're trusting the dates the user is passing. Add validation for:
            // 1) valid dates were provided
            // 2) start date <= end date
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));
            $this->info('Using custom dates...');
        }
        if($this->option('resubmit')) {
            if(!$this->option('start-date') || !$this->option('end-date')) {
                $this->error('--resubmit option can only be used when --start-date and --end-date options are also present');
                exit -1;
            }
        }
        if ($this->option('useSchedule')) {  // Enrollment File schedule (4:00 PM and 11:30 AM Central)
            $timeToCheck = Carbon::now('America/Chicago')->format('H'); 
            //$timeToCheck = '23'; // for testing schedule
            if ($timeToCheck == '16') {  // 4:00pm cst
                $this->startTime = '00:00:00';
                $this->endTime = '15:59:59';
            } else {
                if ($timeToCheck == '23') {  // 11:30pm cst
                    $this->startTime = '00:00:00';
                    $this->endTime = '23:59:59';
                }
            }
        }

        // Get FTP details
        $pi = ProviderIntegration::where(
            'brand_id',
            $this->brandId
        )->where(
            'provider_integration_type_id',
            1
        )->where(
            'service_type_id',
            58
        )->first();

        if (empty($pi)) {
            $this->error("No credentials were found.");
            return -1;
        }

        $this->ftpSettings['host'] = $pi->hostname;
        $this->ftpSettings['username'] = $pi->username;
        $this->ftpSettings['password'] = $pi->password;
      
        $adapter = new SftpAdapter(
            [
                'host' =>  $this->ftpSettings['host'],
                'port' => $this->ftpSettings['port'],
                'username' => $this->ftpSettings['username'],
                'password' => $this->ftpSettings['password'],
                'root' => $this->ftpSettings['root'],
                'timeout' => $this->ftpSettings['timeout'],
                'directoryPerm' => $this->ftpSettings['directoryPerm'],
            ]
        );
        $filesystem = new Filesystem($adapter);
        // Build file name
        if ($this->option('useSchedule')) {  // Enrollment File schedule (4:00 PM and 11:30 AM Central)
            $filename = ($this->mode == 'test' ? 'TEST_' : '')
            . 'AnswerNet_bi_data_dump_'
            . $this->startDate->year  . '_'
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '_' 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
            . ($timeToCheck == '23' ? '_2' : '')
            . '.csv';
        } else {
            $filename = ($this->mode == 'test' ? 'TEST_' : '')
            . 'AnswerNet_bi_data_dump_'
            . $this->startDate->year  . '_'
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '_' 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
            . '.csv';
        }
        $this->info("Retrieving TPV data...");
        $data = StatsProduct::select(
            'stats_product.id',
            'stats_product.event_id',
            'stats_product.confirmation_code',
            'stats_product.language',
            'stats_product.interaction_created_at',
            'stats_product.market',
            'stats_product.btn',
            'stats_product.email_address',
            'stats_product.vendor_grp_id',
            'stats_product.vendor_name',
            'stats_product.vendor_label',
            'stats_product.company_name',
            'stats_product.tpv_agent_label',
            'stats_product.tpv_agent_name',
            'stats_product.sales_agent_rep_id',
            'stats_product.sales_agent_name',
            'stats_product.auth_first_name',
            'stats_product.auth_last_name',
            'stats_product.bill_first_name',
            'stats_product.bill_last_name',
            'stats_product.service_address1',
            'stats_product.service_address2',
            'stats_product.service_city',
            'stats_product.service_state',
            'stats_product.service_zip',
            'stats_product.service_county',
            'stats_product.billing_address1',
            'stats_product.billing_address2',
            'stats_product.billing_city',
            'stats_product.billing_state',
            'stats_product.billing_zip',
            'stats_product.billing_county',
            'stats_product.rate_program_code',
            'stats_product.account_number1',
            'stats_product.account_number2',
            'stats_product.name_key',
            'stats_product.commodity',
            'stats_product.utility_commodity_ldc_code',
            'stats_product.rate_external_id',
            'stats_product.product_id',
            'stats_product.product_rate_amount',
            'stats_product.product_term',
            'stats_product.product_cancellation_fee',
            'stats_product.product_intro_term',
            'rates.intro_rate_amount',
            'stats_product.channel',
            'stats_product.office_name',
            'brand_promotions.promotion_code',
            'brand_promotions.promotion_key',
            'stats_product.result',
            'stats_product.event_created_at',  
            'stats_product.custom_fields',
            'stats_product.recording',
            'stats_product.product_name',
            'stats_product.gps_coords',
            'stats_product.source',
            'stats_product.interaction_time',
            'stats_product.product_rate_type',
            'interactions.notes',
            'stats_product.disposition_reason',
            'stats_product.vendor_code',
            'vendors.grp_id as vendor_grp_id',
            'rates.custom_data_5',
            'gps_coords.coords',
            'stats_product.rate_monthly_fee',
            'stats_product.disposition_label'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_promotions',
            'event_product.brand_promotion_id',
            'brand_promotions.id'
        )->leftJoin(
            'interactions',
            'stats_product.interaction_id',
            'interactions.id'
        )->leftJoin(
            'vendors',
            'stats_product.vendor_id',
            'vendors.vendor_id'
        )->leftJoin(
            'rates',
            'stats_product.rate_id',
            'rates.id'
        )->leftJoin('addresses',function($leftjoin) {
            $leftjoin->on('stats_product.service_address1','=','addresses.line_1')
                ->on('stats_product.service_address2','=','addresses.line_2')
                ->on('stats_product.service_city','=','addresses.city')
                ->on('stats_product.service_state','=','addresses.state_province')
                ->on('stats_product.service_zip','=','addresses.zip');}
        )->leftJoin(
            'gps_coords',   
            'addresses.id', 
            'gps_coords.type_id'          
        )->whereDate(
            'stats_product.interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.interaction_created_at',
            '<=',
            $this->endDate);
        if ($this->option('useSchedule')) {  // Enrollment File schedule (4:00 PM and 11:30 AM Central) 
            $data = $data->whereTime(
                'stats_product.interaction_created_at',
                '>=',
                $this->startTime
            )->whereTime(
                'stats_product.interaction_created_at',
                '<=',
                $this->endTime);
        }
        $data = $data->where(
            'stats_product.brand_id',
            $this->brandId
        )->where(
            'vendors.brand_id',
            $this->brandId
        //  )->where(
        //      'stats_product.confirmation_code',
        //      '30530885534'
        )->whereIn(
            'stats_product.result',
            ['sale','no sale']
        )->orderBy(
            'stats_product.confirmation_code'
        )->orderBy(
            'stats_product.id'  // need this order so seqno is the same in all batch jobs 
        )->get();
       // $data_array = $data->toArray();

        // If no records, email client and exit early
        if(count($data) === 0) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for Spark Data Dump Enrollment File' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'spark_energy/'
        . 'enrollments/'
        . $this->startDate->year 
        . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
        . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
        . strval(time()) . '/'; // folder name

        // Create download directory if it doesn't exist
        if (!file_exists(public_path($folderName))) {
            mkdir(public_path($folderName), 0777, true);
        }

        $excelData = [];
        $confSave = 'zzz';
        $seqNo = 0;
        // Use transactions. This is so that we can rollback writes to json_documents if the job
        // errors out for any reason. The records can then be picked up on the next run.
        DB::beginTransaction();

        foreach ($data as $r) {
            if ($this->option('useSchedule')) {  // spark wanted this logic CERB 2024-04-19-29137
                if ($timeToCheck == '16' && ($r->disposition_label == '60004' || $r->disposition_label == '60003')) {  // 4:00pm cst
                    continue;  // skip these no sale
                } else {
                    if ($timeToCheck == '23' && $r->disposition_label == '60003') {  // 11:30pm cst
                        continue; // skip these no sale
                    }
                }
            }
            // Check if this record was already included in an enrollment file
            if(!$this->option('resubmit')) {
                if(empty($r->event_product_id)) {
                    $jd = JsonDocument::where('document_type', 'spark-data-dump-enrollment-ftp')
                        ->where('ref_id', $r->id)
                        ->first(); // We only care if ANY records exists, so no need to get all of them
                } else {
                    $jd = JsonDocument::where('document_type', 'spark-data-dump-enrollment-ftp')
                        ->where('ref_id', $r->event_product_id)
                        ->first(); // We only care if ANY records exists, so no need to get all of them
                }
                if($jd) {
                    if(!empty($jd->document['Result'])) {
                        if($jd->document['Result'] == $r->result) {  
                            $this->info("{$r->confirmation_code}::{$r->event_product_id} Has already been submitted. Skipping");
                            continue; //skip no change
                        } else { // if result changed drop through and send record again
                            if($jd->document['Result'] == 'Sale' && $r->result == 'No Sale') {
                                // if previous Good Sale is changed to No Sale ignore per Spark customer
                                $this->info("{$r->confirmation_code}::{$r->event_product_id} Has already been submitted. Skipping");
                                continue;
                            } // if previous No Sale is changed to Good Sale send to client per Spark customer
                        }
                    } else {
                        $this->info("{$r->confirmation_code}::{$r->event_product_id} Has already been submitted. Skipping");
                        continue;
                    }
                }
            }

            if ($confSave == $r->confirmation_code) {
                $seqNo++;
            } else {
                $seqNo = 1;
                $confSave = $r->confirmation_code;
            }
            $contact_consent = "";
            $brand_name_electric = "";
            $brand_name_gas = "";
            $pmtid = "";
            $ref_id = "";
            $customFields = [];
           if($r->custom_fields) {
                $customFields = json_decode($r->custom_fields);
                foreach($customFields AS $customField) {
                    switch(strtolower($customField->output_name)) {
                        case 'contact_consent':
                            $contact_consent = $customField->value;
                            break;
                        case 'pmtid_[bigint]_null':
                            $pmtid = $customField->value;
                            break;
                        case 'ref_id':
                            $ref_id = $customField->value;
                            break;
                        case 'brand_name_electric':
                            $brand_name_electric = $customField->value;
                            break;
                        case 'brand_name_gas':
                            $brand_name_gas = $customField->value;
                            break;
                    }
                }
                if (empty($ref_id)) {
                    $ref_id = $pmtid;  // whichever has a value populate (usually both custom fields are the same)
                }
            }
            $comments = ' ';
            if (gettype($r->notes) == 'array' && array_key_exists('reviewerNotes',$r->notes) == true) {
                $comments = $r->notes['reviewerNotes'];
             }
 
             // Map data to enrollment file fields.
            if ($r->interaction_time > 999) {
                $TotalCallTime = '999';  // should not be greater than 999
            } else {
                $ser_call_duration = number_format($r->interaction_time, 2);
                //$ser_hrs = floor($ser_call_duration / 60);
                $ser_mins = floor($ser_call_duration % 60);
                $ser_secs = $ser_call_duration - (int)$ser_call_duration;
                $ser_secs = round($ser_secs * 60);
                $TotalCallTime = strval(($ser_mins *60) + $ser_secs);
            }
            $custom_brand_name = $r->custom_data_5;
            if (empty($custom_brand_name)) {   // if brand not found this is for older data
                if (strtolower($r->commodity) == 'natural gas') {
                    if (empty($brand_name_gas)) { // third choice
                        $rateFound = rate::select('rates.custom_data_5'
                        )->where(
                            'rates.product_id',
                            $r->product_id
                        )->where(
                            'rates.program_code',
                            $r->rate_program_code
                        )->first();
                        if(count($rateFound) === 0) {
                            $custom_brand_name = null;  // didn't find any brand names 
                        } else {
                            $data_array_rate = $rateFound->toArray();
                            $custom_brand_name = $data_array_rate['custom_data_5'];
                        }
                    } else {  
                        $custom_brand_name = $brand_name_gas;  // second choice
                    }
                } else { // electric
                    if (empty($brand_name_electric)) {   // third choice
                        $rateFound = rate::select('rates.custom_data_5'
                        )->where(
                            'rates.product_id',
                            $r->product_id
                        )->where(
                            'rates.program_code',
                            $r->rate_program_code
                        )->first();
                        if(count($rateFound) === 0) {
                            $custom_brand_name = null;  // didn't find any brand names 
                        } else {
                            $data_array_rate = $rateFound->toArray();
                            $custom_brand_name = $data_array_rate['custom_data_5'];
                        }

                    } else {  
                        $custom_brand_name = $brand_name_electric; // second choice
                    }

                }
            }
            $CommodityPriceStep1 = "";
            $TermMonthsStep1 = "";
            $CommodityPriceStep2 = "";
            $TermMonthsStep2 = "";

            $isTieredOrStep = strtolower($r->product_rate_type) === 'tiered' || strtolower($r->product_rate_type) === 'step';

            if (empty($r->product_rate_amount)) { // no rate is a flat fee
                $productType = 'Flat Fee';
            } else {
                if ($isTieredOrStep) {
                    $productType = 'Step';
                    //IF a step product is in cents, it should have the decimal place moved two to the left.
                    //IF a step product is in dollars, it should remain in dollar format.
                    switch (strtolower($r->product_rate_amount_currency)) {
                        case "cents":
                            $product_rate_amount = ($r->product_rate_amount*.01); 
                            $intro_rate_amount = ($r->intro_rate_amount*.01); 
                            break;
                        case "dollars":
                            $product_rate_amount = $r->product_rate_amount; 
                            $intro_rate_amount = $r->intro_rate_amount; 
                            break;
                        default:
                            $product_rate_amount = $r->product_rate_amount; 
                            $intro_rate_amount = $r->intro_rate_amount; 
                    }

                    $CommodityPriceStep1 = $intro_rate_amount;
                    $TermMonthsStep1 = $r->product_intro_term;
                    $CommodityPriceStep2 = $product_rate_amount;
                    $TermMonthsStep2 = ($r->product_term - $r->product_intro_term);
                } else {
                    $productType = $r->product_rate_type;
                }
            }
            $productType = (strpos(strtolower($r->product_name),'flex') ? 'Flex' : $productType);

            $row = [
                'CallDateTime' => $r->interaction_created_at->format('m/d/Y H:i:s'),
                'TotalCallTime' => $TotalCallTime,
                'VendorName' => $r->vendor_name,
                'VendorNumber' => $r->vendor_grp_id,
                'LdcCode' => $r->utility_commodity_ldc_code,
                'UtilityTypeName' => (strtolower($r->commodity) == 'natural gas' ? 'Gas' : $r->commodity),
                'ProgramName' => $r->product_name,
                'Verified' => (strtolower($r->result) == 'sale' ? 'Good Sale' : $r->result),
                'AccountNumber' => $r->account_number1,
                'MeterNumber' => $r->account_number2,
                'MeterType' => ' ',
                'PremiseTypeName' => $r->market,
                'AuthorizationFirstName' => $r->auth_first_name,
                'AuthorizationLastName' => $r->auth_last_name,
                'ServiceAddress' => (empty(trim($r->service_address1) . ' ' . trim($r->service_address2)) 
                    ? ' ' : (trim($r->service_address1) . ' ' . trim($r->service_address2))),
                'ServiceCity' => $r->service_city,
                'ServiceState' => $r->service_state,
                'ServiceZip' => $r->service_zip,
                'ServiceCounty' => $r->service_county,
                'Email' => $r->email_address,
                'Btn' => (empty(str_replace('+1','',$r->btn)) ? ' ' : str_replace('+1','',$r->btn)),
                'AccountFirstName' => $r->bill_first_name,
                'AccountLastName' => $r->bill_last_name,
                'BillingAddress' => (empty(trim($r->billing_address1) . ' ' . trim($r->billing_address2)) 
                ? ' ' : (trim($r->billing_address1) . ' ' . trim($r->billing_address2))),
                'BillingCity' => $r->billing_city,
                'BillingState' => $r->billing_state,
                'BillingZip' => $r->billing_zip,
                'BillingCounty' => $r->billing_county,
                'Language' => $r->language,
                'ProgramCode' => $r->rate_program_code,
                'Rate' => ($isTieredOrStep ? ' ' : $r->product_rate_amount),
                'Term' => $r->product_term,
                'MSF' => $r->rate_monthly_fee,
                'ETF' => $r->product_cancellation_fee,
                'AgentId' => $r->sales_agent_rep_id,
                'Name' => $r->vendor_code,
                'TpvAgentId' => $r->sales_agent_rep_id,
                'TpvAgentName' => $r->sales_agent_name,
                'RateClass' => ' ',
                'MainId' => $r->confirmation_code,
                'Concern' => (strtolower($r->result) == 'sale' ? 'VERIFIED' : $r->disposition_reason),
                'OfficeName' => $r->office_name,
                'ExternalSalesId' => 'TRU' . $r->confirmation_code . str_pad($seqNo,3,'0',STR_PAD_LEFT),
                'Brand' =>   $custom_brand_name,
                'ProductName' => $r->product_name,
                'MarketerCode' => $r->vendor_label,
                'TPVCompanyName' => "AnswerNetTPV",
                'Source' => $r->source,
                'BillCycle' => ' ',
                'Consent' => $contact_consent,
                'LandOrCell' => ' ',
                'PhoneCo' => ' ',
                'VOIP' => ' ',
                'WrapUp' => ' ',
                'WavName' => (empty($r->recording) ? ' ' :  empty($r->btn) ? ' ' : Carbon::parse($r->interaction_created_at,'America/Chicago')->format('YmdHis') . '_' . str_replace('+1','',$r->btn). '_' . $r->confirmation_code . '.mp3'),
                'OutBoundWavName' => ' ',
                'TPVAuditFileName' => ' ',
                'FieldSalesNOCFileName' => ' ',
                'FieldSalesPhotoIDFileName' => ' ',
                'BaseCharge' => ' ',
                'AgentGeolocation' => $r->gps_coords,
                'ServiceGeolocation' => $r->coords,
                'AgentSubmitDistance' => ' ',
                'AUTOPAY' => ' ',
                'Comments' => $comments,   // interactions.notes  'reviewerNotes'
                'custom.pmtid_[bigint]null' => $ref_id,
                'CommodityPriceStep1' => $CommodityPriceStep1,
                'TermMonthsStep1' => $TermMonthsStep1,
                'CommodityPriceStep2' => $CommodityPriceStep2,
                'TermMonthsStep2' => $TermMonthsStep2,
            ];

            // Add this row of data to the correct vendor in the CSV array.
            $excelData[] = $row;
            // Write JSON record, documenting what file this record was included in
            if(!$this->option('no-json-doc')) {
                $jdData = [
                    'Date' => Carbon::now("America/Chicago")->format("Y-m-d H:i:s"),
                    'InteractionDate' => $r->interaction_created_at->timezone('America/Chicago')->format('Y-m-d'),
                    'ConfirmationCode' => $r->confirmation_code,
                    'Commodity' => $r->commodity,
                    'AccountNumber' => $r->account_number1,
                    'Filename' => $filename,
                    'Disposition_label' => $r->disposition_label,
                    'Result' => $r->result  // need to track result "Good sale/no sale" for further logic
                ];
                if($this->option('resubmit')) {
                    if(empty($r->event_product_id)) {
                        $jd = JsonDocument::where('document_type', 'spark-data-dump-enrollment-ftp')
                            ->where('ref_id', $r->id)
                            ->first(); // We only care if ANY records exists, so no need to get all of them
                    } else {
                        $jd = JsonDocument::where('document_type', 'spark-data-dump-enrollment-ftp')
                            ->where('ref_id', $r->event_product_id)
                            ->first(); // We only care if ANY records exists, so no need to get all of them
                    }
                    if($jd) {
                        $jd->delete(); 
                    }
                }
                $jdNew = new JsonDocument();

                $jdNew->document_type = 'spark-data-dump-enrollment-ftp';
                if(empty($r->event_product_id)) {
                    $jdNew->ref_id = $r->id;
                } else {
                    $jdNew->ref_id = $r->event_product_id;
                }
                $jdNew->document = $jdData;

                $jdNew->save();
            }

        }
        // If it got this far and no records selected must use resubmit 
        if(count($excelData) === 0) {
            $this->info('0 records found to submit. You must use resubmit');
            return 0;
        }

        // Write the CSV file.
        $this->info('Writing CSV file...');
        $filecsv = fopen(public_path($folderName . $filename), 'w');

        $fileHeader = [];
         foreach (array_keys($excelData[0]) as $key) {
             $fileHeader[] = $key;
         }
        fputs($filecsv, implode(',', $fileHeader) . "\r\n");

        // Data
         foreach ($excelData as $row) {
             fputs($filecsv,'"' .  implode('","', $row) . '"' . "\r\n");
         }
         fclose($filecsv);

        // Create the XLS file
        //$this->info('Writing data to Xls file...');
        //$this->writeXlsFile($excelData, public_path($folderName . $filename));

        //Upload the file to FTP server
        //$ftpFileName = basename($filename,'.xls') . '.zip';
        $ftpFileName = $filename;
        if (!$this->option('noftp')) {
            $this->info('Uploading file...');
            $this->info($ftpFileName);
            $ftpResult = 'SFTP at ' . Carbon::now() . '. Status: ';
            try {
                $stream = fopen(public_path($folderName . $ftpFileName), 'r+');
                $filesystem->writeStream(
                    $ftpFileName,
                    $stream
                );

                if (is_resource($stream)) {
                    fclose($stream);
                }
                $ftpResult .= 'Success!';
            } catch (\Exception $e) {
                DB::rollBack();
                $ftpResult .= 'Error! The reason reported is: ' . $e;
                $this->info($ftpResult);
            }
        
            $this->info($ftpResult);

            if (isset($ftpResult)) {
                if (strpos(strtolower($ftpResult),'success') > 0) {
                    $this->info('Upload succeeded.');
                    $this->sendEmail('File ' . $ftpFileName . ' has been successfully uploaded.', $this->distroList['ftp_success'][$this->mode]);
                } else {
                    $this->info('Upload failed.');
                    $this->sendEmail(
                        'Error uploading file ' . $ftpFileName . ' to FTP server ' . $this->ftpSettings['host'] .
                            "\n\n FTP Result: " . $ftpResult,
                        $this->distroList['ftp_error'][$this->mode]
                    );

                    return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
                }
            }
        }

        DB::commit();


                // Regardless of FTP result, also email the file as an attachment
                // if (!$this->option('noemail')) {
        $attachments = [public_path($folderName . $filename)];   // only send enrollment file 

        $this->info("Emailing file...");
        $this->sendEmail('Attached is the file for Spark Data Dump Enrollment File' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
        // }
        // Delete tmp files and folders
   //     $this->removeDir(public_path($folderName));

    }

    protected function removeDir($dirname) {
        if (is_dir($dirname)) {
            $dir = new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS);
            foreach (new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST) as $object) {
                if ($object->isFile()) {
                    unlink($object);
                } elseif($object->isDir()) {
                    rmdir($object);
                } else {
                    throw new Exception('Unknown object type: '. $object->getFileName());
                }
            }
            rmdir($dirname); 
        } else {
            throw new Exception('This is not a directory');
        }
    }
    

    /**
     * Sends and email.
     *
     * @param string $message - Email body.
     * @param array  $distro  - Distribution list.
     * @param array  $files   - Optional. List of files to attach.
     *
     * @return string - Status message
     */
    protected function sendEmail(string $message, array $distro, array $files = array())
    {
        $uploadStatus = [];
        $email_address = $distro;

        // Build email subject
        if ('production' != env('APP_ENV')) {
            $subject = $this->jobName . ' (' . env('APP_ENV') . ') '
                . Carbon::now();
        } else {
            $subject = $this->jobName . ' ' . Carbon::now();
        }

        if ($this->mode == 'test') {
            $subject = '(TEST) ' . $subject;
        }

        $data = [
            'subject' => '',
            'content' => $message
        ];

        for ($i = 0; $i < count($email_address); ++$i) {
            $status = 'Email to ' . $email_address[$i]
                . ' at ' . Carbon::now() . '. Status: ';

            try {
                Mail::send(
                    'emails.generic',
                    $data,
                    function ($message) use ($subject, $email_address, $i, $files) {
                        $message->subject($subject);
                        $message->from('no-reply@tpvhub.com');
                        $message->to(trim($email_address[$i]));

                        // add attachments
                        foreach ($files as $file) {
                            $message->attach($file);
                        }
                    }
                );
            } catch (\Exception $e) {
                $status .= 'Error! The reason reported is: ' . $e;
                $uploadStatus[] = $status;
            }

            $status .= 'Success!';
            $uploadStatus[] = $status;
        }

        return $uploadStatus;
    }

    /**
     * Writes an Excel file from a data array. Data array should
     * use named keys as the keys are used for the header row.
     */
    // protected function writeXlsFile($data, $fileName) {

    //     try {
    //         $headers = array_keys($data[0]);

    //         $spreadsheet = new Spreadsheet();
    //         $sheet = $spreadsheet->getActiveSheet();
    //         $sheet = $spreadsheet->getActiveSheet()->setTitle('Sheet1');
    //         $sheet->fromArray($headers, null, 'A1');
    //         $sheet->fromArray($data, null, 'A2');
    //         $recRow = 1;
    //         foreach ($data as $r) {   // fromArray above makes assumptions on numeric fields rewrite cell 
    //             $recRow = $recRow+1; 
    //             $sheet->setCellValueExplicit('B'.strval($recRow),$r['TotalCallTime'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //             $sheet->setCellValueExplicit('I'.strval($recRow),$r['AccountNumber'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //             $sheet->setCellValueExplicit('J'.strval($recRow),$r['MeterNumber'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //             $sheet->setCellValueExplicit('U'.strval($recRow),$r['Btn'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //             $sheet->setCellValueExplicit('AD'.strval($recRow),$r['ProgramCode'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //             $sheet->setCellValueExplicit('AN'.strval($recRow),$r['MainId'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //             $sheet->setCellValueExplicit('BN'.strval($recRow),$r['custom.pmtid_[bigint]null'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //         }
    //         $writer = IOFactory::createWriter($spreadsheet, 'Xls');
    //         $writer->save($fileName);
    //     } catch (\Exception $e) {
    //         // TODO: Handle
    //     }

    //     // TODO: Return a result
    // }
    
}
