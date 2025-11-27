<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\StatsProduct;

/**
 * This replaces the following DXC jobs:
 *   IDT_ENERGY_MD_D2D_EMAIL_FILE_GENERATION.PRG
 *   IDT_ENERGY_OH_D2D_EMAIL_FILE_GENERATION.PRG
 *   RESIDENTS_ENERGY_DE_D2D_EMAIL_FILE_GENERATION.PRG
 *   RESIDENTS_ENERGY_MI_D2D_EMAIL_FILE_GENERATION.PRG
 *   RESIDENTS_ENERGY_OH_D2D_EMAIL_FILE_GENERATION.PRG
 */
class GenieEmailEnrollmentFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Genie:EmailEnrollmentFile {--mode=} {--brand=} {--state=} {--start-date=} {--end-date=} {--show-sql} {--file-num=} {--no-email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emailed enrollment files used for IDT/REs contract states';

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
    protected $mode = 'live'; // live mode by default.

    /**
     * Sales state.
     * 
     * @var string
     */
    protected $state = '';

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
    protected $distroList = [];

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
        // Check for required paramters
        if (!$this->option('brand') || !$this->option('state')) {
            $this->error('Syntax: php artisan Genie:EmailEnrollmentFile --mode=[optional]<live/test> --brand=<IDT/RES> --state=<DE/MD/MI/OH> --start-date=<start date> --end-date=<end date>');
        }

        // Set default report dates.
        $this->startDate = Carbon::yesterday("America/Chicago");
        $this->endDate = Carbon::yesterday("America/Chicago")->endOfDay();

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
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));

            $this->info('Using custom date range.');
        }

        // Validate brand
        $this->brand = strtoupper($this->option('brand'));

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

        // Validate sales state, and set distribution lists
        $this->state = strtoupper($this->option('state'));

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date:   ' . $this->endDate);
        $this->info('Mode:  ' . $this->mode);
        $this->info('Brand: ' . $this->brand . ' (' . $this->brandId . ')');
        $this->info('State: ' . $this->state);
        $this->info('');

        if ($this->brand == 'IDT') {
            if ($this->state == 'MD') {
                $this->distroList = [
                    'DXC_AutoEmails@dxc-inc.com',
                    'processing@genieretail.com',
                    'emortiz@genieretail.com',
                    'tsheehy@genieretail.com',
                    'Autumn.Campbell@Answernet.com',
                    'accountmanagers@answernet.com'
                ];
            } else if ($this->state == 'OH') {
                $this->distroList = [
                    'DXC_AutoEmails@dxc-inc.com',
                    'processing@genieretail.com',
                    'emortiz@genieretail.com',
                    'tsheehy@genieretail.com',
                    'Autumn.Campbell@Answernet.com',
                    'accountmanagers@answernet.com'
                ];
            } else {
                $this->error('Unrecognized state \'' . $this->state . '\'. Program terminated.');
                return -2;
            }
        }

        if ($this->brand == 'RES') {
            if ($this->state == 'DE') {
                $this->distroList = [
                    'DXC_AutoEmails@dxc-inc.com',
                    'SalesSupport@genieretail.com',
                    'processing@genieretail.com',
                    'Autumn.Campbell@Answernet.com',
                    'accountmanagers@answernet.com'
                ];
            } else if ($this->state == 'MI') {
                $this->distroList = [
                    'DXC_AutoEmails@dxc-inc.com',
                    'SalesSupport@genieretail.com',
                    'processing@genieretail.com',
                    'Autumn.Campbell@Answernet.com',
                    'accountmanagers@answernet.com'
                ];
            } else if ($this->state == 'OH') {
                $this->distroList = [
                    'DXC_AutoEmails@dxc-inc.com',
                    'processing@genieretail.com',
                    'Autumn.Campbell@Answernet.com',
                    'accountmanagers@answernet.com'
                ];
            } else {
                $this->error('Unrecognized state \'' . $this->state . '\'. Program terminated.');
                return -2;
            }
        }

        // Build file names, using that start date for the file/reporting dates
        $filename = $this->brand . '_' . $this->state . '_D2D_ALL_' . ($this->option('file-num') ? $this->option('file-num') : '1') . '_' .
            $this->startDate->format('d') .
            substr($this->startDate->format('F'), 0, 3) .
            $this->startDate->format('Y') . '.csv';


        // If test mode, modify filenames and replace distro list with dev address
        if ($this->mode == 'test') {
            $filename = 'TEST_' . $filename;
            $this->distroList = []; // blank out list and replace with it email
            $this->distroList[] = 'dxcit@tpv.com';
        }

        // Header for CSV file
        $csvHeader = [
            'FirstName', 'MI', 'LastName', 'Suffix', 'CompanyName', 'AuthorizedPerson', 'AuthorizedPersonRelationship', 'ServiceAddress', 'ServiceAddress2',
            'ServiceCity', 'ServiceState', 'ServiceZip', 'ServiceZip4', 'BillingAddress', 'BillingAddress2', 'BillingCity', 'BillingState', 'BillingZip', 'BillingZip4',
            'Phone', 'PhoneType', 'ContactAuthorization', 'Brand', 'GasAccountNumber', 'GasUtility', 'GasEnergyType', 'GasOfferID', 'GasVendorCustomData', 'GasServiceClass',
            'GasProcessDate', 'ElectricAccountNumber', 'ElectricUtility', 'ElectricEnergyType', 'ElectricOfferID', 'ElectricVendorCustomData', 'ElectricServiceClass',
            'ElectricProcessDate', 'SignupDate', 'BudgetBillRequest', 'TaxExempt', 'AgencyCode', 'AgencyOfficeCode', 'AgentCode', 'AdvertisingMethod', 'SignupMethod',
            'CustomerEmail', 'PrimaryLanguage', 'Validator1', 'Validator1_Collateral', 'Validator2', 'Validator2_Collateral', 'Payload1', 'Payload2', 'Payload3',
            'Payload4', 'GovAggregation', 'GovAggregationText', 'TPVConfirmationNumber'
        ];

        $csv = array(); // Houses formatted enrollment file data

        $data = StatsProduct::select(
            'event_id',
            'brand_id',
            'event_created_at',
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
            'interaction_created_at',
            'contracts'
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
            $this->brandId
        )->where(
            'market',
            'residential'
        )->where(
            'channel',
            'DTD'
        )->where(
            'service_state',
            $this->state
        )->where(
            'stats_product_type_id',
            1    // TPVs only
        )->where(
            'result',
            'sale'
        )->whereIn(
            'source',
            ['Tablet','Live']
        );

        //Brand "RES" For OH, DE and MI
        // Valid verification, but the agent bypassed the EZTPV so no contract was created
        // Market = residential and channel = DTD
        // Exclude these utilities ('VEDO', 'DEOHG', 'DPL', 'DTEGAS', 'CONSGAS')
        //Source = 'tablet' or 'live'


        if ($this->brand == 'RES' && (in_array($this->state,['OH','DE','MI']))) {
            $data = $data->whereNotIn(
                'product_utility_external_id',
                ['VEDO', 'DEOHG', 'DPL', 'DTEGAS', 'CONSGAS','SEMCOG','MICHGAS']
            );
        }

        // Brand "IDT" For OH and MD
        // Valid verification, but the agent bypassed the EZTPV so no contract was created
        // Market = residential and channel = DTD
        // Source = 'tablet' or 'live'

        // if ($this->brand == 'IDT' && (in_array($this->state,['OH','MD']))) {
        //     $data = $data->whereNotIn(
        //         'product_utility_external_id',
        //         ['PGW', 'VEDO', 'DEOHG', 'DPL', 'DTEGAS', 'CONSGAS']
        //     );
        // }


        $data = $data->orderBy(
            'interaction_created_at'
        );

        $this->info('Retrieving TPV data...');

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

        $this->info(count($data) . ' Record(s) found.');

        // If no records found, quit program after sending an email blast
        if (count($data) == 0) {

            $this->sendEmail(
                'There were no records to send for ' . $this->startDate->format('m-d-Y') . '.',
                $this->distroList
            );

            return 0;
        }

        // Format and populate data for enrollment file.
        $this->info("Formatting data for enrollment file...");

        foreach ($data as $r) {
            $billName = $r->bill_first_name . ' ' . $r->bill_last_name;
            $authName = $r->auth_first_name . ' ' . $r->auth_last_name;

            $commodity = strtolower($r->commodity);

            // Parse recording path to get just the recording name.
            $pathTokens = explode('/', $r->recording);
            $recordingName = $pathTokens[count($pathTokens) - 1];

            // Format account number. If DUKE or NICOR, grab the left 10 digits only
            $accountNumber = $r->account_number1;   
            // remove logic truncation for Duke ticket #22124
            // if (
            //     strtoupper($r->product_utility_external_id) == 'DUKE'
            // ) {
            //     $accountNumber = substr($accountNumber, 0, 10);
            // }

            // Determine utility process date
            $utilProcessDate = $r->event_created_at;
            if (
                $this->brand == 'RES' && $this->state == 'OH' &&
                ($r->product_utility_external_id == 'COH' || $r->product_utility_external_id == 'CPA')
            ) {
                $utilProcessDate = Carbon::now()->add(60, 'day');
            }

            // Determine channel value
            $channel = $r->channel;  // default to however Focus stores the value
            if(strtolower($r->channel) == 'tm') { // Translate 'TM' to Genie's preferred value.
                $channel = 'General TM';
            }
            if(strtolower($r->channel) == 'dtd') { // Translate 'DTD' to Genie's preferred value.
                $channel = 'D2D';
            }

            // Contact consent custom field
            $customFields = json_decode($r->custom_fields);
            $contactConsent = '';

            foreach ($customFields as $field) {
                if ($field->name === 'contact_consent') {
                    $contactConsent = $field->value;
                    break;
                }
            }

            // validator2Collateral needs to be the same as FTP upload contract name that was assigned in BrandFileSync if no contract confirmation .pdf
            if (empty($r->contracts)) {
                $validator2Collateral = $r->confirmation_code . '.pdf';
            } else {
                $file_date = Carbon::parse($r->event_created_at,'America/Chicago')->format('Y_m_d_H_i_s');
                $validator2Collateral = $r->confirmation_code . '-' . $file_date . '-' .  substr($r->contracts,strripos($r->contracts,'/')+1);
            }

            $row = [
                'FirstName' => $r->bill_first_name,
                'MI' => '',                                 // Always blank
                'LastName' => $r->bill_last_name,
                'Suffix' => '',                             // Always blank
                'CompanyName' => (strtolower($r->market) == 'residential' ?
                    $billName :
                    $r->company_name),
                'AuthorizedPerson' => (strtolower($billName) != strtolower($authName) ?
                    $authName :
                    ''),
                'AuthorizedPersonRelationship' => (strtolower($billName) != strtolower($authName) ?
                    $r->auth_relationship :
                    ''),
                'ServiceAddress' => $r->service_address1,
                'ServiceAddress2' => $r->service_address2,
                'ServiceCity' => $r->service_city,
                'ServiceState' => $r->service_state,
                'ServiceZip' => substr(trim($r->service_zip), 0, 5),
                'ServiceZip4' => substr(trim($r->service_zip), 5, 4),
                'BillingAddress' => $r->billing_address1,
                'BillingAddress2' => $r->billing_address2,
                'BillingCity' => $r->billing_city,
                'BillingState' => $r->billing_state,
                'BillingZip' => substr(trim($r->billing_zip), 0, 5),
                'BillingZip4' => substr(trim($r->billing_zip), 5, 4),
                'Phone' => substr(trim($r->btn), 2),
                'PhoneType' => 'Unknown',                   // Always 'Unknown'
                'ContactAuthorization' => (strtoupper($contactConsent) == 'YES' ? 'All' : 'None'),
                'Brand' => ($this->brand == 'IDT' ? 'IDTE' : ($this->brand == 'RES' ? 'RES' : '')),
                'GasAccountNumber' => ($commodity == 'natural gas' ? $accountNumber : ''),
                'GasUtility' => ($commodity == 'natural gas' ? $r->product_utility_external_id : ''),
                'GasEnergyType' => ($commodity == 'natural gas' ? ($r->product_green_percentage == 100 ? 'Green' : 'Brown') : ''),
                'GasOfferID' => ($commodity == 'natural gas' ? $r->rate_program_code : ''),
                'GasVendorCustomData' => ($commodity == 'natural gas' ? '' : ''),
                'GasServiceClass' => ($commodity == 'natural gas' ? $r->market : ''),
                'GasProcessDate' => ($commodity == 'natural gas' ? $utilProcessDate->format('m/d/Y') : ''),
                'ElectricAccountNumber' => ($commodity == 'electric' ? $accountNumber : ''),
                'ElectricUtility' => ($commodity == 'electric' ? $r->product_utility_external_id : ''),
                'ElectricEnergyType' => ($commodity == 'electric' ? ($r->product_green_percentage == 100 ? 'Green' : 'Brown') : ''),
                'ElectricOfferID' => ($commodity == 'electric' ? $r->rate_program_code : ''),
                'ElectricVendorCustomData' => ($commodity == 'electric' ? '' : ''),
                'ElectricServiceClass' => ($commodity == 'electric' ? $r->market : ''),
                'ElectricProcessDate' => ($commodity == 'electric' ? $utilProcessDate->format('m/d/Y') : ''),
                'SignupDate' => $r->event_created_at->format('m/d/Y'),
                'BudgetBillRequest' => 'No',                // Always 'No'
                'TaxExempt' => 'No',                        // Always 'No'
                'AgencyCode' => $r->vendor_code,
                'AgencyOfficeCode' => $r->office_label,
                'AgentCode' => $r->sales_agent_rep_id,
                'AdvertisingMethod' => $channel,
                'SignupMethod' => 'Phone',                  // Always 'Phone'
                'CustomerEmail' => $r->email_address,
                'PrimaryLanguage' => $r->language,
                'Validator1' => 'DXC',                      // Always DXC
                'Validator1_Collateral' => $r->confirmation_code . '_01_' .  strtotime($r->interaction_created_at)  . '.mp3',  // needs to be the same as FTP upload recording name that was assigned in BrandFileSync
//                'Validator1_Collateral' => $recordingName,
                'Validator2' => 'DataProcessingTeam',       // Always DataProcessingTeam
                'Validator2_Collateral' => $validator2Collateral,
                'Payload1' => '',                           // Always Blank
                'Payload2' => '',                           // Always Blank
                'Payload3' => '',                           // Always Blank
                'Payload4' => '',                            // Always Blank
                'GovAggregation' => '',                     // Always Blank
                'GovAggregationText' => '',                 // Always Blank
                'TPVConfirmationNumber' => $r->confirmation_code
            ];

            array_push($csv, $row);
        }

        // Write CSV file
        $this->info("\nWriting CSV file...");
        $file = fopen(public_path('tmp/') . $filename, 'w');

        fputcsv($file, $csvHeader);

        foreach ($csv as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        if (!$this->option('no-email')) {
            // Email the file
            $this->info('Emailing files...');
            $this->sendEmail(
                'Attached are the ' . $this->state . ' enrollment files for ' . $this->startDate->format('m-d-Y') . '.',
                $this->distroList,
                [
                    public_path('tmp/') . $filename
                ]
            );

            if (file_exists(public_path('tmp/') . $filename)) {
                unlink(public_path('tmp/') . $filename);
            }
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
            $subject = $subjectBrand . ' - ' . $this->state . ' - D2D - Email File Generation (' . config('app.env') . ') '
                . Carbon::now('America/Chicago');
        } else {
            $subject = $subjectBrand . ' - ' . $this->state . ' - D2D - Email File Generation '
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
