<?php

namespace App\Console\Commands;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
//use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\CustomFieldStorage;
use App\Models\ProviderIntegration;
use App\Models\StatsProduct;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DirectEnergy_SC_TM_Enrollment_Recordings_Zip extends Command
{

    public $cloudfront;
    public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DirectEnergy_SC_TM_Enrollment_Recordings_Zip {--mode=} {--noftp} {--norecordings} {--noemail} {--start-date=} {--end-date=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Direct Energy - SC -TM - FTP File Generation ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates, FTPs, Recordings and Emails Direct Energy\'s enrollment file';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '94d29d20-0bcf-49a3-a261-7b0c883cbd1d'; //  prod ID
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', '_tpvteam@directenergy.com','contract.alberta@directenergy.com','sharon.gallardo@answernet.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com','curt.cadwell@answernet.com','curt@tpv.com','sharon.gallardo@answernet.com'],
            'test' => ['dxcit@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
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
        'root' => '/inbound/',
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
        $this->cloudfront = config('services.aws.cloudfront.domain');
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
        $pi = ProviderIntegration::where(
            'brand_id',
            $this->brandId
        )->where(
            'provider_integration_type_id',
            1
        )->where(
            'service_type_id',
            33
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
            . 'GATEWAYSC_'
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '_' 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) . '_'
            . $this->startDate->year 
            . '.xls';
         $this->info("Retrieving TPV data...");
        $data = StatsProduct::select(
            'stats_product.id',
            'stats_product.event_id',
            'stats_product.event_product_id',
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
            'stats_product.account_number1',
            'stats_product.account_number2',
            'stats_product.name_key',
            'stats_product.commodity',
            'stats_product.rate_program_code',  
            'stats_product.utility_commodity_ldc_code',
            'stats_product.rate_external_id',
            'stats_product.product_rate_amount',
            'brand_promotions.promotion_code',
            'brand_promotions.promotion_key',
            'stats_product.result',
            'stats_product.event_created_at',  
            'stats_product.custom_fields',
            'stats_product.recording',
            'stats_product.product_name',
            'events.external_id'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_promotions',
            'event_product.brand_promotion_id',
            'brand_promotions.id'
        )->leftJoin(
            'events',
            'stats_product.event_id',
            'events.id'
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
        )->where(
            'stats_product.channel',
            'TM'
        )->where(
            'stats_product.market',
            'commercial'
        )->whereIn(
            'stats_product.result',
            ['sale']
        )->whereNull(
            'events.external_id'    // leave out records that have lead posted only manual transactions sent
        )->orderBy(
            'stats_product.interaction_created_at'
        )->get();

        // If no records, email client and exit early
        if(count($data) === 0) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for ' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'tmp/direct_energy/'
        . 'enrollments/'
        . $this->startDate->year 
        . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
        . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
        . strval(time()) . '/'; // folder name
        // Create download directory if it doesn't exist
        if (!file_exists(public_path($folderName))) {
            mkdir(public_path($folderName), 0777, true);
        }
        // Create voice folder if it doesn't exist
        if (!file_exists(public_path($folderName . 'VOICE/'))) {
            mkdir(public_path($folderName . 'VOICE/'), 0777, true);
        }

        $excelData = [];
        foreach ($data as $r) {
            $billing_account_number = ' ';
            $app_source_code = ' ';
            if($r->confirmation_code == '22920821959') {
                $curtstop = '';
            }
            /**
             * Start - custom fields
             * This logic is needed instead of looping through stats.product custom_fields
             * since there is an issue with multiple values for instance billing_account_number store if
             * multiple accounts are given
             */

            $custom_data = CustomFieldStorage::select('custom_fields.output_name', 'custom_field_storages.value','custom_field_storages.product_id')
                ->join('custom_fields', 'custom_fields.id', 'custom_field_storages.custom_field_id')
                ->join('events', 'events.id', 'custom_field_storages.event_id')
                ->where('events.id', $r->event_id)
//                ->where('custom_field_storages.product_id', $r->event_product_id)
                ->get();
            foreach ($custom_data as $rec) {  
                    switch(strtolower($rec->output_name)) {
                    case 'billing_account_number':
                        if ($rec->product_id == $r->event_product_id) {
                            $billing_account_number = $rec->value;
                        }
                        break;
                    case 'appsourcecode':
                        $app_source_code = $rec->value;
                        break;
                }
            }
            /**
             * End - custom fields
             */

            switch(strtolower($r->vendor_name)) {
                case 'gateway energy':
                $agentCode = 'GES';
                break;
                case 'phoenix outbound':
                $agentCode = 'PHNX';
                break;
                default:
                $agentCode = '';
            }
            //     DO CASE
            //     CASE UPPER(ALLTRIM(gateway_custom.esco_id)) == "DER"
            //       m.escokey = "74709"
            //     CASE UPPER(ALLTRIM(gateway_custom.esco_id)) == "DEV"
            //       m.escokey = "76148"
            //     CASE UPPER(ALLTRIM(gateway_custom.esco_id)) == "ENX"
            //       m.escokey = "76995"
            //     CASE UPPER(ALLTRIM(gateway_custom.esco_id)) == "NSI"
            //       m.escokey = "76994"
            //     OTHERWISE && Gateway
            //       m.escokey = "31"
            //   ENDCASE
            $escoKey = '74709';  // determine if needed Focus DXC code above 
            // Map data to enrollment file fields.
            // DE requires a space if no value specified
            $accountno = ' ';
            if ($r->utility_commodity_ldc_code === 'NHE' && empty($r->account_number1)) {
                $accountno = $r->account_number2;
             } else {
                $accountno = $r->account_number1;
            }
            $meternumber = ' ';
            if ($r->utility_commodity_ldc_code === 'NCG' && !empty($r->account_number1)) {
                $meternumber = $r->account_number2;
            }
            $row = [
                'ACCOUNTNO' => $accountno,
                'TERRCODE' => (empty($r->utility_commodity_ldc_code) ? ' ' : $r->utility_commodity_ldc_code),
                'FNAME' => (empty($r->auth_first_name) ? ' ' : $r->auth_first_name),
                'LNAME' => (empty($r->auth_last_name) ? ' ' : $r->auth_last_name),
                'COMPANY' => (empty($r->company_name) ? ' ' : $r->company_name),
                'ADDRESS' => (empty(trim($r->service_address1) . ' ' . trim($r->service_address2)) 
                    ? ' ' : (trim($r->service_address1) . ' ' . trim($r->service_address2))),
                'CITY' => (empty($r->service_city) ? ' ' : $r->service_city),
                'STATE' => (empty($r->service_state) ? ' ' : $r->service_state),
                'ZIP' => (empty($r->service_zip) ? ' ' : $r->service_zip),
                'SCLASSID' => (empty($r->market) ? ' ' : substr(strtoupper($r->market),0,3)),
                'INFO1' => (empty(str_replace('+1','',$r->btn)) ? ' ' : str_replace('+1','',$r->btn)),
                'DESCRIPT1' => 'HOME',
                'BILLCAREOF' => ' ',
                'BILLADDRESS' => (empty(trim($r->service_address1) . ' ' . trim($r->service_address2)) 
                    ? ' ' : (trim($r->service_address1) . ' ' . trim($r->service_address2))),
                'BILLCITY' => (empty($r->service_city) ? ' ' : $r->service_city),
                'BILLSTATE' => (empty($r->service_state) ? ' ' : $r->service_state),
                'BILLZIP' => (empty($r->service_zip) ? ' ' : $r->service_zip),
                'CONTRACTDATE' => $r->interaction_created_at->format('m/d/Y'),
                'EXPORTDATE_TIME' => ' ',
                'ENTRYDATE_TIME' => $r->interaction_created_at->format('m/d/Y H:i:s'),
                'ENTERED' => $r->interaction_created_at->format('m/d/Y H:i:s'),
                'SECURITYCODE' => ' ',
                'CONTACT' => ' ',
                'SALESREPID' => (empty($r->sales_agent_rep_id) ? ' ' : $r->sales_agent_rep_id),
                'AGENTCODE' => (empty($agentCode) ? ' ' : $agentCode),
                'BATCHNUM' => ' ',
                'METERNUM' => $meternumber,
                'ECORATE' => ' ',
                'CONTRACTRATE' => ' ',
                'VERNUM' => $r->confirmation_code,
                'ADSOURCE' => 'OTHER',
                'NOTES' => ' ',
                'FIXEDSTART' => ' ',
                'FIXEDEND' => ' ',
                'TAXEXEMPT' => ' ',
                'TAXID' => ' ',
                'TAXABLEPERCENT' => ' ',
                'SIGNNAME' => ' ',
                'ENTEREDBYID' => (empty($r->tpv_agent_label) ? ' ' : $r->tpv_agent_label),
                'ENTRYBATCH' => ' ',
                'APPSOURCECODE' => ' ', // temporary change until DE gets back to us (empty($app_source_code) ? ' ' : $app_source_code),
                'UTILPROCESSED' => ' ',
                'APPNUMBER' => ' ',
                'EMAIL' => (empty($r->email_address) ? ' ' : $r->email_address),
                'ACCTNOCHANGE' => ' ',
                'ACTIONCODE' => ' ',
                'ACSTATUSCODE' => ' ',
                'APPNUMBER1' => ' ',
                'CREDITCODE' => ' ',
                'CANCELSTATUS' => ' ',
                'BUFFER_APPID' => ' ',
                'PROMOTIONSKEY' => ' ',
                'CUST_SUBTYPE_KEY' => ' ',
                'TAX_ID' => ' ',
                'SOC_SEC_NUM' => ' ',
                'CONTRACT_RECVD_DATE' => ' ',
                'APPROX_ANNUAL_USAGE' => ' ',
                'PROMOTIONRECIPIENTKEY' => '1',
                'AGENTMARKETINGCD' => ' ',
                'ESCOKEY' => $escoKey,
                'RATEGROUPCD' => ' ',
                'PRODUCTKEY' => (empty($r->rate_program_code) ? ' ' : $r->rate_program_code),
                'ESCORATEPLANOFFERINGKEY' => (empty(str_replace('000000','',$r->rate_program_code)) ? ' ' : str_replace('000000','',$r->rate_program_code)),
                'BUDGETBILL' => ' ',
                'LANGUAGE_KEY' => (empty(strtolower($r->language)) ? ' ' : strtolower($r->language)),
                'LOW_INCOME_IND' => ' ',
                'ENROLL_TRANSITION_TYPE_KEY' => ' ',
                'CREATEDBY' => ' ',
                'CREATEDATETIME' => ' ',
                'LASTUPDATEBY' => ' ',
                'LASTUPDATEDATETIME' => ' ',
//                'CONFIRMATIONID' => ' ',  // in DXC the is recid we only have guid will see if DE kicks back error
                'CONFIRMATIONID' => $r->confirmation_code,  
                'CONTRACTBASECOST' => ' ',
                'DWELLINGTYPEKEY' => ' ',
                'ENROLLSTARTDATE' => ' ', //(empty($r->interaction_created_at->format('m-d-Y')) ? ' ' : $r->interaction_created_at->format('m-d-Y')),  // custom field future_start_date might be this
                'BILLPRESENTERCD' => ' ',
                'BILLCALCCD' => ' ',
                'CREDITREFERENCENUM' => ' ',
                'ADJUSTMENTPERCENT' => ' ',
                'CREDITSCORETYPEKEY' => ' ',
                'DATEOFBIRTH' => ' ',
                'TAXSPECIALSTATUSKEY' => ' ',
                'DOCUMENTATIONRECEIVEDIND' => ' ',
                'SURCHARGEKEY' => ' ',
                'SAFETYNET' => ' ',
                'MARKETINGMETHODCD' => ' ',
                'ORIGINALCONTRACTIND' => ' ',
                'RECIPIENTCUSTOMERNUM' => ' ',
                'SERVICE_TYPE' => (empty(trim(str_replace('Natural','',$r->commodity))) ? ' ' : trim(str_replace('Natural','',$r->commodity))),
                'APP_ID' => ' ',
                'NAMEKEY' => (empty($r->name_key) ? ' ' : $r->name_key),
                'BILLINGACCTNUM' => $billing_account_number,
                'SALESFORCEID' => ' ',
                'MSIID' => ' ',
                'PLENTIACCOUNTNUMBER' => ' ',
                'CONTACT_METHOD_CODE' => ' ',
                'MOBILE_PHONE' => ' ',
                'NONCOMMODITYSERVICES' => ' ',
                'COMMUNICATIONMETHODCDKEY' => ' ',
            ];

            $excelData[] = $row;
            if (!$this->option('norecordings')) {
                if ($r->recording && is_file(public_path($folderName . 'VOICE/' . $r->confirmation_code . '.mp3')) === false) {
                    $fileType = 'wav';
                    $key = 'recording';
                    $file = $this->cloudfront . '/' . $r->recording;
                    $return = $this->downloadFile(
                        $file,
                        $key,
                        $r->interaction_created_at,
                        $r->confirmation_code,
                        'recording',
                        $folderName
                    );
                    $process = new Process('lame --decode ' . 
                    public_path($folderName . 'VOICE/' . $r->confirmation_code . '.mp3 ') .
                    public_path($folderName . 'VOICE/' . $r->confirmation_code . '.wav'));
                    $process->run();

                        // executes after the command finishes
                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    } else {

                        $this->info($process->getOutput());
                        unlink (public_path($folderName . 'VOICE/' . $r->confirmation_code . '.mp3'));
                    }
                }
            }
        }

        // Create the XLS file
        $this->info('Writing data to Xls file...');
        $this->writeXlsFile($excelData, public_path($folderName . $filename));
        $this->info('Finished Writing data to Xls file...');

        // zipping files and folders
        $this->info('Zipping files...');
        $this->zipFiles(public_path($folderName),
        public_path($folderName . basename($filename,'.xls') . '.zip'));
        $this->info('Finished Zipping files...');
        
        // Upload the file to FTP server
        $ftpFileName = basename($filename,'.xls') . '.zip';
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
        if (!$this->option('noemail')) {
            $attachments = [public_path($folderName . $filename)];   // only send enrollment file recordings are to large dont send zipfile

            $this->info("Emailing file...");
            $this->sendEmail('Attached is the FTP file for ' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
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
     * zips all files and folder.
     */
    protected function zipFiles($source, $destination) {
        if (!extension_loaded('zip')) {
            return false;
        }
    
        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }
    
        $source = str_replace('\\', '/', realpath($source));
    
        if (is_dir($source) === true)
        {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
    
            foreach ($files as $file)
            {
                $file = str_replace('\\', '/', $file);
    
                // Ignore "." and ".." folders
                if(in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                    continue;
    
                $file = realpath($file);
    
                if (is_dir($file) === true)
                {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                }
                else if (is_file($file) === true && $file != basename($destination))
                {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        }
        else if (is_file($source) === true && $file != basename($destination))
        {
            $zip->addFromString(basename($source), file_get_contents($source));
        }
    
        return $zip->close();
    
    }

    /**
     * Unzips a file.
     */
    protected function unzipFile($zipFile, $extractTo) {
        
        
        $this->info("\nUnzipping file...");

        $zip = new ZipArchive;

        if(!str_ends_with($extractTo, '/')) {
            $extractTo .= '/';
        }

        $res = $zip->open($zipFile);
        if($res === true) {
            $zip->extractTo($extractTo);
            $zip->close();

            $this->info("Successfully unzipped archive '" . $zipFile . "'.");

            return true;
        } else {
            $this->error("Unable to open Zip file '" . $zipFile . "'.");
        }

        return false;
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
            $sheet->fromArray($headers, null, 'A1');
//            $sheet->fromArray($data, null, 'A2');
            $recRow = 1;
            foreach ($data as $r) {   // fromArray above makes assumptions on numeric fields rewrite cell 
                $recRow = $recRow+1; 
                $sheet->setCellValueExplicit('A'.strval($recRow),$r['ACCOUNTNO'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B'.strval($recRow),$r['TERRCODE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C'.strval($recRow),$r['FNAME'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D'.strval($recRow),$r['LNAME'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.strval($recRow),$r['COMPANY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('F'.strval($recRow),$r['ADDRESS'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('G'.strval($recRow),$r['CITY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H'.strval($recRow),$r['STATE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('I'.strval($recRow),$r['ZIP'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('J'.strval($recRow),$r['SCLASSID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('K'.strval($recRow),$r['INFO1'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('L'.strval($recRow),$r['DESCRIPT1'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('M'.strval($recRow),$r['BILLCAREOF'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('N'.strval($recRow),$r['BILLADDRESS'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('O'.strval($recRow),$r['BILLCITY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('P'.strval($recRow),$r['BILLSTATE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('Q'.strval($recRow),$r['BILLZIP'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('R'.strval($recRow),$r['CONTRACTDATE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('S'.strval($recRow),$r['EXPORTDATE_TIME'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('T'.strval($recRow),$r['ENTRYDATE_TIME'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('U'.strval($recRow),$r['ENTERED'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('V'.strval($recRow),$r['SECURITYCODE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('W'.strval($recRow),$r['CONTACT'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('X'.strval($recRow),$r['SALESREPID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('Y'.strval($recRow),$r['AGENTCODE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('Z'.strval($recRow),$r['BATCHNUM'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
 
                $sheet->setCellValueExplicit('AA'.strval($recRow),$r['METERNUM'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AB'.strval($recRow),$r['ECORATE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AC'.strval($recRow),$r['CONTRACTRATE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AD'.strval($recRow),$r['VERNUM'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AE'.strval($recRow),$r['ADSOURCE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AF'.strval($recRow),$r['NOTES'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AG'.strval($recRow),$r['FIXEDSTART'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AH'.strval($recRow),$r['FIXEDEND'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AI'.strval($recRow),$r['TAXEXEMPT'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AJ'.strval($recRow),$r['TAXID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AK'.strval($recRow),$r['TAXABLEPERCENT'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AL'.strval($recRow),$r['SIGNNAME'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AM'.strval($recRow),$r['ENTEREDBYID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AN'.strval($recRow),$r['ENTRYBATCH'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AO'.strval($recRow),$r['APPSOURCECODE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AP'.strval($recRow),$r['UTILPROCESSED'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AQ'.strval($recRow),$r['APPNUMBER'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AR'.strval($recRow),$r['EMAIL'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AS'.strval($recRow),$r['ACCTNOCHANGE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AT'.strval($recRow),$r['ACTIONCODE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AU'.strval($recRow),$r['ACSTATUSCODE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AV'.strval($recRow),$r['APPNUMBER1'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AW'.strval($recRow),$r['CREDITCODE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AX'.strval($recRow),$r['CANCELSTATUS'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AY'.strval($recRow),$r['BUFFER_APPID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AZ'.strval($recRow),$r['PROMOTIONSKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                $sheet->setCellValueExplicit('BA'.strval($recRow),$r['CUST_SUBTYPE_KEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BB'.strval($recRow),$r['TAX_ID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BC'.strval($recRow),$r['SOC_SEC_NUM'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BD'.strval($recRow),$r['CONTRACT_RECVD_DATE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BE'.strval($recRow),$r['APPROX_ANNUAL_USAGE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BF'.strval($recRow),$r['PROMOTIONRECIPIENTKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BG'.strval($recRow),$r['AGENTMARKETINGCD'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BH'.strval($recRow),$r['ESCOKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BI'.strval($recRow),$r['RATEGROUPCD'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BJ'.strval($recRow),$r['PRODUCTKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BK'.strval($recRow),$r['ESCORATEPLANOFFERINGKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BL'.strval($recRow),$r['BUDGETBILL'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BM'.strval($recRow),$r['LANGUAGE_KEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BN'.strval($recRow),$r['LOW_INCOME_IND'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BO'.strval($recRow),$r['ENROLL_TRANSITION_TYPE_KEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BP'.strval($recRow),$r['CREATEDBY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BQ'.strval($recRow),$r['CREATEDATETIME'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BR'.strval($recRow),$r['LASTUPDATEBY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BS'.strval($recRow),$r['LASTUPDATEDATETIME'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BT'.strval($recRow),$r['CONFIRMATIONID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BU'.strval($recRow),$r['CONTRACTBASECOST'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BV'.strval($recRow),$r['DWELLINGTYPEKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BW'.strval($recRow),$r['ENROLLSTARTDATE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BX'.strval($recRow),$r['BILLPRESENTERCD'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BY'.strval($recRow),$r['BILLCALCCD'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('BZ'.strval($recRow),$r['CREDITREFERENCENUM'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                $sheet->setCellValueExplicit('CA'.strval($recRow),$r['ADJUSTMENTPERCENT'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CB'.strval($recRow),$r['CREDITSCORETYPEKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CC'.strval($recRow),$r['DATEOFBIRTH'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CD'.strval($recRow),$r['TAXSPECIALSTATUSKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CE'.strval($recRow),$r['DOCUMENTATIONRECEIVEDIND'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CF'.strval($recRow),$r['SURCHARGEKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CG'.strval($recRow),$r['SAFETYNET'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CH'.strval($recRow),$r['MARKETINGMETHODCD'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CI'.strval($recRow),$r['ORIGINALCONTRACTIND'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CJ'.strval($recRow),$r['RECIPIENTCUSTOMERNUM'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CK'.strval($recRow),$r['SERVICE_TYPE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CL'.strval($recRow),$r['APP_ID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CM'.strval($recRow),$r['NAMEKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CN'.strval($recRow),$r['BILLINGACCTNUM'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CO'.strval($recRow),$r['SALESFORCEID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CP'.strval($recRow),$r['MSIID'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CQ'.strval($recRow),$r['PLENTIACCOUNTNUMBER'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CR'.strval($recRow),$r['CONTACT_METHOD_CODE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CS'.strval($recRow),$r['MOBILE_PHONE'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CT'.strval($recRow),$r['NONCOMMODITYSERVICES'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('CU'.strval($recRow),$r['COMMUNICATIONMETHODCDKEY'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $writer->save($fileName);
        } catch (\Exception $e) {
            // TODO: Handle
        }

        // TODO: Return a result
    }
 
    protected function downloadFile(
        $file,
        $date_dir,
        $created_at,
        $confirmation_code,
 //       $id,
        $type,
        $folderName
    ) {


        // Extract file name from provided path
        $explode = explode('/', $file);
        $local_filename = $explode[count($explode) - 1];

        // Download the file
        // echo ' -- Fetching '.$file."\n";
        $this->line(' File ' . $file . ' Confirmation Code: ' . $confirmation_code . "\n");
        $content = @file_put_contents(
            public_path($folderName .'VOICE/') . $confirmation_code . '.mp3',
            file_get_contents($file)
        );

        // No content or 'type' arg not provided. Return with a null remote filename.
        if (!$content || !isset($type)) {
            return [
                'type' => $type,
                'remote_filename' => null,
                'local_filename' => $local_filename,
            ];
        }

        $remote_filename = "";            
 
        return [
            'type' => $type,
           'remote_filename' => $remote_filename,
            'local_filename' => $local_filename,
        ];
    }

    
}
