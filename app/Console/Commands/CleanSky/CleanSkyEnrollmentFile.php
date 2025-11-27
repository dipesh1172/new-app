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

class CleanSkyEnrollmentFile extends Command
{

     public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CleanSkyEnrollmentFile {--mode=} {--start-date=} {--end-date=} {--noftp} {--noemail}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'CleanSky Enrollment File ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates and ftp enrollment file';

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
            'live' => ['dxc_autoemails@tpv.com', 'accountmanagers@answernet.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com','accountmanagers@answernet.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com','accountmanagers@answernet.com','curt.cadwell@answernet.com','curt@tpv.com'],
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
             . 'cleansky_energy.csv';
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
            'stats_product.product_rate_amount',
            'states.name',
            'stats_product.external_rate_id',
            'stats_product.product_rate_amount_currency',
            'stats_product.created_at',
            'stats_product.product_intro_term',
            'stats_product.product_term_type',
            'stats_product.rate_source_code'
            )->leftJoin(
            'states',
            'stats_product.billing_state',
            'states.state_abbrev'
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
        // )->whereRaw(
        //     "stats_product.custom_fields LIKE '%Winback%'"
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

            $message = 'There were no records to send for CleanSky Enrollment File' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'tmp/cleansky/'
        . 'enrollment/'
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
            $customer_type = "";
            $mobile_phone = "";
            $market_channel = "";
             $customFields = [];
           if($r->custom_fields) {
                $customFields = json_decode($r->custom_fields);
                foreach($customFields AS $customField) {
                    switch(strtolower($customField->output_name)) {
                      case 'customer_type':
                        $customer_type = $customField->value;
                        break;
                      case 'mobile_phone':
                        $mobile_phone = $customField->value;
                        break;
                      case 'market_channel':
                        $market_channel = $customField->value;
                        break;
                    }
                }
            }
            // begin contract end_date logic taken from CreatEnrollmentFiles.php
            $contract_end_date = Carbon::parse($r->created_at);
            $term = $r->product_term;
            $intro_term = $r->product_intro_term;
            $term_type = $r->product_term_type;
            $rate_type = $r->product_rate_type;

            $addtionalDate = 0;
            if($rate_type == 'fixed'){
                $addtionalDate = $term;
            }elseif($rate_type == 'tiered'){
                $addtionalDate = $term + $intro_term;
            }elseif($rate_type == 'variable'){
                $addtionalDate  = 1;
                $term_type = 'month';
            }

            switch ($term_type) {
                case 'day':
                    $contract_end_date = $contract_end_date
                        ->addDays($addtionalDate);
                    break;
                case 'week':
                    $contract_end_date = $contract_end_date
                        ->addWeeks($addtionalDate);
                    break;
                case 'month':
                    $contract_end_date = $contract_end_date
                        ->addMonths($addtionalDate);
                    break;
                case 'year':
                    $contract_end_date = $contract_end_date
                        ->addYear($addtionalDate);
                    break;
            } ;
            // end contract end_date logic
 
            if ($r->product_rate_amount_currency === 'cents') {  // convert to cents
              $product_rate_amount = rtrim(strval(doubleval($r->product_rate_amount) / 100));
            } else {
              $product_rate_amount = $r->product_rate_amount;  
            }
            $account_number1 = '';
            switch (strtolower($r->utility_commodity_ldc_code)) {
              case "columbia gas ohio":   // xxxxxxxx-xxx-xxxx
                $account_number1 = substr($r->account_number1,0,8) . '-' . substr($r->account_number1,8,3)  . '-' . substr($r->account_number1,11,4);
                break;
              case "columbia gas pennsylvania":  // xxxxxxxx-xxx-xxxx
                $account_number1 = substr($r->account_number1,0,8) . '-' . substr($r->account_number1,8,3)  . '-' . substr($r->account_number1,11,4);
                break;
              case "vectren energy delivery":  // 0340156736522807830 - we should remove first 2 digits  and the last digit 
                $account_number1 = substr($r->account_number1,2,(strlen($r->account_number1)-3));
                break;
              default:
                $account_number1 = $r->account_number1;
            }
            $row = [
                'Customer Type' => $customer_type,
                'Revenue_Class_Desc' => 'Residential',
                'First_Name' => ucwords(strtolower($r->bill_first_name)),
                'Last_Name' => ucwords(strtolower($r->bill_last_name)),
                'Customer_Name' => $r->company_name,
                'Home_Phone_Num' => (empty(str_replace('+1','',$r->btn)) ? '' : str_replace('+1','',$r->btn)),
                'Work_Phone_Num' => '',
                'Social_Sec_Code' => '',
                'Fed_Tax_Id_Num' => '',
                'Cellular_Num' => $mobile_phone,
                'Email_Address' => $r->email_address,
                'Language_Pref_Code' => $r->language,
                'Credit_Score_Num' => '',
                'Contact_Name' => ucwords(strtolower($r->auth_first_name . ' ' . $r->auth_last_name)), 
                'SLine1_Addr' => ucwords(strtolower($r->service_address1)),
                'SLine2_Addr' => ucwords(strtolower($r->service_address2)),
                'SCity_Name' => ucwords(strtolower($r->service_city)),
                'SCounty_Name' => ucwords(strtolower($r->service_county)),
                'SPostal_Code' => $r->service_zip,
                'Marketer_Name' => 'Titan Gas and Power',
                'Distributor_Name' => $r->utility_commodity_ldc_code,
                'Service_Type_Desc' => ($r->commodity == 'Natural Gas' ? 'Gas' : 'Electric'),
                'Bill_Method' => $r->utility_commodity_external_id,
                'LDC_Account_Num' => $account_number1,   
                'Enroll_Type_Desc' => 'Request',
                'Requested_Start_Date' => $r->event_created_at->format('Y-m-d'),
                'Special_Meter_Read_Date' => '',
                'Waive_Notification_Ind' => '',
                'Tax_Exemption_Ind' => 'N',
                'Plan_Desc' => $r->external_rate_id,
                'Contract_Start_Date' => $r->event_created_at->format('Y-m-d'),
                'Contract_End_Date' =>  $contract_end_date->format('m/d/Y'),
                'Fixed_Commodity_Amt' => $product_rate_amount,  
                'Agent' => $r->office_name,
                'Commission_Plan' => '',
                'Commission_Start_Date' => '',
                'Commission_End_Date' => '',
                'Commission_Unit_Num' => '',
                'Promotion_Code' => '',
                'Ad_Source_Desc' => '',
                'MLine1_Addr' => ucwords(strtolower($r->billing_address1)),
                'MLine2_Addr' => ucwords(strtolower($r->billing_address2)),
                'MLine3_Addr' => '',
                'MLine4_Addr' => '',
                'MCity_Name' => ucwords(strtolower($r->billing_city)),
                'MState' => ucwords(strtolower($r->name)),  
                'Mcountry_Name' => 'USA',
                'MPostal_Code' => $r->billing_zip,
                'Employee_Ind' => '',
                'Low_Income_Ind' => '',
                'Life_Support_Ind' => '',
                'Interruptible_Ind' => '',
                'Approx_Annual_Usage' => '',
                'Budget_Amt' => '',
                'Deposit_Installment_Amt' => '',
                'Deposit_Installment_Qty' => '',
                'Security_Question' => '',
                'Security_Answer' => '',
                'Employer_Name' => '',
                'Drivers_Lic_Num' => '',
                'Drivers_Lic_State_Code' => '',
                'Verification_Type_Desc' => '3rd Party',
                'Confirmation Code' => $r->confirmation_code,
                'Commission_Master_Unit_Num' => '',
                'Master_Code' => $market_channel,
                'Index_Adder_Num' => '',
                'Billing_Pkg_Name' => '',
                'Account_Name' => '',
                'ExportFileName' => '',
                'ExportDate' => '',
                'Commission_Sub_Agent_Unit_Num' => '',
                'Sub_Agent_Code' => $r->sales_agent_rep_id,
                'Supply_Zone_Desc' => '',
                'Fax_Num' => '',
                'header": "Equipment_Id_Code' => '',
                'Rto_Amt' => '',
                'Payment_Type_Desc' => '',
                'Payment_Subscriber_Id_Code' => '',
                'Fixed_Charge_Amt' => '0', 
                'Legacy_Account_Num' => '',
                'Legacy_Id' => '',
                'header": "Birth_Date' => '',
                'Enrollment_Source_Code' => '',
                'Esignature_Code' => '',
                'Commission_2_Plan' => '',
                'Commission_2_Start_Date' => '',
                'Commission_2_End_Date' => '',
                'Commission_2_Agent_Unit_Num' => '',
                'Commission_2_Master_Unit_Num' => '',
                'Commission_2_Sub_Unit_Num' => '',
                'Deposit_Suggested_Amt' => '',
                'Group_Desc' => '',
                'Landlord_Agreement_Id_Desc' => '',
                'Attention_Name' => '',
                'Service_Priority_Desc' => '',
                'Delivery_Method_Desc' => '',
                'Heat_Rate_Num' => '',
                'Rep_Adder_Num' => '',
                'Work_Ext_Num' => '',
                'Credit_Rating_Source_Desc' => '',
                'Min_Index_Num' => '0',
                'Max_Index_Num' => '0',
                'Delinquent_Days_Cnt' => '',
                'Request_Hist_Usage_Ind' => '',
                'Request_Hist_Interval_Ind' => '',
                'Interval_Ind' => '',
                'Interval_Non_Edi_Ind' => '',
                'Master_Ind' => '',
                'Type_Of_Service_Desc' => '',
                'Contact_2_Name' => '',
                'Doing_Business_As_Name' => '',
                'Web_Site_Addr' => '',
                'Remit_Duns_Num' => '',
                'Contact_Type_Desc' => '',
                'Contact_Phone_Num' => '',
                'Contact_2_Type_Desc' => '',
                'Contact_2_Phone_Num' => '',
                'Promotion_2_Code' => '',
                'Default_Pricing_Plan_Desc' => $r->rate_source_code,
                'Payment_Sub_Type_Desc' => '',
                'Billing_UOM_Desc' => '',
                'Daily_Late_Fee_Pct' => '',
                'Gas_Pool_Id' => $r->product_utility_external_id,
                'Contact_Relationship' => '',
                'Contact_2_Relationship' => '',
                'Summary_Billing_Ind' => '',
                'Grnty_Waived_Desc' => '',
                'Billing_Pkg_Acct_Num' => '',
                'Referred_By_ID' => '',
                'Membership_Id_Code' => '',
                'Partner_Name' => '',
                'Partner_ID' => '',
                'Contract_ID' => '',
                'Country_Name' => '',
                'Service_State_Code' => '',
                'Plan_Term_Num' => '',
                'Expiration_Code' => '',
                'Early_Termination_Amt' => '',
                'Residual_Value_Amt' => '',
                'ETF_Value_Amt' => '',
                'ETF_Type_Desc' => '',
                'Sales_Date' => $r->event_created_at->format('Y-m-d'),
                'Account_Spec_Name' => '',
                'External_Cross_Reference_Id' => '',
                'Branding_Name' => '',
                'Contract_Type_Desc' => '',
                'Pricing_Type_Desc' => '',
                'Reference_Id' => '',
                'Account_Type_Desc' => '',
                'Sales_Channel_Desc' => '',
                'Log_Text' => '',
                'Stmt_E_Mail_ Addr' => '',
                'Do_Not_Call_Ind' => '',
                'Do_Not_Email_Ind' => '',
                'Internal_Credit_Score_Num' => '',
                'IRA_Code' => '',
                'LDC_Cust_Eligibility_Code_Ind' => '',
                'Aggregator_Code_Ind' => '',
                'Customer_Assistance_Status_Ind' => '',
                'Alt_email' => '',
                'Is_Not_Numeric_Term' => '',
                'Term' => '',
                'Method_Consent' => '',
                'Method_Contact' => '',
                'Method_Delivery' => '',
                'Pricing_Type' => '',
                'Commision_Difference' => '',
                'Company_Commission' => '',
                'Total_Annual_Usage' => '',
            ];

            // Add this row of data to the correct vendor in the CSV array.
            $excelData[] = $row;

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
            $this->sendEmail('Attached is the file for CleanSky Enrollment file' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
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
