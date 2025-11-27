<?php

namespace App\Console\Commands\Family;

use Carbon\Carbon;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use App\Helpers\FtpHelper;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

use App\Models\StatsProduct;

/**
 * Family Energy
 */
class FamilyEnergyDailyCallsReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'family:reports:daily-calls:ftp {--mode=} {--env=} {--show-sql} {--start-date=} {--end-date=} {--market=} {--vendor=} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Family Energy Daily Calls - FTP';

    /**
     * Report start date.
     * 
     * @var mixed
     */
    protected $startDate = null;

    /**
     * Report end date.
     * 
     * @var mixed
     */
    protected $endDate = null;

    /**
     * Report mode: 'live' or 'test'.
     * 
     * @var string
     */
    protected $mode = '';

    /**
     * Environment: 'prod' or 'stage'.
     * 
     * @var string
     */
    protected $env = '';    
    
    /**
     * Marke: Residential or Commercial
     * 
     * @var string
     */
    protected $market = '';

    /**
     * Brand.
     * 
     * @var string
     */
    protected $brand = 'Family Energy';

    /**
     * Vendor name. Populated if vendor option is used.
     * 
     * @var string
     */
    protected $vendorName = '';

    /**
     * FTP Settings
     *
     * @var array
     */
    protected $ftpSettings = null;

    /**
     * Brand ID.
     * 
     * @var string
     */
    protected $brandId = [
        'prod' => '1de6e0fc-8951-45bd-a88b-e353b0d85dfc',
        'stage' => 'x' // At the time this job was written, brand was only set up in product. Use 'x' here to ensure no data can be pulled from staging DB.
    ];

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
        $this->setMode();
        $this->setEnv();
        $this->setMarket();
        $this->setDateRange();

        $this->info('Brand:      (' . $this->brand . ') ' . $this->brandId[$this->env] . "\n");
        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date:   ' . $this->endDate);
        $this->info('Market:     ' . $this->market);
        $this->info('Mode:       ' . $this->mode);
        $this->info('Env:        ' . $this->env);
        $this->info('');
        
        // Get FTP settings
        $this->info('Getting FTP settings...');
        $this->ftpSettings = $this->getFtpSettings();

        if(!$this->ftpSettings) {
            $this->error('Unable to retrieve FTP settings. Exiting...');
            exit -1;
        }

        // Build file names, using that start date for the file/reporting dates
        $fileName = Carbon::now('America/Chicago')->format('Y_m_d_h_i_s') . '.txt';
        
        // Modify filename if test mode
        if ($this->mode == 'test') {
            $fileName = 'TEST_' . $fileName;
        }
      
        $csvHeader = [
            'CONTRACTNO',
            'CUSTTYPE',
            'GAS',
            'ELECTRICITY',
            'GREENGAS',
            'GREENELECTRICITY',
            'Solar',
            'COMPANY',
            'PARENTCOMPANY',
            'POSITION',
            'SALUTATION',
            'FIRSTNAME',
            'MIDDLENAME',
            'LASTNAME',
            'PRINTNAME',
            'HOMEEMAIL',
            'BUSINESSEMAIL',
            'HOMEPHONE',
            'BUSINESSPHONE',
            'BUSINESSPHONEEXT',
            'CELLPHONE',
            'CALLERIDNUMBER',
            'DATEOFBIRTH',
            'CUSTAGEVERIFICATION',
            'LANGUAGE',
            'SERVICEADDRESS1',
            'SERVICEADDRESS2',
            'SERVICECITY',
            'SERVICESTATE',
            'SERVICEZIPCODE',
            'DWELLINGTYPE',
            'OCCUPANCY',
            'MAILINGADDRESS1',
            'MAILINGADDRESS2',
            'MAILINGCITY',
            'MAILINGSTATE',
            'MAILINGZIPCODE',
            'GASUTILITYACCOUNTNUMBER',
            'ELECTRICITYUTILITYACCOUNTNUMBER',
            'GASPODID',
            'ELECTRICITYPODID',
            'SolarUtilityAccountNumber',
            'SolarSAID',
            'GasMeterNumber',
            'ElectricityMeterNumber',
            'GASRATECLASS',
            'ELECTRICITYRATECLASS',
            'SolarMeterNumber',
            'SolarRateClass',
            'GASUTILITY',
            'ELECTRICITYUTILITY',
            'SolarElectricUtility',
            'GASCONTRACTPRICE',
            'ELECTRICITYCONTRACTPRICE',
            'GREENGASCONTRACTPRICE',
            'GREENELECTRICITYCONTRACTPRICE',
            'SolarContractPrice',
            'GREENGASPERCENT',
            'GREENELECTRICITYPERCENT',
            'CONTRACTSIGNED',
            'GASCONTRACTTERMYEARS',
            'GASCONTRACTTERMMONTHS',
            'ELECTRICITYCONTRACTTERMYEARS',
            'ELECTRICITYCONTRACTTERMMONTHS',
            'SolarContractTermYears',
            'SolarContractTermMonths',
            'AGENT',
            'REPNUMBER',
            'ENERGYMARKETERCOMPANY',
            'GASPROGRAMCODE',
            'ELECTRICITYPROGRAMCODE',
            'SolarProgramCode',
            'RELATIONSHIPTOACCOUNTHOLDER',
            'SIGNUPMETHOD',
            'GASPRODUCTCODE',
            'ELECTRICITYPRODUCTCODE',
            'SolarProductCode',
            'ITERATION',
            'GASVOLUMEID',
            'ELECTRICITYVOLUMEID',
            'GASESTIMATEYEARLYVOLUME',
            'ELECTRICITYESTIMATEDYEARLYVOLUME',
            'SolarEstimatedYearlyVolume',
            'SolarBillPeriodStartDate',
            'SolarBillPeriodEndDate',
            'NOAUTORENEWAL',
            'BUDGETBILLING',
            'CUSTOMERSIGNATURE',
            'TPVGASRESULT',
            'TPVELECTRICITYRESULT',
            'TPVGREENGASRESULT',
            'TPVGREENELECTRICITYRESULT',
            'TPVSolarResult',
            'TPVINBOUNDDATE',
            'TPVINBOUNDTIME',
            'TPVOUTBOUNDDATE',
            'TPVOUTBOUNDTIME',
            'TPVEMPLOYEE',
            'TPVCOMPANY',
            'TPVCONTACTEDPERSON',
            'TPVGASPROBLEM',
            'TPVELECTRICITYPROBLEM',
            'TPVGREENGASPROBLEM',
            'TPVGREENELECTRICITYPROBLEM',
            'TPVSolarProblem',
            'TPVCOMMENTS',
            'TPVCONFIRMATIONID',
            'IMPORTCREATIONTYPE',
            'PREFEREDMETHODOFCONTACT',
            'CAMPAIGN',
            'GASREQUESTEDSTARTDATE',
            'ELECTRICITYREQUESTEDSTARTDATE',
            'CONTRACTVERSIONCODE',
            'SolarRequestCallBackDate',
            'SolarRequestCallBackTime',
            'COMMERCIALPRICING'
        ];

        $csv = array(); // Houses formatted enrollment file data

        $this->info('Retrieving data...');

        $data = $this->getData();

        $this->info(count($data) . ' Record(s) found.');

        if($this->option('vendor') && count($data) > 0) {
            $this->vendorName = $data[0]->vendor_name;
        }

        // Format and populate data 
        $this->info("Formatting data ...");

        foreach ($data as $r) {

            // Calculate term years and months
            $term = $this->getTermsYearsMonths($r->product_term, $r->product_term_type);

            $accountIdents = $this->mapAccountIdentifiers($r);

            $isGas   = strtolower($r->commodity) == 'natural gas';
            $isElec  = strtolower($r->commodity) == 'electric';
            $isGreen = !empty($r->product_green_percentage);

            $row = [
                'CONTRACTNO'    => $r->CONTRACTNO,
                'CUSTTYPE'      => $r->CUSTTYPE,
                'GAS'               => ($isGas ? 'YES' : ''),
                'ELECTRICITY'       => ($isElec ? 'YES' : ''),
                'GREENGAS'          => ($isGreen && $isGas ? 'YES' : ''),
                'GREENELECTRICITY'  => ($isGreen && $isElec ? 'YES' : ''),
                'Solar'             => $r->Solar,
                'COMPANY'       => $r->COMPANY,
                'PARENTCOMPANY' => $r->PARENTCOMPANY,
                'POSITION'      => $r->POSITION,
                'SALUTATION'    => $r->SALUTATION,
                'FIRSTNAME'     => $r->FIRSTNAME,
                'MIDDLENAME'    => $r->MIDDLENAME,
                'LASTNAME'      => $r->LASTNAME,
                'PRINTNAME'     => $r->PRINTNAME,
                'HOMEEMAIL'     => $r->HOMEEMAIL,
                'BUSINESSEMAIL' => $r->BUSINESSEMAIL,
                'HOMEPHONE'         => $r->HOMEPHONE,
                'BUSINESSPHONE'     => $r->BUSINESSPHONE,
                'BUSINESSPHONEEXT'  => $r->BUSINESSPHONEEXT,
                'CELLPHONE'         => $r->CELLPHONE,
                'CALLERIDNUMBER'    => $r->CALLERIDNUMBER,
                'DATEOFBIRTH'           => $r->DATEOFBIRTH,
                'CUSTAGEVERIFICATION'   => $r->CUSTAGEVERIFICATION,
                'LANGUAGE'          => $r->LANGUAGE,
                'SERVICEADDRESS1'   => $r->SERVICEADDRESS1,
                'SERVICEADDRESS2'   => $r->SERVICEADDRESS2,
                'SERVICECITY'       => $r->SERVICECITY,
                'SERVICESTATE'      => $r->SERVICESTATE,
                'SERVICEZIPCODE'    => $r->SERVICEZIPCODE,
                'DWELLINGTYPE'  => $r->DWELLINGTYPE,
                'OCCUPANCY'     => $r->OCCUPANCY,
                'MAILINGADDRESS1'   => $r->MAILINGADDRESS1,
                'MAILINGADDRESS2'   => $r->MAILINGADDRESS2,
                'MAILINGCITY'       => $r->MAILINGCITY,
                'MAILINGSTATE'      => $r->MAILINGSTATE,
                'MAILINGZIPCODE'    => $r->MAILINGZIPCODE,
                'GASUTILITYACCOUNTNUMBER'           => ($isGas  ? $accountIdents->account_number : ''),
                'ELECTRICITYUTILITYACCOUNTNUMBER'   => ($isElec ? $accountIdents->account_number : ''),
                'GASPODID'                          => ($isGas  ? $accountIdents->pod_id : ''),
                'ELECTRICITYPODID'                  => ($isElec ? $accountIdents->pod_id : ''),
                'SolarUtilityAccountNumber'         => $r->SolarUtilityAccountNumber,
                'SolarSAID'                         => $r->SolarSAID,
                'GasMeterNumber'                    => ($isGas  ? $accountIdents->meter_number : ''),
                'ElectricityMeterNumber'            => ($isElec ? $accountIdents->meter_number : ''),
                'GASRATECLASS'          => $r->GASRATECLASS,
                'ELECTRICITYRATECLASS'  => $r->ELECTRICITYRATECLASS,
                'SolarMeterNumber'                  => $r->SolarMeterNumber,
                'SolarRateClass'        => $r->SolarRateClass,
                'GASUTILITY'            => ($isGas  ? $r->utility_label : ''),
                'ELECTRICITYUTILITY'    => ($isElec ? $r->utility_label : ''),
                'SolarElectricUtility'  => $r->SolarElectricUtility,
                'GASCONTRACTPRICE'              => $r->GASCONTRACTPRICE,
                'ELECTRICITYCONTRACTPRICE'      => $r->ELECTRICITYCONTRACTPRICE,
                'GREENGASCONTRACTPRICE'         => $r->GREENGASCONTRACTPRICE,
                'GREENELECTRICITYCONTRACTPRICE' => $r->GREENELECTRICITYCONTRACTPRICE,
                'SolarContractPrice'            => $r->SolarContractPrice,
                'GREENGASPERCENT'         => ($isGreen && $isGas && !empty($r->product_green_percentage) ? $r->product_green_percentage : ''),
                'GREENELECTRICITYPERCENT' => ($isGreen && $isElec && !empty($r->product_green_percentage) ? $r->product_green_percentage : ''),
                'CONTRACTSIGNED'                => Carbon::parse($r->CONTRACTSIGNED)->format("m/d/Y"),
                'GASCONTRACTTERMYEARS'          => (strtolower($r->commodity) == 'natural gas' ? $term->years : ''),
                'GASCONTRACTTERMMONTHS'         => (strtolower($r->commodity) == 'natural gas' ? $term->months : ''),
                'ELECTRICITYCONTRACTTERMYEARS'  => (strtolower($r->commodity) == 'electric' ? $term->years : ''),
                'ELECTRICITYCONTRACTTERMMONTHS' => (strtolower($r->commodity) == 'electric' ? $term->months : ''),
                'SolarContractTermYears'        => $r->SolarContractTermYears,
                'SolarContractTermMonths'       => $r->SolarContractTermMonths,
                'AGENT'                 => $r->AGENT,
                'REPNUMBER'             => $r->REPNUMBER,
                'ENERGYMARKETERCOMPANY'     => $r->ENERGYMARKETERCOMPANY,
                'GASPROGRAMCODE'            => $r->GASPROGRAMCODE,
                'ELECTRICITYPROGRAMCODE'    => $r->ELECTRICITYPROGRAMCODE,
                'SolarProgramCode'          => $r->SolarProgramCode,
                'RELATIONSHIPTOACCOUNTHOLDER'   => $r->RELATIONSHIPTOACCOUNTHOLDER,
                'SIGNUPMETHOD'                  => (strtolower($r->SIGNUPMETHOD) == 'tm' ? 'Telemarketing' : $r->SIGNUPMETHOD),
                'GASPRODUCTCODE'            => $r->GASPRODUCTCODE,
                'ELECTRICITYPRODUCTCODE'    => $r->ELECTRICITYPRODUCTCODE,
                'SolarProductCode'          => $r->SolarProductCode,
                'ITERATION'                         => $r->ITERATION,
                'GASVOLUMEID'                       => $r->GASVOLUMEID,
                'ELECTRICITYVOLUMEID'               => $r->ELECTRICITYVOLUMEID,
                'GASESTIMATEYEARLYVOLUME'           => $r->GASESTIMATEYEARLYVOLUME,
                'ELECTRICITYESTIMATEDYEARLYVOLUME'  => $r->ELECTRICITYESTIMATEDYEARLYVOLUME,
                'SolarEstimatedYearlyVolume'        => $r->SolarEstimatedYearlyVolume,
                'SolarBillPeriodStartDate'  => $r->SolarBillPeriodStartDate,
                'SolarBillPeriodEndDate'    => $r->SolarBillPeriodEndDate,
                'NOAUTORENEWAL'     => $r->NOAUTORENEWAL,
                'BUDGETBILLING'     => $r->BUDGETBILLING,
                'CUSTOMERSIGNATURE' => $r->CUSTOMERSIGNATURE,
                'TPVGASRESULT'              => ($isGas ? ($r->result == 'Sale' ? 'Accepted' : 'Declined') : ''),
                'TPVELECTRICITYRESULT'      => ($isElec ? ($r->result == 'Sale' ? 'Accepted' : 'Declined') : ''),
                'TPVGREENGASRESULT'         => ($isGreen && $isGas ? ($r->result == 'Sale' ? 'Accepted' : 'Declined') : ''),
                'TPVGREENELECTRICITYRESULT' => ($isGreen && $isElec ? ($r->result == 'Sale' ? 'Accepted' : 'Declined') : ''),
                'TPVSolarResult'            => $r->TPVSolarResult,
                'TPVINBOUNDDATE'    => $r->TPVINBOUNDDATE,
                'TPVINBOUNDTIME'    => $r->TPVINBOUNDTIME,
                'TPVOUTBOUNDDATE'   => $r->TPVOUTBOUNDDATE,
                'TPVOUTBOUNDTIME'   => $r->TPVOUTBOUNDTIME,
                'TPVEMPLOYEE'   => $r->TPVEMPLOYEE,
                'TPVCOMPANY'    => $r->TPVCOMPANY,
                'TPVCONTACTEDPERSON'    => $r->TPVCONTACTEDPERSON,
                'TPVGASPROBLEM'                 => ($isGas ? ($r->result == 'Sale' ? '' : $r->disposition_reason) : ''),
                'TPVELECTRICITYPROBLEM'         => ($isElec ? ($r->result == 'Sale' ? '' : $r->disposition_reason) : ''),
                'TPVGREENGASPROBLEM'            => ($isGreen && $isGas ? ($r->result == 'Sale' ? '' : $r->disposition_reason) : ''),
                'TPVGREENELECTRICITYPROBLEM'    => ($isGreen && $isElec ? ($r->result == 'Sale' ? '' : $r->disposition_reason) : ''),
                'TPVSolarProblem'               => $r->TPVSolarProblem,
                'TPVCOMMENTS'           => $r->TPVCOMMENTS,
                'TPVCONFIRMATIONID'     => $r->TPVCONFIRMATIONID,
                'IMPORTCREATIONTYPE'    => $r->IMPORTCREATIONTYPE,
                'PREFEREDMETHODOFCONTACT'   => $r->PREFEREDMETHODOFCONTACT,
                'CAMPAIGN'                  => $r->CAMPAIGN,
                'GASREQUESTEDSTARTDATE'         => $r->GASREQUESTEDSTARTDATE,
                'ELECTRICITYREQUESTEDSTARTDATE' => $r->ELECTRICITYREQUESTEDSTARTDATE,
                'CONTRACTVERSIONCODE'       => $r->CONTRACTVERSIONCODE,
                'SolarRequestCallBackDate'  => $r->SolarRequestCallBackDate,
                'SolarRequestCallBackTime'  => $r->SolarRequestCallBackTime,
                'COMMERCIALPRICING'         => $r->COMMERCIALPRICING
            ];

            array_push($csv, $row);
        }

        // Write CSV file
        $this->info("Writing TXT file...");
        $file = fopen(public_path('tmp/' . $fileName), 'w');

        // Header Row        
        fputs($file, implode('|', $csvHeader) . CHR(13) . CHR(10));

        // Data
        foreach ($csv as $row) {
            fputs($file, implode('|', $row) . CHR(13) . CHR(10));
        }
        fclose($file);

        // Upload the file
        $ftpResult = $this->sftpUpload(public_path('tmp/') . $fileName, $this->ftpSettings);

        if($ftpResult->result == "success") {
            unlink(public_path('tmp/' . $fileName));
        } else {
            $this->error($ftpResult->message);
        }
    }

    /**
     * Retrieve FTP settings from provider_integrations table
     */
    private function getFtpSettings(): ?array {

        return FtpHelper::getSettings(
            $this->brandId[$this->env],
            44, // Family Energy SFTP
            1,
            ($this->env === 'prod' ? 1 : 2)
        );
    }

    /**
     * Enrollment File SFTP Upload.
     */
    public function sftpUpload($file, $ftpSettings)
    {
        try {

            if(!$ftpSettings) {
                return (object)["result" => "error", "message" => "SFTP settings are required", "data" => null];
            }

            $adapter = new SftpAdapter($ftpSettings);

            $fileParts = pathinfo($file);
            
            $filesystem = new Filesystem($adapter);
            $stream = fopen($file, 'r+');
            $filesystem->writeStream(
                $fileParts['basename'],
                $stream
            );

            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Exception $e) {

            return (object)["result" => "error", "message" => $e->getMessage(), "data" => null];
        }

        return (object)["result" => "success", "message" => "File(s) successfully uploaded", "data" => null];
    }

    /**
     * Retrieve TPV data from database
     */
    private function getData()
    {
        $data = StatsProduct::select(
            'stats_product.commodity',
            'stats_product.product_green_percentage',
            'stats_product.result',
            'stats_product.disposition_reason',
            'brand_utilities.utility_label',
            'stats_product.account_number1',
            'stats_product.account_number2',
            'stats_product.vendor_name',
            DB::raw('"" as CONTRACTNO'),
            DB::raw('UPPER(`stats_product`.`market`) AS CUSTTYPE'),
            DB::raw('"" as Solar'),
            DB::raw('"" as COMPANY'),
            DB::raw('"" as PARENTCOMPANY'),
            DB::raw('"" as POSITION'),
            DB::raw('"" as SALUTATION'),
            'stats_product.bill_first_name  AS FIRSTNAME',
            'stats_product.bill_middle_name AS MIDDLENAME',
            'stats_product.bill_last_name AS LASTNAME',
            DB::raw('CONCAT(stats_product.auth_first_name, " ", stats_product.auth_last_name) as PRINTNAME'),
            'stats_product.email_address AS HOMEEMAIL', 
            DB::raw('"" as BUSINESSEMAIL'),
            DB::raw("   right(trim(stats_product.btn),10) AS HOMEPHONE"),
            DB::raw('"" as BUSINESSPHONE'),
            DB::raw('"" as BUSINESSPHONEEXT'),
            DB::raw('"" as CELLPHONE'),
            DB::raw('"" as CALLERIDNUMBER'),
            DB::raw("DATE_FORMAT(stats_product.dob,'%Y-%m-%d') AS DATEOFBIRTH"),
            DB::raw('"NO" as CUSTAGEVERIFICATION'),
            DB::raw(" upper(language) AS LANGUAGE"),
            'stats_product.service_address1 AS SERVICEADDRESS1',
            'stats_product.service_address2 AS SERVICEADDRESS2',
            'stats_product.service_city AS SERVICECITY',
            'stats_product.service_state AS SERVICESTATE',
            'stats_product.service_zip AS SERVICEZIPCODE',
            DB::raw('"" as DWELLINGTYPE'),
            DB::raw('"" as OCCUPANCY'),
            'stats_product.billing_address1 AS MAILINGADDRESS1', 
            'stats_product.billing_address2 AS MAILINGADDRESS2',
            'stats_product.billing_city AS MAILINGCITY',
            'stats_product.billing_state  AS MAILINGSTATE',
            'stats_product.billing_zip AS MAILINGZIPCODE',            
            DB::raw('"" as SolarUtilityAccountNumber'),
            DB::raw('"" as SolarSAID'),
            DB::raw('"" as GASRATECLASS'),
            DB::raw('"" as ELECTRICITYRATECLASS'),
            DB::raw('"" as SolarMeterNumber'),
            DB::raw('"" as SolarRateClass'),
            DB::raw('"" as SolarElectricUtility'),
            DB::raw('"" as GASCONTRACTPRICE'),
            DB::raw('"" as ELECTRICITYCONTRACTPRICE'),
            DB::raw('"" as GREENGASCONTRACTPRICE'),
            DB::raw('"" as GREENELECTRICITYCONTRACTPRICE'),
            DB::raw('"" as SolarContractPrice'),
            'stats_product.interaction_created_at as CONTRACTSIGNED',
            'stats_product.product_term',
            'stats_product.product_term_type',
            DB::raw('"" as SolarContractTermYears'),
            DB::raw('"" as SolarContractTermMonths'),
            'stats_product.sales_agent_rep_id AS AGENT',
            'stats_product.sales_agent_rep_id AS REPNUMBER',
            DB::raw('"" as ENERGYMARKETERCOMPANY'),
            DB::raw("(CASE
                        WHEN stats_product.commodity NOT LIKE '%electric%' 
                        THEN
                            stats_product.rate_program_code
                        ELSE
                            ''
                        END) AS GASPROGRAMCODE"),
            DB::raw("(CASE
                        WHEN stats_product.commodity LIKE '%electric%' 
                        THEN
                            stats_product.rate_program_code
                        ELSE
                            ''
                        END) AS ELECTRICITYPROGRAMCODE"),
            DB::raw('"" as SolarProgramCode'),
            'stats_product.auth_relationship AS RELATIONSHIPTOACCOUNTHOLDER',
            'stats_product.channel AS SIGNUPMETHOD',
            DB::raw('"" as GASPRODUCTCODE'),
            DB::raw('"" as ELECTRICITYPRODUCTCODE'),
            DB::raw('"" as SolarProductCode'),
            DB::raw('"" as ITERATION'),
            DB::raw('"" as GASVOLUMEID'),
            DB::raw('"" as ELECTRICITYVOLUMEID'),
            DB::raw('"" as GASESTIMATEYEARLYVOLUME'),
            DB::raw('"" as ELECTRICITYESTIMATEDYEARLYVOLUME'),
            DB::raw('"" as SolarEstimatedYearlyVolume'),
            DB::raw('"" as SolarBillPeriodStartDate'),
            DB::raw('"" as SolarBillPeriodEndDate'),
            DB::raw('"" as NOAUTORENEWAL'),
            DB::raw('"" as BUDGETBILLING'),
            DB::raw('"" as CUSTOMERSIGNATURE'),
            DB::raw('"" as TPVSolarResult'),
            DB::raw("date_format(interaction_created_at,'%m/%d/%Y') AS TPVINBOUNDDATE"),
            DB::raw("date_format(interaction_created_at,'%H:%i') AS TPVINBOUNDTIME"),
            DB::raw('"" AS TPVOUTBOUNDDATE'),
            DB::raw('"" AS TPVOUTBOUNDTIME'),
            'stats_product.tpv_agent_name AS TPVEMPLOYEE',
            DB::raw('"Trusted TPV" as TPVCOMPANY'),
            DB::raw('"" as TPVCONTACTEDPERSON'),
            DB::raw('"" as TPVSolarProblem'),
            'stats_product.disposition_reason AS TPVCOMMENTS',
            'stats_product.confirmation_code AS TPVCONFIRMATIONID',
            DB::raw('"BOTH" as IMPORTCREATIONTYPE'),
            DB::raw('"" as PREFEREDMETHODOFCONTACT'),
            DB::raw('"" as CAMPAIGN'),
            DB::raw('"" as GASREQUESTEDSTARTDATE'),
            DB::raw('"" as ELECTRICITYREQUESTEDSTARTDATE'),
            DB::raw('"" as CONTRACTVERSIONCODE'),
            DB::raw('"" as SolarRequestCallBackDate'),
            DB::raw('"" as SolarRequestCallBackTime'),
            DB::raw('"" as COMMERCIALPRICING')
        )->leftJoin(
            'brand_utilities',
            function($join) {
                $join->on('stats_product.utility_id', 'brand_utilities.utility_id');
                $join->on('stats_product.brand_id', 'brand_utilities.brand_id');
            }
        )->where(
            'stats_product.market',
            $this->market
        )->where(
            'channel', 
            'TM'
        )->where(
            'stats_product.brand_id',
            $this->brandId[$this->env]
        )->whereDate(
            'stats_product.interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.interaction_created_at',
            '<=',
            $this->endDate
        )->where(
            function($query) {

                $query->where(

                    function($query) { // Completed and reviewed IVRs

                        $query->where(
                            'interaction_type',
                            'ivr_review'
                        )->whereIn(
                            'result',
                            ['Sale', 'No Sale']
                        );
                    }
            //     )->orWhere(

            //         function($query) { // In-progress/Incomplete IVRs

            //             $query->where(
            //                 'interaction_type',
            //                 'ivr_script'
            //             )->where(
            //                 'result',
            //                 'No Sale'
            //             )->where(
            //                 'disposition_reason',
            //                 'Incomplete IVR'
            //             );
            //         }

            //     )->orWhere(

            //         function($query) { // Completed IVRs waiting on to be reviewed

            //             $query->where(
            //                 'interaction_type',
            //                 'ivr_script'
            //             )->where(
            //                 'result',
            //                 'No Sale'
            //             )->where(
            //                 'disposition_reason',
            //                 'Pending Review'
            //             );
            //         }

                );
            }
      
        );
        
        if($this->option('vendor')) {
            $data = $data->where(
                'vendor_grp_id',
                $this->option('vendor')
            );
        }

        $data = $data->orderBy(
            'stats_product.interaction_created_at'
        );
        
        if($this->option('show-sql')) {
            $this->info("QUERY:");
            $this->info($data->toSql());
            $this->info("BINDINGS:");
            print_r($data->getBindings());
        }

        $data = $data->get();

        return $data;
    }

    /**
     * Set date range
     */
    private function setDateRange() 
    {
        // Set default report dates.
        $this->startDate = Carbon::yesterday("America/Chicago");
        $this->endDate = Carbon::yesterday("America/Chicago")->endOfDay();

        // Check for custom date range. Custom date range will only be used if both start and end dates are present.
        if ($this->option('start-date') && $this->option('end-date')) {
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));

            if ($this->startDate > $this->endDate) {
                $tmp = $this->startDate;
                $this->startDate = $this->endDate;
                $this->endDate = $tmp;
            }
            $this->info('Using custom date range.');
        }
    }

    /**
     * Set environment job is running against
     */
    private function setEnv() 
    {
        $this->env = 'prod';

        if ($this->option('env')) {
            if (
                strtolower($this->option('env')) == 'prod' ||
                strtolower($this->option('env')) == 'stage'
            ) {
                $this->env = strtolower($this->option('env'));
            } else {
                $this->error('Invalid --env value: ' . $this->option('env') . '. "prod" or "stage" expected.');
                exit -1;
            }
        }        
    }

    /**
     * Set the application mode
     */
    private function setMode() 
    {
        $this->mode = 'live';

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
    }

    /**
     * Set the market
     */
    private function setMarket() 
    {
        $this->market = 'residential';

        // Validate market.
        if ($this->option('market')) {
            if (
                strtolower($this->option('market')) == 'residential' // At this time, only Residential is a valid market
            ) {
                $this->market = strtolower($this->option('market'));
            } else {
                $this->error('Unrecognized --market: ' . $this->option('market'));
                return -1;
            }
        } 
    }

    /**
     * Calculates term years and months based on input values
     */
    private function getTermsYearsMonths($term, $termType)
    {
        $termObj = (object) [
            'years' => '',
            'months' => ''
        ];

        if(strtolower($termType) == 'year') {
            $termObj->years = $term;
        } else { // Assuming months

            // Populate years?
            $years = intval($term / 12);
            $termObj->years = ($years > 0 ? $years : '');

            // Populate months?
            $months = ($term % 12);
            $termObj->months = ($months > 0 ? $months : '');
        }

        return $termObj;
    }

    /**
     * Maps account identifiers. By default, account_number1 from the stats_product
     * table will be mapped to account_number in the returned object. Add code to handle
     * any utility/commodity/state combinations that requires different mapping. For example,
     * a utility may have both an account number and meter number, which will need to be mapped accordingly.
     */
    private function mapAccountIdentifiers($r)
    {
        // Start with default mapping
        $ident = $this->newIdentifiers($r->account_number1);

        // Override mapping for utilities that don't use default mapping.
        $state = strtolower($r->SERVICESTATE);
        $utility = strtolower($r->utility_label);
        $commodity = strtolower($r->commodity);

        // For CA - Pacific Gas & Electric - Gas, store account number as POD ID
        if($state == "ca" && $utility == "pg e" && $commodity == "natural gas") {
            $ident = $this->newIdentifiers($r->account_number1, $r->account_number2);
        }   

        // For MA - Eversource - Gas, store account number and meter number
        if($state == "ma" && $utility == "nstar" && $commodity == "natural gas") {
            $ident = $this->newIdentifiers($r->account_number1, '', $r->account_number2);
        }

        // For MA - Eversource Energy West - Electricity, store account number, and store service ref num as POD ID
        if($state == "ma" && $utility == "nstar-cwe" && $commodity == "electric") {
            $ident = $this->newIdentifiers($r->account_number1, $r->account_number2);
        }

        // For NJ - PSEG - Gas/Elec, store POD ID
        if($state == "nj" && $utility == "pseg") {
            $ident = $this->newIdentifiers('', $r->account_number1);
        }

        // For NY - Central Hudson (CHUD) - Gas/Elec, store both account number and POD ID
        if($state == "ny" && $utility == "chud") {
            $ident = $this->newIdentifiers($r->account_number1, $r->account_number1);
        }

        // For NY - NYSEG - Gas/Elec, store POD ID
        // For NY - Rochester Gas and Electric - Gas/Elec, store POD ID
        if($state == "ny" && ($utility == "nyseg" || $utility == "rge" )) {
            $ident = $this->newIdentifiers('', $r->account_number1);
        }

        // For OH - AEP - Columbus Southern Power - Electricity, store SDI as POD ID
        // For OH - AEP (OP) - Electricity, store SDI as POD ID
        // For OH - Cleveland Illuminating - Electricity, store Customer Number as POD ID
        if($state == "oh" && 
            (
                $utility == "csp" || 
                $utility == "ohp" ||
                $utility == "cei"
            ) && $commodity == "electric")
        { 
            $ident = $this->newIdentifiers('', $r->account_number1);
        }

        // For OH - Duke Energy - Gas/Elec, store both account number and POD ID
        if($state == "oh" && $utility == "duke") {
            $ident = $this->newIdentifiers($r->account_number1, $r->account_number2);
        }

        // For OH - Ohio Edison - Electricity, store customer number as POD ID
        // For OH - Toledo Edison - Electricity, store customer number as POD ID
        if($state == "oh" && ($utility == "ohioed" || $utility == "toled") && $commodity == "electric") {
            $ident = $this->newIdentifiers('', $r->account_number1);
        }

        // For PA - Philadelphia Gas Works - Gas, store both account number and serv ref ID
        if($state == "pa" && $utility == "pgw" && $commodity == "natural gas") {
            $ident = $this->newIdentifiers($r->account_number1. $r->account_number2);
        }

        return $ident;
    }

    /**
     * Returns an object containing fields for account identifiers.
     */
    private function newIdentifiers($acctNum = '', $podId = '', $meterNum = '')
    {
        return (object) [
            'account_number' => $acctNum,
            'pod_id' => $podId,
            'meter_number' => $meterNum
        ];
    }
}
