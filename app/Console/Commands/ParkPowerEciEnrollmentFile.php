<?php

namespace App\Console\Commands;

use PDO;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Traits\IsHolidayTrait;
use App\Models\StatsProduct;

/**
 * Creates ECI enrollment files for Park. The files are broken down by DUNS and vendor.
 * Additionally, this program can be run to create one consolidated report that contains all the enrollment records.
 */
class ParkPowerEciEnrollmentFile extends Command
{
    use IsHolidayTrait;

    /**
     * The name and signature of the console command.
     * 
     * --consolidated-report       Creates one report file with all enrollment records instead of the multiple files. Has it's own distro list.
     *
     * @var string
     */
    protected $signature = 'ParkPower:EciEnrollmentFile {--mode=} {--noftp} {--csvfiles} {--noemail} {--run-num=} {--start-date=} {--end-date=} {--consolidated-report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emailed nightly enrollment files for Park Power. A separate file is created for each active vendor, whether they had activity or not.';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Park Power - ECI Enrollment File';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '5e2e9249-cc27-4681-ab02-3b4b9e71f6cb';
    // protected $brandId = 'f06333fe-3e35-474e-879d-a80e94e13707'; // Staging

    /**
     * Nomination Group list for COH. Used for GasPoolNumber lookup.
     */
    protected $cohNomGroupList = null;

    /**
     * Nomination Group list for Columbia Gas PA. Used for GasPoolNumber lookup.
     */
    protected $colGasPaNomGroupList = null;

    /**
     * FTP Settings
     *
     * @var array
     */
    protected $ftpSettings = [
        'live' => [
            'host' => 'ftp.dxc-inc.com', // TODO: Replace with Frontier FTP info
            'username' => 'dxc',
            'password' => 'DXC_Ftp!',
            'port' => 21,
            'root' => '/',
            'passive' => true,
            'ssl' => false,
            'timeout' => 30,
        ],
        'test' => [
            'host' => 'ftp.dxc-inc.com',
            'username' => 'dxc',
            'password' => 'DXC_Ftp!',
            'port' => 21,
            'root' => '/',
            'passive' => true,
            'ssl' => false,
            'timeout' => 30,
        ]
    ];

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [
            'live' => ['alex@tpv.com'], // TODO: Replace with prod distro
            //'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com'] // TODO: Restore
            'test' => ['alex@tpv.com']
        ],
        'ftp_error' => [
            'live' => ['alex@tpv.com'], // TODO: Replace with prod distro
            //'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com'] // TODO: Restore
            'test' => ['alex@tpv.com']
        ],
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
        $this->startDate = Carbon::yesterday();
        $this->endDate = Carbon::yesterday()->endOfDay();

        $runNumber = 1;      // Multiplier for file counter.

        // Override default run number?
        if ($this->option('run-num')) {
            if (!is_numeric($this->option('run-num'))) {
                $this->error("'run-num' must be a number.");
                return -1;
            }

            if (intval($this->option('run-num')) < 1) {
                $this->error("'run-num' must be greater than 0.");
                return -1;
            }

            $runNumber = intval($this->option('run-num'));
        }

        $filesToEmail = [];  // TODO: Remove this line once we're given ECI FTP details.

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
            $this->endDate = Carbon::parse($this->option('end-date'))->endOfDay();
            $this->info('Using custom dates...');
        }

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date: ' . $this->endDate);
        $this->info('Mode: ' . $this->mode);

        // Load COH and Columbia Gas PA Nomination Group lists.
        $this->cohNomGroupList = $this->loadCohNomGroupList();
        $this->colGasPaNomGroupList = $this->loadColGasPaNomGroupList();


        // Get unique list of vendors/utilities.
        // We'll be creating a separate file for each.
        $this->info("Retrieving vendor/utility list");

        $filesToCreate = StatsProduct::distinct()->select(
            'vendor_label',
            'vendor_name',
            'vendor_grp_id',
            'utility_commodity_ldc_code',
            'utility_commodity_external_id AS utility_duns'
        )->whereDate(
            'interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'interaction_created_at',
            '<=',
            $this->endDate
        )->where(
            'result',
            'sale'
        )->where(
            'brand_id',
            $this->brandId
        )->get();

        $this->info('  ' . count($filesToCreate) . ' Record(s) found...');
        // Create an array of file counters.
        // One array elem for each vendor.
        $fileNums = [];
        foreach ($filesToCreate as $filter) {
            $vendorId = $filter['vendor_grp_id'];

            if (!isset($fileNums[$vendorId])) {
                $fileNums[$vendorId] = 1 * $runNumber;
            }
        }

        $csv = array(); // Houses formatted data CSV file. Array will be reset every iteration of the below for loop unless we're running the consolidated report.

        foreach ($filesToCreate as $filter) {

            $vendorId = $filter['vendor_grp_id'];

            $this->info("");
            $this->info('Vendor: ' . $filter['vendor_label']);
            $this->info('Vendor ID: ' . $vendorId);
            $this->info('Utility: ' . $filter['utility_commodity_ldc_code']);
            $this->info('DUNS: ' . $filter['utility_duns']);
            $this->info("");

            $filename =
                'In_625_ParkPower'
                . '_' . $this->startDate->format('ymd')
                . '_814EN01_'
                . str_pad($vendorId, 2, '0', STR_PAD_LEFT) . str_pad($fileNums[$vendorId], 2, '0', STR_PAD_LEFT)
                . '.txt';

            if ($this->mode == 'test') {
                $filename = 'test_' . $filename;
            }

            // Data layout/File header for CSV file∂.
            $csvHeader = $this->flipKeysAndValues([
                'RecordType', '078610699', $filter['utility_duns'], 'MarketerAccountNumber', 'UtilityAccountNumber', 'MeterNumber', 'CommodityId', 'RequestType', 'Template', 'CustomerName', 'NameCode',
                'BillingAddress1', 'BillingAddress2', 'BillingCity', 'BillingState', 'BillingZip', 'BillingEmail', 'Phone', 'Fax', 'Email', 'BillDeliveryMethod', 'BillPaymentMethod', 'ContractStart', 'ContractExpiration',
                'AccountTypeId', 'AccountClassName', 'LateChargeOption', 'LateChargeRate', 'LateChargeDays', 'CustomerTaxJurisdiction', 'GlobalTaxJurisdiction', 'GlobalTaxSettings', 'IncludeCityTax', 'IncludeCountyTax',
                'IncludeStateTax', 'IncludeSPDTax', 'IncludeMTATax', 'IncludeGRTTax', 'IncludeOtherTax', 'TaxablePercentage', 'PartialResTax', 'BillCycleDay', 'BillCycleDOW', 'BillCycleNth', 'ContactName',
                'SalespersonName', 'SalesCommission', 'SalesCommissionUOM', 'SalespersonName2', 'SalesCommission2', 'SalesCommissionUOM2', 'HomePhone', 'BusinessPhone', 'MobilePhone', 'PIN', 'CriticalLoad',
                'PersonalIdType', 'PersonalIdData', 'PermitName', 'HistoricalData', 'OffCycleSwitch', 'OffCycleDate', 'NextDayMoveIn', 'NextDayMoveInDate', 'MoveInPriority', 'NotifyWaiver', 'NotifyUseServiceAddress',
                'NotifyName', 'NotifyAddress1', 'NotifyAddress2', 'NotifyCity', 'NotifyState', 'NotifyZip', 'LocationName', 'SvcAddress1', 'SvcAddress2', 'SvcCity', 'SvcCounty', 'SvcState', 'SvcZip', 'Status', 'DropDate',
                'SvcTaxJurisdiction', 'SvcGlobalTaxSettings', 'SvcIncludeCityTax', 'SvcIncludeCountyTax', 'SvcIncludeStateTax', 'SvcIncludeSPDTax', 'SvcIncludeMTATax', 'SvcIncludeGRTTax', 'SvcIncludeOtherTax',
                'SvcTaxablePercentage', 'BillUtilityCharges', 'LowIncomeDiscount', 'GreenPower', 'ServiceStartDate', 'ServiceEndDate', 'ReadCycle', 'UtilityLoadProfile', 'BillingMethodId', 'BillingTypeId', 'EstimatedUsage',
                'RateClassStatus', 'RateClassName', 'RateClassOverrideRate', 'RateMarkup', 'RateMarkupUOM', 'UtilizationModel', 'OverUtilizationUsage', 'OverUtilizationAmount', 'OverUtilizationUOM', 'UnderUtilizationUsage',
                'UnderUtilizationAmount', 'UnderUtilizationUOM', 'IntervalDataType', 'MCPELoadProfileName', 'GasPoolNumber', 'SupplyOption', 'CapacityObligation', 'UtilityRateCode', 'MaximumDailyQuantity',
                'HighestDailyAverage', 'RxRateClass1', 'RxRate1', 'RxEffective1', 'RxTerm1', 'RxExpires1', 'RxRateClass2', 'RxRate2', 'RxEffective2', 'RxTerm2', 'RxExpires2', 'CusInfo1', 'CusInfo2', 'CusInfo3', 'CusInfo4',
                'CusInfo5', 'SalesPersonCode', 'SalesPersonCode2', 'RxRateClass3', 'RxRate3', 'RxEffective3', 'RxTerm3', 'RxExpires3', 'CommissionBonusResidential', 'CommissionBonusCommercial', 'CommissionBonusResidential2',
                'CommissionBonusCommercial2', 'EnrollmentSubmitDate', 'AutoSubmitFlag', 'ContactPreference', 'PreferredLanguage', 'PublicAggregator', 'SvcInfo1', 'SvcInfo2', 'SvcInfo3', 'SvcInfo4', 'SvcInfo5', 'MtrInfo1',
                'MtrInfo2', 'MtrInfo3', 'MtrInfo4', 'MtrInfo5'
            ]);

            // Reset the CSV array in prep for next file
            if (!$this->option('consolidated-report')) {
                $csv = array();
            }

            $this->info('Retrieving TPV data...');

            $data = StatsProduct::select(
                'event_id',
                'event_created_at',
                'interaction_created_at',
                'result',
                'channel',
                'market',
                'commodity',
                'vendor_label',
                'vendor_grp_id',
                'vendor_name',
                'sales_agent_rep_id',
                'utility_commodity_ldc_code',
                'utility_commodity_external_id',
                'account_number1',
                'account_number2',
                'name_key',
                'rate_renewal_plan',
                'rate_source_code',
                'product_term',
                'auth_first_name',
                'auth_middle_name',
                'auth_last_name',
                'billing_address1',
                'billing_address2',
                'billing_city',
                'billing_state',
                'billing_zip',
                'billing_county',
                'bill_first_name',
                'bill_middle_name',
                'bill_last_name',
                'service_address1',
                'service_address2',
                'service_city',
                'service_state',
                'service_zip',
                'service_county',
                'btn',
                'email_address',
                'event_product_id'
            )->whereDate(
                'stats_product.interaction_created_at',
                '>=',
                $this->startDate
            )->whereDate(
                'stats_product.interaction_created_at',
                '<=',
                $this->endDate
            )->where(
                'stats_product.result',
                'sale'
            )->where(
                'stats_product.brand_id',
                $this->brandId
            )->where(
                'stats_product.vendor_label',
                $filter['vendor_label']
            )->where(
                'stats_product.utility_commodity_external_id',
                $filter['utility_duns']
            )->orderBy(
                'stats_product.interaction_created_at'
            )->get();

            $this->info(count($data) . ' Record(s) found.');

            // Format and populate data CSV file
            foreach ($data as $r) {

                $this->info($r->event_id . ':');

                // Map data to enrollment CSV file fields.
                $this->info('  Mapping data to CSV file layout...');

                $serviceState = strtolower($r->service_state);

                $daysToAdd = ($serviceState == 'dc' || $serviceState == 'md' || $serviceState == 'pa'
                    ? 4 : 1);

                $contractStartDate = ($r->interaction_created_at->addDays($daysToAdd)->isWeekend()
                    ? $r->interaction_created_at->next(Carbon::MONDAY)->format('m/d/y 0:00')
                    : $r->interaction_created_at->addDays($daysToAdd)->format('m/d/y 0:00'));

                // Billing address. Per #17711, it's now populated for all utilities.
                // If we dont' have the billing address, use the service address instead.
                $billAddr1 = $r->billing_address1;
                if (!empty($r->billing_address2)) {
                    $billAddr2 = $r->billing_address2;
                } else {
                    $billAddr2 = '';
                }
                $billCity  = $r->billing_city;
                $billState = $r->billing_state;
                $billZip = $r->billing_zip;

                if (empty($billAddr1)) { // use service address as billing address?
                    $billAddr1 = $r->service_address1;
                    if (!empty($r->service_address2)) {
                        $billAddr2 = $r->service_address2;
                    } else {
                        $billAddr2 = '';
                    }
                    $billCity  = $r->service_city;
                    $billState = $r->service_state;
                    $billZip = $r->service_zip;
                }

                // Utility-specific vars.
                // For COH and ColGasPa, GasPoolNumber is determined by a zip lookup
                $this->info('  ' . $r->utility_commodity_ldc_code);

                $gasPoolNumber = '';
                $supplyOption = ''; // for DEO only
                $capacityObligation = ''; // for DEO only

                switch (strtolower(trim($r->utility_commodity_ldc_code))) {
                    case 'deo': {
                            $gasPoolNumber = 'A';
                            $supplyOption = 'B';
                            $capacityObligation = 'Y';

                            $this->info('  Setting GasPoolNumber to: ' . $gasPoolNumber);
                            $this->info('  Setting SupplyOption to: ' . $supplyOption);
                            $this->info('  Setting CapacityObligation to: ' . $capacityObligation);
                            break;
                        }

                    case 'pgw': {
                            $gasPoolNumber = 'PAM02';
                            $this->info('  Setting GasPoolNumber to: ' . $gasPoolNumber);
                            break;
                        }
                    case 'columbia gas oh': { // yes, their LDC code is set up to be the same as the utility name......
                            try {
                                $gasPoolNumber = $this->cohNomGroupList[$r->service_zip];
                                $this->info('  Found. Settinng GasPoolNumber to: ' . $gasPoolNumber);
                            } catch (\Exception $e) {; // catch will trigger when zip is not in Nomination Group list. We can ignore the error and leave the value blank
                                $this->info('  Not Found. Leaving GasPoolNumber blank.');
                            }
                            break;
                        }
                    case 'columbia gas pa': { // yes, their LDC code is set up to be the same as the utility name......
                            try {
                                $gasPoolNumber = $this->colGasPaNomGroupList[$r->service_zip];
                                $this->info('  Found. Settinng GasPoolNumber to: ' . $gasPoolNumber);
                            } catch (\Exception $e) {; // catch will trigger when zip is not in Nomination Group list. We can ignore the error and leave the value blank
                                $this->info('  Not Found. Leaving GasPoolNumber blank.');
                            }
                            break;
                        }
                }

                // Tax Jurisdictions
                $customerTaxJurisdiction = '*Any|0*Any|' . $r->service_state; // Default values
                $svcTaxJurisdiction = '*Any|0*Any|' . $r->service_state;

                // For OH - Dominion OH, Duke Gas OH, and Columbia Gas OH - use custom tax jurisdiction values
                if (
                    strtolower($r->service_state) == 'oh' &&
                    strtolower($r->commodity) == 'natural gas' &&
                    (strtolower($r->utility_commodity_ldc_code) == 'columbia gas oh' ||
                        strtolower($r->utility_commodity_ldc_code) == 'deo' ||
                        strtolower($r->utility_commodity_ldc_code) == 'duke')
                ) {
                    $customerTaxJurisdiction = trim($r->service_city) . '|' . trim($r->service_county) . '|' . $r->service_state;
                    $svcTaxJurisdiction = trim($r->service_city) . '|' . trim($r->service_county) . '|' . $r->service_state;
                }

                $row = [
                    $csvHeader['RecordType'] => 'A',                 // Always A
                    $csvHeader['078610699'] => '078610699',          // Per ECI's spec doc, the DUNS number is used as both the header and value
                    $csvHeader[$r->utility_commodity_external_id] => $r->utility_commodity_external_id, // Per ECI's spec doc, the utility DUNS number is used as both the header as well as the value.
                    $csvHeader['MarketerAccountNumber'] => $r->account_number1,
                    $csvHeader['UtilityAccountNumber'] => $r->account_number1,
                    $csvHeader['MeterNumber'] => (strtolower($r->utility_commodity_ldc_code) == 'pgw' ? $r->account_number2 : 'ALL'),
                    $csvHeader['CommodityId'] => (strtolower($r->commodity) == 'electric' ? 'E' : 'G'),
                    $csvHeader['RequestType'] => '1',
                    $csvHeader['Template'] => '',
                    $csvHeader['CustomerName'] => $this->csvSanitize($r->bill_last_name . ' ' . $r->bill_first_name),
                    $csvHeader['NameCode'] => $r->name_key,
                    $csvHeader['BillingAddress1'] => $billAddr1,
                    $csvHeader['BillingAddress2'] => $billAddr2,
                    $csvHeader['BillingCity'] => $billCity,
                    $csvHeader['BillingState'] => $billState,
                    $csvHeader['BillingZip'] => $billZip,
                    $csvHeader['BillingEmail'] => '',
                    $csvHeader['Phone'] => trim($r->btn, '+1'),
                    $csvHeader['Fax'] => '',
                    $csvHeader['Email'] => $r->email_address,
                    $csvHeader['BillDeliveryMethod'] => 'M',
                    $csvHeader['BillPaymentMethod'] => 1,
                    $csvHeader['ContractStart'] => $contractStartDate,
                    $csvHeader['ContractExpiration'] => '',
                    $csvHeader['AccountTypeId'] => ($r->market == 'Residential' ? 1 : 2),
                    $csvHeader['AccountClassName'] => '',
                    $csvHeader['LateChargeOption'] => '',
                    $csvHeader['LateChargeRate'] => '',
                    $csvHeader['LateChargeDays'] => '',
                    $csvHeader['CustomerTaxJurisdiction'] => $customerTaxJurisdiction,
                    $csvHeader['GlobalTaxJurisdiction'] => 'false', // CSV exports booleans as 1/0, so set as string values.
                    $csvHeader['GlobalTaxSettings'] => 'false',
                    $csvHeader['IncludeCityTax'] => 'true',
                    $csvHeader['IncludeCountyTax'] => 'true',
                    $csvHeader['IncludeStateTax'] => 'true',
                    $csvHeader['IncludeSPDTax'] => 'true',
                    $csvHeader['IncludeMTATax'] => 'true',
                    $csvHeader['IncludeGRTTax'] => 'true',
                    $csvHeader['IncludeOtherTax'] => 'true',
                    $csvHeader['TaxablePercentage'] => 100,
                    $csvHeader['PartialResTax'] => '',
                    $csvHeader['BillCycleDay'] => '',
                    $csvHeader['BillCycleDOW'] => '',
                    $csvHeader['BillCycleNth'] => '',
                    $csvHeader['ContactName'] => '',
                    $csvHeader['SalespersonName'] => $this->csvSanitize($r->vendor_label),
                    $csvHeader['SalesCommission'] => '',
                    $csvHeader['SalesCommissionUOM'] => '',
                    $csvHeader['SalespersonName2'] => $r->sales_agent_rep_id,
                    $csvHeader['SalesCommission2'] => '',
                    $csvHeader['SalesCommissionUOM2'] => '',
                    $csvHeader['HomePhone'] => '',
                    $csvHeader['BusinessPhone'] => '',
                    $csvHeader['MobilePhone'] => '',
                    $csvHeader['PIN'] => '',
                    $csvHeader['CriticalLoad'] => '',
                    $csvHeader['PersonalIdType'] => '',
                    $csvHeader['PersonalIdData'] => '',
                    $csvHeader['PermitName'] => '',
                    $csvHeader['HistoricalData'] => 1, // ECI recommendation to always use 1
                    $csvHeader['OffCycleSwitch'] => '',
                    $csvHeader['OffCycleDate'] => '',
                    $csvHeader['NextDayMoveIn'] => '',
                    $csvHeader['NextDayMoveInDate'] => '',
                    $csvHeader['MoveInPriority'] => '',
                    $csvHeader['NotifyWaiver'] => '',
                    $csvHeader['NotifyUseServiceAddress'] => '',
                    $csvHeader['NotifyName'] => '',
                    $csvHeader['NotifyAddress1'] => '',
                    $csvHeader['NotifyAddress2'] => '',
                    $csvHeader['NotifyCity'] => '',
                    $csvHeader['NotifyState'] => '',
                    $csvHeader['NotifyZip'] => '',
                    $csvHeader['LocationName'] => '',
                    $csvHeader['SvcAddress1'] => $this->csvSanitize($r->service_address1),
                    $csvHeader['SvcAddress2'] => $this->csvSanitize($r->service_address2),
                    $csvHeader['SvcCity'] => $this->csvSanitize($r->service_city),
                    $csvHeader['SvcCounty'] => $this->csvSanitize($r->service_county),
                    $csvHeader['SvcState'] => $this->csvSanitize($r->service_state),
                    $csvHeader['SvcZip'] => $r->service_zip,
                    $csvHeader['Status'] => '',
                    $csvHeader['DropDate'] => '',
                    $csvHeader['SvcTaxJurisdiction'] => $svcTaxJurisdiction,
                    $csvHeader['SvcGlobalTaxSettings'] => 'false',
                    $csvHeader['SvcIncludeCityTax'] => 'true',
                    $csvHeader['SvcIncludeCountyTax'] => 'true',
                    $csvHeader['SvcIncludeStateTax'] => 'true',
                    $csvHeader['SvcIncludeSPDTax'] => 'true',
                    $csvHeader['SvcIncludeMTATax'] => 'true',
                    $csvHeader['SvcIncludeGRTTax'] => 'true',
                    $csvHeader['SvcIncludeOtherTax'] => 'true',
                    $csvHeader['SvcTaxablePercentage'] => 100,
                    $csvHeader['BillUtilityCharges'] => '',
                    $csvHeader['LowIncomeDiscount'] => '',
                    $csvHeader['GreenPower'] => '',
                    $csvHeader['ServiceStartDate'] => '',
                    $csvHeader['ServiceEndDate'] => '',
                    $csvHeader['ReadCycle'] => '',
                    $csvHeader['UtilityLoadProfile'] => '',
                    $csvHeader['BillingMethodId'] => (strtolower($r->rate_source_code) == 'ucbr' ? 3 : (strtolower($r->rate_source_code) == 'ucrr' ? 4 : '')), // UCBR --> UBR (3), UCRR --> URR (4)
                    $csvHeader['BillingTypeId'] => '',
                    $csvHeader['EstimatedUsage'] => '',
                    $csvHeader['RateClassStatus'] => 'A', // Always A per ECI
                    $csvHeader['RateClassName'] => '',
                    $csvHeader['RateClassOverrideRate'] => '',
                    $csvHeader['RateMarkup'] => '',
                    $csvHeader['RateMarkupUOM'] => '',
                    $csvHeader['UtilizationModel'] => '',
                    $csvHeader['OverUtilizationUsage'] => '',
                    $csvHeader['OverUtilizationAmount'] => '',
                    $csvHeader['OverUtilizationUOM'] => '',
                    $csvHeader['UnderUtilizationUsage'] => '',
                    $csvHeader['UnderUtilizationAmount'] => '',
                    $csvHeader['UnderUtilizationUOM'] => '',
                    $csvHeader['IntervalDataType'] => '',
                    $csvHeader['MCPELoadProfileName'] => '',
                    $csvHeader['GasPoolNumber'] => $gasPoolNumber,
                    $csvHeader['SupplyOption'] => $supplyOption,
                    $csvHeader['CapacityObligation'] => $capacityObligation,
                    $csvHeader['UtilityRateCode'] => '',
                    $csvHeader['MaximumDailyQuantity'] => '',
                    $csvHeader['HighestDailyAverage'] => '',
                    $csvHeader['RxRateClass1'] => $this->csvSanitize($r->rate_renewal_plan),
                    $csvHeader['RxRate1'] => '',
                    $csvHeader['RxEffective1'] => '',
                    $csvHeader['RxTerm1'] => $r->product_term,
                    $csvHeader['RxExpires1'] => '12/31/9999',
                    $csvHeader['RxRateClass2'] => '',
                    $csvHeader['RxRate2'] => '',
                    $csvHeader['RxEffective2'] => '',
                    $csvHeader['RxTerm2'] => '',
                    $csvHeader['RxExpires2'] => '',
                    $csvHeader['CusInfo1'] => $r->interaction_created_at->format('m/d/y'),
                    $csvHeader['CusInfo2'] => '',
                    $csvHeader['CusInfo3'] => '',
                    $csvHeader['CusInfo4'] => '',
                    $csvHeader['CusInfo5'] => '',
                    $csvHeader['SalesPersonCode'] => '',
                    $csvHeader['SalesPersonCode2'] => '',
                    $csvHeader['RxRateClass3'] => '',
                    $csvHeader['RxRate3'] => '',
                    $csvHeader['RxEffective3'] => '',
                    $csvHeader['RxTerm3'] => '',
                    $csvHeader['RxExpires3'] => '',
                    $csvHeader['CommissionBonusResidential'] => '',
                    $csvHeader['CommissionBonusCommercial'] => '',
                    $csvHeader['CommissionBonusResidential2'] => '',
                    $csvHeader['CommissionBonusCommercial2'] => '',
                    $csvHeader['EnrollmentSubmitDate'] => ($serviceState == 'dc' || $serviceState == 'md' || $serviceState == 'pa' ? $contractStartDate : ''),
                    $csvHeader['AutoSubmitFlag'] => 'true',
                    $csvHeader['ContactPreference'] => '',
                    $csvHeader['PreferredLanguage'] => '',
                    $csvHeader['PublicAggregator'] => '',
                    $csvHeader['SvcInfo1'] => '',
                    $csvHeader['SvcInfo2'] => '',
                    $csvHeader['SvcInfo3'] => '',
                    $csvHeader['SvcInfo4'] => '',
                    $csvHeader['SvcInfo5'] => '',
                    $csvHeader['MtrInfo1'] => '',
                    $csvHeader['MtrInfo2'] => '',
                    $csvHeader['MtrInfo3'] => '',
                    $csvHeader['MtrInfo4'] => '',
                    $csvHeader['MtrInfo5'] => ''
                ];

                // Add row to CSV output array
                $csv[] = $row;
            }


            // Write the CSV file (enrollment files)
            if (!$this->option('consolidated-report')) {
                $this->info('Writing CSV file (' . $filename . ')...');

                $file = fopen(public_path('tmp/' . $filename), 'w');

                fputs($file, implode(",", array_keys($csvHeader)) . "\r\n");

                foreach ($csv as $row) {
                    //fputcsv($file, $row);
                    fputs($file, implode(",", $row) . "\r\n");
                }
                fclose($file);

                // Create internal-use CSV files with names that make sense?
                if ($this->option('csvfiles')) {
                    $file = fopen(public_path('tmp/' .
                        explode('.', $filename)[0] . '__'
                        . $filter['vendor_label'] . '_'
                        . $filter['utility_commodity_ldc_code'] . '_'
                        . $filter['utility_duns'] . '.csv'), 'w');

                    fputs($file, implode(",", array_keys($csvHeader)) . "\r\n");

                    foreach ($csv as $row) {
                        //fputcsv($file, $row);
                        fputs($file, implode(",", $row) . "\r\n");
                    }
                    fclose($file);
                }



                // Upload the file to FTP server
                // if (!$this->option('noftp')) {
                //     $this->info('Uploading file...');
                //     $this->info($filename);
                //     $uploadStatus = $this->ftpUpload($filename);

                //     if (isset($uploadStatus)) {
                //         if (strpos(json_encode($uploadStatus), 'Status: Success!') == true) {
                //             $this->info('Upload succeeded.');

                //             $this->sendEmail('File ' . $filename . ' has been successfully uploaded.', $this->distroList['ftp_success'][$this->mode]);
                //         } else {
                //             $this->info('Upload failed.');
                //             $this->sendEmail(
                //                 'Error uploading file ' . $filename . ' to FTP server ' . $this->ftpSettings[$this->mode]['host'] .
                //                     "\n\n FTP Result: \n\n" . json_encode($uploadStatus),
                //                 $this->distroList['ftp_error'][$this->mode]
                //             );

                //             return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
                //         }
                //     }
                // }

                $filesToEmail[$filter['vendor_name']][] = public_path('tmp/' . $filename); // TODO: Remove this line once we're given ECI FTP details.
            }
            // Delete temp files
            //unlink(public_path('tmp/' . $filename)); // TODO: Restore this line once we're given ECI FTP details.

            $fileNums[$filter['vendor_grp_id']]++;
        }

        // Write the CSV file (consolidated report)
        if ($this->option('consolidated-report')) {

            $filename =
                'In_625_ParkPower'
                . '_' . $this->startDate->format('ymd')
                . '_814EN01_Consolidated_Report.csv';

            if (!isset($csvHeader)) { // TODO: In case of no data. Find a better way to handle this
                // Data layout/File header for CSV file∂.
                $csvHeader = $this->flipKeysAndValues([
                    'RecordType', '078610699', '000000000', 'MarketerAccountNumber', 'UtilityAccountNumber', 'MeterNumber', 'CommodityId', 'RequestType', 'Template', 'CustomerName', 'NameCode',
                    'BillingAddress1', 'BillingAddress2', 'BillingCity', 'BillingState', 'BillingZip', 'BillingEmail', 'Phone', 'Fax', 'Email', 'BillDeliveryMethod', 'BillPaymentMethod', 'ContractStart', 'ContractExpiration',
                    'AccountTypeId', 'AccountClassName', 'LateChargeOption', 'LateChargeRate', 'LateChargeDays', 'CustomerTaxJurisdiction', 'GlobalTaxJurisdiction', 'GlobalTaxSettings', 'IncludeCityTax', 'IncludeCountyTax',
                    'IncludeStateTax', 'IncludeSPDTax', 'IncludeMTATax', 'IncludeGRTTax', 'IncludeOtherTax', 'TaxablePercentage', 'PartialResTax', 'BillCycleDay', 'BillCycleDOW', 'BillCycleNth', 'ContactName',
                    'SalespersonName', 'SalesCommission', 'SalesCommissionUOM', 'SalespersonName2', 'SalesCommission2', 'SalesCommissionUOM2', 'HomePhone', 'BusinessPhone', 'MobilePhone', 'PIN', 'CriticalLoad',
                    'PersonalIdType', 'PersonalIdData', 'PermitName', 'HistoricalData', 'OffCycleSwitch', 'OffCycleDate', 'NextDayMoveIn', 'NextDayMoveInDate', 'MoveInPriority', 'NotifyWaiver', 'NotifyUseServiceAddress',
                    'NotifyName', 'NotifyAddress1', 'NotifyAddress2', 'NotifyCity', 'NotifyState', 'NotifyZip', 'LocationName', 'SvcAddress1', 'SvcAddress2', 'SvcCity', 'SvcCounty', 'SvcState', 'SvcZip', 'Status', 'DropDate',
                    'SvcTaxJurisdiction', 'SvcGlobalTaxSettings', 'SvcIncludeCityTax', 'SvcIncludeCountyTax', 'SvcIncludeStateTax', 'SvcIncludeSPDTax', 'SvcIncludeMTATax', 'SvcIncludeGRTTax', 'SvcIncludeOtherTax',
                    'SvcTaxablePercentage', 'BillUtilityCharges', 'LowIncomeDiscount', 'GreenPower', 'ServiceStartDate', 'ServiceEndDate', 'ReadCycle', 'UtilityLoadProfile', 'BillingMethodId', 'BillingTypeId', 'EstimatedUsage',
                    'RateClassStatus', 'RateClassName', 'RateClassOverrideRate', 'RateMarkup', 'RateMarkupUOM', 'UtilizationModel', 'OverUtilizationUsage', 'OverUtilizationAmount', 'OverUtilizationUOM', 'UnderUtilizationUsage',
                    'UnderUtilizationAmount', 'UnderUtilizationUOM', 'IntervalDataType', 'MCPELoadProfileName', 'GasPoolNumber', 'SupplyOption', 'CapacityObligation', 'UtilityRateCode', 'MaximumDailyQuantity',
                    'HighestDailyAverage', 'RxRateClass1', 'RxRate1', 'RxEffective1', 'RxTerm1', 'RxExpires1', 'RxRateClass2', 'RxRate2', 'RxEffective2', 'RxTerm2', 'RxExpires2', 'CusInfo1', 'CusInfo2', 'CusInfo3', 'CusInfo4',
                    'CusInfo5', 'SalesPersonCode', 'SalesPersonCode2', 'RxRateClass3', 'RxRate3', 'RxEffective3', 'RxTerm3', 'RxExpires3', 'CommissionBonusResidential', 'CommissionBonusCommercial', 'CommissionBonusResidential2',
                    'CommissionBonusCommercial2', 'EnrollmentSubmitDate', 'AutoSubmitFlag', 'ContactPreference', 'PreferredLanguage', 'PublicAggregator', 'SvcInfo1', 'SvcInfo2', 'SvcInfo3', 'SvcInfo4', 'SvcInfo5', 'MtrInfo1',
                    'MtrInfo2', 'MtrInfo3', 'MtrInfo4', 'MtrInfo5'
                ]);
            }

            $this->info('Writing Consolidated Report file (' . $filename . ')...');

            $file = fopen(public_path('tmp/') . $filename, 'w');

            fputs($file, implode(",", array_keys($csvHeader)) . "\r\n");

            foreach ($csv as $row) {
                //fputcsv($file, $row);
                fputs($file, implode(",", $row) . "\r\n");
            }
            fclose($file);
        }

        // Email the files (enrollment files)
        // Use the filesToEmail array to send one email per vendor (each email can have one or more files attached)
        // TODO: Remove this section once we're given ECI FTP details.
        if (!$this->option('noemail') && !$this->option('consolidated-report')) {
            $this->info('Emailing enrollment files...');

            foreach ($filesToEmail as $key => $files) {
                $this->sendEmail(
                    'Attached are the ECI ' . $key . ' enrollment files for ' . $this->startDate->format('m-d-Y') . '.',
                    $this->jobName . ' - ' . $key,
                    ($this->mode == 'test'
                        ? ['alex@tpv.com']
                        : ['dxc_autoemails@dxc-inc.com', 'ldesanto@parkpower.com', 'aferraioli@parkpower.com', 'jcolia@parkpower.com', 'nmcguffin@parkpower.com']),
                    $files
                );

                foreach ($files as $file) {
                    unlink($file);
                }
            }
        }

        // Email the file (consolidated report)
        if (!$this->option('noemail') && $this->option('consolidated-report')) {
            $this->info('Emailing consolidated report...');

            $this->sendEmail(
                'Attached is the ECI consolidated enrollments report for ' . $this->startDate->format('m-d-Y') . '.',
                $this->jobName . ' - Consolidated Report',
                ($this->mode == 'test'
                    ? ['alex@tpv.com']
                    : ['dxc_autoemails@dxc-inc.com', 'ldesanto@parkpower.com', 'aferraioli@parkpower.com', 'nmcguffin@parkpower.com']),
                [public_path('tmp/') . $filename]
            );

            unlink(public_path('tmp/') . $filename);
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
    public function sendEmail(string $message, string $subject, array $distro, array $files = array())
    {
        $uploadStatus = [];
        $email_address = $distro;

        // Build email subject
        if ('production' != env('APP_ENV')) {
            $subject .= ' (' . env('APP_ENV') . ') ' . Carbon::now();
        } else {
            $subject .= ' ' . Carbon::now();
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
     * Load COH Nomination Groups from file.
     */
    private function loadCohNomGroupList()
    {
        $data = json_decode(file_get_contents('resources/js/park_coh_nom_groups.json'), true); // 'true' so we get data back as associative array
        return $data;
    }

    /**
     * Load Columbia Gas PA Nomination Groups from file.
     */
    private function loadColGasPaNomGroupList()
    {
        $data = json_decode(file_get_contents('resources/js/park_col_gas_pa_nom_groups.json'), true); // 'true' so we get data back as associative array
        return $data;
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
     * Removes CSV reserved characters from text values
     *
     * @param mixed $str The string value to sanitize
     *
     * @return string Sanitizeds string value
     */
    private function csvSanitize($str)
    {
        if (!$str) {
            return '';
        }

        $str = str_replace(",", "", $str);
        $str = str_replace("'", "", $str);
        $str = str_replace('"', "", $str);

        return $str;
    }
}
