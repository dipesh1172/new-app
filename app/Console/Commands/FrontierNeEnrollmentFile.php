<?php

namespace App\Console\Commands;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\ProviderIntegration;
use App\Models\Interaction;

class FrontierNeEnrollmentFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FrontierNE:EnrollmentFile {--mode=} {--noftp} {--noemail} {--start-date=} {--end-date=} {--show-sql}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Frontier NE - Enrollment File';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates, FTPs, and Emails Frontier NE\'s enrollment file';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '2c958990-af67-485b-bbcf-488f1e5e2dd3'; // Same ID for staging and prod

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'SharedMailboxFrontierNETeam@gexaenergy.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com', 'scott.birmingham@gexaenergy.com', 'melissa.lane@gexaenergy.com', 'SharedMailboxFrontierNETeam@gexaenergy.com'],
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
        'port' => 990,
        'root' => '/Enrollments/Vendor14/LVL_AGENT1/Inbox',
        'passive' => true,
        'ssl' => true,
        'timeout' => 30
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

        // Retrieve FTP details
        $result = $this->getFtpConfig();

        if (empty($result)) {
            $this->error("No credentials were found.");
            return -1;
        }

        // Build file name
        $filename = ($this->mode == 'test' ? 'TEST_' : '')
            . 'FRONTIER_NE_API_'
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
            'VendorAgentName', 'VendorAgentCode', 'TPVDate', 'Promotion'
        ]);

        $csv = array(); // Houses formatted data CSV file.

        // Retrieve the TPV data
        $this->info("Retrieving TPV data...");
        $data = $this->getData();

        $this->info(count($data) . ' Record(s) found.');

        // Format and populate data CSV file
        $this->info('Formatting data...');
        foreach ($data as $r) {
            $this->info('   ' . $r->event_id . ':');
            $this->info('   ' . $r->interaction_id . ':');

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
                $csvHeader['MobileNum'] => '1111111111',
                $csvHeader['DateOfBirth'] => '7/4/1976',
                $csvHeader['Email'] => (!empty($r->email_address) ? $r->email_address : 'no@noemail.com'),
                $csvHeader['ContactType'] => 'PHONE',
                $csvHeader['Language'] => $r->language,
                $csvHeader['EBill'] => '',
                $csvHeader['IsBillingSame'] => 'TRUE',
                $csvHeader['BillingCity'] => '',
                $csvHeader['BillingState'] => '',
                $csvHeader['BillingStreetNum'] => '',
                $csvHeader['BillingStreet'] => '',
                $csvHeader['BillingAptNum'] => '',
                $csvHeader['BillingZipCode'] => '',
                $csvHeader['ServiceAddress'] => str_replace(',',' ',trim($r->service_address1 . ' ' . $r->service_address2)),
                $csvHeader['ServiceCity'] => $r->service_city,
                $csvHeader['ServiceState'] => $r->service_state,
                $csvHeader['ServiceZipCode'] => substr($r->service_zip, 0, 5),
                $csvHeader['SwitchMoveDate'] => Carbon::parse($r->interaction_created_at)->format('m-d-Y'),
                $csvHeader['PowerMeterNo'] => (strtolower($r->commodity) == 'electric' ? $r->account_number1 : ''),
                $csvHeader['GasMeterNo'] => (strtolower($r->commodity) == 'natural gas' ? $r->account_number1 : ''),
                $csvHeader['Ssn'] => '111111111',
                $csvHeader['SwitchMoveType'] => 'SWITCH',
                $csvHeader['TpvNumber'] => '99' . $r->confirmation_code,
                $csvHeader['Commodity'] => (strtolower($r->commodity) == 'electric' ? 'POWER' : 'GAS'),
                $csvHeader['VendorAgentName'] => implode('', explode(',', $r->sales_agent_name)), // remove commas from agnet name. copied logic from legacy.
                $csvHeader['VendorAgentCode'] => $r->sales_agent_rep_id,
                $csvHeader['TPVDate'] => Carbon::parse($r->interaction_created_at)->format('m-d-Y'),
                $csvHeader['Promotion'] => $r->promotion_code,
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
        if (!$this->option('noftp')) {
            $this->info('Uploading file...');
            $this->info($filename);

            $ftpResult = curlFtpUpload(public_path("tmp/") . $filename, $this->ftpSettings);

            if (isset($ftpResult)) {
                if (strtolower($ftpResult) === "success") {
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
            $this->sendEmail('Attached is the API file for ' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);

            // Delete tmp file
            unlink(public_path('tmp/' . $filename));
        }
    }

    /**
     * Retrieve FTP config from provider_integrations table and
     * build our FTP config from that data.
     */
    private function getFtpConfig() {
        
        // Get data from DB
        $pi = ProviderIntegration::where(
            'brand_id',
            $this->brandId
        )->where(
            'provider_integration_type_id',
            1
        )->where(
            'service_type_id',
            28
        )->first();

        // Can't find record, return false.
        if (empty($pi)) {
            return false;
        }

        // Populate the fields we need in our global FTP config array
        $this->ftpSettings['host'] = $pi->hostname;
        $this->ftpSettings['username'] = $pi->username;
        $this->ftpSettings['password'] = $pi->password;

        return true;
    }

    /**
     * Retrieves data for the enrollment file
     */
    private function getData() {

        $data = Interaction::select(
            'interactions.id as interaction_id',
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
            'brand_promotions.promotion_code'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->leftJoin(
            'stats_product',
            'events.confirmation_code',
            'stats_product.confirmation_code'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_promotions',
            'event_product.brand_promotion_id',
            'brand_promotions.id'
        )->whereDate(
            'interactions.created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'interactions.created_at',
            '<=',
            $this->endDate
        )->where(
            'events.brand_id',
            $this->brandId
        )->where(
            'interactions.event_result_id',
            1 // sale
        // )->whereIn(
        //     'stats_product.confirmation_code',
        //     [
        //         '31171647291',
        //         '31181260132',
        //         '31181328532',
        //     ]
        )->orderBy(
            'interactions.created_at'
        );
        
        // Before retrieving data, do we need to show SQL?
        // This can't be done after get().
        if($this->option('show-sql')) {
            $queryStr = str_replace(array('?'), array('\'%s\''), $data->toSql());
            $queryStr = vsprintf($queryStr, $data->getBindings());

            $this->info("");
            $this->info('QUERY:');
            $this->info($queryStr);
            $this->info("");
        }

        // Retrieve the data
        $data = $data->get();

        return $data;
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
