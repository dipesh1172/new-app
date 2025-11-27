<?php

namespace App\Console\Commands;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\ProviderIntegration;
use App\Models\StatsProduct;

/**
 * Creates Gexa's enrollment file, and delivers it via FTP and email.
 *
 * 2021-07-23 -- #18865 Commented out FTP functionality. Left all flags and logic in place in case they ever want FTP function back.
 */
class GexaEnrollmentFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Gexa:EnrollmentFile {--mode=} {--env=} {--no-ftp} {--no-email} {--start-date=} {--end-date=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Gexa - Enrollment File';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Creates and emails Gexa's enrollment file";

    /**
     * The brand identifier
     *
     * @var array
     */
    protected $brandId = [
        'staging' => 'cb20573d-161f-49bf-b058-8a006b73fb0f',
        'prod' => '988c08dc-a420-42b6-a034-ea4be465d20d'
    ];

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'scott.birmingham@gexaenergy.com', 'melissa.lane@gexaenergy.com', 'ne.team_frontier@gexaenergy.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com', 'scott.birmingham@gexaenergy.com', 'melissa.lane@gexaenergy.com'],
            'test' => ['engineering@tpv.com']
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
        'port' => 990,
        'root' => '/Enrollments/Vendor14/GEXA/Inbox',
        'passive' => true,
        'ssl' => true,
        'timeout' => 30,
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
     * Environment: 'prod' or 'staging'.
     *
     * @var string
     */
    protected $env = 'prod'; // prod env by default.

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
        $this->endDate = Carbon::today('America/Chicago')->endOfDay();

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
            $this->startDate = Carbon::parse($this->option('start-date'), 'America/Chicago');
            $this->endDate = Carbon::parse($this->option('end-date'), 'America/Chicago');
            $this->info('Using custom dates...');
        }

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date: ' . $this->endDate);

        // Get FTP details
        $pi = ProviderIntegration::where(
            'brand_id',
            $this->brandId[$this->env]
        )->where(
            'provider_integration_type_id',
            1
        )->where(
            'service_type_id',
            25
        )->first();

        if (empty($pi)) {
            $this->error("No credentials were found.");
            return -1;
        }

        $this->ftpSettings['host'] = $pi->hostname;
        $this->ftpSettings['username'] = $pi->username;
        $this->ftpSettings['password'] = $pi->password;

        // Build file name
        $filename = ($this->mode == 'test' ? 'TEST_' : '')
            . 'GEXA_ENROLLMENTS_'
            . $this->startDate->year . '_'
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '_'
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) . '_00_00_00.csv';

        // Data layout/File header for main TXT/CSV files.
        $csvHeader = $this->flipKeysAndValues([
            'TransactionID', 'UserID', 'VendorTransactionID', 'Version', 'PowerPricePlanID', 'GasPricePlanID', 'PowerTDSP', 'GasTDSP',
            'PowerPriceZoneCode', 'GasPriceZoneCode', 'PowerProductID', 'GasProductID', 'PromoCode', 'FirstName', 'MiddleInitial', 'LastName',
            'PhoneNum', 'MobileNum', 'DateOfBirth', 'Email', 'ContactType', 'Language', 'EBill', 'IsBillingSame', 'BillingCity', 'BillingState',
            'BillingStreetNum', 'BillingStreet', 'BillingAptNum', 'BillingZipCode', 'ServiceAddress', 'ServiceCity', 'ServiceState',
            'ServiceZipCode', 'SwitchMoveDate', 'PowerMeterNo', 'GasMeterNo', 'Ssn', 'SwitchMoveType', 'TpvNumber', 'Commodity',
            'VendorAgentName', 'VendorAgentCode', 'TPVDate', 'Promotion', 'GRefNum', 'TCPA'
        ]);

        $csv = array(); // Houses formatted data CSV file.

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
            'stats_product.vendor_label',
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
            'stats_product.commodity',
            'stats_product.rate_program_code',
            'stats_product.utility_commodity_ldc_code',
            'stats_product.rate_external_id',
            'stats_product.product_rate_amount',
            'stats_product.custom_fields',
            'brand_promotions.promotion_code',
            'leads.external_lead_id'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_promotions',
            'event_product.brand_promotion_id',
            'brand_promotions.id'
        )->leftJoin(
            'leads',
            function ($join) {
                $join->on('stats_product.lead_id', '=', 'leads.id');
                $join->on('stats_product.brand_id', '=', 'leads.brand_id');
            }
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
        )->orderBy(
            'stats_product.interaction_created_at'
        )->get();

        $this->info(count($data) . ' Record(s) found.');

        // Format and populate data CSV file
        $this->info('Formatting data...');
        foreach ($data as $r) {
            $this->info('   ' . $r->event_id . ':');

            // Parse custom fields
            $customFields = json_decode($r->custom_fields);

            $eBill = $this->getCustomFieldValue('bill_delivery', $customFields);
            if (empty($eBill)) { // If custom field not found, set to false
                $eBill = 'FALSE';
            }

            $prefLanguage = $this->getCustomFieldValue('language_preference', $customFields);
            if (empty($prefLanguage)) { // Default to English if not found
                $prefLanguage = 'English';
            }

            $tcpa = $this->getCustomFieldValue('tcpa', $customFields);
            if (empty($tcpa)) { // Default to 'N' if not found
                $tcpa = 'N';
            }

            $ssn = $this->getCustomFieldValue('ssn', $customFields);
            $ssnLast4 = $this->getCustomFieldValue('last_four_ssn', $customFields);
            $dob = $this->getCustomFieldValue('birthdate', $customFields);

            // Map data to enrollment CSV file fields.
            $row = [
                $csvHeader['TransactionID'] => '',
                $csvHeader['UserID'] => $r->vendor_label,
                $csvHeader['VendorTransactionID'] => '99' . $r->confirmation_code,
                $csvHeader['Version'] => '1',
                $csvHeader['PowerPricePlanID'] => (strtolower($r->commodity) == 'electric' ? $r->rate_program_code : ''),
                $csvHeader['GasPricePlanID'] => (strtolower($r->commodity) == 'natural gas' ? $r->rate_program_code : ''),
                $csvHeader['PowerTDSP'] => (strtolower($r->commodity) == 'electric' ? $r->utility_commodity_ldc_code : ''),
                $csvHeader['GasTDSP'] => (strtolower($r->commodity) == 'natural gas' ? $r->utility_commodity_ldc_code : ''),
                $csvHeader['PowerPriceZoneCode'] => '',
                $csvHeader['GasPriceZoneCode'] => '',
                $csvHeader['PowerProductID'] => (strtolower($r->commodity) == 'electric' ? $r->rate_external_id : ''),
                $csvHeader['GasProductID'] => (strtolower($r->commodity) == 'natural gas' ? $r->rate_external_id : ''),
                $csvHeader['PromoCode'] => '',
                $csvHeader['FirstName'] => (strtolower($r->market) == 'residential' ? $r->bill_first_name : $r->auth_first_name),
                $csvHeader['MiddleInitial'] => '',
                $csvHeader['LastName'] => (strtolower($r->market) == 'residential' ? $r->bill_last_name : $r->auth_last_name),
                $csvHeader['PhoneNum'] => ltrim($r->btn, '+1'),
                $csvHeader['MobileNum'] => ltrim($r->btn, '+1'),
                $csvHeader['DateOfBirth'] => $dob,
                $csvHeader['Email'] => (!empty($r->email_address) ? $r->email_address : 'no@noemail.com'),
                $csvHeader['ContactType'] => 'PHONE',
                $csvHeader['Language'] => $prefLanguage,
                $csvHeader['EBill'] => strtoupper($eBill),
                $csvHeader['IsBillingSame'] => 'TRUE',
                $csvHeader['BillingCity'] => '',
                $csvHeader['BillingState'] => '',
                $csvHeader['BillingStreetNum'] => '',
                $csvHeader['BillingStreet'] => '',
                $csvHeader['BillingAptNum'] => '',
                $csvHeader['BillingZipCode'] => '',
                $csvHeader['ServiceAddress'] => trim($r->service_address1 . ' ' . $r->service_address2),
                $csvHeader['ServiceCity'] => $r->service_city,
                $csvHeader['ServiceState'] => $r->service_state,
                $csvHeader['ServiceZipCode'] => substr($r->service_zip, 0, 5),
                $csvHeader['SwitchMoveDate'] => $r->interaction_created_at->format('m-d-Y'),
                $csvHeader['PowerMeterNo'] => (strtolower($r->commodity) == 'electric' ? $r->account_number1 : ''),
                $csvHeader['GasMeterNo'] => (strtolower($r->commodity) == 'natural gas' ? $r->account_number1 : ''),
                $csvHeader['Ssn'] => (!empty($ssn) ? $ssn : $ssnLast4),
                $csvHeader['SwitchMoveType'] => 'SWITCH',
                $csvHeader['TpvNumber'] => '99' . $r->confirmation_code,
                $csvHeader['Commodity'] => (strtolower($r->commodity) == 'electric' ? 'POWER' : 'GAS'),
                $csvHeader['VendorAgentName'] => implode('', explode(',', $r->sales_agent_name)), // remove commas from agnet name. copied logic from legacy.
                $csvHeader['VendorAgentCode'] => $r->sales_agent_rep_id,
                $csvHeader['TPVDate'] => $r->interaction_created_at->format('m-d-Y'),
                $csvHeader['Promotion'] => $r->promotion_code,
                $csvHeader['GRefNum'] => $r->external_lead_id,
                $csvHeader['TCPA'] => $tcpa,
            ];

            // Add this row of data to the correct vendor in the CSV array.
            $csv[] = $row;
        }

        // Write the CSV file.
        // Intentially writing without enclosures to mimic legacy enrollment file.
        $this->info('Writing CSV file...');
        $file = fopen(public_path('tmp/' . $filename), 'w');

        // Header
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
        // if (!$this->option('no-ftp')) {
        //     $this->info('Uploading file...');
        //     $this->info($filename);
        //
        //     $ftpResult = curlFtpUpload(public_path("tmp/") . $filename, $this->ftpSettings);

        //     if (isset($ftpResult)) {
        //         if (strtolower($ftpResult) === "success") {
        //             $this->info('Upload succeeded.');

        //             $this->sendEmail('File ' . $filename . ' has been successfully uploaded.', $this->distroList['ftp_success'][$this->mode]);
        //         } else {
        //             $this->info('Upload failed.');
        //             $this->sendEmail(
        //                 'Error uploading file ' . $filename . ' to FTP server ' . $this->ftpSettings[$this->mode]['host'] .
        //                     "\n\n FTP Result: " . $ftpResult,
        //                 $this->distroList['ftp_error'][$this->mode]
        //             );

        //             return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
        //         }
        //     }
        // }


        // Regardless of FTP result, also email the file as an attachment
        if (!$this->option('no-email')) {
            $attachments = [public_path('tmp/' . $filename)];

            $this->info("Emailing file...");
            $this->sendEmail('Attached is the enrollment file for ' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);

            // Delete tmp file
            unlink(public_path('tmp/' . $filename));
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


    /**
     * Finds a custom field value, by product ID. If there are duplicate fields, the latest version is used.
     */
    private function getCustomFieldValue(string $fieldName, array $fieldList, $productId = null)
    {
        $customFieldValue = "";
        $lastDateTime = null;

        foreach ($fieldList as $field) {

            if (!$field->output_name) {
                continue;
            }

            if ($field->output_name == $fieldName && $field->product == $productId) {
                if ($lastDateTime == null || $lastDateTime < $field->date) {
                    $customFieldValue = $field->value;
                    $lastDateTime = $field->date;
                }
            }
        }

        return $customFieldValue;
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
            $stream = fopen(public_path('tmp/' . $file), 'r+');
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
}
