<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientAlert;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\ProviderIntegration;
use App\Models\JsonDocument;
use App\Models\Brand;
use App\Models\Signature;

class VendorLiveEnrollment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vendor:live:enrollments {--brand=} {--debug} {--forever} {--prevDay} {--hoursAgo=} {--vendorCode=} {--redo} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process live enrollments (to vendors)';

    protected $genieBrands = [  '0e80edba-dd3f-4761-9b67-3d4a15914adb', // Residents Energy
                                '77c6df91-8384-45a5-8a17-3d6c67ed78bf', // IDT Energy
                                '872c2c64-9d19-4087-a35a-fb75a48a1d0f', // Townsquare Energy Production
                                'dda4ac42-c7b8-4796-8230-9668ad64f261'  // Townsquare Energy Old Staging
    ];

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
        if ($this->option('brand')) {
            $brand = Brand::find($this->option('brand'));

            if (!$brand) {
                $this->error('Brand specified ' . $this->option('brand') . ' does not exist.');
                exit();                
            }
        } else {
            $this->error('You must specify a brand.');
            exit();
        }

        $selectColumns = [
            'stats_product.*',
            'brand_utilities.service_territory',
            'brand_utilities.utility_label',
            'offices.grp_id AS office_grp_id'
        ];

        $isGenie = false;
        if (in_array($brand->id, $this->genieBrands)) {
            $isGenie = true;

            $selectColumns[] = 'interactions.notes AS interaction_notes';
        }

        $sps = StatsProduct::select(
            $selectColumns
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_utilities',
            function ($join) {
                $join->on(
                    'stats_product.utility_id',
                    'brand_utilities.utility_id'
                )->where(
                    'brand_utilities.brand_id',
                    'stats_product.brand_id'
                );
            }
        )->leftJoin(
            'offices',
            'stats_product.office_id',
            'offices.id'
        )->whereNull(
            'event_product.live_enroll'
        )->where(
            'stats_product.brand_id',
            $brand->id
        );

        if ($this->option('vendorCode')) {
            $sps = $sps->where(
                'stats_product.vendor_code',
                $this->option('vendorCode')
            );
        }

        if (!$this->option('forever')) {
            if ($this->option('prevDay')) {
                $sps = $sps->where(
                    'stats_product.event_created_at',
                    '>=',
                    Carbon::yesterday()
                )->where(
                    'stats_product.event_created_at',
                    '<=',
                    Carbon::today()->add(-1, 'second')
                );
            } else {
                if ($this->option('hoursAgo')) {
                    $sps = $sps->where(
                        'stats_product.event_created_at',
                        '>=',
                        Carbon::now()->subHours($this->option('hoursAgo'))
                    );
                } else {
                    $sps = $sps->where(
                        'stats_product.event_created_at',
                        '>=',
                        Carbon::now()->subHours(48)
                    );
                }
            }
        }

        if ($isGenie) {
            // 2024-09-03-57671 Update: We're joining only to interactions now and populating based on interactions notes instead of alerts
            // because Residents and Townsquare don't have that alert on, and it was causing us to send wrong data
            // to vendors.
            $sps = $sps->leftJoin('interactions', function($join) {
                $join->on('stats_product.event_id', 'interactions.event_id');
                $join->where('interactions.interaction_type_id', 6); // 6 - Digital TPV
            });

            $sps = $sps->whereIn('stats_product.result', ['sale','no sale']);
        }

        $sps = $sps->where(
            'stats_product_type_id',
            1
        );

        // print_r($sps->toSql());
        // $this->info("\n");
        // print_r($sps->getBindings());

        $sps = $sps->get();

        $this->info("Records: " . count($sps));

        switch ($brand->name) {
            case 'Clearview Energy':
            case 'RPA Energy':
                $pi = ProviderIntegration::where(
                    'service_type_id',
                    11
                )->where(
                    'brand_id',
                    $brand->id
                )->first();
                if ($pi) {
                    foreach ($sps as $sp) {
                        $j = JsonDocument::where(
                            'ref_id',
                            $sp->event_product_id
                        )->where(
                            'document_type',
                            'vendor-live-enrollment'
                        )->first();
                        if (!$j) {
                            $body = [
                                'campIndex' => '8',
                                'Username' => $pi->username,
                                'Password' => $pi->password,
                                '1' => $sp->event_id,
                                '2' => $sp->event_product_id,
                                '3' => $sp->event_created_at->format('m/d/y g:i'),
                                '6' => $sp->language,
                                '7' => $sp->channel,
                                '8' => $sp->confirmation_code,
                                '9' => $sp->result,
                                '12' => ($sp->disposition_name) ? $sp->disposition_name : '',
                                '14' => $sp->brand_name,
                                '15' => $sp->vendor_name,
                                '16' => $sp->office_name,
                                '19' => $sp->commodity,
                                '20' => $sp->sales_agent_name,
                                '21' => $sp->sales_agent_rep_id,
                                '27' => $sp->bill_first_name,
                                '29' => $sp->bill_last_name,
                                '34' => ltrim(
                                    trim(
                                        $sp->btn
                                    ),
                                    '+1'
                                ),
                                '35' => ($sp->email_address)
                                    ? $sp->email_address
                                    : '',
                                '36' => $sp->billing_address1,
                                '38' => $sp->billing_city,
                                '39' => $sp->billing_state,
                                '40' => $sp->billing_zip,
                                '51' => $sp->rate_uom,
                                '52' => $sp->product_name,
                                '63' => $sp->product_utility_name,
                                '64' => $sp->account_number1,
                                '100' => 'TBS',
                            ];

                            // $this->info(json_encode($body));
                            // print_r($body);
                            // exit();

                            // $j = JsonDocument::where(
                            //     'ref_id',
                            //     $sp->event_product_id
                            // )->where(
                            //     'document_type',
                            //     'vendor-live-enrollment'
                            // )->first();
                            // if (!$j) {
                            //     $j = new JsonDocument();
                            // }
                            // $j->document_type = 'vendor-live-enrollment';
                            // $j->ref_id = $sp->event_product_id;
                            // $j->document = $body;
                            // $j->save();

                            $client = new \GuzzleHttp\Client(
                                [
                                    'verify' => false,
                                ]
                            );

                            $res = $client->post(
                                'https://apps.terribite.com/tbsmarketingllc/api/SaveTPV',
                                [
                                    'debug' => $this->option('debug'),
                                    'http_errors' => false,
                                    'headers' => [
                                        'Content-Type' => 'text/plain',
                                        'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                    ],
                                    'body' => json_encode($body),
                                ]
                            );

                            $body = $res->getBody();
                            // $this->info($body);
                        }
                    }
                } else {
                    $this->error('Could not find any provider integration credentials.');
                }

                break;

            case 'IDT Energy':
            case 'Residents Energy':
            case 'Townsquare Energy':
                if (!$this->option('vendorCode')) {
                    $this->error("Command parameter 'vendorCode' required!");
                    break;
                }

                $allowed_vendor_codes = ['005', '182', '189', '323', '328', '330', '345'];

                if (!in_array($this->option('vendorCode'), $allowed_vendor_codes)) {
                    $this->error("Invalid vendorCode value '" . $this->option('vendorCode') . "'");
                    break;
                }

                if ($this->option('vendorCode') == '005') {
                    // Genius POST

                    $totalRecords = count($sps);
                    $currentRecord = 1;

                    foreach ($sps as $sp) {

                        $this->info("------------------------------------------------------------");
                        $this->info("[ " . $currentRecord . " / " . $totalRecords . " ]\n");
                        $this->info("  Confirmation Code: " . $sp->confirmation_code);
                        $this->info("  TPV Date: " . $sp->interaction_created_at);
                        $this->info("  TPV Status: " . $sp->result);
                        $this->info("  Acct #: " . $sp->account_number1);
                        $this->info("  Commodity: " . $sp->commodity);
                        $this->info("  Sales Rep ID: " . $sp->sales_agent_rep_id . "\n");

                        // Ignore records with empty account numbers
                        if (empty($sp->account_number1)) {
                            $this->info("  Empty account number. Skipping...\n");
                            $currentRecord++;
                            continue;
                        }

                        // Redo mode skips doc lookup and sets $j to null to indicate a doc was not found.
                        if (!$this->option('redo')) {
                            $this->info("  Performing doc lookup...\n");
                            $j = JsonDocument::where(  // See if this record was already posted
                                'ref_id',
                                $sp->event_product_id
                            )->where(
                                'document_type',
                                'vendor-live-enrollment'
                            )->first();
                        } else {
                            $this->info("  Redo mode. Skipping doc lookup...\n");
                            $j = null;
                        }

                        if (!$j) {
                            $body =
                                'rep_code=' . $sp->sales_agent_rep_id
                                . '&util_type=' . (strtolower($sp->commodity) == 'electric' ? 'E' : (strtolower($sp->commodity) == 'natural gas' ? 'G' : ''))
                                . '&p_date=' . $sp->interaction_created_at->format("m/d/Y H:i:s")
                                . '&acc_number=' . $sp->account_number1
                                . '&status_txt=' . (strtolower($sp->result) == 'sale' ? 'good sale' : strtolower($sp->result));

                            $this->info("  Payload:");
                            $this->info("  " . $body . "\n");

                            // For dry runs, skip client creation and data post. Create a fake response for console output.
                            if ($this->option('dry-run')) {
                                $this->info("  Dry run. Skipping client setup and post...\n");

                                $response = '{"success":true,"message":"Dry Run. This is a fake response"}';;
                            } else {
                                $this->info("  Creating HTTP client...");

                                $client = new \GuzzleHttp\Client(
                                    [
                                        'verify' => false,
                                    ]
                                );

                                $this->info("  Posting data...\n");
                                $res = $client->post(
                                    'https://worknet.geniussales.com/api/sales',
                                    [
                                        'debug' => $this->option('debug'),
                                        'http_errors' => false,
                                        'headers' => [
                                            'Content-Type' => 'application/x-www-form-urlencoded',
                                            'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                        ],
                                        'body' => $body,
                                    ]
                                );

                                $response = $res->getBody();
                                // $this->info($body);
                            }

                            $this->info("  Response:");
                            $this->info("  " . $response . "\n");

                            // Prepare the JSON doc for the log record.
                            $jsonDoc = [
                                "request" => $body,
                                "response" => $response
                            ];

                            $this->info("  JSON Doc:");
                            $this->info("  " . json_encode($jsonDoc) . "\n");

                            // For dry runs we don't want to save the JSON doc, as this will prevent live runs from submitting the TPV record.
                            if ($this->option('dry-run')) {
                                $this->info("  Dry run. JSON doc will NOT be saved.\n");
                            } else {
                                $this->info("  Saving JSON doc...\n");

                                // Save JSON doc to flag this record as 'sent'
                                $j = new JsonDocument();
                                $j->document_type = 'vendor-live-enrollment';
                                $j->ref_id = $sp->event_product_id;
                                $j->document = $jsonDoc;
                                $j->save();
                            }

                            $currentRecord++;
                        }
                    }
                } else {

                    // Terrabite POST

                    foreach ($sps as $sp) {

                        // Documentation for Genie Mappings
                        // https://docs.google.com/document/d/1zKwAVDQiH-PU141beC-X90TRwk6B_3u7/edit?usp=sharing&ouid=105460709878939035679&rtpof=true&sd=true                        

                        // Genie always uses these credentials and URL for ALL their Terribite endpoint for all Vendors, only Mapping[100] changes for some vendors
                        $creds = [
                            "campcode" => "GENIE",
                            "username" => "tpv.com@terribite.com",
                            "password" => "tpv.com"
                        ];
                        $url = 'https://apps.terribite.com/api/SaveTPV';                        

                        // We do this to match the recorded data that we sent to the client
                        $sp_result = (strtolower($sp->result) == 'sale' ? 'good sale' : 'no sale');

                        $j = JsonDocument::where('ref_id', $sp->event_product_id)
                            ->where('document_type', 'vendor-live-enrollment')
                            ->orderBy('created_at','desc')
                            ->first();

                        // Default value, to be changed only in specific circumstances
                        $jd_send = true;

                        if ($j) {
                            // JSON Document may already be parsed, we need it to be an array so convert it only if its a string
                            $jd_parsed = ($j->document && gettype($j->document) == 'string') ? json_decode($j->document) : $j->document;

                            // Check if we need to NOT send a document
                            if ($this->isGenieInvalidToSendJson($jd_parsed, $sp_result)) { $jd_send = false; }
                        }

                        // If Valid to send JSON Data
                        if ($jd_send) {
                            $body = $this->getGenieVendorMappings($creds, $sp);

                            $client = new \GuzzleHttp\Client([ 'verify' => false ]);

                            if ($this->option('dry-run')) {
                                $this->info("  Dry run. JSON doc will NOT be saved.\n");
                            } else {
                                try {
                                    $payload = [
                                        'debug' => $this->option('debug'),
                                        'http_errors' => false,
                                        'headers' => [
                                            'Content-Type' => 'text/plain',
                                            'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                        ],
                                        'body' => json_encode($body)
                                    ];

                                    $res = $client->post($url, $payload);

                                    // Keep in mind you can only call these methods ONCE before you have to REWIND the internal pointer
                                    $res_code = $res->getStatusCode();
                                    $res_message =  $res->getBody()->getContents();                                    
                                }
                                catch(\Exception $e) {
                                    // Get the Status Code and Message from the Error Object
                                    $res_code = $e->getCode();
                                    $res_message =  $e->getMessage();

                                    // Send notification to Mattermost Channel for Monitoring
                                    SendTeamMessage('monitoring', "[VendorLiveEnrollment][$sp->brand_name][$sp->confirmation_code] ERROR: Code: $res_code, Error Message: $res_message, Event Product ID: $sp->event_product_id, Tennant (100): " . $body['100']);                                    
                                }

                                $j = new JsonDocument();
                                $j->document_type = 'vendor-live-enrollment';
                                $j->ref_id = $sp->event_product_id;
                                $j->document = json_encode(['request' => $body, 'response' => ['code' => $res_code, 'data' => $res_message], 'sp_result' => $sp_result]);
                                $j->save();

                                if ($this->option('debug')) {
                                    echo 'Saved: ' . $j->document;
                                }
                            }
                        }

                    }
                }

                break;
        }
    }

    private function isGenieInvalidToSendJson($jd_parsed, $sp_result) {
        // Valid to send if there is no JSON Data ($jd_parsed)
        if (!$jd_parsed) { return false; }
        // Valid to send if JSON Data is not an array or object
        if (!is_array($jd_parsed) && !is_object($jd_parsed)) { return false; }
        // Mappings changed so we can record request and response, this handles when we ONLY put the Body we sent to them in the JSON Data Table

        // This handles JSON Data Shape prior to adding in 'request' and 'response' style
        if (is_array($jd_parsed)) {
            // Dont send status of disposition_reason of Pending (new mapping)
            if (array_key_exists('14', $jd_parsed) && $jd_parsed['14'] == 'pending') { return true; }
            // Dont send status of disposition_reason of Pending (old mapping)
            if (array_key_exists('40', $jd_parsed) && $jd_parsed['40'] == 'pending') { return true; }
            // If data matches what has already been sent, does not need to be sent again so return true to prevent sending data again
            if (array_key_exists('38', $jd_parsed) && $jd_parsed['38'] == $sp_result) { return true; }
        }

        if (is_object($jd_parsed)) {
            if (property_exists($jd_parsed, 'request')) {
                if ($jd_parsed->request) {
                    // Due to numeric keys on payload need to handle as an Array
                    $request = (array)$jd_parsed->request;
                    // New Mapping, if we are in a Pending state on New Mapping, do not send data
                    if (array_key_exists('14', $request) && $request['14'] == 'pending') { 
                        return true; 
                    }
                }
            }
            // Check their Response object
            if (property_exists($jd_parsed, 'response')) {
                // Valid to resend if we do not have data
                if (!property_exists($jd_parsed->response, 'data') || !$jd_parsed->response->data) { return false; }
                // Parse the data
                $data = (gettype($jd_parsed->response->data) == 'string' ? json_decode($jd_parsed->response->data) : $jd_parsed->response->data);

                // Check object validity
                if (is_object($data)) {
                    // If we dont have a Message property in the data, need to resend
                    if (!property_exists($data, 'message')) { return false; }

                    // Message NOT OK, return false, resend data
                    if ($data->message != 'OK') { return false; }
                }
            }

            // sp_result is an added field to the JSON Document which now saves 'request', 'response' and 'sp_result'
            if (property_exists($jd_parsed, 'sp_result')) {
                // If data matches what has already been sent, does not need to be sent again so return true to prevent sending data again
                if ($jd_parsed->sp_result == $sp_result) { return true; }
            }
        }


        // Valid to send data, inverse logic, returning false here allows data to be sent
        return false;
    }

    private function getGenieVendorMappings($creds, $sp)
    {
        // Mappings to send data to Genie's third party, Terribite
        $mappings = [
            'campcode' => $creds['campcode'],
            'Username' => $creds['username'],
            'Password' => $creds['password'],
            '2' => $sp->event_product_id,
            '3' => $sp->event_created_at->format('n-d-Y H:i'), // n-d-Y H:i:s results in 1/25/2024 14:07 where seconds and AM / PM are omitted due to 24 hour format
            '10' => $sp->confirmation_code,                
            '11' => (strtolower($sp->result) == 'sale' ? 'good sale' : 'no sale'),
            '14' => $sp->disposition_reason,
            '16' => $sp->brand_name,
            '21' => $sp->market,
            '22' => $sp->commodity,
            '23' => $sp->sales_agent_name,
            '24' => $sp->sales_agent_rep_id,
            '26' => $sp->billing_city,
            '27' => $sp->billing_state,
            '28' => $sp->billing_zip,
            '38' => $sp->product_name,
            '58' => $sp->bill_first_name,
            '60' => $sp->bill_last_name,
            '65' => ltrim(trim($sp->btn), '+1'),
            '66' => ($sp->email_address ? $sp->email_address : ''),
            '69' => $sp->product_utility_name,
            '77' => $sp->service_address1,
            '78' => $sp->ip_address,
            '79' => null, // Customer IP Address from signature capture
            // Per 2024-07-05-64122, fields 80, 81 and 82 correspond to the Completed On Agents Device alert,
            // per client, if the event did not trigger the alert, we send 'false' in 80 and empty strings on 81 and 82.
            '80' => false,  // Completed On Sales Agent Device
            '81' => '',     // Submitted on Name
            '82' => '',     // Submitted on TSR ID
            '100' => 'PEM', // Default is PEM for a reference for their Tenant, which we refer to as a Vendor
        ];

        // Populate fields 80-82 if alert was triggered
        $notesData = json_decode($sp->interaction_notes, true);
        if($notesData['is_sales_browser'] == 'true') {
            $mappings['80'] = true;
            
            if(isset($notesData['submitter_name'])) {
                $mappings['81'] = $notesData['submitter_name'];
            }

            if(isset($notesData['submitter_tsr_id'])) {
                $mappings['82'] = $notesData['submitter_tsr_id'];
            }
        }

        // Per Ticket # 2024-03-14-22498
        if ($sp->vendor_code == '328') {
            $mappings[100] = 'wca';
        }

        // Per Ticket # 2024-07-10-36432
        if ($sp->vendor_code == '345') {
            $mappings[100] = 'ucm';
        }

        // Per client request, we need to change the Tenant (100) value for vendor 330
        if ($sp->vendor_code == '330') {
            $mappings[100] = 'lvlup';
        }

        // Uncomment to intentionally generate errors
        // $mappings[100] = 'TEST_ERROR12345';

        /*
        // Update this if we need to change the Vendor Code which is Field 100 in the mapping
        if ($sp->vendor_code == '323') {
            $mappings[100] = ''
        }
        */

        // Retrieves customer's signature IP address
        $signature = Signature::where('ref_id', $sp->eztpv_id)
            ->where('signature_type_id', '25')
            ->first();
        if ($signature) {
            $mappings['79'] = long2ip((int)(float)$signature->ip_addr);
        }

        return $mappings;
    }
}
