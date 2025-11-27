<?php

namespace App\Console\Commands\Family;

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
use App\Models\EventProductIdentifier;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class FamilyEnergyEnrollmentFile extends Command
{

     public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FamilyEnergyEnrollmentFile 
        {--mode=        : Optional. Valid values are "live" and "test". "live" is used by default.} 
        {--noftp        : Optional. If provided, creates the file but does not FTP it.} 
        {--start-date=  : Optional. Start date for data query. Must be paired with --end-date. If omitted, current date is used.} 
        {--end-date=    : Optional. End date for data query. Must be paired with --start-date. If omitted, current date is used.}'; 

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Family Energy Enrollment File ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates and Emails Family Energy\'s enrollment file';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '1de6e0fc-8951-45bd-a88b-e353b0d85dfc'; //  prod ID 
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [  
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'accountmanagers@answernet.com'],
           // 'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            'test' => ['curt.cadwell@answernet.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com','accountmanagers@answernet.com','curt.cadwell@answernet.com'],
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
        'port' => 8022,
        'root' => '/Report/Telesales',
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
        $this->startDate = Carbon::yesterday('America/Chicago');
        $this->endDate = Carbon::today('America/Chicago')->add(-1, 'second');

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

        // Get FTP details
        $pi = ProviderIntegration::where(
            'brand_id',
            $this->brandId
        )->where(
            'provider_integration_type_id',
            1
        )->where(
            'service_type_id',
            44
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
            $filename = ($this->mode == 'test' ? 'TEST_' : '')
            . 'FamilyEnergyEnrollment_'
            . $this->startDate->year  . '_'
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '_' 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
            . '.txt';
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
            'stats_product.disposition_label',
            'stats_product.event_product_id',
            'stats_product.structure_type',
            'stats_product.product_green_percentage',
            'stats_product.auth_relationship',
            'brand_utilities.utility_label'
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
        )->leftJoin(
            'brand_utilities',
            function($join) {
                $join->on('stats_product.utility_id', 'brand_utilities.utility_id');
                $join->on('stats_product.brand_id', 'brand_utilities.brand_id');
            }
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
        $data = $data->where(
            'stats_product.brand_id',
            $this->brandId
        )->where(
            'vendors.brand_id',
            $this->brandId
        //   )->where(
        //       'stats_product.confirmation_code',
        //       '41371055011'
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

            $message = 'There were no records to send for Family Energy Enrollment File' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'family_energy/'
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

        foreach ($data as $r) {
            $result = ($r->result == 'Sale' ? 'Accepted' : 'Declined');
            $row = [
                'CONTRACTNO' => '',
                'CUSTTYPE' => $r->market,
                'GAS' => (strtolower($r->commodity) == 'natural gas' ? 'YES' : ''),
                'ELECTRICITY' => (strtolower($r->commodity) == 'electric' ? 'YES' : ''),
                'GREENGAS' => (strtolower($r->commodity) == 'natural gas' && !empty($r->product_green_percentage) ? 'YES' : ''),
                'GREENELECTRICITY' => (strtolower($r->commodity) == 'electric' && !empty($r->product_green_percentage) ? 'YES' : ''),
                'SALUTATION' => '',
                'FIRSTNAME' => $r->bill_first_name,
                'MIDDLENAME' => '',
                'LASTNAME' => $r->bill_last_name,
                'PRINTNAME' => $r->bill_first_name . (empty(trim($r->bill_middle_name)) ? ' ' : trim($r->bill_middle_name) . ' ' ) . $r->bill_last_name,
                'HOMEEMAIL' => $r->email_address,
                'HOMEPHONE' => (empty(str_replace('+1','',$r->btn)) ? ' ' : str_replace('+1','',$r->btn)),
                'CELLPHONE' => '',
                'CALLERIDNUMBER' => $r->ani,
                'DATEOFBIRTH' => '',
                'CUSTAGEVERIFICATION' => '',
                'LANGUAGE' => $r->language,
                'SERVICEADDRESS1' => $r->service_address1,
                'SERVICEADDRESS2' => $r->service_address2,
                'SERVICECITY' => $r->service_city,
                'SERVICESTATE' => $r->service_state,
                'SERVICEZIPCODE' => $r->service_zip,
                'DWELLINGTYPE' => $r->structure_type,
                'OCCUPANCY' => '',
                'MAILINGADDRESS1' => $r->billing_address1,
                'MAILINGADDRESS2' => $r->billing_address2,
                'MAILINGCITY' => $r->billing_city,
                'MAILINGSTATE' => $r->billing_state,
                'MAILINGZIPCODE' => $r->billing_zip,
                'GASUTILITYACCOUNTNUMBER' => (strtolower($r->commodity) == 'natural gas' ? $r->account_number1 : ''),
                'ELECTRICITYUTILITYACCOUNTNUMBER' => (strtolower($r->commodity) == 'electric' ? $r->account_number1 : ''),
                'GASPODID' => (strtolower($r->commodity) == 'natural gas' && empty(!$r->account_number2) ? $r->account_number2 : ''),
                'ELECTRICITYPODID' => (strtolower($r->commodity) == 'electric' && empty(!$r->account_number2) ? $r->account_number2 : ''),
                'GASMETERNUMBER' => (strtolower($r->commodity) == 'natural gas' && empty(!$r->name_key) ? $r->name_key : ''),
                'ELECTRICITYMETERNUMBER' => (strtolower($r->commodity) == 'electric' && empty(!$r->name_key) ? $r->name_key : ''),
                'GASUTILITY' => (strtolower($r->commodity) == 'natural gas' ? $r->utility_commodity_ldc_code : ''),
                'ELECTRICITYUTILITY' => (strtolower($r->commodity) == 'electric' ? $r->utility_commodity_ldc_code : ''),
                'GASCONTRACTPRICE' => '', //(strtolower($r->commodity) == 'natural gas' ? $r->product_rate_amount : ''),
                'ELECTRICITYCONTRACTPRICE' => '', //(strtolower($r->commodity) == 'electric' ? $r->product_rate_amount : ''),
                'GREENGASCONTRACTPRICE' => '', //(strtolower($r->commodity) == 'natural gas' && !empty($r->product_green_percentage) ? $r->product_rate_amount : ''),
                'GREENELECTRICITYCONTRACTPRICE' => '', //(strtolower($r->commodity) == 'electric' && !empty($r->product_green_percentage) ? $r->product_rate_amount : ''),
                'GREENGASPERCENT' => '', //(strtolower($r->commodity) == 'natural gas' && !empty($r->product_green_percentage) ? $r->product_green_percentage : ''),
                'GREENELECTRICITYPERCENT' => '', //(strtolower($r->commodity) == 'electric' && !empty($r->product_green_percentage) ? $r->product_green_percentage : ''),
                'CONTRACTSIGNED' => $r->event_created_at->format('m/d/Y'),
                'GASCONTRACTTERMYEARS' => '',
                'GASCONTRACTTERMMONTHS' => '', //(strtolower($r->commodity) == 'natural gas' && !empty($r->product_green_percentage) ? $r->product_term : ''),
                'ELECTRICITYCONTRACTTERMYEARS' => '',
                'ELECTRICITYCONTRACTTERMMONTHS' => '', //(strtolower($r->commodity) == 'electric' && !empty($r->product_green_percentage) ? $r->product_term : ''),
                'AGENT' => $r->office_name,
                'REPNUMBER' => $r->sales_agent_rep_id,
                'GASPROGRAMCODE' => (strtolower($r->commodity) == 'natural gas' ? $r->rate_external_id : ''),
                'ELECTRICITYPROGRAMCODE' => (strtolower($r->commodity) == 'electric' ? $r->rate_external_id : ''),
                'RELATIONSHIPTOACCOUNTHOLDER' => $r->auth_relationship,
                'SIGNUPMETHOD' => ($r->channel == 'TM' ? 'Telemarketing' : $r->channel),
                'GASPRODUCTCODE' => '',
                'ELECTRICITYPRODUCTCODE' => '',
                'ITERATION' => '',
                'TPVGASRESULT' => (strtolower($r->commodity) == 'natural gas' ? $result : ''),
                'TPVELECTRICITYRESULT' => (strtolower($r->commodity) == 'electric' ? $result : ''),
                'TPVGREENGASRESULT' => (strtolower($r->commodity) == 'natural gas' && !empty($r->product_green_percentage) ? $result : ''),
                'TPVGREENELECTRICITYRESULT' =>(strtolower($r->commodity) == 'electric' && !empty($r->product_green_percentage) ? $result : ''),
                'TPVINBOUNDDATE' => $r->event_created_at->format('m/d/Y'),
                'TPVINBOUNDTIME' => $r->interaction_created_at->format('H:i:s'), //'?? ivr start time ??',
                'TPVOUTBOUNDDATE' => '',
                'TPVOUTBOUNDTIME' => '',
                'TPVEMPLOYEE' => 'Answernet',
                'TPVCOMPANY' => 'Answernet',
                'TPVCONTACTEDPERSON' => 'Answernet',
                'TPVGASPROBLEM' => (strtolower($r->commodity) == 'natural gas' ? $r->disposition_reason : ''),
                'TPVELECTRICITYPROBLEM' => (strtolower($r->commodity) == 'electric' ? $r->disposition_reason : ''),
                'TPVGREENGASPROBLEM' => (strtolower($r->commodity) == 'natural gas' && !empty($r->product_green_percentage) ? $r->disposition_reason : ''),
                'TPVGREENELECTRICITYPROBLEM' => (strtolower($r->commodity) == 'electric' && !empty($r->product_green_percentage) ? $r->disposition_reason : ''),
                'TPVCOMMENTS' => '',
                'TPVCONFIRMATIONID' => $r->confirmation_code,
                'IMPORTCREATIONTYPE' => 'BOTH',
                'PREFEREDMETHODOFCONTACT' => '',
                'CAMPAIGN' => '',
            ];

            // Add this row of data to the correct vendor in the CSV array.
            $excelData[] = $row;

        }
        if(count($excelData) === 0) {
            $this->info('0 records found to submit.');
            return 0;
        }

        // Write the CSV file.
        $this->info('Writing TXT file...');
        $filecsv = fopen(public_path($folderName . $filename), 'w');

        $fileHeader = [];
         foreach (array_keys($excelData[0]) as $key) {
             $fileHeader[] = $key;
         }
        fputs($filecsv, implode('|', $fileHeader) . "\r\n");

        // Data
         foreach ($excelData as $row) {
             //fputs($filecsv,'"' .  implode('","', $row) . '"' . "\r\n");
             fputs($filecsv, implode('|', $row) . "\r\n");
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

                // Regardless of FTP result, also email the file as an attachment
                // if (!$this->option('noemail')) {
        $attachments = [public_path($folderName . $filename)];   // only send enrollment file 

        $this->info("Emailing file...");
        $this->sendEmail('Attached is the file for Family Energy Enrollment File' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
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
