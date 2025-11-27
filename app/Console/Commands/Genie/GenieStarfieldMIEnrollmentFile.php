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
class GenieStarfieldMIEnrollmentFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Genie:Starfield:MI:EnrollmentFile 
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
    protected $description = 'Starfield MI emailed enrollment file';

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
        'live' => ['DXC_AutoEmails@dxc-inc.com', 'processing@genieretail.com','SalesSupport@genieretail.com'],
        'test' => ['dxcit@tpv.com', 'engineering@tpv.com']
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
        $fileName = 'TPV.COM_DailySales_3_' . $this->endDate->format('Ymd') . '.csv';

        // Modify filename if test mode
        if ($this->mode == 'test') {
            $fileName = 'TEST_' . $fileName;
        }

        // Data layout/File header for TXT enrollment file
        $csvHeader = [
            'LdcPricePlanCode', 'MarketerAgentID', 'Created', 'Revenue_Class', 'First_Name', 'Last_Name', 'AuthorizedContactPerson', 'Company_Name', 'Phone_Num', 'Phone_Num2',
            'Email_Address', 'Serv_Address1', 'Serv_Address2', 'Serv_City', 'Serv_County', 'Serv_State', 'Serv_Zip', 'Mail_Address1', 'Mail_Address2', 'Mail_City', 'Mail_State', 'Mail_Zip',
            'LDC_Account_Num', 'Source_Description', 'Confirmation_Type', 'Confirmation_Code', 'CustomerNameKey', 'Contact_Preference1', 'Contact_Preference1_Note',
            'Contact_Preference2', 'Contact_Preference2_Note', 'ProcessOn', 'TpvProviderID', 'EscoID'
        ];

        $csv = array(); // Houses formatted enrollment file data

        $data = StatsProduct::select(
            'event_id',
            'brand_id',
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
            'company_name',
            'market',
            'btn',
            'account_number1',
            'account_number2',
            'name_key',
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
            'product_green_percentage'
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

        $data = $data->where(
            'brand_id',
            $this->brandId
        )->where(
            'market',
            'residential'
        )->where(
            'channel',
            'DTD'
        )->where(
            'stats_product_type_id',
            1 // TPVs only
        )->where(
            'result',
            'sale'
        )->whereRaw(
            "service_state IN ('MI') AND product_utility_external_id IN ('DTEGAS', 'CONSGAS','SEMCOG','MICHGAS') AND ISNULL(contracts)"
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
                $jd = JsonDocument::where('document_type', 'genie-starfield-mi-enrollment')
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

            $row = [
                'LdcPricePlanCode' => $r->rate_program_code,
                'MarketerAgentID' => $r->sales_agent_rep_id,
                'Created' => $r->interaction_created_at->addHour()->format('m/d/Y H:i:s'), // interaction_created_at is stored in CST. Add one hour to convert to EST.
                'Revenue_Class' => $r->market,
                'First_Name' => trim($r->bill_first_name),
                'Last_Name' => trim($r->bill_last_name),
                'AuthorizedContactPerson' => trim($r->auth_first_name) . ' ' . trim($r->auth_last_name),
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
                'LDC_Account_Num' => (strtolower($r->product_utility_external_id) == 'pgw' ? $r->account_number1 . '-' . $r->account_number2 : $r->account_number1),
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
                'EscoID' => (strtolower($this->brand) == 'idt' ? '4' : (strtolower($this->brand) == 'res' ? '3' : (strtolower($this->brand) == 'townsquare' ? '2' : ''))) // TODO: TownSquare is a future brand
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

                $jd->document_type = 'genie-starfield-mi-enrollment';
                $jd->ref_id = $r->event_product_id;
                $jd->document = $jdData;

                $jd->save();
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
            $this->info('Emailing files...');

            $message = 'Attached is the enrollment file for ' . $this->startDate->format('m-d-Y');
            if ($this->startDate->format('Ymd') != $this->endDate->format('Ymd')) {
                $message .= ' through ' . $this->endDate->format('m-d-Y');
            }
            $message .= '.';

            $this->sendEmail($message, $this->distroList[$this->mode], [public_path('tmp/' . $fileName)]);

            unlink(public_path('tmp/' . $fileName));
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

        $subjectBrand = ($this->brand == 'IDT' ? 'IDT Energy' : ($this->brand == 'RES' ? 'Residents Energy' : '???'));

        // Build email subject
        if ('production' != config('app.env')) {
            $subject = $subjectBrand . ' - MI - D2D Email File Generation - Starfield (' . config('app.env') . ') '
                . Carbon::now('America/Chicago');
        } else {
            $subject = $subjectBrand . ' - MI - D2D Email File Generation - Starfield '
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
