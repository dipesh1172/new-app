<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\StatsProduct;

class GenieCommercialGroupEmailedReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Genie:CommercialGroupReport {--mode=} {--start-date=} {--end-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emailed report showing commercial group TPV records';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandIds = [
        '0e80edba-dd3f-4761-9b67-3d4a15914adb', // Residents Energy
        '77c6df91-8384-45a5-8a17-3d6c67ed78bf'  // IDT Energy
    ];

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
       'live' => ['tzupnik@genieretail.com', 'SalesSupport@genieretail.com', 'ebramwell@genieretail.com'],
       'test' => ['dxcit@tpv.com', 'engineering@tpv.com']
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
        // Set default date range to yesterday
        $this->startDate = Carbon::yesterday("America/Chicago");
        $this->endDate = Carbon::yesterday("America/Chicago")->endOfDay();

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

        // Build file names, using that start date for the file/reporting dates
        $txtFilename = 'GENIE_COMMERCIAL_GROUP_ALL_1_' .
            $this->startDate->format('d') .
            substr($this->startDate->format('F'), 0, 3) .
            $this->startDate->format('Y') . '.txt';

        $csvFilename = 'GENIE_COMMERCIAL_GROUP_ALL_1_' .
            $this->startDate->format('d') .
            substr($this->startDate->format('F'), 0, 3) .
            $this->startDate->format('Y') . '.csv';

        $csvPortalFilename = 'GENIE_COMMERCIAL_GROUP_ALL_1_' .
            $this->startDate->format('d') .
            substr($this->startDate->format('F'), 0, 3) .
            $this->startDate->format('Y') . '_portal_report.csv';

        if ($this->mode == 'test') {
            $txtFilename = 'test_' . $txtFilename;
            $csvFilename = 'test_' . $csvFilename;
            $csvPortalFilename = 'test_' . $csvPortalFilename;
        }

        // Data layout/File header for main TXT/CSV files.
        $csvHeader = $this->flipKeysAndValues([
            'FirstName', 'MI', 'LastName', 'Suffix', 'CompanyName', 'AuthorizedPerson', 'AuthorizedPersonRelationship', 'ServiceAddress', 'ServiceAddress2',
            'ServiceCity', 'ServiceState', 'ServiceZip', 'ServiceZip4', 'BillingAddress', 'BillingAddress2', 'BillingCity', 'BillingState', 'BillingZip', 'BillingZip4',
            'Phone', 'PhoneType', 'ContactAuthorization', 'Brand', 'GasAccountNumber', 'GasUtility', 'GasEnergyType', 'GasOfferID', 'GasVendorCustomData', 'GasServiceClass',
            'GasProcessDate', 'ElectricAccountNumber', 'ElectricUtility', 'ElectricEnergyType', 'ElectricOfferID', 'ElectricVendorCustomData', 'ElectricServiceClass',
            'ElectricProcessDate', 'SignupDate', 'BudgetBillRequest', 'TaxExempt', 'AgencyCode', 'AgencyOfficeCode', 'AgentCode', 'AdvertisingMethod', 'SignupMethod',
            'CustomerEmail', 'PrimaryLanguage', 'Validator1', 'Validator1_Collateral', 'Validator2', 'Validator2_Collateral', 'Payload1', 'Payload2', 'Payload3',
            'Payload4', 'GovAggregation', 'GovAggregationText', 'TPVConfirmationNumber'
        ]);

        // Data layout/File header for portal report CSV file.
        $csvPortalReportHeader = $this->flipKeysAndValues([
            'p_date', 'dt_insert', 'dt_date', 'brand_name', 'center_id', 'vendor_name', 'source', 'language', 'sales_state', 'program', 'auth_fname', 'auth_lname',
            'bill_fname', 'bill_lname', 'company_name', 'btn', 'acct_num', 'service_address1', 'service_address2', 'service_city', 'service_state', 'service_zip',
            'billing_address1', 'billing_address2', 'billing_city', 'billing_state', 'billing_zip', 'email_address', 'src_code', 'utility', 'util_type',
            'green_energy', 'trans_type', 'cust_type', 'record_locator', 'ver_code', 'tsr_id', 'status_txt', 'status_id', 'reason', 'tsr_name', 'dxc_rep_id',
            'call_time', 'ib_ct', 'ob_ct', 'call_log', 'activewav', 'audited', 'attempts', 'station_id', 'cic_call_id', 'form_name', 'call_back', 'rec_id', 'relationship',
            'consent_to_call', 'caller_id', 'energy_type', 'offer_id', 'rate', 'office_id', 'office_name', 'term'
        ]);

        $csv = array(); // Houses formatted data for TXT and CSV files.
        $csvPortalReport = array(); // Houses formatted data for portal report CSV file.


        $this->info('Retrieving TPV data...');
        $data = StatsProduct::select(
            'stats_product.event_id',
            'stats_product.brand_id',
            'stats_product.event_created_at',
            'stats_product.confirmation_code',
            'stats_product.language',
            'stats_product.channel',
            'stats_product.event_product_id',
            'stats_product.bill_first_name',
            'stats_product.bill_middle_name',
            'stats_product.bill_last_name',
            'stats_product.auth_first_name',
            'stats_product.auth_middle_name',
            'stats_product.auth_last_name',
            'stats_product.auth_relationship',
            'stats_product.market',
            'stats_product.btn',
            'stats_product.account_number1',
            'stats_product.commodity',
            'stats_product.rate_program_code',
            'stats_product.product_rate_amount',
            'stats_product.product_utility_name',
            'stats_product.product_utility_external_id',
            'stats_product.brand_name',
            'stats_product.office_label',
            'stats_product.office_name',
            'stats_product.vendor_code',
            'stats_product.vendor_name',
            'stats_product.sales_agent_rep_id',
            'stats_product.sales_agent_name',
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
            'stats_product.result',
            'stats_product.disposition_reason',
            'stats_product.disposition_label',
            'stats_product.recording',
            'stats_product.product_term',
            'stats_product.custom_fields',
            'stats_product.interaction_created_at',
            'tablet_submission.payload AS tablet_payload',
            'offices.grp_id AS office_grp_id'
        )->leftJoin(
            'tablet_submission',
            'stats_product.confirmation_code',
            'tablet_submission.confirmation_code'
        )->leftJoin(
            'offices',
            'stats_product.office_id',
            'offices.id'
        )->whereDate(
            'stats_product.event_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.event_created_at',
            '<=',
            $this->endDate
        )->whereIn(
            'stats_product.result',
            ['sale', 'no sale']  // Include no sales, for third file (portal report)
        )->whereIn(
            'stats_product.brand_id',
            $this->brandIds
        )->where(
            'stats_product.market',
            'commercial'
        )->orderBy(
            'stats_product.event_created_at'
        )->get();

        $this->info(count($data) . ' Record(s) found.');

        // Format and populate data for TXT/CSV files and portal report CSV file
        foreach ($data as $r) {

            $this->info($r->event_id . ':');

            $billName = $r->bill_first_name . ' ' . $r->bill_last_name;
            $authName = $r->auth_first_name . ' ' . $r->auth_last_name;

            // Get brand name
            $brand = '';
            if ($r->brand_id == '0e80edba-dd3f-4761-9b67-3d4a15914adb') {
                $brand = 'RES';
            }
            if ($r->brand_id == '77c6df91-8384-45a5-8a17-3d6c67ed78bf') {
                $brand = 'IDTE';
            }

            // Get recording name
            $recordingTokens = explode('/', $r->recording);
            $recording = $recordingTokens[count($recordingTokens) - 1];

            // Determine advert method
            $advertMethod = '';
            switch (strtolower($r->channel)) {
                case 'dtd':
                    $advertMethod = 'SMB D2D';
                    break;
                case 'tm':
                    $advertMethod = 'SMB TM';
                    break;
                default:
                    $advertMethod = $r->channel;
                    break;
            }

            // Convert commodity to IDT's fuel type values
            $fuelType = '';
            switch (strtolower(trim($r->commodity))) {
                case 'natural gas': {
                        $fuelType = 'GAS';
                        break;
                    }
                case 'electric': {
                        $fuelType = 'ELE';
                        break;
                    }
            }

            // Parse custom fields
            $customFields = json_decode($r->custom_fields);
            $contactConsent = $this->getCustomValue('contact consent', $customFields);
            $rateTerm = $this->getCustomValue('customer term', $customFields, $r->event_product_id);
            $rateAmount = $this->getCustomValue('customer_rate', $customFields, $r->event_product_id);
            $rateUom = $this->getCustomValue('customer uom', $customFields, $r->event_product_id);
            $documentsProvided = $this->getCustomValue('documents provided', $customFields);
            $ledInfo = $this->getCustomValue('led info', $customFields);
            $accountNumber = $this->getCustomValue('account number', $customFields, $r->event_product_id);
            $utilityName = $this->getCustomValue('utility name', $customFields, $r->event_product_id);

            // Obtain record locator.
            // For TLP, use the Sale ID from the tablet paylod.
            // For EZTPV, use the confirmation_code (TPV confiramtion code will be same as the one in EZTPV submission)
            $recordLocator = '';
            if (strtolower($r->source) == 'eztpv') {
                $recordLocator = $r->confirmation_code;
            } else {

                // check for tablet data
                if ($r->tablet_payload) {
                    $tlpData = json_decode($r->tablet_payload);
                    $recordLocator = $tlpData->SaleId;
                }
            }

            // Map data to enrollment TXT and CSV file fields. Good sales only            
            if (strtolower($r->result) == 'sale') {
                $this->info('  Mapping data to main TXT/CSV file layouts...');
                $row = [
                    $csvHeader['FirstName'] => $r->bill_first_name,
                    $csvHeader['MI'] => '',                                 // Always blank
                    $csvHeader['LastName'] => $r->bill_last_name,
                    $csvHeader['Suffix'] => '',                             // Always blank
                    $csvHeader['CompanyName'] => (strtolower($r->market) == 'residential' ?
                        $billName :
                        $r->company_name),
                    $csvHeader['AuthorizedPerson'] => (strtolower($billName) != strtolower($authName) ?
                        $authName :
                        ''),
                    $csvHeader['AuthorizedPersonRelationship'] => (strtolower($billName) != strtolower($authName) ?
                        $r->auth_relationship :
                        ''),
                    $csvHeader['ServiceAddress'] => $r->service_address1,
                    $csvHeader['ServiceAddress2'] => $r->service_address2,
                    $csvHeader['ServiceCity'] => $r->service_city,
                    $csvHeader['ServiceState'] => $r->service_state,
                    $csvHeader['ServiceZip'] => substr(trim($r->service_zip), 0, 5),
                    $csvHeader['ServiceZip4'] => substr(trim($r->service_zip), 5, 4),
                    $csvHeader['BillingAddress'] => $r->billing_address1,
                    $csvHeader['BillingAddress2'] => $r->billing_address2,
                    $csvHeader['BillingCity'] => $r->billing_city,
                    $csvHeader['BillingState'] => $r->billing_state,
                    $csvHeader['BillingZip'] => substr(trim($r->billing_zip), 0, 5),
                    $csvHeader['BillingZip4'] => substr(trim($r->billing_zip), 5, 4),
                    $csvHeader['Phone'] => trim($r->btn, '+1'),
                    $csvHeader['PhoneType'] => 'Unknown',                   // Always 'Unknown'
                    $csvHeader['ContactAuthorization'] => (strtoupper($contactConsent) == 'YES' ? 'All' : 'None'),
                    $csvHeader['Brand'] => $brand,
                    $csvHeader['GasAccountNumber'] => ($fuelType == 'GAS' ? $accountNumber : ''),
                    $csvHeader['GasUtility'] => ($fuelType == 'GAS' ? $utilityName : ''),
                    $csvHeader['GasEnergyType'] => ($fuelType == 'GAS' ? ($r->product_green_percentage > 0 ? 'GREEN' : 'BROWN') : ''),
                    $csvHeader['GasOfferID'] => ($fuelType == 'GAS' ? '|' . $rateAmount . '|' . $rateTerm . '|' : ''),
                    $csvHeader['GasVendorCustomData'] => ($fuelType == 'GAS' ? '' : ''),        // Always Blank
                    $csvHeader['GasServiceClass'] => ($fuelType == 'GAS' ? 'Commercial Group' : ''),    // Mapped in legacy, but hard-coded here as Commercial Group is not a thing in Focus.
                    $csvHeader['GasProcessDate'] => ($fuelType == 'GAS' ? $r->event_created_at->format('m/d/Y') : ''),
                    $csvHeader['ElectricAccountNumber'] => ($fuelType == 'ELE' ? $accountNumber : ''),
                    $csvHeader['ElectricUtility'] => ($fuelType == 'ELE' ? $utilityName : ''),
                    $csvHeader['ElectricEnergyType'] => ($fuelType == 'ELE' ? ($r->product_green_percentage > 0 ? 'GREEN' : 'BROWN') : ''),
                    $csvHeader['ElectricOfferID'] => ($fuelType == 'ELE' ? '|' . $rateAmount . '|' . $rateTerm . '|' : ''),
                    $csvHeader['ElectricVendorCustomData'] => ($fuelType == 'ELE' ? '' : ''),   // Always Blank
                    $csvHeader['ElectricServiceClass'] => ($fuelType == 'ELE' ? 'Commercial Group' : ''),
                    $csvHeader['ElectricProcessDate'] => ($fuelType == 'ELE' ? $r->event_created_at->format('m/d/Y') : ''),
                    $csvHeader['SignupDate'] => $r->event_created_at->format('m/d/Y'),
                    $csvHeader['BudgetBillRequest'] => 'No',                // Always 'No'
                    $csvHeader['TaxExempt'] => 'No',                        // Always 'No'
                    $csvHeader['AgencyCode'] => $r->vendor_code,
                    $csvHeader['AgencyOfficeCode'] => $r->office_grp_id,
                    $csvHeader['AgentCode'] => $r->tsrs_id,
                    $csvHeader['AdvertisingMethod'] => $advertMethod,
                    $csvHeader['SignupMethod'] => 'Phone',                  // Always 'Phone'
                    $csvHeader['CustomerEmail'] => $r->email_address,
                    $csvHeader['PrimaryLanguage'] => $r->language,
                    $csvHeader['Validator1'] => 'DXC',                      // Always 'DXC'
                    $csvHeader['Validator1_Collateral'] => $r->confirmation_code . '_01_' .  strtotime($r->interaction_created_at)  . '.mp3',  // needs to be the same as FTP upload recording name that was assigned in BrandFileSync
//                    $csvHeader['Validator1_Collateral'] => $recording,
                    $csvHeader['Validator2'] => '',                         // Always Blank
                    $csvHeader['Validator2_Collateral'] => '',              // Always Blank
                    $csvHeader['Payload1'] => '',                           // Always Blank
                    $csvHeader['Payload2'] => '',                           // Always Blank
                    $csvHeader['Payload3'] => '',                           // Always Blank
                    $csvHeader['Payload4'] => '',                            // Always Blank
                    $csvHeader['GovAggregation'] => '',                     // Always Blank
                    $csvHeader['GovAggregationText'] => '',                 // Always Blank
                    $csvHeader['TPVConfirmationNumber'] => $r->confirmation_code
                   ];

                array_push($csv, $row);
            }

            // Map data to portal report CSV file fields. For both good sales and no sales.
            $this->info('  Mapping data to Portal Report CSV file layout...');
            $row2 = [
                $csvPortalReportHeader['p_date'] => $r->event_created_at->format('m/d/Y'),
                $csvPortalReportHeader['dt_insert'] => $r->event_created_at->format('m/d/Y'),
                $csvPortalReportHeader['dt_date'] => $r->event_created_at->format('m/d/Y'),
                $csvPortalReportHeader['brand_name'] => $r->brand_name,
                $csvPortalReportHeader['center_id'] => $r->vendor_code,
                $csvPortalReportHeader['vendor_name'] => $r->vendor_name,
                $csvPortalReportHeader['source'] => $r->source,
                $csvPortalReportHeader['language'] => $r->language,
                $csvPortalReportHeader['sales_state'] => $r->service_state,
                $csvPortalReportHeader['program'] => '',        // Always blank
                $csvPortalReportHeader['auth_fname'] => $r->auth_first_name,
                $csvPortalReportHeader['auth_lname'] => $r->auth_last_name,
                $csvPortalReportHeader['bill_fname'] => $r->bill_first_name,
                $csvPortalReportHeader['bill_lname'] => $r->bill_last_name,
                $csvPortalReportHeader['company_name'] => $r->company_name,
                $csvPortalReportHeader['btn'] => trim($r->btn, '+1'),
                $csvPortalReportHeader['acct_num'] => $accountNumber,
                $csvPortalReportHeader['service_address1'] => $r->service_address1,
                $csvPortalReportHeader['service_address2'] => $r->service_address2,
                $csvPortalReportHeader['service_city'] => $r->service_city,
                $csvPortalReportHeader['service_state'] => $r->service_state,
                $csvPortalReportHeader['service_zip'] => $r->service_zip,
                $csvPortalReportHeader['billing_address1'] => $r->billing_address1,
                $csvPortalReportHeader['billing_address2'] => $r->billing_address2,
                $csvPortalReportHeader['billing_city'] => $r->billing_city,
                $csvPortalReportHeader['billing_state'] => $r->billing_state,
                $csvPortalReportHeader['billing_zip'] => $r->billing_zip,
                $csvPortalReportHeader['email_address'] => $r->email_address,
                $csvPortalReportHeader['src_code'] => $r->rate_source_code,
                $csvPortalReportHeader['utility'] => $utilityName,
                $csvPortalReportHeader['util_type'] => $r->commodity,
                $csvPortalReportHeader['green_energy'] => ($r->product_green_percentage > 0 ? 'Yes' : ''),
                $csvPortalReportHeader['trans_type'] => $r->channel,
                $csvPortalReportHeader['cust_type'] => $r->market,
                $csvPortalReportHeader['record_locator'] => $recordLocator,
                $csvPortalReportHeader['ver_code'] => $r->confirmation_code,
                $csvPortalReportHeader['tsr_id'] => $r->sales_agent_rep_id,
                $csvPortalReportHeader['status_txt'] => $r->result,
                $csvPortalReportHeader['status_id'] => $r->disposition_label,
                $csvPortalReportHeader['reason'] => $r->disposition_reason,
                $csvPortalReportHeader['tsr_name'] => $r->sales_agent_name,
                $csvPortalReportHeader['dxc_rep_id'] => $r->tpv_agent_label,
                $csvPortalReportHeader['call_time'] => $r->interaction_time,
                $csvPortalReportHeader['ib_ct'] => '0',
                $csvPortalReportHeader['ob_ct'] => '0',
                $csvPortalReportHeader['call_log'] => '',
                $csvPortalReportHeader['activewav'] => $r->confirmation_code . '_01_' .  strtotime($r->interaction_created_at)  . '.mp3',  // needs to be the same as FTP upload recording name that was assigned in BrandFileSync
//                $csvPortalReportHeader['activewav'] => $recording,
                $csvPortalReportHeader['audited'] => '',
                $csvPortalReportHeader['attempts'] => '0',
                $csvPortalReportHeader['station_id'] => '',
                $csvPortalReportHeader['cic_call_id'] => '',
                $csvPortalReportHeader['form_name'] => 'IDT TPV Script',
                $csvPortalReportHeader['call_back'] => '0',
                $csvPortalReportHeader['rec_id'] => '',
                $csvPortalReportHeader['relationship'] => $r->auth_relationship,
                $csvPortalReportHeader['consent_to_call'] => $contactConsent,
                $csvPortalReportHeader['caller_id'] => trim($r->ani, '+1'),
                $csvPortalReportHeader['energy_type'] => ($r->product_green_percentage > 0 ? 'GREEN' : 'BROWN'),
                $csvPortalReportHeader['offer_id'] => $r->rate_program_code,
                $csvPortalReportHeader['rate'] => $rateAmount,
                $csvPortalReportHeader['office_id'] => $r->office_label,
                $csvPortalReportHeader['office_name'] => $r->office_name,
                $csvPortalReportHeader['term'] => $rateTerm
            ];

            array_push($csvPortalReport, $row2);
        }

        // Check if we have any records in the TXT/CSV files.
        // All three files are sent only when the main TXT/CSV files hav records.
        if (count($csv) == 0) {

            $this->sendEmail(
                'There were no records to send for ' . $this->startDate->format('m-d-Y') . '.',
                $this->distroList[$this->mode]
            );
            return 0;
        }

        // Write TXT file
        $this->info('Writing TXT file...');
        $file = fopen(public_path('tmp/' . $txtFilename), 'w');

        foreach ($csv as $row) {

            $line = implode('~', $row); // TXT file uses '~' as field delimiter
            fputs($file, $line . chr(13));
            //fputcsv($file, $row, '~', '', ''); // no enclosure or escape characters for TXT file
        }
        fclose($file);

        // Write CSV file
        $this->info('Writing CSV file...');
        $file = fopen(public_path('tmp/' . $csvFilename), 'w');

        $fileHeader = [];
        foreach ($csvHeader as $key => $value) {
            $fileHeader[] = $key;
        }
        fputcsv($file, $fileHeader);

        foreach ($csv as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        // Write portal report CSV file
        $this->info('Writing Portal Report CSV file...');
        $file = fopen(public_path('tmp/' . $csvPortalFilename), 'w');

        $fileHeader = [];
        foreach ($csvPortalReportHeader as $key => $value) {
            $fileHeader[] = $key;
        }
        fputcsv($file, $fileHeader);

        foreach ($csvPortalReport as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        // Email the files
        $this->info('Emailing files...');
        $this->sendEmail(
            'Attached is the Commercial Group enrollment file for ' . $this->startDate->format('m-d-Y') . '.',
            $this->distroList[$this->mode],
            [
                public_path('tmp/' . $txtFilename),
                public_path('tmp/' . $csvFilename),
                public_path('tmp/' . $csvPortalFilename)
            ]
        );

        unlink(public_path('tmp/' . $txtFilename));
        unlink(public_path('tmp/' . $csvFilename));
        unlink(public_path('tmp/' . $csvPortalFilename));
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
        if ('production' != config('app.env')) {
            $subject = 'Genie Retail Energy - Commercial Group - Email File Generation (' . config('app.env') . ') '
                . Carbon::now();
        } else {
            $subject = 'Genie Retail Energy - Commercial Group - Email File Generation '
                . Carbon::now();
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
    private function getCustomValue(string $fieldName, array $fieldList, $productId = null)
    {
        $customkFieldValue = "";
        $lastDateTime = null;

        foreach ($fieldList as $field) {

            if (!$field->name) {
                continue;
            }

            if ($field->name == $fieldName && $field->product == $productId) {
                if ($lastDateTime == null || $lastDateTime < $field->date) {
                    $customkFieldValue = $field->value;
                    $lastDateTime = $field->date;
                }
            }
        }

        return $customkFieldValue;
    }
}
