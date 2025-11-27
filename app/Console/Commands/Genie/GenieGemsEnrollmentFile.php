<?php

namespace App\Console\Commands\Genie;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

use App\Models\JsonDocument;
use App\Models\ProviderIntegration;
use App\Models\StatsProduct;

use App\Traits\ExportableTrait;

/**
 * GEMS enrollment file.
 * 
 * This replaces the job 'IDT_ENERGY_FTP_FILE_GENERATION.PRG' in DXC.
 */
class GenieGemsEnrollmentFile extends Command
{
    use ExportableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Genie:GemsEnrollmentFile 
        {--mode=        : Optional. Valid values are "live" and "test". "live" is used by default. Setting this determines which GUIDs, amount other things, are used for database queries.} 
        {--start-date=  : Optional. Start date for data query. Must be paired with --end-date or it will be ignored. If omitted, current date minutes 30 days is used.} 
        {--end-date=    : Optional. End date for data query. Must be paired with --start-date or it will be ignored.} 
        {--show-sql     : Optional. If provided, SQL statement for queries will be output to console.} 
        {--no-ftp       : Optional. If provided, creates the file but does not FTP it.} 
        {--no-email     : Optional. If provided, sales totals email will not be sent.} 
        {--resubmit     : Optional. This should only be used if data needs to be resubmitted. This option will only work if --start-date and --end-date are also provided.} 
        {--no-json-doc  : Optional. This should only be used for tests. If provided, this prevents the program from writing the json_document log record, and allowing this record to be picked up again.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates and sends an enrollment file via FTP. Also, creates statistics and emails the details in the email body.';

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
        'live' => ['SalesSupport@genieretail.com', 'dxc_autoemails@dxc-inc.com', 'autumn.siegel@answernet.com'],
        'test' => ['dxcit@tpv.com', 'engineering@tpv.com', 'dxc_autoemails@dxc-inc.com'],
        'error' => ['dxcit@tpv.com', 'engineering@tpv.com', 'dxc_autoemails@dxc-inc.com']
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
     * Oldest date to look back to in data query.
     * Only applied if a custom date range is not provided.
     * 
     * @var mixed
     */
    protected $oldestDate = null;

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
        $this->startDate  = Carbon::today("America/Chicago")->subDay(30)->startOfDay();
        $this->endDate    = Carbon::today("America/Chicago")->endOfDay();
        $this->oldestDate = Carbon::parse("2023-10-17", "America/Chicago");

        // Validate mode. Leave in 'live' mode if not provided or an invalide value was provided.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            } else {
                $this->error('Unrecognized --mode: ' . $this->option('mode'));
                return -1;
            }
        }

        // Check for custom run date range. Custom range will only be used if both start and end dates are present.
        if ($this->option('start-date') && $this->option('end-date')) {
            $this->startDate = Carbon::parse($this->option('start-date'))->startOfDay();
            $this->endDate = Carbon::parse($this->option('end-date'))->endOfDay();

            $this->info('Using custom date range.');
        }

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date: ' . $this->endDate);
        $this->info('Mode: ' . $this->mode);
        $this->info("");

        if($this->option('resubmit')) {
            if(!$this->option('start-date') || !$this->option('end-date')) {
                $this->error('--resubmit option can only be used when --start-date and --end-date options are also present');
                exit -1;
            }
        }

        if (!$this->option('no-ftp')) { // No FTP. Skip credentials
            // Get FTP settings. Will be used when building file name (file run number based on if file already exists on FTP server) and 
            // for writing the file to SFTP server.
            $this->ftpSettings = $this->getFtpSettings();

            if(!$this->ftpSettings) {
                $this->error("Unable to retrieve FTP settings. Exiting...");
                exit -1;
            }
        }

        // Build file name
        $this->info("Building enrollment file name...");

        $fnResult = $this->buildFilename();
        if ($fnResult['result'] != 'success') {
            $this->error("  Error: " . $fnResult['message']);

            $this->sendEmail(
                "An error occurred while building the enrollment filename. Enrollment file was not created. Investigate immediately.\n\n"
                    . "Message:\n"
                    . $fnResult['message'],
                $this->distroList['error']
            );

            return -1;
        }

        $filename = $fnResult['message']; // On success, 'message' will have the filename.

        $this->info("  Done!");
        $this->info("  Filename: " . $filename);

        // Data layout/File header for enrollment file
        $csvHeader = $this->flipKeysAndValues([
            'FirstName', 'MI', 'LastName', 'Suffix', 'CompanyName', 'AuthorizedPerson', 'AuthorizedPersonRelationship', 'ServiceAddress', 'ServiceAddress2',
            'ServiceCity', 'ServiceState', 'ServiceZip', 'ServiceZip4', 'BillingAddress', 'BillingAddress2', 'BillingCity', 'BillingState', 'BillingZip', 'BillingZip4',
            'Phone', 'PhoneType', 'ContactAuthorization', 'Brand', 'GasAccountNumber', 'GasUtility', 'GasEnergyType', 'GasOfferID', 'GasVendorCustomData', 'GasServiceClass',
            'GasProcessDate', 'ElectricAccountNumber', 'ElectricUtility', 'ElectricEnergyType', 'ElectricOfferID', 'ElectricVendorCustomData', 'ElectricServiceClass',
            'ElectricProcessDate', 'SignupDate', 'BudgetBillRequest', 'TaxExempt', 'AgencyCode', 'AgencyOfficeCode', 'AgentCode', 'AdvertisingMethod', 'SignupMethod',
            'CustomerEmail', 'PrimaryLanguage', 'Validator1', 'Validator1_Collateral', 'Validator2', 'Validator2_Collateral', 'Payload1', 'Payload2', 'Payload3',
            'Payload4', 'GovAggregation', 'GovAggregationText', 'TPVConfirmationNumber'
        ]);

        $this->info("Creating FTP file system adapter...\n");

        $csv = array(); // Houses formatted enrollment file data       

        $data = StatsProduct::select(
            'event_id',
            'brand_id',
            'source',
            'interaction_created_at',
            'confirmation_code',
            'language',
            'channel',
            'event_product_id',
            'bill_first_name',
            'bill_middle_name',
            'bill_last_name',
            'auth_first_name',
            'auth_middle_name',
            'auth_last_name',
            'auth_relationship',
            'market',
            'btn',
            'account_number1',
            'commodity',
            'rate_program_code',
            'product_rate_amount',
            'product_utility_name',
            'product_utility_external_id',
            'brand_name',
            'office_label',
            'office_name',
            'vendor_code',
            'vendor_name',
            'sales_agent_rep_id',
            'sales_agent_name',
            'email_address',
            'service_address1',
            'service_address2',
            'service_city',
            'service_state',
            'service_zip',
            'billing_address1',
            'billing_address2',
            'billing_city',
            'billing_state',
            'billing_zip',
            'result',
            'disposition_reason',
            'disposition_label',
            'recording',
            'product_term',
            'custom_fields',
            'product_green_percentage',
            'contracts',
            'event_created_at',
            'company_name'
        )->where(
            'interaction_created_at',
            '>=',
            $this->startDate
        )->where(
            'interaction_created_at',
            '<=',
            $this->endDate
        );

        // If not using custom tables, add oldest date lookup
        if( !($this->option('start-date') && $this->option('end-date')) ) {

            $data = $data->where(
                'interaction_created_at',
                '>=',
                $this->oldestDate
            );
        }

        $data = $data->whereIn(
            'brand_id',
            $this->brandIds
        )->whereIn(
            'market',
            ['residential','commercial']
        )->where(
            'stats_product_type_id',
            1 // TPVs only
        )->where(
            'result',
            'sale'
        )->whereNotIn( // TODO: verify purpose of this clause. Likely exclusions for records that were sent in the original Starfield file. This may be redundant now and needs to be removed?
            'product_utility_external_id',
            ['CEIL', 'OHED', 'TOLED', 'PGW', 'VEDO', 'DEOHG', 'DPL', 'DTEGAS', 'CONSGAS', 'PEOPGAS', 'NSHORE','SEMCOG','MICHGAS']
        )->where(
            // Exclude PA records sent in Starfield files
            function ($query) {
                $query->where(
                    'service_state',
                    'PA'
                )->where(
                    'product_utility_external_id',
                    'PGW'
                );
            },
            null,
            null,
            'and not'
        )->where(
            // Exclude IL records sent in Starfield files
            function ($query) {
                $query->where(
                    'service_state',
                    'IL'
                )->whereIn(
                    'product_utility_external_id',
                    ['PEOPGAS', 'NSHORE']
                );
            },
            null,
            null,
            'and not'
        )->where(
            // Exclude OH records sent in Starfield files
            function ($query) {
                $query->where(
                    'service_state',
                    'OH'
                )->whereIn(
                    'product_utility_external_id',
                    ['VEDO', 'DEOHG', 'DPL']
                );
            },
            null,
            null,
            'and not'      
        )->where(
            // Exclude MI records sent in Starfield files
            function ($query) {
                $query->where(
                    'service_state',
                    'MI'
                )->whereIn(
                    'product_utility_external_id',
                    ['DTEGAS', 'CONSGAS','SEMCOG','MICHGAS']
                );
            },
            null,
            null,
            'and not'       
        )->where(
            // Exclude records that are submitted in the IDTE D2D MD Email Enrollment File job
            function ($query) {
                $query->where(
                    'brand_id',
                    '77c6df91-8384-45a5-8a17-3d6c67ed78bf' 
                )->where(
                    'service_state',
                    'MD'
                )->whereIn(
                    'channel',
                    ['DTD', 'Retail']
                )->whereNotIn(
                    'source',
                    ['EZTPV', 'Tablet']
                );
            },
            null,
            null,
            'and not'
        )->where(
            // Exclude records that are submitted in the IDTE D2D OH Email Enrollment File job
            function ($query) {
                $query->where(
                    'brand_id',
                    '77c6df91-8384-45a5-8a17-3d6c67ed78bf'
                )->where(
                    'service_state',
                    'OH'
                )->whereIn(
                    'channel',
                    ['DTD', 'Retail']
                )->whereNotIn(
                    'source',
                    ['EZTPV', 'Tablet']
                );
            },
            null,
            null,
            'and not'
        )->where(
            // Exclude records that are submitted in the Residents D2D DE Email Enrollment File job
            function ($query) {
                $query->where(
                    'brand_id',
                    '0e80edba-dd3f-4761-9b67-3d4a15914adb'
                )->where(
                    'service_state',
                    'DE'
                )->whereIn(
                    'channel',
                    ['DTD', 'Retail']
                )->whereNotIn(
                    'source',
                    ['EZTPV', 'Tablet']
                );
            },
            null,
            null,
            'and not'
        )->where(
            // Exclude records that are submitted in the Residents D2D OH Email Enrollment File job
            function ($query) {
                $query->where(
                    'brand_id',
                    '0e80edba-dd3f-4761-9b67-3d4a15914adb'
                )->where(
                    'service_state',
                    'OH'
                )->whereIn(
                    'channel',
                    ['DTD', 'Retail']
                )->whereNotIn(
                    'source',
                    ['EZTPV', 'Tablet']
                );
            },
            null,
            null,
            'and not'
        )->where(
            // Exclude records that are submitted in the Residents D2D MI Email Enrollment File job
            function ($query) {
                $query->where(
                    'brand_id',
                    '0e80edba-dd3f-4761-9b67-3d4a15914adb' // Res MI DTD
                )->where(
                    'service_state',
                    'MI'
                )->whereIn(
                    'channel',
                    ['DTD', 'Retail']
                )->where(
                    'source',
                    '<>',
                    'Tablet'
                );
            },
            null,
            null,
            'and not'
        )->where(
            // Exclude records that are submitted in the Residents Energy for the state of IN these will be supplied in starfield batch job
            function ($query) {
                $query->where(
                    'brand_id',
                    '0e80edba-dd3f-4761-9b67-3d4a15914adb' // 
                )->where(
                    'service_state',
                    'IN'
                );
            },
            null,
            null,
            'and not'
        )->where(
        // Exclude all CT records for Connecticute Light & Power and United Illuminating utilities
            function ($query) {
                $query->where(
                    'service_state',
                    'CT'
                )->whereIn(
                    'product_utility_external_id',
                    ['CLP', 'UI']
                );
            },
            null,
            null,
            'and not'
        )->where(
            // Exclude test agent
                function ($query) {
                    $query->where(DB::raw("LEFT(sales_agent_rep_id,3)"),
                     '999'
                    );
                },
                null,
                null,
                'and not'
        )->orderBy(
            'interaction_created_at'
        );

        // Display SQL query.        
        if ($this->option('show-sql')) {
            $queryStr = str_replace(array('?'), array('\'%s\''), $data->toSql());
            $queryStr = vsprintf($queryStr, $data->getBindings());

            $this->info("");
            $this->info('QUERY:');
            $this->info($queryStr);
            $this->info("");
        }

        $this->info('Retrieving TPV data...');
        $data = $data->get();

        $this->info(count($data) . ' Record(s) found.');

        // If no records found, quit program after sending an email blast
        if (count($data) == 0) {

            $this->sendEmail(
                'There were no records to send for ' . $this->startDate->format('m-d-Y') . '.',
                $this->distroList[$this->mode]
            );

            return 0;
        }

        $salesTotals = array(); // For emailed totals breakdown

        // Use transactions. This is so that we can rollback writes to json_documents if the job
        // errors out for any reason. The records can then be picked up on the next run.
        DB::beginTransaction();

        // Format and populate data for enrollment file.
        foreach ($data as $r) {

            $this->info("\n" . $r->event_id . ':');

            $billName = $r->bill_first_name . ' ' . $r->bill_last_name;
            $authName = $r->auth_first_name . ' ' . $r->auth_last_name;

            $channel = strtolower($r->channel);
            $source = strtolower($r->source);
            $commodity = strtolower($r->commodity);
            $serviceState = strtolower($r->service_state);

            // Parse recording path to get just the recording name.
            $pathTokens = explode('/', $r->recording);
            $recordingName = $pathTokens[count($pathTokens) - 1];

            $this->info('  Brand:   ' . $r->brand_name . '  (' . $r->brand_id . ')');
            $this->info('  Conf#:   ' . $r->confirmation_code);
            $this->info('  Channel: ' . $channel);
            $this->info('  Source:  ' . $source);
            $this->info('  State:   ' . $serviceState);
            $this->info("");

            // Check if this record was already included in an enrollment file
            if(!$this->option('resubmit')) {
                $jd = JsonDocument::where('document_type', 'genie-gems-enrollment')
                    ->where('ref_id', $r->event_product_id)
                    ->first(); // We only care if ANY records exists, so no need to get all of them

                if($jd) {
                    $this->info("{$r->confirmation_code}::{$r->event_product_id} Has already been submitted. Skipping");
                    continue;
                }
            }

            $brand = '';
            if ($r->brand_id == '0e80edba-dd3f-4761-9b67-3d4a15914adb') {
                $brand = 'RES';
            }
            if ($r->brand_id == '77c6df91-8384-45a5-8a17-3d6c67ed78bf') {
                $brand = 'IDTE';
            }

            // Format account number. If DUKE or NICOR, grab the left 10 digits only
            $accountNumber = $r->account_number1;
            if (
                // remove logic truncation for Duke ticket #22124
                //                strtoupper($r->product_utility_external_id) == 'DUKE' ||  
                strtoupper($r->product_utility_external_id) == 'NICOR'
            ) {
                $accountNumber = substr($accountNumber, 0, 10);
            }

            // validator2Collateral needs to be the same as FTP upload contract name that was assigned in BrandFileSync
            if (empty($r->contracts)) {
                $validator2 = '';
                $validator2Collateral = '';
            } else {
                $validator2 = 'DXC';
                $file_date = Carbon::parse($r->event_created_at,'America/Chicago')->format('Y_m_d_H_i_s');
                $validator2Collateral = $r->confirmation_code . '-' . $file_date . '-' .  substr($r->contracts,strripos($r->contracts,'/')+1);
            }
            // if (
            //     $source == 'eztpv' &&
            //     ($brand == 'IDTE' && ($serviceState == 'oh' || $serviceState == 'md') ||
            //         $brand == 'RES' && ($serviceState == 'de' || $serviceState == 'oh'))
            // ) {
            //     $validator2 = 'DXC';
            //     $validator2Collateral = trim($r->confirmation_code) . '.pdf';
            // } else if ($source == 'tablet') {
            //     $validator2 = 'TLP';

            //     // In legacy, we're using the link_id, a value that we create. This doesn't exist in Focus,
            //     // so use confirmation_code instead
            //     $validator2Collateral = ($commodity == 'natural gas' ? 'Gas ' : 'Electric ') . $r->confirmation_code . '.pdf';
            // }
 
            // Contact consent custom field
            $customFields = json_decode($r->custom_fields);
            if (!$customFields) { // Handle nulls
                $customFields = array();
            }
            $contactConsent = 'None';

            foreach ($customFields as $field) {
                if ($field->name == 'contact_consent') {
                    $contactConsent = (strtolower($field->value) == 'yes' ? 'All' : 'None');
                    break;
                }
            }
            
            if ($brand == 'IDTE' && $serviceState == 'md' && isset($r->contracts) && $r->result == 'Sale') { //override $contactConsent
                $contactConsent = 'All';
            }

            $signupMethod = 'Phone';
            // set to Phone/Esig when:
            // - DTD or Retail and tablet
            // - DTD or Retail and EZTPV and IDTE and MD or OH (in legacy, this is EZTPV Plus)
            // - DTD or Retail and EZTPV and Residents and DE or OH (in legacy, this is EZTPV Plus)
            if ($channel == 'dtd' || $channel == 'retail') {
                if ($source == 'tablet') {
                    $signupMethod = 'Phone/Esignature';
                } else if ($brand == 'IDTE' && $source == 'eztpv' && ($serviceState == 'md' || $serviceState == 'oh')) {
                    $signupMethod = 'Phone/Esignature';
                } else if ($brand == 'RES' && $source == 'eztpv' && ($serviceState == 'de' || $serviceState == 'oh')) {
                    $signupMethod = 'Phone/Esignature';
                }
            }

            $this->info('  Mapping data to enrollment file layout...');
            $row = [
                $csvHeader['FirstName'] => $r->bill_first_name,
                $csvHeader['MI'] => '',                                 // Always blank
                $csvHeader['LastName'] => $r->bill_last_name,
                $csvHeader['Suffix'] => '',                             // Always blank
                $csvHeader['CompanyName'] => $r->company_name,          
                $csvHeader['AuthorizedPerson'] => (strtolower($billName) != strtolower($authName)
                    ? $authName
                    : ''),
                $csvHeader['AuthorizedPersonRelationship'] => (strtolower($billName) != strtolower($authName)
                    ? ($r->auth_relationship != '' ? $r->auth_relationship : 'NA') // If relationship not provided, use NA
                    : ''), // Same name? Leave relationship field blank
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
                $csvHeader['Phone'] => substr(trim($r->btn), 2),
                $csvHeader['PhoneType'] => 'Unknown',                   // Always 'Unknown'
                $csvHeader['ContactAuthorization'] => $contactConsent,
                $csvHeader['Brand'] => $brand,
                $csvHeader['GasAccountNumber'] => ($commodity == 'natural gas' ? $accountNumber : ''),
                $csvHeader['GasUtility'] => ($commodity == 'natural gas' ? $r->product_utility_external_id : ''),
                $csvHeader['GasEnergyType'] => ($commodity == 'natural gas' ? ($r->product_green_percentage > 0 ? 'GREEN' : 'BROWN') : ''),
                $csvHeader['GasOfferID'] => ($commodity == 'natural gas' ? $r->rate_program_code : ''),
                $csvHeader['GasVendorCustomData'] => '',
                $csvHeader['GasServiceClass'] => ($commodity == 'natural gas' ? $r->market : ''),
                $csvHeader['GasProcessDate'] => ($commodity == 'natural gas' ? $r->interaction_created_at->timezone('America/Chicago')->format('m/d/Y') : ''),
                $csvHeader['ElectricAccountNumber'] => ($commodity == 'electric' ? $accountNumber : ''),
                $csvHeader['ElectricUtility'] => ($commodity == 'electric' ? $r->product_utility_external_id : ''),
                $csvHeader['ElectricEnergyType'] => ($commodity == 'electric' ? ($r->product_green_percentage > 0 ? 'GREEN' : 'BROWN') : ''),
                $csvHeader['ElectricOfferID'] => ($commodity == 'electric' ? $r->rate_program_code : ''),
                $csvHeader['ElectricVendorCustomData'] => '',
                $csvHeader['ElectricServiceClass'] => ($commodity == 'electric' ? $r->market : ''),
                $csvHeader['ElectricProcessDate'] => ($commodity == 'electric' ? $r->interaction_created_at->timezone('America/Chicago')->format('m/d/Y') : ''),
                $csvHeader['SignupDate'] => $r->interaction_created_at->addHour()->format('m/d/Y H:i'), // interaction_created_at is stored in CST. Add hour to convert to EST.
                $csvHeader['BudgetBillRequest'] => 'No',                // Always 'No'
                $csvHeader['TaxExempt'] => 'No',                        // Always 'No'
                $csvHeader['AgencyCode'] => $r->vendor_code,
                $csvHeader['AgencyOfficeCode'] => $r->office_label,
                $csvHeader['AgentCode'] => $r->sales_agent_rep_id,
                $csvHeader['AdvertisingMethod'] => ($channel == 'dtd' ? 'D2D' : ($channel == 'tm' ? 'Outbound TM' : ($channel == 'retail' ? 'TableTop' : $channel))),
                $csvHeader['SignupMethod'] => $signupMethod,
                $csvHeader['CustomerEmail'] => $r->email_address,
                $csvHeader['PrimaryLanguage'] => strtolower($r->language),
                $csvHeader['Validator1'] => 'DXC',                      // Always DXC
                $csvHeader['Validator1_Collateral'] => $r->confirmation_code . '_01_' .  strtotime($r->interaction_created_at->format("Ymd"))  . '.mp3',  // needs to be the same as FTP upload recording name that was assigned in BrandFileSync
//                $csvHeader['Validator1_Collateral'] => $recordingName,
                $csvHeader['Validator2'] => $validator2,
                $csvHeader['Validator2_Collateral'] => $validator2Collateral,
                $csvHeader['Payload1'] => '',                           // Always Blank
                $csvHeader['Payload2'] => '',                           // Always Blank
                $csvHeader['Payload3'] => '',                           // Always Blank
                $csvHeader['Payload4'] => '',                           // Always Blank
                $csvHeader['GovAggregation'] => '',                     // Always Blank
                $csvHeader['GovAggregationText'] => '',                 // Always Blank
                $csvHeader['TPVConfirmationNumber'] => $r->confirmation_code
            ];

            array_push($csv, $row);

            // Write JSON record, documenting what file this record was included in
            if(!$this->option('no-json-doc')) {
                $jdData = [
                    'Date' => Carbon::now("America/Chicago")->format("Y-m-d H:i:s"),
                    'InteractionDate' => $r->interaction_created_at->timezone('America/Chicago')->format('m/d/Y'),
                    'ConfirmationCode' => $r->confirmation_code,
                    'Commodity' => $commodity,
                    'AccountNumber' => $accountNumber,
                    'Filename' => $filename
                ];

                $jd = new JsonDocument();

                $jd->document_type = 'genie-gems-enrollment';
                $jd->ref_id = $r->event_product_id;
                $jd->document = $jdData;

                $jd->save();
            }

            // Accumulate totals for emailed totals breakdown
            if (!empty($r->btn) && !empty($r->vendor_code)) {
                if (!isset($salesTotals[$r->brand_name])) {
                    $salesTotals[$r->brand_name] = [
                        'sales' => 0,
                        'vendors' => array()
                    ];
                }

                $salesTotals[$r->brand_name]['sales']++;

                if (!isset($salesTotals[$r->brand_name]['vendors'][$r->vendor_code])) {
                    $salesTotals[$r->brand_name]['vendors'][$r->vendor_code] = [
                        'name' => $r->vendor_name,
                        'sales' => 0
                    ];
                }

                $salesTotals[$r->brand_name]['vendors'][$r->vendor_code]['sales']++;
            }
        }

        // Write TXT file
        $this->info("\nWriting TXT file...");

        // Create the file
        try {
            if ($this->option('no-ftp')) { // No FTP. Create and leave the file in the public dir on server
                $this->info('No-FTP option. Creating file in public path.');

                $file = fopen(public_path('tmp/' . $filename), 'w');

                foreach ($csv as $row) {
                    $line = implode('~', $row); // TXT file uses '~' as field delimiter
                    fputs($file, $line . chr(13)); // Mimicking legacy EOL. Only CR used.
                }
                fclose($file);

                $this->info('  Done!');
            } else {

                // Write the content
                $this->info("  Writing file...");

                $file = fopen(public_path('tmp/' . $filename), 'w');

                foreach ($csv as $row) {
                    $line = implode('~', $row); // TXT file uses '~' as field delimiter
                    fputs($file, $line . chr(13)); // Mimicking legacy EOL from DXC. Only CR used.
                }
                fclose($file);

                $this->info("Uploading file...");

                $this->curlFtpUpload(public_path('tmp/' . $filename), $this->ftpSettings);

                $this->info('  Done!');
            }
        } catch (\Exception $e) {
            DB::rollBack();

            $this->info('    ERROR: ' . $e->getMessage());

            $message = "Error createing file " . $filename . "\n\n"
                . "Error Message: \n"
                . $e->getMessage();

            $this->sendEmail($message, $this->distroList['error']);

            return -1;
        }

        DB::commit();

        // Email notification and totals breakdown
        if (!$this->option('no-email')) {
            $this->info("\nTotals breakdown:");
            $this->info("  Building email body...");

            $message = '<p>File ' . $filename . ' was successfully uploaded.&nbsp;</p>';

            $message .= '<ul>';
            foreach ($salesTotals as $brandKey => $brandValue) {

                $message .= '<li><strong>' . $brandKey . ':= ' . $brandValue['sales'] . '</strong></li>'
                    . '<ul>';

                foreach ($brandValue['vendors'] as $vendorKey => $vendorValue) {
                    $message .= '<li><strong>' . $vendorKey . ' - ' . $vendorValue['name'] . ':= ' . $vendorValue['sales'] . '</strong></li>';
                }

                $message .= '</ul>';
            }
            $message .= '</ul>';

            $this->info("  Sending email...");
            $this->sendEmail($message, $this->distroList[$this->mode]);
            $this->info("  Done!");
        } else {
            $this->info('No-Email option. Skipping totals breakdown.');
        }

        $this->info('End of Job.');
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
            $subject = 'Genie Retail - FTP File Generation (' . env('APP_ENV') . ') '
                . Carbon::now('America/Chicago');
        } else {
            $subject = 'Genie Retail - FTP File Generation '
                . Carbon::now('America/Chicago');
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
     * FTP upload using Curl. At this time, we can only upload one file at a time.
     */
    function curlFtpUpload($file, $ftpSettings)
    {
        // Validate params
        if (!$file) {
            return '$file arg is empty';
        }

        if (!isset($ftpSettings) || !$ftpSettings) {
            return '$ftpSettings arg is missing or null';
        }

        // Does local file exist?
        if (!file_exists($file)) {
            return 'Unable to locate local file: ' . $file;
        }

        // Get file info
        $fileInfo = pathinfo($file);

        // Populate settings
        // Start with default settings, then override with user values
        $settings = curlGetDefaultFtpSettings();
        $settings = curlMergeFtpSettings($settings, $ftpSettings);

        // Validate settings
        if (!$settings['host']) {
            return "Missing setting: host";
        }

        // Root path must begin and end in '/'. If root is empty, only one '/' is required.
        if (substr(trim($settings['root']), -1) != "/") {
            $settings['root'] = trim($settings['root']) . "/";
        }

        if (substr(trim($settings['root']), 0, 1) != "/") {
            $settings['root'] = "/" . trim($settings['root']);
        }


        // Only FTP the file if we can open it. File handle is provided to CURL.
        if ($fp = fopen($file, 'r')) {

            // Determine protocol
            // if ($settings['ssl']) {
            //     $protocol = "ftps://";
            // } else {
                $protocol = "ftp://";
            // }

            // Build URL
            $ftp_server = $protocol . $settings['host'] . ":" . $settings['port'] . $settings['root'] . $fileInfo['basename'];

            // exit();
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $ftp_server);
            curl_setopt($ch, CURLOPT_USERPWD, $settings['username'] . ':' . $settings['password']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
            curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
            curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, true);
            curl_setopt($ch, CURLOPT_UPLOAD, 1);
            curl_setopt($ch, CURLOPT_INFILE, $fp);

            curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            fclose($fp);

            return (!$err ? "Success" : "Error");
        } else {
            return "Error opening the specified file. FTP cancelled.";
        }

        return "Unexpected error";
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
     * Builds the enrollment file name
     *
     * @return mixed
     */
    private function buildFilename()
    {
        $fileNum = 1;

        // Check FTP 'sent' folder for a file with the same name.
        // Increment file counter and rebuild name if found.
        try {

            $filename = "TPVCOM_ALL_" . $fileNum . "_" .
                $this->endDate->format('d') .
                substr($this->endDate->format('F'), 0, 3) .
                $this->endDate->format('Y') . '.txt';

            if ($this->mode == 'test') {
                $filename = 'test_' . $filename;
            }

        } catch (\Exception $e) {

            return ["result" => "error", "message" => $e->getMessage()];
        }

        return ["result" => "success", "message" => $filename];
    }

    private function getFtpSettings() {

        $pi = ProviderIntegration::select(
            'username',
            'password',
            'hostname'
        )
        ->where('brand_id', '77c6df91-8384-45a5-8a17-3d6c67ed78bf') // FTP settings exist under IDTE's brand ID
        ->where('service_type_id', 36) // Genie FTP
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
            'port' => 2136,
            'root' => '/DXCTPV%20Drop',
            'passive' => true,
            'ssl' => true,
            'timeout' => 30
        ];

        print_r($settings);
        return $settings;
    }
}
