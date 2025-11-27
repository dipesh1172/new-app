<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\ScriptAnswer;
use App\Models\StatsProduct;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;


class DirectEnergy_AB_SfdcApiSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DirectEnergy_AB_SfdcApiSubmissions {--debug} {--internal} {--mode=} {--dry-run} {--sfdc-env=} {--start-date=} {--end-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Direct Energy AB SFDC API Submissions';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Direct Energy - AB SFDC API Submissions';

    /**
     * Start date
     * 
     * @var mixed
     */
    protected $startDate = null;

    /**
     * End date
     * 
     * @var mixed
     */
    protected $endDate = null;

    protected $sfdcEnv = 'prod'; // Prod by default, but can be switch via an arg.

    protected $sfdcLoginUrl = [
        'sandbox' => 'https://test.salesforce.com/services/Soap/u/37.0',
        'prod' => 'https://login.salesforce.com/services/Soap/u/37.0'

    ];

    // Prod credentials
    protected $sfdcUsername = [
        'sandbox' => 'dxc@directenergy.com.full',
        'prod' => 'dxc@directenergy.com'
    ];
    protected $sfdcPass = [
        'sandbox' => '!.3rQd5f"Q4c.S9G>-;P',
        'prod' => '!.3rQd5f"Q4c.S9G>-;P'
    ];
    protected $sfdcToken = [
        'sandbox' => '8CtYvUT95gdxfKZLgbdZm59P',
        'prod' => '8CtYvUT95gdxfKZLgbdZm59P'
    ];

    protected $sfdcServerUrl = ''; // URL returned by SFDC login API response
    protected $sfdcSessionId = ''; // Session ID returned by SFDC login API call.

    /**
     * Mode: 'live' or 'test'.
     * 
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

    /**
     * Create a new command instance.
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
        if ('production' != config('app.env') && !$this->option('internal')) {
            echo "This is only run in production unless the --internal flag is used as well.\n";
            exit();
        }

        // By default we're grabbing data for month-to-date.
        $this->startDate = Carbon::now('America/Chicago')->startOfMonth();
        $this->endDate = Carbon::now('America/Chicago');

        // Override default if custom date range was provided
        if ($this->option('start-date') && $this->option('end-date')) {
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));
        }

        // Check mode.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            } else {
                $this->error('Unrecognized --mode arg value: ' . $this->option('mode'));
            }
        }

        if ($this->mode == 'test') {
            $this->sfdcEnv = 'sandbox'; // By default testing mode will used sandbox API. User can override via command arg.
        }

        // Check SFDC Env.
        if ($this->option('sfdc-env')) {
            if (
                strtolower($this->option('sfdc-env')) == 'sandbox' ||
                strtolower($this->option('sfdc-env')) == 'prod'
            ) {
                $this->sfdcEnv = strtolower($this->option('sfdc-env'));
            } else {
                $this->error('Unrecognized --sfdc-env arg value: ' . $this->option('sfdc-env'));
                return -1;
            }
        }

        $this->info('Creating HTTP client...');
        $client = new \GuzzleHttp\Client();

        // Log in to SFDC and retrieve server URL.
        if (!$this->option('dry-run')) {
            $this->info('Logging into SFDC...');
            $sfdcLoginResult = $this->sfdcLogin($client);

            if ($sfdcLoginResult != 'Success') {
                $this->error('Error: ' . $sfdcLoginResult);
                return -1;
            }


            $this->info('  Server URL: ' . $this->sfdcServerUrl);
            $this->info(' Session ID: ' . $this->sfdcSessionId);
        } else {
            $this->info('Dry run. Skipping SFDC login.');
        }

        $debug = $this->option('debug');
        $brand = Brand::where(
            'name',
            'Direct Energy'
        )->first();

        $this->info('Brand: ' . $brand->name);

        if (!$brand) {
            $this->error('Unable to determine brand ID for: "Direct Energy"');
            return -1;
        }

        $this->info('Getting data...');

        $results = StatsProduct::select(
            'stats_product.id',
            'stats_product.event_id',
            'stats_product.confirmation_code',
            'stats_product.language',
            'stats_product.interaction_created_at',
            'stats_product.market',
            DB::raw("REPLACE(stats_product.btn,'+1','') AS btn"),
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
            DB::raw("REPLACE(stats_product.commodity,'Natural ','') AS commodity"),
            DB::raw("REPLACE(REPLACE(stats_product.rate_program_code,'g',''),'e','') AS rate_program_code"),   
            'stats_product.utility_commodity_ldc_code',
            'stats_product.rate_external_id',
            'stats_product.product_rate_amount',
            'brand_promotions.promotion_code',
            'brand_promotions.promotion_key',
            'stats_product.result',
            'stats_product.event_created_at',  // can't use interaction_created_at because of psa surveys
            'stats_product.product_name',
            'events.external_id',
            'stats_product.custom_fields',
            'stats_product.office_name',
            'stats_product.vendor_name',
            'stats_product.product_time',
            'stats_product.interaction_time',
            'stats_product.tpv_agent_label',
            'stats_product.disposition_label'      
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_promotions',
            'event_product.brand_promotion_id',
            'brand_promotions.id'
        )->leftJoin(
            'events',
            'stats_product.event_id',
            'events.id'
        )->whereDate(
            'stats_product.event_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.event_created_at',
            '<=',
            $this->endDate
        )->where(
            'stats_product.brand_id',
            $brand->id
        // )->where(
        //     'stats_product.confirmation_code',
        //     '23451345055'
        )->whereIn(
            'stats_product.service_state',
            ['AB','SK']
        )->whereIn(
           'stats_product.result',
            ['sale','no sale']
        )->orderBy(
            'stats_product.confirmation_code'
        )->orderBy(
            'stats_product.commodity'
        )->get();


        if (count($results) == 0) {
            $this->info('No records found. Exiting...');
            return 0;
        } else {
            $this->info(count($results) . ' Record(s) found.');
        }

        // process to combine Gas and Electric to Dual Fuel
        $data_array = $results->toArray();
        $saveCompareKey = 'first';
        $save_last_r1 = [];
        $toProcess = [];
        foreach ($data_array as $r1) {
            if ($saveCompareKey == $r1['confirmation_code'] . $r1['account_number1'] . $r1['rate_program_code']) {
                if (strtolower($save_last_r1['commodity']) == 'electric' && strtolower($r1['commodity']) == 'gas') {  // electric is first in dual  because of order by in select
                    $save_last_r1['commodity'] = 'Dual Fuel';  // create dual fuel combine
                    array_push($toProcess,$save_last_r1);
                    $save_last_r1 = [];
                    $saveCompareKey = 'first';
                } else {
                    array_push($toProcess,$save_last_r1);
                    $save_last_r1 = [];
                    $saveCompareKey = 'first';
                }
             } else {
                if ((!empty($save_last_r1) && $saveCompareKey <> 'first' ) || (count($results) == 1)) {   // must be single fuel
                    array_push($toProcess,$save_last_r1);
                }
                $saveCompareKey = $r1['confirmation_code'] . $r1['account_number1'] . $r1['rate_program_code'];
                $save_last_r1 = $r1; // save array for compare on next record
             }
        }
        // finished combining

        // Submit the records in batches of 150
        $apiQueue = array_chunk($toProcess, 1);
        $batchNum = 1;
        $requestFilename = "Direct Energy - AB - SFDC API - Request - " . str_pad($batchNum, 4, "0", STR_PAD_LEFT) . ".xml";
        $requestPath = public_path('tmp/' . $requestFilename);
        $requestFile = fopen($requestPath, 'w');
        $errorsFilename = "Direct Energy - AB - SFDC API - Errors - " . str_pad($batchNum, 4, "0", STR_PAD_LEFT) . ".xml";
        $errorsPath = public_path('tmp/' . $errorsFilename);
        $errorsFile = fopen($errorsPath, 'w');
        $errorsMatch = false;
        $filename = "Direct Energy - AB - SFDC API - " . str_pad($batchNum, 4, "0", STR_PAD_LEFT) . ".xml";
        $path = public_path('tmp/' . $filename);
        $file = fopen($path, 'w');
        foreach ($apiQueue as $queue) {

            $payload = $this->buildXmlRequest($queue);

            if (!$this->option('dry-run')) {
                $this->info('Posting batch ' . $batchNum . ' / ' . count($apiQueue));
//                $this->sfdcUpsert($client, $payload, $batchNum, count($apiQueue));
                $body2 = '';

                try {
                $res = $client->post(
                $this->sfdcServerUrl,
                [
                    'debug' => false,
                    'http_errors' => true,
                    'body' => $payload,
                    'headers' => [
                        'Content-Type' => 'text/xml; charset=utf-8',
                        'SOAPAction' => '""'
                    ]
                ]
                );

                $body = $res->getBody();

                // This is byte stream. Seek to beginning and read.
                $body->seek(0);
                $body2 = $body->read(10000); // should be enough to read full response...

                } catch (\Exception $e) {
                    print_r($e->getMessage());
                }

                if ($this->option('debug')) {
                print_r("Batch " . $batchNum . ' / ' . $batchCount . ":\n\n");
                print_r("REQUEST:\n");
                print_r($payload . "\n\n");

                print_r("RESPONSE:\n");
                print_r($body2 . "\n\n");
                }

                // Check for faults
                $match = null;

                $reg = '/<faultstring>(.*?)<\/faultstring>/s';
                preg_match($reg, $body2, $match);

                if (!empty($match)) { // Fault found

                    $this->sendEmail(
                        "Fault encountered when logging in to direct energy's SFDC instance: \n\n"
                            . "URL: " . $this->sfdcLoginUrl[$this->sfdcEnv] . "\n"
                            . "API Response:\n"
                            . $this->xmlEncode($body2),
                        ['curt.cadwell@answernet.com']
                    );

                    return $match[1]; // idx 0 is matching text with tags, idx 1 is matching text without tags
                }

                // Look for errors. The response is an XML array of results.
                // If at least one record has an error, email a the response and 
                // return error
                $match = null;

                $reg = '/<success>false<\/success>/s';
                preg_match($reg, $body2, $match);

                if (!empty($match)) {
                    // Requests file
                    $errorsMatch = true;
                    fputs($requestFile, $payload . "\r\n" );
                    // fclose($requestFile);

                    // Errors file

                    fputs($errorsFile, $body2 . "\r\n");
                    // fclose($errorsFile);

                    // $this->sendEmail(
                    //     'Error returned for at least one record in the upsert batch ' . $batchNum . ' / ' . $batchCount . '. See attachment.',
                    //     ['curt.cadwell@answernet.com','dxc_autoemails@tpv.com'],
                    //     [$requestPath, $errorsPath]
                    // );

                    //unlink($requestPath);
                    //unlink($errorsPath);
                }

                
            } else {

                            $this->info('Dry run. Creating request XML file for batch ' . $batchNum . ' / ' . count($apiQueue) . '...');


                            fputs($file, $payload . "\r\n");
                            //fclose($file);
                        }

            $batchNum += 1;
        }
        if (!$this->option('dry-run')) {
            if ($errorsMatch) {
                fclose($requestFile);
                fclose($errorsFile);
                $this->sendEmail(
                    'Errors returned for SFDC submissions See attachment.',
                    ['curt.cadwell@answernet.com'],
                    [$requestPath, $errorsPath]
                );

                unlink($requestPath);
                unlink($errorsPath);
            } 
        } else {
         fclose($file);
        }
    }

    /**
     * Builds the XML string to post to SFDC
     */
    private function buildXmlRequest($data)
    {

        $request = '<soapenv:Envelope '
            . 'xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
            . 'xmlns:urn="urn:partner.soap.sforce.com" '
            . 'xmlns:urn1="urn:sobject.partner.soap.sforce.com">'
            . '<soapenv:Header>'
            . '<urn:SessionHeader>'
            . '<urn:sessionId>' . $this->sfdcSessionId . '</urn:sessionId>'
            . '</urn:SessionHeader>'
            . '</soapenv:Header>'
            . '<soapenv:Body>'
            . '<urn:upsert>'
            . '<urn:externalIDFieldName>RecID_Unique__c</urn:externalIDFieldName>';

        foreach ($data as $row) {
            $customFields = json_decode($row['custom_fields']);
            $num_sites = '';
            $pod_id = '';
            foreach($customFields AS $customField) {

                switch(strtolower($customField->output_name)) {
                    case 'num_sites':
                        $num_sites = $customField->value;
                        break;
                    case 'pod_id':
                        $pod_id = $customField->value;
                        break;
                }
            }
            $request .=
                '<urn:sObjects>'
                . '<type>D2D_Sale__c</type>'
                . '<fieldsToNull></fieldsToNull>'
                . '<Id></Id>'
                . '<Name_Aggregation__c></Name_Aggregation__c>'
                . '<p_date__c>' . $this->xmlEncode(substr($row['interaction_created_at'],0,10)) . '</p_date__c>'
                . '<office_id__c>' . $this->xmlEncode($row['office_name']) . '</office_id__c>'
                . '<vendor_name__c>' . $this->xmlEncode($row['vendor_name']) . '</vendor_name__c>'
                . '<sales_state__c>' . $this->xmlEncode($row['service_state']) . '</sales_state__c>'
                . '<ver_code__c>' . $this->xmlEncode($row['confirmation_code']) . '</ver_code__c>'
                . '<tsr_id__c>' . $this->xmlEncode($row['sales_agent_rep_id']) . '</tsr_id__c>'
                . '<tsr_name__c>' . $this->xmlEncode($row['sales_agent_name']) . '</tsr_name__c>'
                . '<status_txt__c>' . $this->xmlEncode((($row['result'] == 'Sale') ? 'Good Sale' : 'No Sale')) . '</status_txt__c>'
                . '<DT_Insert_Date__c>' . $this->xmlEncode($row['event_created_at']) . '</DT_Insert_Date__c>'
                . '<call_time__c>' . $this->xmlEncode($row['interaction_time']) . '</call_time__c>'
                . '<ib_ct__c>' . $this->xmlEncode($row['interaction_time']) . '</ib_ct__c>'
                . '<OB_only__c>0.00</OB_only__c>'
                . '<DXC_Record_ID__c>' . $this->xmlEncode($row['external_id']) . '</DXC_Record_ID__c>'
                . '<DXC_Rep_ID__c>' . $this->xmlEncode($row['tpv_agent_label']) . '</DXC_Rep_ID__c>'
                . '<Program_code__c>' . $this->xmlEncode($row['rate_program_code']) . '</Program_code__c>'
//                . '<EnrolledLanguage__c>' . $this->xmlEncode($row['language']) . '<EnrolledLanguage__c>'
                . '<Service_Type__c>' . $this->xmlEncode($row['commodity']) . '</Service_Type__c>'
                . '<app_id__c>' . $this->xmlEncode(strtoupper($row['account_number1'])) . '</app_id__c>'
                . '<auth_fname__c>' . $this->xmlEncode($row['auth_first_name']) . '</auth_fname__c>'
                . '<auth_lname__c>' . $this->xmlEncode($row['auth_last_name']) . '</auth_lname__c>'
                . '<bill_fname__c>' . $this->xmlEncode($row['bill_first_name']) . '</bill_fname__c>'
                . '<bill_lname__c>' . $this->xmlEncode($row['bill_last_name']) . '</bill_lname__c>'
                . '<btn__c>' . $this->xmlEncode(str_replace('+1','',$row['btn'])) . '</btn__c>'
                . '<OfficeID__c>' . $this->xmlEncode($pod_id) . '</OfficeID__c>'
                . (empty($row['disposition_label']) ? '' : '<Status_Code__c>' . $this->xmlEncode($row['disposition_label']) . '</Status_Code__c>')
                . '<Pre_Audit_Status__c>' . $this->xmlEncode((($row['result'] == 'Sale') ? 'Good Sale' : 'No Sale')) . '</Pre_Audit_Status__c>'
                . '<Pre_Audit_Status_Code__c>' . $this->xmlEncode($row['disposition_label']) . '</Pre_Audit_Status_Code__c>'
                . '<Enrolled_Units__c>' . $this->xmlEncode($num_sites) . '</Enrolled_Units__c>'
                . (empty($row['sales_agent_rep_id']) ? '' : '<Related_Representative__r>'
                    . '    <type>Representative__c</type>'
                    .  '   <Rep_ID__c>' . $this->xmlEncode($row['sales_agent_rep_id']) . '</Rep_ID__c>'
                    . '</Related_Representative__r>')
                . '<RecID_Unique__c>' . $this->xmlEncode(str_replace('-','',$row['id'])) . '</RecID_Unique__c>'
                . '<Program_Description__c>' . $this->xmlEncode($row['product_name']) . '</Program_Description__c>'
                . '<TPV_Type_Actual__c>Voice</TPV_Type_Actual__c>'
                . '<center_id__c>' . $this->xmlEncode($row['vendor_grp_id']) . '</center_id__c>'
                . '<TPV_Type_Original__c>Voice</TPV_Type_Original__c>'
                . '</urn:sObjects>';
                
        }


//                . '<RecID_Unique__c>' . $this->xmlEncode($row['external_id'] . '-' . $row['confirmation_code']) . '</RecID_Unique__c>'
                // . '<urn1:Name_Aggregation__c></urn1:Name_Aggregation__c>'
                // . '<urn1:p_date__c>' . $this->xmlEncode(substr($row['interaction_created_at'],0,10)) . '</urn1:p_date__c>'
                // . '<urn1:office_id__c>' . $this->xmlEncode($row['office_name']) . '</urn1:office_id__c>'
                // . '<urn1:vendor_name__c>' . $this->xmlEncode($row['vendor_name']) . '</urn1:vendor_name__c>'
                // . '<urn1:sales_state__c>' . $this->xmlEncode($row['service_state']) . '</urn1:sales_state__c>'
                // . '<urn1:ver_code__c>' . $this->xmlEncode($row['confirmation_code']) . '</urn1:ver_code__c>'
                // . '<urn1:tsr_id__c>' . $this->xmlEncode($row['sales_agent_rep_id']) . '</urn1:tsr_id__c>'
                // . '<urn1:tsr_name__c>' . $this->xmlEncode($row['sales_agent_name']) . '</urn1:tsr_name__c>'
                // . '<urn1:status_txt__c>' . $this->xmlEncode((($row['result'] == 'Sale') ? 'Good Sale' : 'No Sale')) . '</urn1:status_txt__c>'
                // . '<urn1:DT_Insert_Date__c>' . $this->xmlEncode($row['event_created_at']) . '</urn1:DT_Insert_Date__c>'
                // . '<urn1:call_time__c>' . $this->xmlEncode($row['interaction_time']) . '</urn1:call_time__c>'
                // . '<urn1:ib_ct__c>' . $this->xmlEncode($row['interaction_time']) . '</urn1:ib_ct__c>'
                // . '<urn1:OB_only__c>0.00</urn1:OB_only__c>'
                // . '<urn1:DXC_Record_ID__c>' . $this->xmlEncode($row['external_id']) . '</urn1:DXC_Record_ID__c>'
                // . '<urn1:DXC_Rep_ID__c>' . $this->xmlEncode($row['tpv_agent_label']) . '</urn1:DXC_Rep_ID__c>'
                // . '<urn1:Program_code__c>' . $this->xmlEncode($row['rate_program_code']) . '</urn1:Program_code__c>'
                // . '<urn1:EnrolledLanguage__c>' . $this->xmlEncode($row['language']) . '<urn1:EnrolledLanguage__c>'
                // . '<urn1:Service_Type__c>' . $this->xmlEncode($row['commodity']) . '</urn1:Service_Type__c>'
                // . '<urn1:app_id__c>' . $this->xmlEncode(strtoupper($row['account_number1'])) . '</urn1:app_id__c>'
                // . '<urn1:auth_fname__c>' . $this->xmlEncode($row['auth_first_name']) . '</urn1:auth_fname__c>'
                // . '<urn1:auth_lname__c>' . $this->xmlEncode($row['auth_last_name']) . '</urn1:auth_lname__c>'
                // . '<urn1:bill_fname__c>' . $this->xmlEncode($row['bill_first_name']) . '</urn1:bill_fname__c>'
                // . '<urn1:bill_lname__c>' . $this->xmlEncode($row['bill_last_name']) . '</urn1:bill_lname__c>'
                // . '<urn1:btn__c>' . $this->xmlEncode($row['btn']) . '</urn1:btn__c>'
                // . '<urn1:OfficeID__c>' . $this->xmlEncode($pod_id) . '</urn1:OfficeID__c>'
                // . '<urn1:Status_Code__c>' . $this->xmlEncode($row['disposition_label']) . '</urn1:Status_Code__c>'
                // . '<urn1:Pre_Audit_Status__c>' . $this->xmlEncode((($row['result'] == 'Sale') ? 'Good Sale' : 'No Sale')) . '</urn1:Pre_Audit_Status__c>'
                // . '<urn1:Pre_Audit_Status_Code__c>' . $this->xmlEncode($row['disposition_label']) . '</urn1:Pre_Audit_Status_Code__c>'
                // . '<urn1:Enrolled_Units__c>' . $this->xmlEncode($num_sites) . '</urn1:Enrolled_Units__c>'
                // . '<urn1:Related_Representative__r>'
                // . '    <urn1:type>Representative__c</urn1:type>'
                // .  '   <urn1:Rep_ID__c>' . $this->xmlEncode($row['sales_agent_rep_id']) . '</urn1:Rep_ID__c>'
                // . '</urn1:Related_Representative__r>'
                // . '<urn1:RecID_Unique__c>' . $this->xmlEncode($row['external_id'] . '-' . $row['confirmation_code']) . '</urn1:RecID_Unique__c>'
                // . '<urn1:Program_Description__c>' . $this->xmlEncode($row['product_name']) . '</urn1:Program_Description__c>'
                // . '<urn1:TPV_Type_Actual__c>Voice</urn1:TPV_Type_Actual__c>'
                // . '<urn1:center_id__c>' . $this->xmlEncode($row['vendor_grp_id']) . '</urn1:center_id__c>'
                // . '<urn1:TPV_Type_Original__c>Voice</urn1:TPV_Type_Original__c>'


                // . '<urn1:Name>' . $row['confirmation_code'] . '</urn1:Name>'
                // . '<urn1:Reason_Customer_Chose_to_Switch__c>' . $this->xmlEncode($row['reason_for_choosing']) . '</urn1:Reason_Customer_Chose_to_Switch__c>'
                // . '<urn1:Service_Zip__c>' . $this->xmlEncode(trim($row['service_zip'])) . '</urn1:Service_Zip__c>'
                // . '<urn1:Reason_Description__c>' . $this->xmlEncode(trim($row['disposition_reason'])) . '</urn1:Reason_Description__c>'
                // . '<urn1:Phone_Carrier__c></urn1:Phone_Carrier__c>' // TODO: Map for Res DTD
                // . '<urn1:BTN__c>' . $this->xmlEncode(trim(ltrim($row['btn'], '+1'))) . '</urn1:BTN__c>'
                // . '<urn1:Attempt_3__c>' . $this->xmlEncode(trim($row['attempt3'])) . '</urn1:Attempt_3__c>'
                // . '<urn1:Attempt_2__c>' . $this->xmlEncode(trim($row['attempt2'])) . '</urn1:Attempt_2__c>'
                // . '<urn1:Attempt_1__c>' . $this->xmlEncode(trim($row['attempt1'])) . '</urn1:Attempt_1__c>'
                // . '<urn1:Status__c>' . $this->xmlEncode(trim($row['result'])) . '</urn1:Status__c>'
                // . '<urn1:Enrollment_Type__c></urn1:Enrollment_Type__c>' // TODO: Map for Retail surveys
                // . '<urn1:Used_ECL__c></urn1:Used_ECL__c>' // TODO: Map for Retail surveys
                // . '<urn1:Did_Agent_Represent_Supplier__c></urn1:Did_Agent_Represent_Supplier__c>'
                // . '<urn1:Reason_Code__c>0</urn1:Reason_Code__c>' // DOUBLE -- Only Map TPV disposition label here.
                // . '<urn1:Utility_Acct_Number__c>' . $this->xmlEncode(trim($row['account_number'])) . '</urn1:Utility_Acct_Number__c>'
                // . '<urn1:ANI__c>' . $this->xmlEncode(trim(ltrim($row['caller_id'], '+1'))) . '</urn1:ANI__c>'
                // . '<urn1:Date__c>' . Carbon::parse($row['interaction_created_at'])->toIso8601ZuluString() . '</urn1:Date__c>'
                // . '<urn1:Enrollment_ID__c></urn1:Enrollment_ID__c>' // TODO: Map for Retail surveys
                // . '<urn1:Did_Agent_Give_Customer_Collateral__c>' . $this->xmlEncode(trim($row['agent_gave_toc'])) . '</urn1:Did_Agent_Give_Customer_Collateral__c>'
                // . '<urn1:Customer_Confirms_Switching_Suppliers__c>' . $this->xmlEncode(trim($row['understand_chose_supplier'])) . '</urn1:Customer_Confirms_Switching_Suppliers__c>'
                // . '<urn1:How_Likely__c>' . $this->xmlEncode(trim($row['agent_rating'])) . '</urn1:How_Likely__c>'
                // . '<urn1:Verification_Language__c>' . $this->xmlEncode(trim($row['language'])) . '</urn1:Verification_Language__c>'
                // . '<urn1:Unique_Field__c>' . $row['event_id'] . '</urn1:Unique_Field__c>'
                // . '<urn1:Service_Address_2__c></urn1:Service_Address_2__c>' // TODO: Map for Retail(?), DTD
                // . '<urn1:Customer_Last_Name__c>' . $this->xmlEncode(trim($row['customer_last_name'])) . '</urn1:Customer_Last_Name__c>'
                // . '<urn1:Service_Address_1__c>' . $this->xmlEncode(trim($row['srvc_address'])) . '</urn1:Service_Address_1__c>'
                // . '<urn1:Contact__r>'
                // . '<urn1:type>Contact</urn1:type>'
                // //. '<urn1:Agent_ID__c>' . $this->xmlEncode(trim($row['survey_rep_id'])) . '</urn1:Agent_ID__c>'
                // . '<urn1:Agent_ID__c>1234</urn1:Agent_ID__c>'
                // . '</urn1:Contact__r>'
                // . '<urn1:Duration__c>' . $row['interaction_time'] . '</urn1:Duration__c>' // DOUBLE
                // . '<urn1:Email__c>' . $this->xmlEncode(trim($row['email_address'])) . '</urn1:Email__c>'
                // . '<urn1:Did_Customer_Agree_to_Switch_Suppliers__c></urn1:Did_Customer_Agree_to_Switch_Suppliers__c>'
                // . '<urn1:Phone_Type__c></urn1:Phone_Type__c>' // TODO: Map? Btn lookup phone type
                // . '<urn1:Service_State__c>' . $this->xmlEncode(trim($row['state_abbrev'])) . '</urn1:Service_State__c>'
                // . '<urn1:Was_Agent_Polite_Courteous__c>' . $this->xmlEncode(trim($row['agent_polite'])) . '</urn1:Was_Agent_Polite_Courteous__c>'
                // . '<urn1:Service_City__c>' . $this->xmlEncode(trim($row['service_city'])) . '</urn1:Service_City__c>'
                // . '<urn1:Customer_Sat_Score__c>' . $customerSatScore . '</urn1:Customer_Sat_Score__c>' // DOUBLE
                // . '<urn1:Did_Cstmr_Cnfrm_Sig_and_Phone_Call__c>' . $this->xmlEncode(trim($row['signed_form_call'])) . '</urn1:Did_Cstmr_Cnfrm_Sig_and_Phone_Call__c>'
                // . '<urn1:Attempt_3_Date__c>' . $row['dt_attempt3'] . '</urn1:Attempt_3_Date__c>'
                // . '<urn1:Attempt_2_Date__c>' . $row['dt_attempt2'] . '</urn1:Attempt_2_Date__c>'
                // . '<urn1:Other_Feedback_From_Customer__c>' . $this->xmlEncode(trim($row['notes']['feedback'])) . '</urn1:Other_Feedback_From_Customer__c>'
                // . '<urn1:Attempt_1_Date__c>' . $row['dt_attempt1'] . '</urn1:Attempt_1_Date__c>'
                // . '<urn1:Customer_First_Name__c>' . $this->xmlEncode(trim($row['customer_first_name'])) . '</urn1:Customer_First_Name__c>'
                // . '<urn1:Email_Validation__c></urn1:Email_Validation__c>' // TODO: Map for Retail surveys
                // . '<urn1:Retail_Location__c></urn1:Retail_Location__c>' // TODO: Map for Retail surveys
                // . '<urn1:Customer_Sat_Score_Reason__c>' . $this->xmlEncode(trim($row['agent_rating_reason'])) . '</urn1:Customer_Sat_Score_Reason__c>'
                // . '<urn1:CenterID__c>' . $centerId . '</urn1:CenterID__c>' // DOUBLE
                // . '<urn1:Operator_ID__c>' . $this->xmlEncode(trim($row['tpv_agent_label'])) . '</urn1:Operator_ID__c>'
                // . '<urn1:Utility__c>' . $this->xmlEncode(trim($row['product_utility_name'])) . '</urn1:Utility__c>'
                // . '<urn1:Dirty_Vs_Clean__c></urn1:Dirty_Vs_Clean__c>' // TODO: Map for GME Retail and DTD
                // . '<urn1:Secondary_First_Name__c></urn1:Secondary_First_Name__c>' // TODO: Map for Retail surveys
                // . '<urn1:Secondary_Last_Name__c></urn1:Secondary_Last_Name__c>' // TODO: Map for Retail surveys
                // . '<urn1:Mode__c>Offline</urn1:Mode__c>' // TODO: Map Online/Offline (based on TPV lead being found) for DTD surveys

        $request .=
            '</urn:upsert>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        return $request;
    }

    /**
     * Log in to SFDC.
     */
    private function sfdcLogin($client)
    {
        $request = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:partner.soap.sforce.com">'
            . '  <soapenv:Header>'
            . '    <urn:CallOptions>'
            . '      <urn:client></urn:client>'
            . '      <urn:defaultNamespace></urn:defaultNamespace>'
            . '    </urn:CallOptions>'
            . '    <urn:LoginScopeHeader>'
            . '      <urn:organizationId></urn:organizationId>'
            . '    </urn:LoginScopeHeader>'
            . '  </soapenv:Header>'
            . '  <soapenv:Body>'
            . '    <urn:login>'
            . '      <urn:username>' . $this->sfdcUsername[$this->sfdcEnv] . '</urn:username>'
            . '      <urn:password>' . $this->sfdcPass[$this->sfdcEnv] . $this->sfdcToken[$this->sfdcEnv] . '</urn:password>'
            . '    </urn:login>'
            . '  </soapenv:Body>'
            . '</soapenv:Envelope>';

        $res = $client->post(
            $this->sfdcLoginUrl[$this->sfdcEnv],
            [
                'body' => $request,
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '""'
                ]
            ]
        );

        $body = $res->getBody();

        // This is byte stream. Seek to beginning and read.
        $body->seek(0);
        $body2 = $body->read(2000); // should be enough to read full response...


        // Check for faults
        $match = null;

        $reg = '/<faultstring>(.*?)<\/faultstring>/s';
        preg_match($reg, $body2, $match);

        if (!empty($match)) { // Fault found

            $this->sendEmail(
                "Fault encountered when logging in to Direct Energy's SFDC instance: \n\n"
                    . "URL: " . $this->sfdcLoginUrl[$this->sfdcEnv] . "\n"
                    . "API Response:\n"
                    . $this->xmlEncode($body2),
                ['curt.cadwell@answernet.com','dxc_autoemails@tpv.com']
            );

            return $match[1]; // idx 0 is matching text with tags, idx 1 is matching text without tags
        }

        // extract the server URL
        $match = null;

        $reg = '/<serverUrl>(.*?)<\/serverUrl>/s';
        preg_match($reg, $body2, $match);

        if (!empty($match)) {
            $this->sfdcServerUrl = $match[1];
        } else { // Not a fault, but some other unexpected response

            $this->sendEmail(
                "Unable to find session ID in SFDC Login API response: \n\n"
                    . "URL: " . $this->sfdcLoginUrl[$this->sfdcEnv] . "\n"
                    . "API Response:\n"
                    . $this->xmlEncode($body2),
                ['curt.cadwell@answernet.com','dxc_autoemails@tpv.com']
            );

            return "Unexpected response";
        }

        // extract the session ID
        $match = null;

        $reg = '/<sessionId>(.*?)<\/sessionId>/s';
        preg_match($reg, $body2, $match);

        if (!empty($match)) {
            $this->sfdcSessionId = $match[1];
        } else { // Not a fault, but some other unexpected response

            $this->sendEmail(
                "Unable to find session ID in SFDC Login API response: \n\n"
                    . "URL: " . $this->sfdcLoginUrl[$this->sfdcEnv] . "\n"
                    . "API Response:\n"
                    . $this->xmlEncode($body2),
                ['curt.cadwell@answernet.com']
            );

            return "Unexpected response";
        }
        return "Success";
    }

    /**
     * Upsert data to SFDC
     */
    private function sfdcUpsert($client, $payload, $batchNum, $batchCount)
    {
        $body2 = '';

         try {
        $res = $client->post(
            $this->sfdcServerUrl,
            [
                'debug' => true,
                'http_errors' => true,
                'body' => $payload,
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '""'
                ]
            ]
        );

        $body = $res->getBody();

        // This is byte stream. Seek to beginning and read.
        $body->seek(0);
        $body2 = $body->read(10000); // should be enough to read full response...

         } catch (\Exception $e) {
             print_r($e->getMessage());
         }

        if ($this->option('debug')) {
            print_r("Batch " . $batchNum . ' / ' . $batchCount . ":\n\n");
            print_r("REQUEST:\n");
            print_r($payload . "\n\n");

            print_r("RESPONSE:\n");
            print_r($body2 . "\n\n");
        }

        // Check for faults
        $match = null;

        $reg = '/<faultstring>(.*?)<\/faultstring>/s';
        preg_match($reg, $body2, $match);

        if (!empty($match)) { // Fault found

            $this->sendEmail(
                "Fault encountered when logging in to direct energy's SFDC instance: \n\n"
                    . "URL: " . $this->sfdcLoginUrl[$this->sfdcEnv] . "\n"
                    . "API Response:\n"
                    . $this->xmlEncode($body2),
                ['curt.cadwell@answernet.com','dxc_autoemails@tpv.com']
            );

            return $match[1]; // idx 0 is matching text with tags, idx 1 is matching text without tags
        }

        // Look for errors. The response is an XML array of results.
        // If at least one record has an error, email a the response and 
        // return error
        $match = null;

        $reg = '/<success>false<\/success>/s';
        preg_match($reg, $body2, $match);

        if (!empty($match)) {
            // Requests file
            $requestFilename = "Direct Energy - AB - SFDC API - Request - " . str_pad($batchNum, 4, "0", STR_PAD_LEFT) . ".xml";
            $requestPath = public_path('tmp/' . $requestFilename);
            $requestFile = fopen($requestPath, 'w');

            fputs($requestFile, $payload);
            fclose($requestFile);

            // Errors file
            $errorsFilename = "Direct Energy - AB - SFDC API - Errors - " . str_pad($batchNum, 4, "0", STR_PAD_LEFT) . ".xml";
            $errorsPath = public_path('tmp/' . $errorsFilename);
            $errorsFile = fopen($errorsPath, 'w');

            fputs($errorsFile, $body2);
            fclose($errorsFile);

            // $this->sendEmail(
            //     'Error returned for at least one record in the upsert batch ' . $batchNum . ' / ' . $batchCount . '. See attachment.',
            //     ['curt.cadwell@answernet.com'],
            //     [$requestPath, $errorsPath]
            // );

            unlink($requestPath);
            unlink($errorsPath);
        }

        return "Success";
    }

    /**
     * Encodes XML reserved characters
     */
    private function xmlEncode($val)
    {
        $tran = array('<' => '&lt;', '&' => '&amp;', '>' => '&gt;', '"' => '&quot;', "'" => '&apos;');
        return strtr($val, $tran);
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
}
