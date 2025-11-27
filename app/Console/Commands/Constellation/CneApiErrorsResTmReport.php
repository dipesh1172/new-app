<?php

namespace App\Console\Commands\Constellation;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
//use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\ProviderIntegration;
use App\Models\StatsProduct;
use App\Models\JsonDocument;
use App\Models\Rate;
use App\Models\ScriptAnswer;
use App\Models\EventAlert;
use App\Models\ClientAlert;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CneApiErrorsResTmReport extends Command
{

     public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CneApiErrorsResTmReport {--mode=} {--start-date=} {--end-date=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Constellation API Errors ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates Constellation API Error Report';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = 'fd9470af-5045-4ee6-82e0-88d608c110dc'; //  prod ID
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [  // Left FTP logic in case client changes mind
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['michelle.pietron@constellation.com', 'patricia.mares@constellation.com','sharon.gallardo@answernet.com','curt.cadwell@answernet.com','accountmanagers@answernet.com'],
           // 'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
           'test' => ['curt.cadwell@answernet.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            //'live' => ['dxc_autoemails@tpv.com','curt.cadwell@answernet.com','curt@tpv.com'], // add this when ftp is reinstated 2023-02-03
            'live' => ['michelle.pietron@constellation.com', 'patricia.mares@constellation.com','sharon.gallardo@answernet.com','curt.cadwell@answernet.com','accountmanagers@answernet.com'],
            //'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
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
        'root' => '/',
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

        // Get FTP details
        // $pi = ProviderIntegration::where(
        //     'brand_id',
        //     $this->brandId
        // )->where(
        //     'provider_integration_type_id',
        //     1
        // )->where(
        //     'service_type_id',
        //     58
        // )->first();

        // if (empty($pi)) {
        //     $this->error("No credentials were found.");
        //     return -1;
        // }

        // $this->ftpSettings['host'] = $pi->hostname;
        // $this->ftpSettings['username'] = $pi->username;
        // $this->ftpSettings['password'] = $pi->password;
      
        // $adapter = new SftpAdapter(
        //     [
        //         'host' =>  $this->ftpSettings['host'],
        //         'port' => $this->ftpSettings['port'],
        //         'username' => $this->ftpSettings['username'],
        //         'password' => $this->ftpSettings['password'],
        //         'root' => $this->ftpSettings['root'],
        //         'timeout' => $this->ftpSettings['timeout'],
        //         'directoryPerm' => $this->ftpSettings['directoryPerm'],
        //     ]
        // );
        // $filesystem = new Filesystem($adapter);
        // Build file name
        $filename = ($this->mode == 'test' ? 'TEST_' : '')
            . 'Constellation API Errors Report '
            . $this->startDate->year . '-'
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '-' 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) 
            . '.xlsx';
         $this->info("Retrieving TPV data...");
        $data = JsonDocument::select(
            'json_documents.created_at',
            'json_documents.document_type',
            'json_documents.document',
            'json_documents.ref_id',
            DB::raw('json_extract(document,"$.updateTpvStatus_Response.data.IsSuccess") as is_success'),
            DB::raw('json_extract(document,"$.updateTpvStatus_Response.data.ErrorBusinessMessageList.*.ErrorCode") as error_code'),
            DB::raw('json_extract(document,"$.updateTpvStatus_Response.data.ErrorBusinessMessageList.*.ErrorText") as error_text'),
            DB::raw('json_extract(document,"$.updateTpvStatus_Response.data.ErrorBusinessMessageList") as test'),
            DB::raw('json_extract(document,"$.updateTpvStatus_Request") as response_id'),
            DB::raw("(SELECT COUNT(*) 
                WHERE json_documents.ref_id = stats_product.confirmation_code 
                GROUP BY stats_product.confirmation_code,stats_product.commodity) AS dual_fuel"),
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
            'stats_product.vendor_grp_id',
            'stats_product.company_name',
            'stats_product.tpv_agent_label',
            'stats_product.tpv_agent_name',
            'stats_product.sales_agent_rep_id',
            'stats_product.sales_agent_name',
            'users.first_name',
            'users.last_name',
            'stats_product.auth_first_name',
            'stats_product.auth_middle_name',
            'stats_product.auth_last_name',
            'stats_product.bill_first_name',
            'stats_product.bill_middle_name',
            'stats_product.bill_last_name',
            'stats_product.service_address1',
            'stats_product.service_city',
            'stats_product.service_state',
            'stats_product.service_zip',
            'stats_product.service_county',
            'stats_product.billing_address1',
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
            'stats_product.product_utility_name',
            'stats_product.rate_external_id',
            'stats_product.product_id',
            'stats_product.product_rate_amount',
            'stats_product.product_rate_amount_currency',
            'stats_product.product_intro_term',
            'rates.intro_rate_amount',
            'stats_product.rate_uom',
            'stats_product.product_term',
            'stats_product.product_cancellation_fee',
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
            'stats_product.product_monthly_fee',
            'stats_product.vendor_code',
            'stats_product.product_utility_external_id',
            'stats_product.utility_commodity_external_id',
            'stats_product.rate_monthly_fee',
            'stats_product.disposition_label',
            'stats_product.disposition_reason',
            'stats_product.interaction_id',
            'vendors.grp_id as vendor_grp_id',
            'scripts.title',
            'stats_product.rate_id',
            'rates.rate_renewal_plan',
            'rates.custom_data_5'
    )->leftJoin(
            'stats_product',
            'stats_product.confirmation_code',
            'json_documents.ref_id'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_promotions',
            'event_product.brand_promotion_id',
            'brand_promotions.id'
        )->leftJoin(
            'vendors',
            'stats_product.vendor_id',
            'vendors.vendor_id'
        )->leftJoin(
            'events',
            'stats_product.event_id',
            'events.id'
        )->leftJoin(
            'scripts',
            'events.script_id',
            'scripts.id'
        )->leftJoin(
            'rates',
            'stats_product.rate_id',
            'rates.id'
        )->leftJoin(
            'brand_users',
            'stats_product.sales_agent_id',
            'brand_users.id'
        )->leftJoin(
            'users',
            'brand_users.user_id',
            'users.id'
        )->whereDate(
            'stats_product.interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.interaction_created_at',
            '<=',
            $this->endDate
        )->where('json_documents.document_type',
            'cne-send-tpv-notifications'
        //)->where(DB::raw('json_extract(document,"$.updateTpvStatus_Response.data.IsSuccess"))','false')
         //   $query->where(DB::raw("json_extract(old_value, '$.theme_id')"), 1);
        )->where('json_documents.document',
             'like',
             '%,"IsSuccess":false}}}%'
        )->where(
            'vendors.brand_id',
            $this->brandId
        // )->whereIn(
        //     'stats_product.result',
        //     ['sale']
        )->orderBy(
            'json_documents.ref_id'
        )->orderBy(
            'json_documents.created_at' 
        )->get();

        // If no records, email client and exit early
        if(count($data) === 0) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for Constellation API Error Report ' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'tmp/constellation/api/';
            // . $this->startDate->year 
            // . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
            // . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
            // . strval(time()) . '/'; // folder name

        // Create download directory if it doesn't exist
        if (!file_exists(public_path($folderName))) {
            mkdir(public_path($folderName), 0777, true);
        }

        $excelData = [];
        foreach ($data as $r) {
            // $questions_answers = ScriptAnswer::select(
            //     'script_questions.section_id',
            //     'script_questions.subsection_id',
            //     'script_questions.question_id',
            //     'script_questions.question',
            //     'script_answers.answer_type',
            //     'script_answers.answer'
            // )->leftJoin(
            //     'script_questions',
            //     'script_answers.question_id',
            //     'script_questions.id'
            // )->where(
            //     'script_answers.interaction_id',
            //     $r->interaction_id
            // )->orderBy(
            //     'script_questions.section_id'
            // )->orderBy('script_questions.subsection_id')
            //     ->orderBy('script_questions.question_id'
            // )->get();
            // $question = '';
            // foreach ($questions_answers as $qa) {
            //     if (!empty($qa->question)) {
            //         switch (strtolower($r->language)) {
            //             case "english":
            //                 if (!empty($qa->question["english"])) {
            //                     $question = $question . $qa->question["english"] . PHP_EOL;
            //                 }
            //                 break;
            //             case "spanish":
            //                 if (!empty($qa->question["spanish"])) {
            //                     $question = $question . $qa->question["spanish"] . PHP_EOL;
            //                 }
            //                 break;    
            //         }
            //     }
            // }    
            $alerts = EventAlert::select(
                'client_alerts.title'
            )->leftJoin(
                'client_alerts',
                'client_alerts.id',
                'event_alerts.client_alert_id'
            )->where(
                'event_alerts.event_id',
                $r->event_id
            )->orderBy(
                'client_alerts.title'
            )->get();
            $alerts_sent = '';
            foreach ($alerts as $sent) {
                if (!empty($sent->title)) {
                    $alerts_sent = $alerts_sent . $sent->title . PHP_EOL;
                }
            }

             $IsSuccess = $r->document["updateTpvStatus_Response"]["data"]["IsSuccess"];
             $ErrorCode = $r->document["updateTpvStatus_Response"]["data"]["ErrorBusinessMessageList"][0]["ErrorCode"];
             $ErrorText = $r->document["updateTpvStatus_Response"]["data"]["ErrorBusinessMessageList"][0]["ErrorText"];

            $row = ['API_Errors' => $ErrorText,
                'Date' => $r->created_at->format('m/d/Y H:i:s'),
                'Ver_Code' => $r->confirmation_code,
                'Status_txt' => $r->result,
                'Status_id' => $r->disposition_label,
                'Reason' => $r->disposition_reason,
                'Channel' => $r->channel,
                'Partner_name' => $r->vendor_name,
                'Partner_id' => $r->vendor_grp_id,
                'Office_id' => $r->office_name,
                'Source' => 'Live',
                'VL_rep_id' => $r->tpv_agent_label,
                'CNE_rep_id' => $r->sales_agent_rep_id,
                'CNE_rep_first name' => $r->first_name,
                'CNE_rep_last name' => $r->last_name,
                'Language' => $r->language,
                'Sales_state' => $r->service_state,
                'Dual_Fuel' => ($r->dual_fuel > 1 ? 'Yes' : 'No'),
                'Commodity' => $r->commodity,
                'Home_Svc_name' => '',
                'Include_on_BGEBill' => '',
                'HS_Plan_id' => '',
                'Enrollment_id' => '',
                'Response_id' => substr($r->response_id,strpos($r->response_id,"ResponseId=")+11,strpos(substr($r->response_id,strpos($r->response_id,"ResponseId=")+11),"&")),
                'Btn' => (empty(str_replace('+1','',$r->btn)) ? '' : str_replace('+1','',$r->btn)),
                'Email' => $r->email_address,
                'Updated_email' => '',
                'Auth_fname' => $r->auth_first_name,
                'Auth_mi' => $r->auth_middle_name,
                'Auth_lname' => $r->auth_last_name,
                'Relationship' => '', //Relationship to Account Holder Question - Y or N',
                'Bill_fname' => $r->bill_first_name,
                'Bill_mi' => $r->bill_middle_name,
                'Bill_lname' => $r->bill_last_name,
                'Billing_address' => $r->billing_address1,
                'Billing_city' => $r->billing_city,
                'Billing_state' => $r->billing_state,
                'Billing_zip' => $r->billing_zip,
                'Addr1' => $r->service_address1,
                'Addr2' => '',
                'City' =>  $r->service_city,
                'State' => $r->service_state,
                'Zip' => $r->service_zip,
                'Name_key' => $r->name_key,
                'Acct_num' => $r->account_number1,
                'Acct_or_pod' => ' ', // Customer Number Account Number
                'Meter_number' => $r->account_number2,
                'Utility' => $r->product_utility_name,
                'Ldc_code' => $r->utility_commodity_ldc_code,
                'Rate' => $r->product_rate_amount,
                'Unit_measurement' => $r->product_rate_amount_currency . ' per ' . $r->rate_uom,
                'Term' => $r->product_term,
                'ETF_fee' => $r->product_cancellation_fee,
                'Plan_id' => $r->rate_program_code,
                'Call_time' => $r->interaction_time,
                'Ib_ct' => $r->interaction_time,
                'Ob_ct' => '',
                'Form_name' => $r->title,
//                'NoSale_Alert' => 'do we send an alert out?',  // do we send an alert out?
                'NoSale_Alert' => $alerts_sent,  
                'Internal_NoSale_Alert' => '',
                'Home_Svc_Manager_Alert' => ''
//                'Question' => $question
             ];

            // Add this row of data to the correct vendor in the CSV array.
            $excelData[] = $row;

        }

        // Create the XLS file
        $this->info('Writing data to Xls file...');
        $this->writeXlsFile($excelData, public_path($folderName . $filename));

        // Upload the file to FTP server
        // $ftpFileName = basename($filename);
        // if (!$this->option('noftp')) {
        //     $this->info('Uploading file...');
        //     $this->info($ftpFileName);
        //     $ftpResult = 'SFTP at ' . Carbon::now() . '. Status: ';
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
        // } else {
        //     $this->info('Upload failed.');
        //     $this->sendEmail(
        //         'Error uploading file ' . $ftpFileName . ' to FTP server ' . $this->ftpSettings['host'] .
        //             "\n\n FTP Result: " . $ftpResult,
        //         $this->distroList['ftp_error'][$this->mode]
        //     );

        //     return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
        // }
        //     }
        // }


        // Regardless of FTP result, also email the file as an attachment
        // if (!$this->option('noemail')) {
        $attachments = [public_path($folderName . $filename)];   // only send enrollment file 

        $this->info("Emailing file...");
        $this->sendEmail('Attached is the file for Constellation Report ' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
        // }
        // Delete tmp files and folders
        //$this->removeDir(public_path($folderName));

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
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($data, null, 'A2');
            $recRow = 1;
            foreach ($data as $r) {   // fromArray above makes assumptions on numeric fields rewrite cell 
                $recRow = $recRow+1; 
                $sheet->setCellValueExplicit('C'.strval($recRow),$r['Ver_Code'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.strval($recRow),$r['Status_id'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('I'.strval($recRow),$r['Partner_id'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('L'.strval($recRow),$r['VL_rep_id'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('M'.strval($recRow),$r['CNE_rep_id'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('X'.strval($recRow),$r['Response_id'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('Y'.strval($recRow),$r['Btn'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AL'.strval($recRow),$r['Billing_zip'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AQ'.strval($recRow),$r['Zip'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AS'.strval($recRow),$r['Acct_num'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AU'.strval($recRow),$r['Meter_number'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AX'.strval($recRow),$r['Rate'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AZ'.strval($recRow),$r['Term'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BA'.strval($recRow),$r['ETF_fee'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BB'.strval($recRow),$r['Plan_id'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BC'.strval($recRow),$r['Call_time'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BD'.strval($recRow),$r['Ib_ct'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($fileName);
        } catch (\Exception $e) {
            // TODO: Handle
        }

        // TODO: Return a result
    }
    
}
