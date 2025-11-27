<?php

namespace App\Console\Commands\Genie;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\JsonDocument;
use App\Models\StatsProduct;

/**
 * Starfield enrollment file.
 * 
 * This replaces the following DXC jobs:
 *   RESIDENTS_ENERGY_STARFIELD_EMAIL_ENROLLMENT_FILE.PRG
 */
class GenieStarfieldEnrollmentFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Genie:StarfieldEnrollmentFile 
        {--mode=       : Optional. Valid values are "live" and "test". "live" is used by default. Setting this determines which GUIDs, amount other things, are used for database queries.} 
        {--no-email    : Optional. If provided, sales totals email will not be sent.} 
        {--show-sql    : Optional. If provided, SQL statement for queries will be output to console.} 
        {--start-date= : Optional. Start date for data query. Must be paired with --end-date or it will be ignored. If omitted, current date minutes 30 days is used.} 
        {--end-date=   : Optional. End date for data query. Must be paired with --start-date or it will be ignored.}
        {--resubmit    : Optional. This should only be used if data needs to be resubmitted. This option will only work if --start-date and --end-date are also provided.}
        {--no-json-doc : Optional. This should only be used for tests. If provided, this prevents the program from writing the json_document log record, and allowing this record to be picked up again.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starfield emailed enrollment file';

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
     * Brand.
     * 
     * @var string
     */
    protected $brand = '';

    /**
     * Brand ID.
     * 
     * @var string
     */
    protected $brandId = '';

    /**
     * Distribution list.
     * 
     * @var array
     */
    protected $distroList = [
        'live' => ['DXC_AutoEmails@dxc-inc.com', 'tkrapf@townsquareenergy.com','SalesSupport@genieretail.com','accountmanagers@answernet.com','curt.cadwell@answernet.com', 'autumn.siegel@answernet.com'],
        'test' => ['curt@tpv.com', 'curt.cadwell@answernet.com']
        //'test' => ['dxcit@tpv.com', 'engineering@tpv.com']
    ];

    protected $salesTotals = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    //HG#D3dPEJNqX$t888a61
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Set default report dates.
        $this->startDate  = Carbon::yesterday("America/Chicago")->subDay(30)->startOfDay();
        $this->endDate    = Carbon::yesterday("America/Chicago")->endOfDay();
        $this->oldestDate = Carbon::parse("2023-10-17", "America/Chicago");

        $this->brand = 'RES'; // This file is for Residents. Leaving this var in case IDTE needs to be an option down the road.

        // Validate mode.
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

        // Check for custom date range. Custom date range will only be used if both start and end dates are present.
        if ($this->option('start-date') && $this->option('end-date')) {
            $this->startDate = Carbon::parse($this->option('start-date'))->startOfDay();
            $this->endDate = Carbon::parse($this->option('end-date'))->endOfDay();

            if ($this->startDate > $this->endDate) {
                $tmp = $this->startDate;
                $this->startDate = $this->endDate;
                $this->endDate = $tmp;
            }
            $this->info('Using custom date range.');
        }

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date:   ' . $this->endDate);
        $this->info('Mode:       ' . $this->mode);

        if($this->option('resubmit')) {
            if(!$this->option('start-date') || !$this->option('end-date')) {
                $this->error('--resubmit option can only be used when --start-date and --end-date options are also present');
                exit -1;
            }
        }

        // Validate brand
        // $this->brand = strtoupper($this->option('brand'));

        switch ($this->brand) {
            case 'IDT': {
                    $this->brandId = '77c6df91-8384-45a5-8a17-3d6c67ed78bf';
                    break;
                }
            case 'RES': {
                    $this->brandId = '0e80edba-dd3f-4761-9b67-3d4a15914adb';
                    break;
                }
            default: {
                    $this->error('Unrecognized brand \'' . $this->brand . '\'. Program terminated.');
                    return -1;
                }
        }

        $this->info('Brand:      (' . $this->brand . ') ' . $this->brandId . "\n");

        // Build file names, using that start date for the file/reporting dates
        $fileName = 'TPV.COM_DailySales_2_' . $this->endDate->format('Ymd') . '.csv';

        // Modify filename if test mode
        if ($this->mode == 'test') {
            $fileName = 'TEST_' . $fileName;
        }

        // Data layout/File header for TXT enrollment file
        $csvHeader = [
            'LdcPricePlanCode', 'MarketerAgentID', 'Created', 'Revenue_Class', 'First_Name', 'Last_Name', 'AuthorizedContactPerson', 'Company_Name', 'Phone_Num', 'Phone_Num2',
            'Email_Address', 'Serv_Address1', 'Serv_Address2', 'Serv_City', 'Serv_County', 'Serv_State', 'Serv_Zip', 'Mail_Address1', 'Mail_Address2', 'Mail_City', 'Mail_State', 'Mail_Zip',
            'LDC_Account_Num', 'Source_Description', 'Confirmation_Type', 'Confirmation_Code', 'CustomerNameKey', 'Contact_Preference1', 'Contact_Preference1_Note',
            'Contact_Preference2', 'Contact_Preference2_Note', 'ProcessOn', 'TpvProviderID', 'EscoID','Language','ContactAuthorization'
        ];

        $csv = array(); // Houses formatted enrollment file data

        $data = StatsProduct::select(
            'stats_product.event_id',
            'stats_product.brand_id',
            'stats_product.interaction_created_at',
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
            'stats_product.company_name',
            'stats_product.market',
            'stats_product.btn',
            'stats_product.account_number1',
            'stats_product.account_number2',
            'stats_product.name_key',
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
            'stats_product.product_green_percentage',
            'utilities.ldc_code'
        )->leftJoin(
            'utilities',
            'stats_product.utility_id',
            'utilities.id'
        )->where(
            'interaction_created_at',
            '>=',
            $this->startDate
        )->where(
            'interaction_created_at',
            '<=',
            $this->endDate
        // )->where(     // removed need to add town square
        //     'brand_id',
        //     $this->brandId
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
            'market',
            ['residential','commercial']
        // )->where(
        //     'channel',
        //     'DTD'            
        )->where(
            'stats_product_type_id',
            1 // TPVs only
        )->where(
            'result',
            'sale'
        )->whereRaw( // For MI and OH, include Retail and TM channel; only include DTD records that have contracts
                     // Also for the state of IN all records for Residents Energy 
                     // Also all records for Town Square brand - PROD - '872c2c64-9d19-4087-a35a-fb75a48a1d0f' - staging.tpvhub.com - 'dda4ac42-c7b8-4796-8230-9668ad64f261'
            "(
                 (brand_id = '0e80edba-dd3f-4761-9b67-3d4a15914adb' AND service_state IN ('PA') AND product_utility_external_id IN ('PGW')) 
                 OR (brand_id = '0e80edba-dd3f-4761-9b67-3d4a15914adb' AND service_state IN ('IL') AND product_utility_external_id IN ('PEOPGAS', 'NSHORE')) 
                 OR (brand_id = '0e80edba-dd3f-4761-9b67-3d4a15914adb' AND service_state IN ('OH') AND product_utility_external_id IN ('VEDO', 'DEOHG', 'DPL') AND (channel IN ('TM', 'Retail') OR (channel = 'DTD' AND NOT ISNULL(contracts))))
                 OR (brand_id = '0e80edba-dd3f-4761-9b67-3d4a15914adb' AND service_state IN ('MI') AND product_utility_external_id IN ('DTEGAS', 'CONSGAS','SEMCOG','MICHGAS') AND (channel IN ('TM', 'Retail') OR (channel = 'DTD' AND NOT ISNULL(contracts))))
                 OR (brand_id = '0e80edba-dd3f-4761-9b67-3d4a15914adb' AND service_state IN ('IN')) 
                 OR (brand_id = '77c6df91-8384-45a5-8a17-3d6c67ed78bf' AND service_state IN ('PA') AND product_utility_external_id IN ('PGW')) 
                 OR (brand_id = '77c6df91-8384-45a5-8a17-3d6c67ed78bf' AND service_state IN ('IL') AND product_utility_external_id IN ('PEOPGAS', 'NSHORE')) 
                 OR (brand_id = '77c6df91-8384-45a5-8a17-3d6c67ed78bf' AND service_state IN ('OH') AND product_utility_external_id IN ('VEDO', 'DEOHG', 'DPL') AND (channel IN ('TM', 'Retail') OR (channel = 'DTD' AND NOT ISNULL(contracts))))
                 OR (brand_id = '77c6df91-8384-45a5-8a17-3d6c67ed78bf' AND service_state IN ('MI') AND product_utility_external_id IN ('DTEGAS', 'CONSGAS') AND (channel IN ('TM', 'Retail') OR (channel = 'DTD' AND NOT ISNULL(contracts))))
                 OR (brand_id = '77c6df91-8384-45a5-8a17-3d6c67ed78bf' AND service_state IN ('IN')) 
                 OR (brand_id IN ('872c2c64-9d19-4087-a35a-fb75a48a1d0f','dda4ac42-c7b8-4796-8230-9668ad64f261'))
                 )"
              

        )->whereNotIn( // Exclude agent
            DB::raw("LEFT(sales_agent_rep_id,3)"),
            ['999']
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

            $message = 'There were no records to send for ' . $this->startDate->format('m-d-Y');
            if ($this->startDate->format('Ymd') != $this->endDate->format('Ymd')) {
                $message .= ' through ' . $this->endDate->format('m-d-Y');
            }
            $message .= '.';

            $this->sendEmail($message, $this->distroList[$this->mode]);

            return 0;
        }

        // Format and populate data for enrollment file.
        $this->info("Formatting data for enrollment file...");

        // Use transactions. This is so that we can rollback writes to json_documents if the job
        // errors out for any reason. The records can then be picked up on the next run.
        DB::beginTransaction();

        foreach ($data as $r) {

            // Check if this record was already included in an enrollment file
            if(!$this->option('resubmit')) {
                $jd = JsonDocument::where('document_type', 'genie-starfield-enrollment')
                    ->where('ref_id', $r->event_product_id)
                    ->first(); // We only care if ANY records exists, so no need to get all of them

                if($jd) {
                    $this->info("{$r->confirmation_code}::{$r->event_product_id} Has already been submitted. Skipping");
                    continue;
                }
            }

            // Format account number. If DUKE or NICOR, grab the left 10 digits only
            $accountNumber = $r->account_number1;
            // if (
            //     strtoupper($r->product_utility_external_id) == 'DUKE'
            // ) {
            //     $accountNumber = substr($accountNumber, 0, 10);
            // }
            $escoID = ''; // (strtolower($this->brand) == 'idt' ? '4' : (strtolower($this->brand) == 'res' ? '3' : (strtolower($this->brand) == 'townsquare Energy' ? '2' : ''))) // TODO: TownSquare is a future brand
            switch ($r->brand_id) {
                case '0e80edba-dd3f-4761-9b67-3d4a15914adb':   // residents energy
                    $escoID = '3';
                    break;
                case '872c2c64-9d19-4087-a35a-fb75a48a1d0f':  // Townsquare Energy (PROD)
                    $escoID = '2';
                    break;
                case 'dda4ac42-c7b8-4796-8230-9668ad64f261':   // Townsquare Energy (staging.tpvhub.com)
                    $escoID = '2';
                    break;
                default:  // Must be IDT
                    $escoID = '5';
            }
            switch (strtolower($r->ldc_code)) {
                case 'pgw':  
                    $LDC_Account_Num = $r->account_number1 . '-' . $r->account_number2;
                    break;
                case 'wmeco':  
                    $LDC_Account_Num = $r->account_number1 . '-' . $r->account_number2;
                    break;
                case 'nu-psnh':  
                    $LDC_Account_Num = $r->account_number1 . '-' . $r->account_number2;
                    break;
                default: 
                    $LDC_Account_Num = $r->account_number1;
            }
            // Contact consent custom field
            if ($r->custom_fields <> null) {
                $customFields = json_decode($r->custom_fields);
                $contactConsent = '';
                foreach ($customFields as $field) {
                    if ($field->name === 'contact_consent') {
                        $contactConsent = $field->value;
                        break;
                    }
                }
            } else {
                $contactConsent = 'null'; 
            }
            if($r->service_state == 'MI' || $r->service_state == 'IN' || $r->service_state == 'RI' || $r->service_state == 'NH') {  // force contact consent to yes per Genie
                $contactConsent = 'Yes';
            }
            $row = [
                'LdcPricePlanCode' => $r->rate_program_code,
                'MarketerAgentID' => $r->sales_agent_rep_id,
                'Created' => $r->interaction_created_at->addHour()->format('m/d/Y H:i:s'), // interaction_created_at is stored in CST. Add one hour to convert to EST.
                'Revenue_Class' => $r->market,
                'First_Name' => trim(trim($r->bill_first_name) . ' ' . trim($r->bill_middle_name)),
                'Last_Name' => trim($r->bill_last_name),
                'AuthorizedContactPerson' => $r->auth_first_name . (empty(trim($r->auth_middle_name)) ? ' ' : ' ' . trim($r->auth_middle_name) . ' ' ) . $r->auth_last_name,
                'Company_Name' => trim($r->company_name),
                'Phone_Num' => substr(trim($r->btn), 2),
                'Phone_Num2' => '',                         // always blank
                'Email_Address' => trim($r->email_address),
                'Serv_Address1' => trim($r->service_address1),
                'Serv_Address2' => trim($r->service_address2),
                'Serv_City' => trim($r->service_city),
                'Serv_County' => '',                        // always blank
                'Serv_State' => trim($r->service_state),
                'Serv_Zip' => trim($r->service_zip),
                'Mail_Address1' => trim($r->billing_address1),
                'Mail_Address2' => trim($r->billing_address2),
                'Mail_City' => trim($r->billing_city),
                'Mail_State' => trim($r->billing_state),
                'Mail_Zip' => trim($r->billing_zip),
                'LDC_Account_Num' => $LDC_Account_Num,
                'Source_Description' => (strtoupper($r->channel) == 'TM' ? 'Telemarketer' : (strtoupper($r->channel) == 'DTD' ? 'Door to Door' : (strtoupper($r->channel) == 'RETAIL' ? 'TableTop' : $r->channel))),
                'Confirmation_Type' => 'TPV',
                'Confirmation_Code' => $r->confirmation_code,
                'CustomerNameKey' => strtoupper($r->name_key),
                'Contact_Preference1' => 'USPS MAIL',
                'Contact_Preference1_Note' => trim(trim($r->billing_address1) . ' ' . trim($r->billing_address2)) . ', ' . trim($r->billing_city) . ' ' . trim($r->billing_zip), // billing_state is intentionally left out. The stacked trim is used to get rid of the extra space if bill addr2 value is blank.
                'Contact_Preference2' => '',                // always blank
                'Contact_Preference2_Note' => '',           // always blank
                'ProcessOn' => '',
                'TpvProviderID' => '13',                    // Always 13. Identifies TPV.com 
                'EscoID' => $escoID,
                'Language' => $r->language,
                'ContactAuthorization' => $contactConsent
            ];

            array_push($csv, $row);

            // Write JSON record, documenting what file this record was included in
            if(!$this->option('no-json-doc')) {
                $jdData = [
                    'Date' => Carbon::now("America/Chicago")->format("Y-m-d H:i:s"),
                    'InteractionDate' => $r->interaction_created_at->timezone('America/Chicago')->format('m/d/Y'),
                    'ConfirmationCode' => $r->confirmation_code,
                    'Commodity' => $r->commodity,
                    'AccountNumber' => $accountNumber,
                    'Filename' => $fileName
                ];

                $jd = new JsonDocument();

                $jd->document_type = 'genie-starfield-enrollment';
                $jd->ref_id = $r->event_product_id;
                $jd->document = $jdData;

                $jd->save();
            }

            if (!empty($r->btn) && !empty($r->vendor_code)) {
                if (!isset($this->salesTotals[$r->brand_name])) {
                    $this->salesTotals[$r->brand_name] = [
                        'sales' => 0,
                        'vendors' => array()
                    ];
                }

                $this->salesTotals[$r->brand_name]['sales']++;

                if (!isset($this->salesTotals[$r->brand_name]['vendors'][$r->vendor_code])) {
                    $this->salesTotals[$r->brand_name]['vendors'][$r->vendor_code] = [
                        'name' => $r->vendor_name,
                        'sales' => 0
                    ];
                }

                $this->salesTotals[$r->brand_name]['vendors'][$r->vendor_code]['sales']++;
            }
        }

        // Write CSV file
        $this->info("Writing CSV file...");
        $file = fopen(public_path('tmp/' . $fileName), 'w');

        // Header Row
        fputcsv($file, $csvHeader);

        // Data
        foreach ($csv as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        DB::commit();

        // Email the files
        if (!$this->option('no-email')) {
            $this->info('Preparing email with summary and file attachment...');

            $message = '<p>Attached is the enrollment file for ' . $this->startDate->format('m-d-Y');
            if ($this->startDate->format('Ymd') != $this->endDate->format('Ymd')) {
                $message .= ' through ' . $this->endDate->format('m-d-Y');
            }
            $message .= '.</p>';

            $message .= $this->getSummaryMessage();

            $this->sendEmail($message, $this->distroList[$this->mode], [public_path('tmp/' . $fileName)]);

            unlink(public_path('tmp/' . $fileName));
        }
    }

    /**
     * Get the summary message for TPV.
     *
     * @return string The summary message.
     */
    private function getSummaryMessage(): string
    {
        $message = '<p>TPV Summary:</p>';

        $message .= '<ul>';
        foreach ($this->salesTotals as $brandKey => $brandValue) {
            $message .= '<li><strong>' . $brandKey . ': ' . $brandValue['sales'] . '</strong></li>'
                . '<ul>';

            foreach ($brandValue['vendors'] as $vendorKey => $vendorValue) {
                $message .= '<li><strong>' . $vendorKey . ' - ' . $vendorValue['name'] . ': ' . $vendorValue['sales'] . '</strong></li>';
            }

            $message .= '</ul>';
        }
        $message .= '</ul>';

        return $message;
    }

    /**
     * Sends and email.
     *
     * @param string $message - Email body.
     * @param array  $distro  - Distribution list.
     * @param array  $files   - Optional. List of files to attach.
     *
     * @return array - Status message
     */
    public function sendEmail(string $message, array $distro, array $files = array())
    {
        $uploadStatus = [];
        $email_address = $distro;

        $subjectBrand = ($this->brand == 'IDT' ? 'IDT Energy' : ($this->brand == 'RES' ? 'Residents Energy' : '???'));

        // Build email subject
        if ('production' != config('app.env')) {
            $subject = $subjectBrand . ' - IDT - TownSquare Energy - Starfield File Generation (' . config('app.env') . ') '
                . Carbon::now('America/Chicago');
        } else {
            $subject = $subjectBrand . ' - Starfield File Generation '
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
}
