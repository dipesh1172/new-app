<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\ScriptAnswer;
use App\Models\StatsProduct;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NrgSurveySfdcApiSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'NRG:SurveySfdcApiSubmissions {--debug} {--internal} {--mode=} {--dry-run} {--sfdc-env=} {--start-date=} {--end-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'NRG Survey SFDC API Submissions';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'NRG - Survey SFDC API Submissions';

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
        'sandbox' => 'dxcit@tpv.com.gme.tpv2sfapi',
        'prod' => 'dxcit@tpv.com.gme'
    ];
    protected $sfdcPass = [
        'sandbox' => 'yT6utLLi88P7%e%VpiH!',
        'prod' => 'DaYArcFMDabc7ZFD8CA7'
    ];
    protected $sfdcToken = [
        'sandbox' => 'ETw3ldxIVs63vykKh03IVJ90',
        'prod' => '3Fvhju5HFYSEmg90U2vzTvmZi'
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
            'NRG'
        )->first();

        $this->info('Brand: ' . $brand->name);

        if (!$brand) {
            $this->error('Unable to determine brand ID for: "NRG"');
            return -1;
        }

        $this->info('Getting data...');

        $results = StatsProduct::select(
            'stats_product.brand_name',
            'stats_product.event_id',
            'stats_product.event_created_at',
            'stats_product.interaction_id',
            'stats_product.interaction_created_at',
            'stats_product.confirmation_code',
            'stats_product.btn',
            'stats_product.ani AS caller_id',
            'stats_product.tpv_agent_name',
            'stats_product.tpv_agent_label',
            'stats_product.vendor_grp_id',
            'stats_product.vendor_name',
            'stats_product.sales_agent_rep_id',
            'stats_product.sales_agent_name',
            'surveys.refcode',
            'stats_product.product_utility_name',
            'surveys.account_number',
            'surveys.customer_first_name',
            'surveys.customer_last_name',
            'stats_product.email_address',
            'stats_product.language',
            'stats_product.channel',
            'stats_product.interaction_time',
            DB::raw('DATE(surveys.customer_enroll_date) AS customer_enroll_date'),
            'surveys.referral_id',
            'surveys.srvc_address',
            'surveys.account_number',
            'surveys.agency',
            'surveys.enroll_source',
            'surveys.agent_vendor',
            'surveys.agent_name as survey_agent_name',
            'surveys.rep_id AS survey_rep_id',
            'surveys.contr_acct_id',
            'stats_product.result',
            'stats_product.disposition_label',
            'stats_product.disposition_id',
            'stats_product.disposition_reason',
            'interactions.notes',
            'stats_product.service_city',
            'stats_product.service_zip',
            'stats_product.service_state',
            'states.state_abbrev',
            DB::raw("'' as product_code")
        )->with(
            'interactions',
            'interactions.disposition'
        )->where(
            'stats_product_type_id',
            3
        )->leftJoin(
            'surveys',
            'stats_product.survey_id',
            'surveys.id'
        )->leftJoin(
            'interactions',
            'stats_product.interaction_id',
            'interactions.id'
        )->leftJoin(
            'states',
            'surveys.state_id',
            'states.id'
        )->where(
            'stats_product.brand_id',
            $brand->id
        )->where(
            'interaction_created_at',
            '>=',
            $this->startDate
        )->where(
            'interaction_created_at',
            '<=',
            $this->endDate
            // )->whereIn(
            //     'stats_product.confirmation_code',
            //     ['03432147541', '03411623746', '03382210334']
        )->orderBy(
            'interaction_created_at',
            'desc'
        )->get()->map(
            function ($item) {
                $dt_attempt1 = null;
                $dt_attempt2 = null;
                $dt_attempt3 = null;

                $attempt1 = null;
                $attempt2 = null;
                $attempt3 = null;

                $attempt1_id = null;
                $attempt2_id = null;
                $attempt3_id = null;

                for ($i = 0; $i < 3; ++$i) {
                    if (isset($item->interactions[$i])) {
                        if (null === $dt_attempt1) {
                            $attempt1_id = $item->interactions[$i]['id'];
                            $dt_attempt1 = $item->interactions[$i]['created_at']->toDateTimeString();
                            $attempt1 = (isset($item->interactions[$i]['disposition']))
                                ? $item->interactions[$i]['disposition']['reason']
                                : null;
                            continue;
                        }

                        if (null === $dt_attempt2) {
                            $attempt2_id = $item->interactions[$i]['id'];
                            $dt_attempt2 = $item->interactions[$i]['created_at']->toDateTimeString();
                            $attempt2 = (isset($item->interactions[$i]['disposition']))
                                ? $item->interactions[$i]['disposition']['reason']
                                : null;
                            continue;
                        }

                        if (null === $dt_attempt3) {
                            $attempt3_id = $item->interactions[$i]['id'];
                            $dt_attempt3 = $item->interactions[$i]['created_at']->toDateTimeString();
                            $attempt3 = (isset($item->interactions[$i]['disposition']))
                                ? $item->interactions[$i]['disposition']['reason']
                                : null;
                            continue;
                        }
                    }
                }

                $item->dt_attempt1 = $dt_attempt1;
                $item->attempt1 = $attempt1;
                $item->attempt1_id = $attempt1_id;

                $item->dt_attempt2 = $dt_attempt2;
                $item->attempt2 = $attempt2;
                $item->attempt2_id = $attempt2_id;

                $item->dt_attempt3 = $dt_attempt3;
                $item->attempt3 = $attempt3;
                $item->attempt3_id = $attempt3_id;

                if (!isset($item->feedback)) {
                    if (isset($item->notes) && isset($item->notes['feedback'])) {
                        $item->feedback = trim(
                            preg_replace(
                                '/\s\s+/',
                                ' ',
                                $item->notes['feedback']
                            )
                        );
                    }
                }

                $qas = [];
                $questions_answers = ScriptAnswer::select(
                    'script_questions.section_id',
                    'script_questions.subsection_id',
                    'script_questions.question_id',
                    'script_questions.question',
                    'script_answers.answer_type',
                    'script_answers.answer'
                )->leftJoin(
                    'script_questions',
                    'script_answers.question_id',
                    'script_questions.id'
                )->where(
                    'script_answers.event_id',
                    $item->event_id
                )->get();
                foreach ($questions_answers as $qa) {
                    $qid = $qa->section_id . '.'
                        . $qa->subsection_id . '.'
                        . $qa->question_id;
                    $qas[$qid] = (isset($qa->answer) && 'null' !== $qa->answer && null !== $qa->answer)
                        ? $qa->answer : $qa->answer_type;
                }

                $item->agent_polite = '';
                $item->understand_chose_supplier = (isset($qas['6.1.1']))
                    ? $qas['6.1.1'] : '';
                $item->signed_form_call = (isset($qas['7.1.1']))
                    ? $qas['7.1.1'] : '';
                $item->agent_rating = '';
                $item->agent_rating_reason = '';
                $item->agent_gave_toc = '';
                $item->reason_for_choosing = '';

                return $item;
            }
        );

        $recordCount = count($results);

        if ($recordCount == 0) {
            $this->info('No records found. Exiting...');
            return 0;
        }

        // Submit the records in batches of 150
        $apiQueue = array_chunk($results->toArray(), 150);
        $batchNum = 1;
        foreach ($apiQueue as $queue) {

            $payload = $this->buildXmlRequest($queue);

            if (!$this->option('dry-run')) {
                $this->info('Posting batch ' . $batchNum . ' / ' . count($apiQueue));
                $this->sfdcUpsert($client, $payload, $batchNum, count($apiQueue));
            } else {

                $this->info('Dry run. Creating request XML file for batch ' . $batchNum . ' / ' . count($apiQueue) . '...');

                $filename = "NRG - SFDC API - " . str_pad($batchNum, 4, "0", STR_PAD_LEFT) . ".xml";
                $path = public_path('tmp/' . $filename);
                $file = fopen($path, 'w');

                fputs($file, $payload);
                fclose($file);
            }

            $batchNum += 1;
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
            . '<urn:externalIDFieldName>Unique_Field__c</urn:externalIDFieldName>';

        foreach ($data as $row) {

            // The following values are of type 'double'
            // if empty we'll need to convert value to '0'
            // $reasonCode = $this->xmlEncode(trim($row['disposition_label']));
            // if (empty($reasonCode)) {
            //     $reasonCode = '0';
            // }

            $customerSatScore = $this->xmlEncode(trim($row['agent_rating']));
            if (empty($customerSatScore)) {
                $customerSatScore = '0';
            }

            $centerId = $this->xmlEncode(trim($row['vendor_grp_id']));
            if (empty($centerId)) {
                $centerId = '0';
            }

            $svcAddr = '';
            $svcCity = '';
            $svcState = '';
            $svcZip = '';

            $request .=
                '<urn:sObjects>'
                . '<urn1:type>TPV_Report__c</urn1:type>'
                . '<urn1:fieldsToNull></urn1:fieldsToNull>'
                . '<urn1:Id></urn1:Id>'
                . '<urn1:Name>' . $row['confirmation_code'] . '</urn1:Name>'
                . '<urn1:Reason_Customer_Chose_to_Switch__c>' . $this->xmlEncode($row['reason_for_choosing']) . '</urn1:Reason_Customer_Chose_to_Switch__c>'
                . '<urn1:Service_Zip__c>' . $this->xmlEncode(trim($row['service_zip'])) . '</urn1:Service_Zip__c>'
                . '<urn1:Reason_Description__c>' . $this->xmlEncode(trim($row['disposition_reason'])) . '</urn1:Reason_Description__c>'
                . '<urn1:Phone_Carrier__c></urn1:Phone_Carrier__c>' // TODO: Map for Res DTD
                . '<urn1:BTN__c>' . $this->xmlEncode(trim(ltrim($row['btn'], '+1'))) . '</urn1:BTN__c>'
                . '<urn1:Attempt_3__c>' . $this->xmlEncode(trim($row['attempt3'])) . '</urn1:Attempt_3__c>'
                . '<urn1:Attempt_2__c>' . $this->xmlEncode(trim($row['attempt2'])) . '</urn1:Attempt_2__c>'
                . '<urn1:Attempt_1__c>' . $this->xmlEncode(trim($row['attempt1'])) . '</urn1:Attempt_1__c>'
                . '<urn1:Status__c>' . $this->xmlEncode(trim($row['result'])) . '</urn1:Status__c>'
                . '<urn1:Enrollment_Type__c></urn1:Enrollment_Type__c>' // TODO: Map for Retail surveys
                . '<urn1:Used_ECL__c></urn1:Used_ECL__c>' // TODO: Map for Retail surveys
                . '<urn1:Did_Agent_Represent_Supplier__c></urn1:Did_Agent_Represent_Supplier__c>'
                . '<urn1:Reason_Code__c>0</urn1:Reason_Code__c>' // DOUBLE -- Only Map TPV disposition label here.
                . '<urn1:Utility_Acct_Number__c>' . $this->xmlEncode(trim($row['account_number'])) . '</urn1:Utility_Acct_Number__c>'
                . '<urn1:ANI__c>' . $this->xmlEncode(trim(ltrim($row['caller_id'], '+1'))) . '</urn1:ANI__c>'
                . '<urn1:Date__c>' . Carbon::parse($row['interaction_created_at'])->toIso8601ZuluString() . '</urn1:Date__c>'
                . '<urn1:Enrollment_ID__c></urn1:Enrollment_ID__c>' // TODO: Map for Retail surveys
                . '<urn1:Did_Agent_Give_Customer_Collateral__c>' . $this->xmlEncode(trim($row['agent_gave_toc'])) . '</urn1:Did_Agent_Give_Customer_Collateral__c>'
                . '<urn1:Customer_Confirms_Switching_Suppliers__c>' . $this->xmlEncode(trim($row['understand_chose_supplier'])) . '</urn1:Customer_Confirms_Switching_Suppliers__c>'
                . '<urn1:How_Likely__c>' . $this->xmlEncode(trim($row['agent_rating'])) . '</urn1:How_Likely__c>'
                . '<urn1:Verification_Language__c>' . $this->xmlEncode(trim($row['language'])) . '</urn1:Verification_Language__c>'
                . '<urn1:Unique_Field__c>' . $row['event_id'] . '</urn1:Unique_Field__c>'
                . '<urn1:Service_Address_2__c></urn1:Service_Address_2__c>' // TODO: Map for Retail(?), DTD
                . '<urn1:Customer_Last_Name__c>' . $this->xmlEncode(trim($row['customer_last_name'])) . '</urn1:Customer_Last_Name__c>'
                . '<urn1:Service_Address_1__c>' . $this->xmlEncode(trim($row['srvc_address'])) . '</urn1:Service_Address_1__c>'
                . '<urn1:Contact__r>'
                . '<urn1:type>Contact</urn1:type>'
                //. '<urn1:Agent_ID__c>' . $this->xmlEncode(trim($row['survey_rep_id'])) . '</urn1:Agent_ID__c>'
                . '<urn1:Agent_ID__c>1234</urn1:Agent_ID__c>'
                . '</urn1:Contact__r>'
                . '<urn1:Duration__c>' . $row['interaction_time'] . '</urn1:Duration__c>' // DOUBLE
                . '<urn1:Email__c>' . $this->xmlEncode(trim($row['email_address'])) . '</urn1:Email__c>'
                . '<urn1:Did_Customer_Agree_to_Switch_Suppliers__c></urn1:Did_Customer_Agree_to_Switch_Suppliers__c>'
                . '<urn1:Phone_Type__c></urn1:Phone_Type__c>' // TODO: Map? Btn lookup phone type
                . '<urn1:Service_State__c>' . $this->xmlEncode(trim($row['state_abbrev'])) . '</urn1:Service_State__c>'
                . '<urn1:Was_Agent_Polite_Courteous__c>' . $this->xmlEncode(trim($row['agent_polite'])) . '</urn1:Was_Agent_Polite_Courteous__c>'
                . '<urn1:Service_City__c>' . $this->xmlEncode(trim($row['service_city'])) . '</urn1:Service_City__c>'
                . '<urn1:Customer_Sat_Score__c>' . $customerSatScore . '</urn1:Customer_Sat_Score__c>' // DOUBLE
                . '<urn1:Did_Cstmr_Cnfrm_Sig_and_Phone_Call__c>' . $this->xmlEncode(trim($row['signed_form_call'])) . '</urn1:Did_Cstmr_Cnfrm_Sig_and_Phone_Call__c>'
                . '<urn1:Attempt_3_Date__c>' . $row['dt_attempt3'] . '</urn1:Attempt_3_Date__c>'
                . '<urn1:Attempt_2_Date__c>' . $row['dt_attempt2'] . '</urn1:Attempt_2_Date__c>'
                . '<urn1:Other_Feedback_From_Customer__c>' . $this->xmlEncode(trim($row['notes']['feedback'])) . '</urn1:Other_Feedback_From_Customer__c>'
                . '<urn1:Attempt_1_Date__c>' . $row['dt_attempt1'] . '</urn1:Attempt_1_Date__c>'
                . '<urn1:Customer_First_Name__c>' . $this->xmlEncode(trim($row['customer_first_name'])) . '</urn1:Customer_First_Name__c>'
                . '<urn1:Email_Validation__c></urn1:Email_Validation__c>' // TODO: Map for Retail surveys
                . '<urn1:Retail_Location__c></urn1:Retail_Location__c>' // TODO: Map for Retail surveys
                . '<urn1:Customer_Sat_Score_Reason__c>' . $this->xmlEncode(trim($row['agent_rating_reason'])) . '</urn1:Customer_Sat_Score_Reason__c>'
                . '<urn1:CenterID__c>' . $centerId . '</urn1:CenterID__c>' // DOUBLE
                . '<urn1:Operator_ID__c>' . $this->xmlEncode(trim($row['tpv_agent_label'])) . '</urn1:Operator_ID__c>'
                . '<urn1:Utility__c>' . $this->xmlEncode(trim($row['product_utility_name'])) . '</urn1:Utility__c>'
                . '<urn1:Dirty_Vs_Clean__c></urn1:Dirty_Vs_Clean__c>' // TODO: Map for GME Retail and DTD
                . '<urn1:Secondary_First_Name__c></urn1:Secondary_First_Name__c>' // TODO: Map for Retail surveys
                . '<urn1:Secondary_Last_Name__c></urn1:Secondary_Last_Name__c>' // TODO: Map for Retail surveys
                . '<urn1:Mode__c>Offline</urn1:Mode__c>' // TODO: Map Online/Offline (based on TPV lead being found) for DTD surveys
                . '</urn:sObjects>';
        }

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
                "Fault encountered when logging in to NRG's SFDC instance: \n\n"
                    . "URL: " . $this->sfdcLoginUrl[$this->sfdcEnv] . "\n"
                    . "API Response:\n"
                    . $this->xmlEncode($body2),
                ['alex@tpv.com']
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
                ['alex@tpv.com']
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
                ['alex@tpv.com']
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

        // try {
        $res = $client->post(
            $this->sfdcServerUrl,
            [
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

        // } catch (\Exception $e) {
        //     print_r($e->getMessage());
        // }

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
                "Fault encountered when logging in to NRG's SFDC instance: \n\n"
                    . "URL: " . $this->sfdcLoginUrl[$this->sfdcEnv] . "\n"
                    . "API Response:\n"
                    . $this->xmlEncode($body2),
                ['alex@tpv.com']
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
            $requestFilename = "NRG - SFDC API - Request - " . str_pad($batchNum, 4, "0", STR_PAD_LEFT) . ".xml";
            $requestPath = public_path('tmp/' . $requestFilename);
            $requestFile = fopen($requestPath, 'w');

            fputs($requestFile, $payload);
            fclose($requestFile);

            // Errors file
            $errorsFilename = "NRG - SFDC API - Errors - " . str_pad($batchNum, 4, "0", STR_PAD_LEFT) . ".xml";
            $errorsPath = public_path('tmp/' . $errorsFilename);
            $errorsFile = fopen($errorsPath, 'w');

            fputs($errorsFile, $body2);
            fclose($errorsFile);

            $this->sendEmail(
                'Error returned for at least one record in the upsert batch ' . $batchNum . ' / ' . $batchCount . '. See attachment.',
                ['alex@tpv.com'],
                [$requestPath, $errorsPath]
            );

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
