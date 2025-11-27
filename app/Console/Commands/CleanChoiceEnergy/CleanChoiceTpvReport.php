<?php

namespace App\Console\Commands\CleanChoiceEnergy;

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

class CleanChoiceTpvReport extends Command
{

     public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CleanChoiceTpvReport {--mode=} {--start-date=} {--end-date=} {--noemail}';

    //{--noftp}

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'CleanChoice TPV Report ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CleanChoice TPV Report ';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '37cb9600-76e7-45cd-b24b-d0e2ccff8032'; //  prod ID
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [  // Left FTP logic in case client changes mind
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['salessupport@cleanchoice.com', 'curt.cadwell@answernet.com','accountmanagers@answernet.com'],
            'test' => ['curt.cadwell@answernet.com','accountmanagers@answernet.com']
       ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['salessupport@cleanchoice.com', 'curt.cadwell@answernet.com','accountmanagers@answernet.com'],
            'test' => ['curt.cadwell@answernet.com','accountmanagers@answernet.com']
        ],
         'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['salessupport@cleanchoice.com', 'curt.cadwell@answernet.com','accountmanagers@answernet.com'],
            'test' => ['curt.cadwell@answernet.com','accountmanagers@answernet.com']

        ]
    ];

    /**
     * FTP Settings
     *
     * @var array
     */

    //  protected $ftpSettings = [
    //     'host' => '',
    //     'username' => '',
    //     'password' => '',
    //     'port' => 21,
    //     'root' => '/',
    //     'passive' => true,
    //     'ssl' => true,
    //     'timeout' => 30,
    //     'directoryPerm' => 0755,
    // ];

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
//        $this->startDate = Carbon::yesterday('America/Chicago');
        $this->startDate = Carbon::now('America/Chicago')->subDays(7); // wed thru tue
        $this->endDate = Carbon::yesterday('America/Chicago'); //->add(-1, 'second');

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
        // if (!$this->option('noftp')) {

        //     // Get FTP details
        //     $pi = ProviderIntegration::where(
        //         'brand_id',
        //         $this->brandId
        //     )->where(
        //         'provider_integration_type_id',
        //         1
        //     )->where(
        //         'service_type_id',
        //         59
        //     )->first();

        //     if (empty($pi)) {
        //         $this->error("No credentials were found.");
        //         return -1;
        //     }

        //     $this->ftpSettings['host'] = $pi->hostname;
        //     $this->ftpSettings['username'] = $pi->username;
        //     $this->ftpSettings['password'] = $pi->password;
        
        //     $adapter = new ftp($this->ftpSettings);
        //     $filesystem = new Filesystem($adapter);
        // }

        // Build file name
        $filename = ($this->mode == 'test' ? 'TEST_' : '')
             . 'clean_choice_energy_weekly_report_'
             . $this->startDate->year
             . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
             . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) . '_thru_' 
             . $this->endDate->year   
             . str_pad($this->endDate->month, 2, '0', STR_PAD_LEFT) 
             . str_pad($this->endDate->day, 2, '0', STR_PAD_LEFT) . '.csv'; 

         $this->info("Retrieving TPV data...");
        $data = StatsProduct::distinct()->select(
//            'stats_product.id',
//            'stats_product.event_id',
//            'stats_product.confirmation_code',
//            'stats_product.language',
            'stats_product.interaction_created_at',
//            'stats_product.market',
            'stats_product.btn',
            'stats_product.email_address',
//            'stats_product.vendor_name',
//            'stats_product.vendor_label',
//            'stats_product.company_name',
//            'stats_product.tpv_agent_label',
//            'stats_product.tpv_agent_name',
//            'stats_product.sales_agent_rep_id',
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
//            'stats_product.billing_address1',
//            'stats_product.billing_address2',
//            'stats_product.billing_city',
//            'stats_product.billing_state',
//            'stats_product.billing_zip',
//            'stats_product.billing_county',
//            'stats_product.rate_program_code',
//           'stats_product.account_number1',
//            'stats_product.account_number2',
//            'stats_product.name_key',
//            'stats_product.commodity',
//            'stats_product.rate_program_code',  
//            'stats_product.utility_commodity_ldc_code',
//            'stats_product.rate_external_id',
//            'stats_product.product_rate_amount',
//            'stats_product.product_term',
//            'stats_product.product_cancellation_fee',
//            'stats_product.channel',
            'stats_product.office_name',
            'stats_product.result',
//            'stats_product.event_created_at',  
//            'stats_product.custom_fields',
//            'stats_product.recording',
//            'stats_product.product_name',
//            'stats_product.gps_coords',
            'stats_product.source',
            'stats_product.interaction_time'
//            'stats_product.product_rate_type',
//            'stats_product.product_monthly_fee',
//            'stats_product.vendor_code',
//            'stats_product.product_utility_external_id',
//            'stats_product.utility_commodity_external_id',
//            'stats_product.rate_monthly_fee',
//            'stats_product.rate_id',
//            'stats_product.product_rate_amount',
//            'stats_product.disposition_reason',
//            'interactions.notes'
//        )->leftJoin(
//            'interactions',
//            'stats_product.interaction_id',
//            'interactions.id'
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
            ['sale','no sale']
        // )->where(
        //     'stats_product.confirmation_code',
        //     '=',
        //     '32971734260'
        )->orderBy(
            'stats_product.interaction_created_at'
        )->get();

        // If no records, email client and exit early
        if(count($data) === 0) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for CleanChoice TPV Report ' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'tmp/CleanChoice/'
        . 'tpvreport/'
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
            $comments = ' ';
//            if (gettype($r->notes) == 'array' && array_key_exists('reviewerNotes',$r->notes) == true) {
//                $comments = $r->notes['reviewerNotes'];
//             }
                $row = [
//                    'confirmation_code' => $r->confirmation_code,
//                    'event_created_at' => $r->event_created_at,
                    'interaction_created_at' => $r->interaction_created_at,
//                    'channel' => $r->channel,
                    'result' => $r->result,
//                    'disposition' => $r->disposition_reason,
//                    'notes' => $comments,
                    'source' => $r->source,
                    'cust_name' => trim($r->bill_first_name) . ' ' . trim($r->bill_last_name),
                    'auth_name' => trim($r->auth_first_name) . ' ' . trim($r->auth_last_name),
                    'btn' => $r->btn,
                    'email_address' => $r->email_address,
                    'service_address1' => $r->service_address1,
                    'service_city' => $r->service_city,
                    'service_state' => $r->service_state,
                    'service_zip_code' => $r->service_zip,
                    'office_name' => $r->office_name,
                    'sales_agent_name' => $r->sales_agent_name,
                ];
                // Add this row of data to the correct vendor in the CSV array.
                $excelData[] = $row;

        }
        if (empty($excelData)) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for CleanChoice TPV Report' . $this->startDate->format('m-d-Y') . '.';
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
        // $ftpFileName = $filename;
        // if (!$this->option('noftp')) {
        //     $this->info('Uploading file...');
        //     $this->info($ftpFileName);
        //     $ftpResult = 'FTP at ' . Carbon::now() . '. Status: ';
        //     try {
        //         $stream = fopen(public_path($folderName . $ftpFileName), 'r+');
        //         $filesystem->writeStream(
        //             $ftpFileName,
        //             $stream
        //         );

        //         if (is_resource($stream)) {
        //             fclose($stream);
        //         }
        //         $ftpResult .= 'Success!';
        //     } catch (\Exception $e) {
        //         $ftpResult .= 'Error! The reason reported is: ' . $e;
        //         $this->info($ftpResult);
        //     }
        
        //     $this->info($ftpResult);

        //     if (isset($ftpResult)) {
        //         if (strpos(strtolower($ftpResult),'success') > 0) {
        //             $this->info('Upload succeeded.');

        //         $this->sendEmail('File ' . $ftpFileName . ' has been successfully uploaded.', $this->distroList['ftp_success'][$this->mode]);
        //         } else {
        //             $this->info('Upload failed.');
        //             $this->sendEmail(
        //                 'Error uploading file ' . $ftpFileName . ' to FTP server ' . $this->ftpSettings['host'] .
        //                     "\n\n FTP Result: " . $ftpResult,
        //                 $this->distroList['ftp_error'][$this->mode]
        //             );

        //             return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
        //         }
        //     }
        // }


        // Regardless of FTP result, also email the file as an attachment
        if (!$this->option('noemail')) {
            $attachments = [public_path($folderName . $filename)];   // only send enrollment file 

            $this->info("Emailing file...");
            $this->sendEmail('Attached is the file for CleanChoice TPV Report' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
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
