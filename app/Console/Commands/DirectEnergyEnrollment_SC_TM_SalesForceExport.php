<?php

namespace App\Console\Commands;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
//use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\ProviderIntegration;
use App\Models\StatsProduct;

class DirectEnergyEnrollment_SC_TM_SalesForceExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DirectEnergy:Enrollment_SC_TM_SalesForceExport {--mode=} {--noftp} {--noemail} {--start-date=} {--end-date=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Direct Energy - Enrollment SC TM - SalesForce Export ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates, FTPs, and Emails Direct Energy\'s enrollment file';

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
            'live' => ['dxc_autoemails@tpv.com', '_tpvteam@directenergy.com','sharon.gallardo@answernet.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com','sharon.gallardo@answernet.com'],
            'test' => ['dxcit@tpv.com']

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
        'root' => '/outbound/',
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
                //        'passive' => true,
                //        'ssl' => true,
                'timeout' => $this->ftpSettings['timeout'],
                'directoryPerm' => $this->ftpSettings['directoryPerm'],
            ]
        );
        $filesystem = new Filesystem($adapter);
        // Build file name
        $filename = ($this->mode == 'test' ? 'TEST_' : '')
            . 'de_sc_tm_salesforce_export_new_'
            . $this->startDate->year 
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) 
            . '.csv';

        // Data layout/File header for main TXT/CSV files.
        $csvHeader = $this->flipKeysAndValues([
            'p_date','dt_insert','dt_date','center_id','office_id','vendor_name','language','sales_state','record_id',
            'service_type','app_source_code','btn','program_code','app_id','contract_type','billing_acct_num','acct_num',
            'meter_number','plenti_account_number','cust_type','auth_fname','auth_mi','auth_lname','bill_fname','bill_mi',
            'bill_lname','business_name','email','billing_address','billing_city','billing_state','billing_zip','addr1','addr2',
            'city','state','zip','ldc_code','promo_code_sel','ver_code','tsr_id','pre_audit_status_txt','pre_audit_status_id',
            'status_txt','status_id','dxc_rep_id','call_time','orig_call_time','ib_ct','ob_ct','activewav','attempts',
            'salesforce_update_result','salesforce_order_id','salesforce_tpv_id','contact_method_code','mobile_phone',
            'rec_id','recid_unique','channel','esco_id','marketer_name','tsr_name','depp_promo_code_sel',
            'depp_promo_code_sel_for_file','depp_promo_offered','depp_promo_accepted','tpv_type_actual','tpv_type_original'
        ]);

        $csv = array(); //  formatted data CSV file.

        $this->info("Retrieving TPV data...");
        $data = StatsProduct::select(
             'stats_product.id',
             'stats_product.event_id',
             'stats_product.vendor_code',
             'stats_product.office_name',
             'stats_product.vendor_name',
             'stats_product.language',
             'stats_product.lead_id',
             'stats_product.rate_program_code',
             'stats_product.commodity',
             'stats_product.account_number1',
             'stats_product.account_number2',  // hopefully this is always meter number
             'stats_product.confirmation_code',
             'stats_product.interaction_created_at',
             'stats_product.market',
             'stats_product.btn',
             'stats_product.email_address',
             'stats_product.vendor_grp_id',
             'stats_product.vendor_label',
             'stats_product.sales_agent_rep_id',
             'stats_product.sales_agent_name',
             'stats_product.auth_first_name',
             'stats_product.auth_middle_name',
             'stats_product.auth_last_name',
             'stats_product.bill_first_name',
             'stats_product.bill_middle_name',
             'stats_product.bill_last_name',
             'stats_product.service_address1',
             'stats_product.service_address2',
             'stats_product.service_city',
             'stats_product.service_state',
             'stats_product.service_zip',
             'stats_product.billing_address1',
             'stats_product.billing_city',
             'stats_product.billing_state',
             'stats_product.billing_zip',
             'stats_product.company_name',
             'stats_product.email_address',
             'stats_product.utility_commodity_ldc_code',
             'stats_product.rate_external_id',
             'stats_product.sales_agent_rep_id',
             'stats_product.disposition_label',
             'stats_product.tpv_agent_label',
             'stats_product.interaction_time',
             'stats_product.product_rate_amount',
             'stats_product.recording',
             'brand_promotions.promotion_code',
             'brand_promotions.promotion_key',
             'stats_product.result',
             'stats_product.event_created_at',  // can't use interaction_created_at because of psa surveys
             'stats_product.product_name',
             'stats_product.sales_agent_name',
             'stats_product.custom_fields'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_promotions',
            'event_product.brand_promotion_id',
            'brand_promotions.id'
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
//        )->where(
//            'stats_product.confirmation_code',
//            '22511207336'
        )->whereIn(
            'stats_product.result',
            ['sale', 'no sale']
        )->orderBy(
            'stats_product.interaction_created_at'
        )->get();
        $this->info(count($data) . ' Record(s) found.');
            //          print_r($data->toArray());
            //          print_r(DB::getQueryLog());
           // $curt = $data->toArray();
         // Format and populate data CSV file
        $this->info('Formatting data...');
        foreach ($data as $r) {
            $this->info('   ' . $r->event_id . ':');
            $name_key = "";
            $billing_account_number = "";
            $meter_number = "";
            $app_source_code = "";
            $customFields = [];

 //           if($r->custom_fields) {
                $customFields = json_decode($r->custom_fields);
 //           }

            foreach($customFields AS $customField) {

                switch(strtolower($customField->output_name)) {

                    case 'billing_account_number':
                        $billing_account_number = $customField->value;
                        break;
                    case 'namekey':
                        $name_key = $customField->value;
                        break;
                    case 'appsourcecode':
                        $app_source_code = $customField->value;
                        break;
                    case 'meternumber':
                        $meter_number = $customField->value;
                        break;
                    }
            }

            // Map data to enrollment CSV file fields.
            $row = [
                $csvHeader['p_date'] => $r->interaction_created_at,
                $csvHeader['dt_insert'] => $r->interaction_created_at,
                $csvHeader['dt_date'] => $r->interaction_created_at,
                $csvHeader['center_id'] => $r->vendor_code,
                $csvHeader['office_id'] => $r->office_name,
                $csvHeader['vendor_name'] => $r->vendor_name,
                $csvHeader['language'] => $r->language,
                $csvHeader['sales_state'] => $r->service_state,
                $csvHeader['record_id'] => $r->lead_id,
                $csvHeader['service_type'] => (strtolower($r->commodity) == 'natural gas' ? 'Gas' : $r->commodity),
                $csvHeader['app_source_code'] => $app_source_code,
                $csvHeader['btn'] => ltrim($r->btn,'+1'),
                $csvHeader['program_code'] => $r->rate_program_code,
                $csvHeader['app_id'] => '',  // empty
                $csvHeader['contract_type'] => '', // empty
                $csvHeader['billing_acct_num'] => $billing_account_number,
                $csvHeader['acct_num'] => $r->account_number1,
 //               $csvHeader['meter_number'] => $meter_number,
                $csvHeader['meter_number'] => $r->account_number2,
                $csvHeader['plenti_account_number'] => '', //empty
                $csvHeader['cust_type'] => $r->market,
                $csvHeader['auth_fname'] => $r->auth_first_name,
                $csvHeader['auth_mi'] => $r->auth_middle_name,
                $csvHeader['auth_lname'] => $r->auth_last_name,
                $csvHeader['bill_fname'] => $r->bill_first_name,
                $csvHeader['bill_mi'] => $r->bill_middle_name,
                $csvHeader['bill_lname'] => $r->bill_last_name,
                $csvHeader['business_name'] => '"' . $r->company_name . '"',  // fix issue with commas
                $csvHeader['email'] => $r->email_address,
                $csvHeader['billing_address'] => '"' . $r->billing_address1 . '"',  // fix issue with commas
                $csvHeader['billing_city'] => $r->billing_city,
                $csvHeader['billing_state'] => $r->billing_state,
                $csvHeader['billing_zip'] => $r->billing_zip,
                $csvHeader['addr1'] => '"' . $r->service_address1 . '"',  // fix issue with commas
                $csvHeader['addr2'] => '', //empty
                $csvHeader['city'] => $r->service_city,
                $csvHeader['state'] => $r->service_state,
                $csvHeader['zip'] => $r->service_zip,
                $csvHeader['ldc_code'] => $r->utility_commodity_ldc_code,
                $csvHeader['promo_code_sel'] => $r->promotion_code,
                $csvHeader['ver_code'] => $r->confirmation_code,
                $csvHeader['tsr_id'] => $r->sales_agent_rep_id,
                $csvHeader['pre_audit_status_txt'] => $r->result,
                $csvHeader['pre_audit_status_id'] => $r->disposition_label,
                $csvHeader['status_txt'] => $r->result,
                $csvHeader['status_id'] => $r->disposition_label,
                $csvHeader['dxc_rep_id'] => $r->tpv_agent_label,
                $csvHeader['call_time'] => $r->interaction_time,
                $csvHeader['orig_call_time'] => $r->interaction_time,
                $csvHeader['ib_ct'] => $r->interaction_time,
                $csvHeader['ob_ct'] => '0',
                $csvHeader['activewav'] => $r->recording,
                $csvHeader['attempts'] => '0',
                $csvHeader['salesforce_update_result'] => '',
                $csvHeader['salesforce_order_id'] => '',
                $csvHeader['salesforce_tpv_id'] => '',
                $csvHeader['contact_method_code'] => '',
                $csvHeader['mobile_phone'] => '',
                $csvHeader['rec_id'] => $r->id,
                $csvHeader['recid_unique'] => $r->id,
                $csvHeader['channel'] => 'SC TM',
                $csvHeader['esco_id'] => '',
                $csvHeader['marketer_name'] => '',
                $csvHeader['tsr_name'] => $r->sales_agent_name,
                $csvHeader['depp_promo_code_sel'] => '',
                $csvHeader['depp_promo_code_sel_for_file'] => '',
                $csvHeader['depp_promo_offered'] => '',
                $csvHeader['depp_promo_accepted'] => '',
                $csvHeader['tpv_type_actual'] => '',
                $csvHeader['tpv_type_original'] => '',
            ];

            // Add this row of data to the correct vendor in the CSV array.
            $csv[] = $row;
        }

        // Write the CSV file.
        // Intentially writing without enclosures to mimic legacy enrollment file.
        $this->info('Writing CSV file...');
        $file = fopen(public_path('tmp/' . $filename), 'w');

        $fileHeader = [];
         foreach ($csvHeader as $key => $value) {
             $fileHeader[] = $key;
         }
        fputs($file, implode(',', $fileHeader) . "\r\n");

        // Data
         foreach ($csv as $row) {
             fputs($file, implode(',', $row) . "\r\n");
         }
         fclose($file);
 

        // Upload the file to FTP server
        if (!$this->option('noftp')) {
            $this->info('Uploading file...');
            $this->info($filename);
            $ftpResult = 'SFTP at ' . Carbon::now() . '. Status: ';
            try {
                $stream = fopen(public_path('tmp/' . $filename), 'r+');
                $filesystem->writeStream(
                    $filename,
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

                    $this->sendEmail('File ' . $filename . ' has been successfully uploaded.', $this->distroList['ftp_success'][$this->mode]);
                } else {
                    $this->info('Upload failed.');
                    $this->sendEmail(
                        'Error uploading file ' . $filename . ' to FTP server ' . $this->ftpSettings['host'] .
                            "\n\n FTP Result: " . $ftpResult,
                        $this->distroList['ftp_error'][$this->mode]
                    );

                    return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
                }
            }
        }


        // Regardless of FTP result, also email the file as an attachment
        if (!$this->option('noemail')) {
            $attachments = [public_path('tmp/' . $filename)];

            $this->info("Emailing file...");
            $this->sendEmail('SC TM for ' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
        }
        // Delete tmp file
        unlink(public_path('tmp/' . $filename));
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
    public function sendEmail(string $message, array $distro, array $files = array())
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
     * Keys become values and values become keys. It's assumed that the values are unique.
     *
     * @return mixed
     */
    private function flipKeysAndValues($inputArray)
    {
        $tempArray = [];

        foreach ($inputArray as $key => $value) {
            $tempArray[trim($value)] = $key;
        }

        return $tempArray;
    }
}
