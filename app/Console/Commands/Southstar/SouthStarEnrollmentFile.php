<?php

namespace App\Console\Commands\Southstar;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;

use App\Models\ProviderIntegration;
use App\Models\StatsProduct;

use App\Traits\DeliverableTrait;

class SouthStarEnrollmentFile extends Command
{
    use DeliverableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SouthStar:EnrollmentFile {--mode=} {--env=} {--no-delivery} {--no-email} {--start-date=} {--end-date=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'SouthStar - Enrollment File';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'SouthStar enrollment files. Creates one file per state/LDC.';

    /**
     * The brand identifier
     *
     * @var array
     */
    protected $brandId = [
        'staging' => '9c1a1d3f-6edf-4d66-be92-e1905d557811',
        'prod' => '4436027c-39dc-48cb-8b7f-4d55b739c09e'
    ];

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'LLively@southstarenergy.com', 'Stacy.Worthy@southstarenergy.com', 'nhill@southstarenergy.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
        ],
        'dxc_copy' => [ // We don't have access to the /Outbound_Archive folder, so we'll email ourselves a copy of each file uploaded
            'live' => 'dxc_autoemails@tpv.com',
            'test' => 'dxc_autoemails@tpv.com'
        ],
        'error' => [ // For general errors that require the program to quit early
            'dxc_autoemails@tpv.com', 'engineering@tpv.com'
        ]    
    ];


    /**
     * FTP Settings
     *
     * @var array
     */
    protected $ftpSettings = null;

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
     * Environment: 'prod' or 'staging'.
     *
     * @var string
     */
    protected $env = 'prod'; // prod env by default.

    /**
     * Files are written to here before upload
     * 
     * @var string
     */
    protected $filePath = '';

    /**
     * Whether or not to show console messages.
     */
    protected $verbose = false;

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
        $this->startDate = Carbon::yesterday();
        $this->endDate = Carbon::yesterday()->endOfDay();

        $this->verbose = $this->option('verbose');

        $this->filePath = public_path('tmp/');

        // Validate mode.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            } else {
                $this->error('Invalid --mode value: ' . $this->option('mode'));
                return -1;
            }
        }

        // Validate env.
        if ($this->option('env')) {
            if (
                strtolower($this->option('env')) == 'prod' ||
                strtolower($this->option('env')) == 'staging'
            ) {
                $this->env = strtolower($this->option('env'));
            } else {
                $this->error('Invalid --env value: ' . $this->option('env'));
                return -1;
            }
        }

        // Check for and validate custom report dates, but only if both start and end dates are provided
        if ($this->option('start-date') && $this->option('end-date')) {
            // TODO: We're trusting the dates the user is passing. Add validation for:
            // 1) valid dates were provided
            // 2) start date <= end date
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));
            $this->info('Custom dates:');
            $this->info('Start: ' . $this->startDate);
            $this->info('End:   ' . $this->endDate);
        }

        $dateRange = $this->startDate->format("m-d-Y"); // Date range string for email messages
        if ($this->startDate->format("m-d-Y") != $this->endDate->format("m-d-Y")) {
            $dateRange = $this->startDate->format("m-d-Y") . " - " . $this->endDate->format("m-d-Y");
        }

        // Get FTP settings
        $this->info('Getting FTP settings...');
        $this->ftpSettings = $this->getFtpSettings();

        if(!$this->ftpSettings) {
            $this->error('Unable to retrieve FTP settings. Exiting...');
            exit -1;
        }

        // Get list of unique state/utility combinations.
        // This will serve as a sales count check as well as a list of files to create.
        $this->info('Checking for sales...');
        $filesList = StatsProduct::select(
            'service_state',
            'utility_commodity_ldc_code',
            DB::raw('count(*) as sales')
        )->whereDate(
            'interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'interaction_created_at',
            '<=',
            $this->endDate
        )->where(
            'brand_id',
            $this->brandId[$this->env]
        )->where(
            'result',
            'sale'
        )->groupBy(
            'service_state'
        )->groupBy(
            'utility_commodity_ldc_code'
        )->orderBy(
            'service_state'
        )->orderBy(
            'utility_commodity_ldc_code'
        )->get()->toArray();

        // Any sales? If not, exit after sending 'no sales' email.
        $this->info(count($filesList) . ' states/utilities with sales found.');

        if (count($filesList) == 0) {
            if (!$this->option('no-email')) {
                $this->info('Sending results email...');
                // Send email
                $message = "All utilities were succesfully processed.\n\nNo data was found in any utility for " . $dateRange . ".";

                $this->sendEmail($message, $this->distroList['ftp_success'][$this->mode]);
            }

            $this->info('Quitting...');
            return -1;
        }

        // TODO: Get list of all state/utils combinations from rates data
        // We need a list of all state/util combos, even ones not sold on a particular date.
        // This is for the email body, which will report if a state/util combo does NOT have any sales.

        // Loop through files list, get data, format it, and create the file.
        foreach ($filesList as $file) {
            $serviceState = $file['service_state'];
            $ldcCode = $file['utility_commodity_ldc_code'];

            // Build file name
            // Enrollment_<state>_<ldc code>_<YYYYMMDD>_<h>_<m>_<s>.txt
            $now = Carbon::now("America/Chicago");

            $filename = ($this->mode == 'test' ? 'TEST_' : '')
                . 'Enrollment_'
                . $serviceState . '_'
                . $ldcCode . '_'
                . $this->startDate->format('Ymd') . '_'
                . (ltrim($now->format('H')) ? ltrim($now->format('H')) : '0') . '_' // Trim left zeros
                . (ltrim($now->format('i')) ? ltrim($now->format('i')) : '0') . '_'
                . (ltrim($now->format('s')) ? ltrim($now->format('s')) : '0')
                . '.txt';

            $this->info('For service state ' . $serviceState . ', utility ' . $ldcCode . ':');
            $this->info('Getting data...');

            // Get Data
            $data = StatsProduct::select(
                'events.external_id',
                'stats_product.id',
                'stats_product.event_created_at',
                'stats_product.account_number1',
                'stats_product.bill_first_name',
                'stats_product.bill_last_name',
                'stats_product.email_address',
                'stats_product.service_address1',
                'stats_product.service_address2',
                'stats_product.service_city',
                'stats_product.service_state',
                'stats_product.service_zip',
                'stats_product.billing_address1',
                'stats_product.billing_address2',
                'stats_product.billing_city',
                'stats_product.billing_state',
                'stats_product.billing_zip',
                'stats_product.btn',
                'stats_product.rate_program_code',
                'stats_product.rate_external_id',
                'stats_product.company_name',
                'stats_product.product_rate_amount',
                'stats_product.sales_agent_rep_id',
                'stats_product.custom_fields'
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
                $this->brandId[$this->env]
            )->where(
                'stats_product.result',
                'sale'
            )->where(
                'stats_product.service_state',
                $file['service_state']
            )->where(
                'stats_product.utility_commodity_ldc_code',
                $file['utility_commodity_ldc_code']
            )->get();

            // Format
            $this->info('Formatting data...');

            // Header for enrollment file
            $h = $this->flipKeysAndValues([
                'Format_Cd', 'LDC', 'Acct', 'First_Name', 'Last_Name', 'Email', 'Srvc_Addr', 'Srvc_Addr2', 'Srvc_City', 'Srvc_State', 'Srvc_Zip',
                'Mail_Addr', 'Mail_Addr2', 'Mail_City', 'Mail_State', 'Mail_Zip', 'Phone_Nbr', 'Product_Cd', 'Promo_Cd', 'Business_Name', 'Heard_About_Us', 'Contract_Action',
                'Prevent_Cancel_Fee', 'Conf_Num', 'Transaction_Date', 'Channel_ID', 'Email_Notifications', 'Refer_A_Friend_Code', 'EnrolledByUserID', 'Price_Point',
                'Email_Request_Code', 'Alt_Email_Address', 'Call_Consent_Flag'
            ]);

            $csv = [];
            foreach ($data as $row) {

                // Parse custom fields
                $cf = json_decode($row->custom_fields);

                $promoCode = $this->getCustomValue('promo_code', $cf);
                $heardAboutUs = $this->getCustomValue('heard_about_us', $cf);
                $actionIfContExists = $this->getCustomValue('action_if_cont_exists', $cf);
                $preventCancelFee = $this->getCustomValue('prevent_cancel_fee', $cf);
                $referAFriedCode = $this->getCustomValue('refer_a_friend_code', $cf);
                $emailNotification = $this->getCustomValue('email_notification', $cf);
                $emailRequestCode = $this->getCustomValue('email_request_code', $cf);
                $altEmailAddress = $this->getCustomValue('alt_email_address', $cf);
                $callConsentFlag = $this->getCustomValue('call_consent_flag', $cf);

                // Format data row
                $r = [
                    $h['Format_Cd'] => 'TME', // Always TME
                    $h['LDC'] => $ldcCode,
                    $h['Acct'] => $row->account_number1,
                    $h['First_Name'] => $row->bill_first_name,
                    $h['Last_Name'] => $row->bill_last_name,
                    $h['Email'] => $row->email_address,
                    $h['Srvc_Addr'] => $row->service_address1,
                    $h['Srvc_Addr2'] => $row->service_address2,
                    $h['Srvc_City'] => $row->service_city,
                    $h['Srvc_State'] => $row->service_state,
                    $h['Srvc_Zip'] => $row->service_zip,
                    $h['Mail_Addr'] => $row->billing_address1,
                    $h['Mail_Addr2'] => $row->billing_address2,
                    $h['Mail_City'] => $row->billing_city,
                    $h['Mail_State'] => $row->billing_state,
                    $h['Mail_Zip'] => $row->billing_zip,
                    $h['Phone_Nbr'] => ltrim($row->btn, '+1'),
                    $h['Product_Cd'] => $row->external_rate_id,
                    $h['Promo_Cd'] => $promoCode,
                    $h['Business_Name'] => $row->company_name,
                    $h['Heard_About_Us'] => $heardAboutUs,
                    $h['Contract_Action'] => $actionIfContExists,
                    $h['Prevent_Cancel_Fee'] => $preventCancelFee,
                    $h['Conf_Num'] => $row->external_id,
                    $h['Transaction_Date'] => $row->event_created_at->format('m/d/Y'),
                    $h['Channel_ID'] => 'PHONE IN', // Always PHONE IN
                    $h['Email_Notifications'] => $emailNotification,
                    $h['Refer_A_Friend_Code'] => $referAFriedCode,
                    $h['EnrolledByUserID'] => $row->sales_agent_rep_id,
                    $h['Price_Point'] => number_format($row->product_rate_amount, 4),
                    $h['Email_Request_Code'] => $emailRequestCode,
                    $h['Alt_Email_Address'] => $altEmailAddress,
                    $h['Call_Consent_Flag'] => $callConsentFlag
                ];

                $csv[] = $r;
            }

            $this->info("Writing file '" . public_path('tmp/' . $filename) . "'...");
            $f = fopen(public_path('tmp/' . $filename), 'w');

            // Write file header
            $fileHeader = [];
            foreach ($h as $key => $value) {
                $fileHeader[] = $key;
            }
            fputs($f, implode("\t", $fileHeader) . "\r\n");

            // Write data
            foreach ($csv as $r) {
                fputs($f, implode("\t", $r) . "\r\n");
            }
            fclose($f);

            // We're done here if the no-delivery option is set
            if($this->option('no-delivery')) {
                $this->info('no-delivery option set. File was created but not uploaded.');
                break;;
            }

            // Deliver the file
            try {
                $this->info('Uploading file...');
                $ftpResult = $this->sftpUpload(public_path('tmp') . '/' . $filename, $this->ftpSettings);

                if (strpos(json_encode($ftpResult), 'Status: Success!') != true) {
                    $this->error('FTP upload failed!');
                    $this->error($ftpResult);
                    break;
                }
            } catch (\Exception $e) {

                $this->sendGenericEmail([
                    'to' => $this->distroList['ftp_error'][$this->mode],
                    'subject' => $this->getEmailSubject(),
                    'body' => 'Error uploading file ' . $filename . "\n\n" . "Error Message: \n" . $e->getMessage()
                ]);                                    
            }

            if ($this->option('no-email')) {
                $this->info('Skipping email notification...');
                break;
            }

            $this->sendGenericEmail([
                'to' => $this->distroList['ftp_success'][$this->mode],
                'subject' => $this->getEmailSubject(),
                'body' => 'The following report file was created successfully: ' . $filename
            ]);
            
            unlink(public_path('tmp') . '/' . $filename);            
        }
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

    /**
     * Enrollment File FTP Upload.
     *
     * @param string $file   - path to file being uploaded
     *
     * @return string - Status message
     */
    public function ftpUpload($file)
    {
        $status = 'FTP at ' . Carbon::now() . '. Status: ';
        try {
            $adapter = new Ftp($this->ftpSettings[$this->mode]);

            $filesystem = new Filesystem($adapter);
            $stream = fopen($this->filePath . $file, 'r+');
            $filesystem->writeStream(
                $file,
                $stream
            );

            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Exception $e) {
            $status .= 'Error! The reason reported is: ' . $e;

            return $status;
        }
        $status .= 'Success!';

        return $status;
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
     * Finds a custom field value, by product ID. If there are duplicate fields, the latest version is used.
     */
    private function getCustomValue(string $fieldName, array $fieldList, $productId = null)
    {
        $customkFieldValue = "";
        $lastDateTime = null;

        foreach ($fieldList as $field) {

            if (!$field->output_name) {
                continue;
            }

            if ($field->output_name == $fieldName && $field->product == $productId) {
                if ($lastDateTime == null || $lastDateTime < $field->date) {
                    $customkFieldValue = $field->value;
                    $lastDateTime = $field->date;
                }
            }
        }

        return $customkFieldValue;
    }

    /**
     * Creates and returns an email subject line string
     */
    private function getEmailSubject() {
        return $this->jobName . (env('APP_ENV') != 'production' ? ' (' . env('APP_ENV') . ') ' : ' ' . Carbon::now("America/Chicago")->format('Y-m-d'));
    }

    /**
     * Retrieve FTP settings from provider_integrations table
     */
    private function getFtpSettings() {

        $pi = ProviderIntegration::select(
            'username',
            'password',
            'hostname'
        )
        ->where('brand_id', $this->brandId[$this->env])
        ->where('service_type_id', 37) // Southstar TPV Prod SFTP
        ->where('provider_integration_type_id', 1) // SFTP
        ->where('env_id', (config('app.env') === 'production' ? 1 : 2))
        ->first();

        if(!$pi) {
            return null;
        }

        $settings = [
            'host' => $pi->hostname,
            'username' => $pi->username,
            'password' => $pi->password,
            'port' => 22,
            'root' => 'outgoing',
            'timeout' => 30
        ];

        return $settings;
    }    
}
