<?php

namespace App\Console\Commands\Constellation;

use Carbon\Carbon;
use App\Helpers\FtpHelper;
use App\Models\StatsProduct;
use App\Traits\ExportableTrait;

use Illuminate\Console\Command;
use App\Traits\DeliverableTrait;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ResTmAllDataDetailReport extends Command
{
    use DeliverableTrait;
    use ExportableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'constellation:reports:restm-all-data-detail:email {--mode=} {--env=} {--no-email} {--show-sql} {--weekly} {--start-date=} {--end-date=} {--vendor=} {--email-to=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RES TM All Data Report for Constellation';

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
     * Application mode. 'live' or 'test'. Use to branch logic if something has to be ignored or done differently when running in test mode.
     * 
     * @var string
     */
    protected $mode = '';

    /**
     * Application env. Used to switch between environment specific data, such as which brand ID is used in queries.
     */
    protected $env = 'prod';

    /**
     * Report distribution list
     * 
     * @var array
     */
    protected $distroList = [];

    /**
     * Brand.
     * 
     * @var string
     */
    protected $brand = 'Constellation';

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
        'prod' => 'fd9470af-5045-4ee6-82e0-88d608c110dc',
        'stage' => 'fd9470af-5045-4ee6-82e0-88d608c110dc'
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
        $this->setDateRange();
        $this->setDistroList();

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date:   ' . $this->endDate);

        $this->info('Brand:      (' . $this->brand . ') ' . $this->brandId[$this->env] . "\n");

        // Build file names, using that start date for the file/reporting dates
        if($this->option('vendor') == '86'){
            $fileName = sprintf('RES TM All Data Global %s.xls', $this->startDate->format('Y_m_d'));
        }else if($this->option('vendor') == '44'){
            $fileName = sprintf('RES TM All Data Protocall %s.xls',  $this->startDate->format('Y_m_d'));
        }else{
            $fileName = sprintf(($this->option('weekly') ? 'WEEKLY ' : '') . 'RES TM All Data %s.xls', ($this->option('weekly') ? Carbon::now("America/Chicago")->format('Y_m_d') : $this->startDate->format('Y_m_d')));
        }
        

        // Data layout/File header for report
        $csvHeader = [
            'Date',
            'Confirmation_Code',
            'Status_txt',
            'Status_id',
            'Reason',
            'Channel',
            'Partner_name',
            'Partner_id',
            'Office_id',
            'Source',
            'VL_rep_id',
            'CNE_rep_id',
            'CNE_rep_first name',
            'CNE_rep_last name',
            'Language',
            'Sales_state',
            'Dual_Fuel',
            'Commodity',
            'Home_Svc_name',
            'Include_on_BGEBill',
            'HS_Plan_id',
            'Enrollment_id',
            'Response_id',
            'Btn',
            'Email',
            'Updated_email',
            'Auth_fname',
            'Auth_mi',
            'Auth_lname',
            'Relationship',
            'Bill_fname',
            'Bill_mi',
            'Bill_lname',
            'Billing_address',
            'Billing_city',
            'Billing_state',
            'Billing_zip',
            'Addr1',
            'Addr2',
            'City',
            'State',
            'Zip',
            'Name_key',
            'Acct_num',
            'Acct_or_pod',
            'Meter_number',
            'Utility',
            'Ldc_code',
            'Rate',
            'Unit_measurement',
            'Term',
            'ETF_fee',
            'Plan_id',
            'Plan Name',
            'Promo Code',
            'Call_time',
            'Ib_ct',
            'Ob_ct',
            'Form_name',
            'NoSale_Alert',
            'Internal_NoSale_Alert',
            'Home_Svc_Manager_Alert',
        ];

        $csv = array(); // Houses formatted report data

        $this->info('Retrieving data...');

        $data = $this->getData();

        $this->info(count($data) . ' Record(s) found.');

        if($this->option('vendor') && count($data) > 0) {
            $this->vendorName = $data[0]->vendor_name;
        }

        // Format and populate data for report file.
        $this->info("Formatting data for Daily Calls Report...");

        foreach ($data as $r) {

            $customFields = json_decode($r->custom_fields);

            $isDual ="";
            if(!empty($customFields)) {
                $apiCustResponse = $this->getCustomFieldValue('api_customer_response', $customFields);
                if (!empty($apiCustResponse)) { 
                    $cr = json_decode($apiCustResponse);

                    $isDual = ($cr->SignUpType == 'Dual') ? 'Yes' : 'No';
                }

                $updateEmail = $this->getCustomFieldValue('email_up', $customFields);
                    
                $relationship = $this->getCustomFieldValue('relationship', $customFields);
            }

            $cneFirstName = '';
            $cneLastName = '';
            if(!empty($r->sales_agent_name)) {
                $fullname = explode(" ", $r->sales_agent_name);
                $cneFirstName = $fullname[0];
                $cneLastName = $fullname[1];
            }

            $result = ($r->result == 'Sale' ? 'good sale' : 'no sale');

            $language = '';
            if($r->language == 'English'){
                $language = 'ENG';
            }else if($r->language == 'Spanish'){
                $language = 'SPA';
            }

            $titleString = $r->script_name;
            $channel = '';
            if (str_contains($titleString, 'Inbound')) {
                $channel = 'RES IB ' . $language;
            }else if(str_contains($titleString, 'Outbound')){
                $channel = 'RES OB ' . $language;
            }else if(str_contains($titleString, 'Renewal')){
                $channel = 'RENEWAL ' . $language;
            }

            $planId = trim($r->rate_program_code, 'P');
            $commodity = ($r->commodity == 'Electric' ? 'Electric' : 'Gas');



            $row = [
                'Date'              => $r->interaction_created_at->format('m/d/Y H:i:s'),
                'Confirmation_Code' => $r->confirmation_code,
                'Status_txt'        => $result,
                'Status_id'         => $r->disposition_label,
                'Reason'            => $r->disposition_reason,
                'Channel'           => $channel,
                'Partner_name'      => $r->vendor_name,
                'Partner_id'        => $r->grp_id,
                'Office_id'         => $r->office_name,
                'Source'            => $r->source,
                'VL_rep_id'         => $r->tpv_agent_label,
                'CNE_rep_id'        => $r->sales_agent_rep_id,
                'CNE_rep_first name'=> $cneFirstName,
                'CNE_rep_last name' => $cneLastName,
                'Language'          => $r->language,
                'Sales_state'       => $r->service_state,
                'Dual_Fuel'         => $isDual,
                'Commodity'         => $commodity,
                'Home_Svc_name'     => '',
                'Include_on_BGEBill'=> '',
                'HS_Plan_id'        => '',
                'Enrollment_id'     => '',
                'Response_id'       => $r->external_id,
                'Btn'               => substr($r->btn, 2),
                'Email'             => $r->email_address,
                'Updated_email'     => $updateEmail,
                'Auth_fname'        => $r->auth_first_name,
                'Auth_mi'           => $r->auth_middle_name,
                'Auth_lname'        => $r->auth_last_name,
                'Relationship'      => $relationship,
                'Bill_fname'        => $r->bill_first_name,
                'Bill_mi'           => $r->bill_middle_name,
                'Bill_lname'        => $r->bill_last_name,
                'Billing_address'   => $r->billing_address1,
                'Billing_city'      => $r->billing_city,
                'Billing_state'     => $r->billing_state,
                'Billing_zip'       => $r->billing_zip,
                'Addr1'             => $r->service_address1,
                'Addr2'             => $r->service_address2,
                'City'              => $r->service_city,
                'State'             => $r->service_state,
                'Zip'               => $r->service_zip,
                'Name_key'          => $r->name_key,
                'Acct_num'          => $r->account_number1,
                'Acct_or_pod'       => $r->account_type,
                'Meter_number'      => $r->account_number2,
                'Utility'           => $r->product_utility_name,
                'Ldc_code'          => $r->utility_label,
                'Rate'              => $r->product_rate_amount,
                'Unit_measurement'  => ((!empty($r->product_rate_amount_currency) && !empty($r->rate_uom)) ?  $r->product_rate_amount_currency. " per " .$r->rate_uom : " "),
                'Term'              => $r->product_term,
                'ETF_fee'           => $r->product_cancellation_fee,
                'Plan_id'           => $planId,
                'Plan Name'         => $r->product_name,
                'Promo Code'        => $r->rate_promo_code,
                'Call_time'         => $r->interaction_time,
                'Ib_ct'             => $r->interaction_time,
                'Ob_ct'             => '',
                'Form_name'         => $r->script_name,
                'NoSale_Alert'      => '',
                'Internal_NoSale_Alert'=> '',
                'Home_Svc_Manager_Alert'=> '',
            ];

            array_push($csv, $row);
        }

        // Write Xls file

        $this->writeXlsFile($csv, public_path('tmp/' . $fileName), $csvHeader);

        // Email the files
        if (!$this->option('no-email')) {

            $message = 'Attached is the All Data Detail Report for ' . $this->startDate->format('m-d-Y');
            if ($this->startDate->format('Ymd') != $this->endDate->format('Ymd')) {
                $message .= ' through ' . $this->endDate->format('m-d-Y');
            }
            $message .= '.';

            try {
                $this->info('Sending e-mail notification with file attachment...');

                $this->sendGenericEmail([
                    'to' => $this->distroList,
                    'subject' => $this->getEmailSubject(),
                    'body' => $message,
                    'attachments' => [public_path('tmp/' . $fileName)]
                ]);
            } catch (\Exception $e) {
                $this->error('Email exception:');
                $this->error($e->getMessage());
            }

            unlink(public_path('tmp/' . $fileName));
        }
    }

    /**
     * Retrieve TPV data from database
     */
    private function getData() 
    {
        $data = StatsProduct::select(
            'utility_account_types.account_type',
            'stats_product.custom_fields',
            'events.external_id',
            'scripts.title AS script_name', 
            'vendors.grp_id',
            'stats_product.interaction_created_at',
            'stats_product.confirmation_code',
            'stats_product.interaction_time',
            'stats_product.sales_agent_rep_id',
            'stats_product.btn',
            'stats_product.disposition_reason',
            'stats_product.bill_first_name',
            'stats_product.bill_middle_name',
            'stats_product.bill_last_name',
            'stats_product.language',
            'stats_product.billing_address1',
            'stats_product.billing_city',
            'stats_product.billing_state',
            'stats_product.billing_zip',
            'stats_product.email_address',
            'stats_product.product_utility_name',
            'stats_product.market',
            'stats_product.commodity',
            'stats_product.account_number1',
            'stats_product.name_key',
            'stats_product.service_address1',
            'stats_product.service_address2',
            'stats_product.service_city',
            'stats_product.service_state',
            'stats_product.service_zip',
            'stats_product.service_county',
            'stats_product.rate_program_code',
            'stats_product.result',
            'stats_product.vendor_name',
            'stats_product.auth_first_name',
            'stats_product.auth_middle_name',
            'stats_product.auth_last_name',
            'stats_product.disposition_label',
            'stats_product.office_name',
            'stats_product.source',
            'stats_product.tpv_agent_label',
            'stats_product.event_product_id',
            'stats_product.sales_agent_name',
            'stats_product.service_address2',
            'stats_product.account_number2',
            'stats_product.product_rate_amount',
            'stats_product.product_rate_amount_currency',
            'stats_product.rate_uom',
            'stats_product.product_term',
            'stats_product.product_cancellation_fee',
            'stats_product.product_name',
            'stats_product.rate_promo_code',
            'brand_utilities.utility_label'
        )
        ->join('events', 'stats_product.event_id', 'events.id')
        ->join('scripts', 'events.script_id', 'scripts.id')
        ->join('brand_utilities', function($join) {
            $join->on('stats_product.utility_id', 'brand_utilities.utility_id');
            $join->on('stats_product.brand_id', 'brand_utilities.brand_id');
        })
        ->leftJoin('vendors', function($join) { // In stats product, venodr_grp_id is numeric. Until that's fixed, pull the grp_id value directly from vendors table.
            $join->on('stats_product.brand_id', 'vendors.brand_id');
            $join->on('stats_product.vendor_id', 'vendors.vendor_id');
        })            
        ->leftJoin('event_product_identifiers', function($join) {
            $join->on('stats_product.event_product_id', 'event_product_identifiers.event_product_id');
            $join->on('stats_product.account_number1', 'event_product_identifiers.identifier');
        })
        ->leftJoin('utility_account_types', 'event_product_identifiers.utility_account_type_id', 'utility_account_types.id')
        ->whereDate(
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
        )->whereIn(
            'stats_product.result', ['Sale', 'No Sale']
        )
        ->whereNull(
            'event_product_identifiers.deleted_at'
        )->orderBy(
            'interaction_created_at'
        );

        if($this->option('vendor')) {
            $data = $data->where(
                'vendor_grp_id',
                $this->option('vendor')
            );
        }

        // Display SQL query.        
        if ($this->option('show-sql')) {
            $queryStr = str_replace(array('?'), array('\'%s\''), $data->toSql());
            $queryStr = vsprintf($queryStr, $data->getBindings());

            $this->info("");
            $this->info('QUERY:');
            $this->info($queryStr);
            $this->info("");
        }
        
        $data = $data->get();

        return $data;
    }

    /**
     * Creates and returns an email subject line string
     */
    private function getEmailSubject() 
    {
        return $this-> brand . ' - ' . ($this->option('weekly') ? 'WEEKLY ' : '') . 'RES TM All Data Report' . (!empty($this->vendorName) ? ' (' . $this->vendorName . ')' : '')
        . (config('app.env') != 'production' ? ' (' . config('app.env') . ') ' : '') 
        . ' ' . Carbon::now("America/Chicago")->format('Y-m-d');
    }    

    /**
     * Set date range
     */
    private function setDateRange() 
    {
        // Set default report dates.
        if($this->option('weekly')){
            $this->startDate = Carbon::now("America/Chicago")->previous(Carbon::FRIDAY);
            $this->endDate = Carbon::yesterday("America/Chicago")->endOfDay();
        }else{
            $this->startDate = Carbon::yesterday("America/Chicago");
            $this->endDate = Carbon::yesterday("America/Chicago")->endOfDay();
        }


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
    private function setDistroList() 
    {
        $this->distroList = ['engineering@tpv.com']; // Default, in case distro list was not provided.
        
        if ($this->option('email-to')) {
            $this->distroList = $this->option('email-to');
        } 
    }

    /**
     * Retrieve FTP settings from provider_integrations table
     */
    private function getFtpSettings(): ?array {

        return FtpHelper::getSettings(
            $this->brandId[$this->env],
            43,
            1,
            (config('app.env') === 'production' ? 1 : 2)
        );
    }

    /**
     * @param string $name
     * @param string $path
     * @return bool
     */
    private function ftpWithCurl(string $name, string $path): bool
    {
        $authDetails = $this->ftpSettings['username'].':'.$this->ftpSettings['password'];
        $url = 'ftp://'.$this->ftpSettings['host'].'/TPV/'.$name;
        $ch = curl_init();
        $fp = fopen($path, 'r');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $authDetails);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($path));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_exec ($ch);
        $error_no = curl_errno($ch);
        curl_close ($ch);
        if ($error_no == 0) {
            return true;
        } else {
           return false;
        }
    }

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

    protected function writeXlsFile($csv, $fileName, $csvHeader) {

        try {
            $headers = $csvHeader;

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet()->setTitle('Sheet1');
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($csv, null, 'A2');
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $writer->save($fileName);
        } catch (\Exception $e) {
            // TODO: Handle
        }

        // TODO: Return a result
    }
    

    
}
