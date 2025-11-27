<?php

namespace App\Console\Commands\CleanSky;

//use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\ProviderIntegration;
use App\Models\StatsProduct;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CleanskyWinBackSalesFile extends Command
{

     public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CleanskyWinBackSalesFile {--mode=} {--start-date=} {--end-date=} {--noftp} {--noemail}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'CleanSky WinBack Sales ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates and ftp WinBack file';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0'; //  prod ID
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [  // Left FTP logic in case client changes mind
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

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
        'port' => 21,
        'root' => '/',
        'passive' => true,
        'ssl' => true,
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
        if (!$this->option('noftp')) {

            // Get FTP details
            $pi = ProviderIntegration::where(
                'brand_id',
                $this->brandId
            )->where(
                'provider_integration_type_id',
                1
            )->where(
                'service_type_id',
                59
            )->first();

            if (empty($pi)) {
                $this->error("No credentials were found.");
                return -1;
            }

            $this->ftpSettings['host'] = $pi->hostname;
            $this->ftpSettings['username'] = $pi->username;
            $this->ftpSettings['password'] = $pi->password;
        
            $adapter = new ftp($this->ftpSettings);
            $filesystem = new Filesystem($adapter);
        }
        // Build file name
        $filename = ($this->mode == 'test' ? 'TEST_' : '')
             . $this->startDate->year  . '_'
             . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '_' 
             . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) . '_' 
             . 'cleansky_energy_wbc.csv';
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
            'stats_product.bill_middle_name',
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
            'stats_product.rate_program_code',  
            'stats_product.utility_commodity_ldc_code',
            'stats_product.rate_external_id',
            'stats_product.product_rate_amount',
            'stats_product.product_term',
            'stats_product.product_cancellation_fee',
            'stats_product.channel',
            'stats_product.office_name',
            'stats_product.result',
            'stats_product.event_created_at',  
            'stats_product.custom_fields',
            'stats_product.recording',
            'stats_product.product_name',
            'stats_product.gps_coords',
            'stats_product.source',
            'stats_product.interaction_time',
            'stats_product.product_rate_type',
            'stats_product.product_monthly_fee',
            'stats_product.vendor_code',
            'stats_product.product_utility_external_id',
            'stats_product.utility_commodity_external_id',
            'stats_product.rate_monthly_fee',
            'stats_product.rate_id',
            'stats_product.product_rate_amount'
        )->whereDate(
            'stats_product.interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.interaction_created_at',
            '<=',
            $this->endDate
        )->where(
            'stats_product.brand_id',
            $this->brandId
        )->whereIn(
            'stats_product.result',
            ['sale']
        )->whereRaw(
            "stats_product.custom_fields LIKE '%Winback%'"
        // )->where(
        //     'stats_product.confirmation_code',
        //     '=',
        //     '31151263122'
        )->orderBy(
            'stats_product.confirmation_code'
        )->get();

        // If no records, email client and exit early
        if(count($data) === 0) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for CleanSky WinBack Sales File' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'tmp/cleansky/'
        . 'winback/'
        . $this->startDate->year 
        . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
        . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
        . strval(time()) . '/'; // folder name

        // Create download directory if it doesn't exist
        if (!file_exists(public_path($folderName))) {
            mkdir(public_path($folderName), 0777, true);
        }

        $csvData = [];
        foreach ($data as $r) {
            $winback_end_date = "";
            $winback_start_date = "";
            $winback_price = "";
            $market_channel = "";  // "Winback"
            $customer_type = ""; // "Current Plan"
            $customFields = [];
           if($r->custom_fields) {
                $customFields = json_decode($r->custom_fields);
                foreach($customFields AS $customField) {
                    switch(strtolower($customField->output_name)) {
                        case 'winback_end_date':
                            $winback_end_date = date("Y-m-d",strtotime($customField->value));
                            break;
                        case 'winback_start_date':
                            $winback_start_date = date("Y-m-d",strtotime($customField->value));
                            break;
                        case 'winback_price':
                            $winback_price = $customField->value;
                            break;
                        case 'market_channel':
                            $market_channel = $customField->value;
                            break;
                        case 'customer_type':
                            $customer_type = $customField->value;
                            break;
                            }
                }
            }
            if (strtolower($customer_type) == 'current plan'  && strtolower($market_channel) == 'winback') {
                $row = [
                    'Agent_ID' => strtoupper($r->sales_agent_rep_id),
                    'Confirmation_Number' => $r->confirmation_code,
                    'First_Name' => $r->bill_first_name,
                    'Last_Name' => $r->bill_last_name,
                    'Customer_Name' => $r->auth_first_name . ' ' . $r->auth_last_name,    
                    'Home_Phone_Number' => (empty(str_replace('+1','',$r->btn)) ? '' : str_replace('+1','',$r->btn)),
                    'Distributor_Name' => $r->utility_commodity_ldc_code,
                    'Service_Type_Desc' => ($r->commodity == 'Natural Gas' ? 'Gas' : 'Electric'),
                    'LDC_Account_Num' => $r->account_number1,
                    'Requested_Start_Date' => $r->event_created_at->format('Y-m-d'),
                    'Contract_Start_Date' => $winback_start_date, // $r->event_created_at->format('m/d/Y'),
                    'Contract_End_Date' => $winback_end_date,
                    'Fixed_Commodity_Amt' => $winback_price, //  $r->product_rate_amount * 0.01,
                    'Fixed_Charge_Amt' => '0',
                    'SPostal_Code' => $r->service_zip,
                ];
                // Add this row of data to the correct vendor in the CSV array.
                $excelData[] = $row;
            }

        }
        if (empty($excelData)) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for CleanSky WinBack Sales File' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

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

        //        Upload the file to FTP server
        $ftpFileName = $filename;
        if (!$this->option('noftp')) {
            $this->info('Uploading file...');
            $this->info($ftpFileName);
            $ftpResult = 'FTP at ' . Carbon::now() . '. Status: ';
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
        if (!$this->option('noemail')) {
            $attachments = [public_path($folderName . $filename)];   // only send enrollment file 

            $this->info("Emailing file...");
            $this->sendEmail('Attached is the file for CleanSky WinBack file' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
        }
        // Delete tmp files and folders
        $this->removeDir(public_path($folderName));

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
    protected function writeXlsFile($data, $fileName) {

        try {
            $headers = array_keys($data[0]);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet = $spreadsheet->getActiveSheet()->setTitle('Sheet1');
            $sheet->fromArray($headers, null, 'B1');
            $sheet->fromArray($data, null, 'B2');
            $recRow = 1;
            foreach ($data as $r) {   // fromArray above makes assumptions on numeric fields rewrite cell 
                $recRow = $recRow+1; 
                $sheet->setCellValueExplicit('D'.strval($recRow),$r['confirmation_code'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.strval($recRow),$r['confirmation_code'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('J'.strval($recRow),$r['account_number1'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AE'.strval($recRow),$r['btn'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $writer->save($fileName);
        } catch (\Exception $e) {
            // TODO: Handle
        }

        // TODO: Return a result
    }
    
}
