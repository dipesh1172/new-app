<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use App\Models\ProviderIntegration;
use App\Models\JsonDocument;
use App\Models\Interaction;
use App\Models\CustomFieldStorage;
use App\Models\Brand;

class AtlanticLiveEnroll extends Command
{
    public $accessToken;
    public $posturl = '/services/data/v34.0/composite/tree/TPV__c/';

    private $logger;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlantic:live:enrollments {--skipRecordingCheck} {--nopost} {--debug} {--limit=} {--confirmation_code=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atlantic Live Enrollments (at the interaction level instead of event product)';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->logger = app('logger.enrollment');
    }

    private function log($msg)
    {
        echo $msg;
        $this->logger->info("[AtlanticLiveEnroll] " . $msg);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $brand = Brand::where(
            'name',
            'Atlantic Energy'
        )->first();
        if (!$brand) {
            $this->log("Cannot find Atlantic in the brand table.\n");
            
            exit();
        }

        $env_id = (config('app.env') === 'production') ? 1 : 2;

        $pi = ProviderIntegration::where(
            'brand_id',
            $brand->id
        )->where(
            'env_id',
            $env_id
        )->first();
        if (!$pi) {
            $this->log("Unable to find provider integration information.\n");
            exit();
        }

        if (!$this->option('nopost')) {
            $piconfig = json_decode($pi->notes);
            $provider = new \Stevenmaguire\OAuth2\Client\Provider\Salesforce([
                'clientId' => $piconfig->client_id,    // The client ID assigned to you by the provider
                'clientSecret' => $piconfig->client_secret,   // The client password assigned to you by the provider
                'urlAuthorize' => $pi->hostname . '/services/oauth2/authorize',
                'urlAccessToken' => $pi->hostname . '/services/oauth2/token',
                'urlResourceOwnerDetails' => $pi->hostname . '/services/oauth2/resource',
                'domain' => $pi->hostname,
            ]);

            try {
                $accessToken = $provider->getAccessToken('password', [
                    'username' => $pi->username,
                    'password' => $pi->password,
                ]);
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                // Failed to get the access token
                exit('ipe: ' . $e->getMessage());
            } catch (\Exception $e) {
                exit('e:' . $e->getMessage());
            }

            $this->accessToken = $accessToken->getToken();
        }

        $this->line("AccessToken: " . $this->accessToken);

        $client = new \GuzzleHttp\Client(['verify' => false]);

        $limit = ($this->option('limit')) ? $this->option('limit') : 40;
        $interactions = Interaction::select(
            'interactions.id',
            'interactions.event_id',
            'interactions.created_at',
            'interactions.event_result_id',
            'interactions.tpv_staff_id',
            'interactions.interaction_type_id',
            'interactions.interaction_time',
            'interactions.notes',
            'eztpvs.id AS eztpv_id',
            'eztpvs.ip_addr',
            // Subquery to get event source. This is going pull the event source from first interaction from event. Same as what happens for 'source' field stored in stats product.
            DB::raw('(SELECT es.source FROM interactions i2 JOIN event_sources es ON i2.event_source_id = es.id JOIN events e2 ON i2.event_id = e2.id WHERE e2.id = interactions.event_id ORDER BY i2.created_at LIMIT 1) AS source'),
            // Subquery to get eztpv contract delivery method
            DB::raw('(SELECT eztpv_contract_delivery FROM eztpvs WHERE events.eztpv_id = eztpvs.id) AS eztpv_contract_delivery')
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->leftJoin(
            'eztpvs',
            'events.eztpv_id',
            'eztpvs.id'
        )->where(
            'events.brand_id',
            $brand->id
        );

        if ($this->option('confirmation_code')) {
            $this->log("Resetting " . $this->option('confirmation_code') . " enrolled to NULL\n");
            $interactions = $interactions->where(
                'events.confirmation_code',
                $this->option('confirmation_code')
            );
        } else {
            // retrieve historical records with no-enrollment status
            $interactions = $interactions->whereNull(
                'interactions.enrolled'
            );
        }

        $interactions = $interactions->where(
            'interactions.created_at',
            '<=',
            Carbon::now('America/Chicago')->subMinutes(15)
        )->whereIn(
            'interactions.interaction_type_id',
            [
                1, // call_inbound
                2, // call_outbound
                3, // eztpv
                6, // digital
                88, // qa_update
                99, // status_update
            ]
        )->orderBy(
            'interactions.created_at',
            'desc'
        )->with(
            'interaction_type',
            'tpv_agent',
            'recordings',
            'event',
            'event.documents.uploads',
            'event.customFieldStorage',
            'event.customFieldStorage.customField',
            'event.vendor',
            'event.sales_agent',
            'event.sales_agent.user',
            'event.products.rate.product',
            'event.products.rate.utility',
            'event.products.rate.utility.identifiers',
            'event.products.rate.utility.utility',
            'event.products.serviceAddress',
            'event.products.billingAddress',
            'event.products.identifiers',
            'event.products.identifiers.utility_account_type',
            'event.phone',
            'event.phone.phone_number',
            'event.email',
            'event.email.email_address'
        )->limit($limit)->get();

        if ($this->option('debug')) {
            // print_r($interactions->toArray());
        }

        if (!$interactions || $interactions->count() === 0) {
            $this->log("No interactions were found.\n");
            exit();
        }

        foreach ($interactions as $interaction) {
            if (isset($interaction->event->products) && $interaction->event->products->count() > 0) {
                $records = [];
                $record = [];
                if ($interaction->event->customFieldStorage !== null) {
                    foreach ($interaction->event->customFieldStorage as $customField) {
                        switch ($customField->customField->output_name) {
                            case 'marketer_account_number':
                                $record['TPV_MAN__c'] = $customField->value;
                                break;

                            case 'flow_status':
                                $record['Flow_Status__c'] = $customField->value;
                                break;

                            case 'call_direction':
                                $record['TM_Call_Direction__c'] = $customField->value;
                                break;
                        }
                    }
                }

                $this->log("Interaction: #" . $interaction->id . "\n");

                $record['Sales_Vendor_Licensee__c'] = (isset($interaction->event)
                    && isset($interaction->event->vendor)
                    && isset($interaction->event->vendor->name))
                    ? $interaction->event->vendor->name
                    : null;
                $record['IP_Address__c'] = (isset($interaction->ip_addr))
                    ? $interaction->ip_addr
                    : null;

                $this->log("From IP Address: " . $record['IP_Address__c'] . "\n");

                switch ($interaction->event->channel_id) {
                    case 2:
                        // TM - Client requested TM to be displayed as Inbound Telesales.
                        $channel = 'Inbound Telesales';
                        break;
                    case 3:
                        $channel = 'Retail';
                        break;
                    default:
                        $channel = 'DTD';
                        break;
                }

                $this->log("From Channel: " . $channel . "\n");

                $record['Sales_Channel__c'] = $channel;
                $record['Allow_SMS__c'] = false;
                $record['Call_Duration__c'] = number_format($interaction->interaction_time, 2);
                $record['New_Enrollment__c'] = 'Yes';
                $record['Preferred_Language__c'] = $interaction->event->language_id == 1 ? 'English' : 'Spanish';

                $this->log("Preferred Language: " . $record['Preferred_Language__c'] . "\n");

                $record['Phone__c'] = @str_replace(
                    '+1',
                    '',
                    $interaction->event->phone->phone_number->phone_number
                );

                if(isset($interaction->event->email) && isset($interaction->event->email->email_address)) {
                    $record['Email__c'] = $interaction->event->email->email_address->email_address;
                }

                $this->log("Contact phone: " . $record['Phone__c'] . "\n");

                $sales_agent = $interaction->event->sales_agent;
                if ($sales_agent) {
                    $record['Rep_Full_Name__c'] = $sales_agent->user->first_name
                        . ' ' . $sales_agent->user->last_name;
                    $record['Rep_ID__c'] = @$interaction->event->sales_agent->tsr_id;
                } else {
                    $record['Rep_Full_Name__c'] = null;
                    $record['Rep_ID__c'] = null;
                }


                $this->log("Contact Full Name: " . $record['Rep_Full_Name__c'] . "\n");

                $record['TPV_Agent_First_Name__c'] = @$interaction->tpv_agent->first_name;
                $record['TPV_Agent_Last_Name__c'] = @$interaction->tpv_agent->last_name;
                $record['TPV_Agent_ID__c'] = @$interaction->tpv_agent->username;
                $record['TPV_Conformation_Number__c'] = $interaction->event->confirmation_code;
                $record['TPV_File_Link__c'] = (isset($interaction->recordings)
                    && isset($interaction->recordings[0])
                    && isset($interaction->recordings[0]->recording))
                    ? config('services.aws.cloudfront.domain') . '/' . $interaction->recordings[0]->recording
                    : null;

                if ($interaction->event->channel_id !== 3) {
                    if (
                        !$this->option('skipRecordingCheck')
                        && $interaction->event_result_id === 1
                        && $record['TPV_File_Link__c'] === null
                        && $interaction->interaction_type_id !== 6 // Digital interactions don't have recordings
                    ) {
                        $this->log("Good Sale with no recording.  Skipping for now...\n");
                        continue;
                    }

                    $record['Electronic_TPV_Summary__c'] = null;
                } else {
                    $record['Electronic_TPV_Summary__c'] = config('app.urls.clients')
                        . '/summary/' . $interaction->eztpv_id;
                }

                $event_result = null;
                switch ($interaction->event_result_id) {
                    case 1:
                        $event_result = 'Good Sale';
                        break;
                    default:
                        $event_result = 'No Sale';
                }

                $this->log("Event Result: " . $event_result . "\n");

                $sendDate = new Carbon($interaction->created_at->format('Y-m-d H:i:s'), 'America/Chicago');

                $record['TPV_Providor__c'] = 'Focus by TPV.com';
                $record['TPV_Sale_Date__c'] = $sendDate->setTimezone('America/New_York')->toIso8601String();
                $record['TPV_Sale_Date_Time__c'] = $sendDate->setTimezone('America/New_York')->toIso8601String();
                $record['TPV_Sale_Outcome__c'] = $event_result;
                $record['TPV_Type__c'] = @$interaction->interaction_type->name;
                $record['TPV_CLID_ANI__c'] = ($interaction->notes && isset($interaction->notes['ani']))
                    ? str_replace(
                        '+1',
                        '',
                        $interaction->notes['ani']
                    )
                    : null;

                foreach ($interaction->event->products as $product) {

                    $this->log("=> Dive In Product : #" . $product->id . "\n");

                    $productCustomFields = CustomFieldStorage::where('event_id', $interaction->event->id)->where('product_id', $product->id)->with('customField')->get();
                    $comm_matrix_rate = null;
                    $comm_matrix_pricing_term = null;
                    foreach ($productCustomFields as $item) {
                        // The assumption here is that we'll either see the elec or gas version
                        // of the custom fields; never both.
                        if ($item->customField->output_name === 'comm_matrix_pricing_term_ele') {
                            $comm_matrix_pricing_term = trim($item->value);
                        }
                        if ($item->customField->output_name === 'comm_matrix_pricing_term_gas') {
                            $comm_matrix_pricing_term = trim($item->value);
                        }
                        if ($item->customField->output_name === 'comm_matrix_rate_ele') {
                            $comm_matrix_rate = trim($item->value);
                        }
                        if ($item->customField->output_name === 'comm_matrix_rate_gas') {
                            $comm_matrix_rate = trim($item->value);
                        }
                    }
                    if (
                        isset($product->billingAddress)
                        && isset($product->billingAddress->address)
                        && isset($product->billingAddress->address->line_1)
                    ) {
                        $record['Billing_City__c'] = $product->billingAddress->address->city;
                        $record['Billing_County__c'] = @$product->billingAddress->address->zipExtended->county;
                        $record['Billing_State__c'] = $product->billingAddress->address->state_province;
                        $record['Billing_Zip__c'] = $product->billingAddress->address->zip;
                        $record['Billing_Street__c'] = $product->billingAddress->address->line_1 . ' ' . $product->billingAddress->address->line_2;
                    } else {
                        $record['Billing_City__c'] = $product->serviceAddress->address->city;
                        $record['Billing_County__c'] = @$product->serviceAddress->address->zipExtended->county;
                        $record['Billing_State__c'] = $product->serviceAddress->address->state_province;
                        $record['Billing_Zip__c'] = $product->serviceAddress->address->zip;
                        $record['Billing_Street__c'] = $product->serviceAddress->address->line_1 . ' ' . $product->serviceAddress->address->line_2;
                    }

                    $record['attributes'] = [
                        'type' => 'TPV__c',
                        'referenceId' => $product->id . '-' . $interaction->id,
                    ];

                    $record['Business_Type__c'] = ($product->market_id === 1)
                        ? 'Residential' : 'Commercial';
                    $record['Auth_First_Name__c'] = $product->auth_first_name;
                    $record['Auth_Last_Name__c'] = $product->auth_last_name;

                    if ($product->company_name) {
                        $record['Commercial_Business_Name__c'] = $product->company_name;
                    }

                    $documents = null;
                    if (isset($product->event) && isset($product->event->documents)) {
                        for ($i = 0; $i < count($product->event->documents); $i++) {
                            if (
                                isset($product->event->documents[$i])
                                && $product->event->documents[$i]->preview_only == 0
                                && isset($product->event->documents[$i]->uploads)
                                && isset($product->event->documents[$i]->uploads->filename)
                                && $product->event->documents[$i]->uploads->deleted_at === null
                            ) {
                                $documents .= config('services.aws.cloudfront.domain')
                                    . '/' . $product->event->documents[$i]->uploads->filename . ",";
                            }
                        }
                    }

                    $record['Contract_Link_Raw__c'] = rtrim($documents, ",");

                    $this->log("=> Contract_Link_Raw__c : #" . $record['Contract_Link_Raw__c'] . "\n");

                    $this->log("=> Source : " . (isset($interaction->source) && $interaction->source ? $interaction->source : "NULL") . "\n");

                    $this->log("=> EZTPV Contract Delivery : " . (isset($interaction->eztpv_contract_delivery) && $interaction->eztpv_contract_delivery ? $interaction->eztpv_contract_delivery : "NULL") . "\n");

                    if (
                        empty($record['Contract_Link_Raw__c']) &&
                        $channel !== 'TM' &&
                        $interaction->event_result_id === 1 &&
                        !empty($interaction->eztpv_contract_delivery) &&
                        $interaction->source === 'EZTPV'
                    ) {
                        $this->log(sprintf("Good sell without expected contract yet.  Skipping for now...\n\tChannel: %s\n\tContract delivery: %s\n\tSource: %s\n\tConfirmation code: %s\n", $channel, $interaction->eztpv_contract_delivery, $interaction->source, $interaction->event->confirmation_code));
                        continue;
                    }

                    if ($comm_matrix_pricing_term != null) {
                        $record['Contract_Length__c'] = $comm_matrix_pricing_term;
                    } else {
                        $record['Contract_Length__c'] = @$product->rate->product->term;
                    }
                    $record['Customer_First_Name__c'] = (isset($product->bill_first_name))
                        ? $product->bill_first_name
                        : $product->auth_first_name;
                    $record['Customer_Last_Name__c'] = (isset($product->bill_last_name))
                        ? $product->bill_last_name
                        : $product->auth_last_name;
                    $record['TPV_Utility__c'] = @$product->rate->utility->utility->name;

                    foreach ($product->identifiers as $identifier) {
                        $uanT = 1;
                        foreach ($product->rate->utility->identifiers as $utId) {
                            if ($utId->utility_account_type_id === $identifier->utility_account_type_id) {
                                $uanT = $utId->utility_account_number_type_id;
                            }
                        }

                        switch ($uanT) {
                            case 2:
                                $record['Account_Number_2__c'] = @$identifier->identifier;
                                break;
                            case 3:
                                $record['Name_Key__c'] = mb_strtoupper(@$identifier->identifier);
                                break;
                            default:
                                $record['Utility_Account_Number__c'] = @$identifier->identifier;
                        }
                    }

                    if (
                        !isset($record['Utility_Account_Number__c'])
                        && isset($record['Account_Number_2__c'])
                    ) {
                        $record['Utility_Account_Number__c'] = $record['Account_Number_2__c'];
                        unset($record['Account_Number_2__c']);
                    }

                    $record['Name'] = $interaction->event->confirmation_code;
                    $record['Product_Code__c'] = $product->rate->product->name;
                    $record['Program_Code__c'] = $product->rate->program_code;
                    $record['Rate__c'] = @$product->rate->external_rate_id;

                    if ($comm_matrix_rate != null) {
                        $record['Rate_Value__c'] = $comm_matrix_rate;
                    }

                    $record['Service_type__c'] = ($product->event_type_id === 1)
                        ? 'Electric'
                        : 'Gas';
                    $record['Interaction_ID__c'] = $interaction->id
                        . '-' . ($product->event_type_id === 1
                            ? 'Electric'
                            : 'Gas')
                        . '-' . $record['Utility_Account_Number__c'];

                    $this->log("=> Interaction_ID__c : #" . $record['Interaction_ID__c'] . "\n");

                    ksort($record);

                    $records['records'][] = $record;
                }
            }

            if ($this->option('debug') && isset($records)) {
                info(print_r($records, true));
                $this->log(json_encode($records) . "\n");
            }

            if (!$this->option('nopost')) {
                if (!empty($records)) {
                    try {
                        $res = $client->post(
                            $pi->hostname . $this->posturl,
                            [
                                'verify' => false,
                                'debug' => $this->option('debug'),
                                'headers' => [
                                    'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                    'Accept' => 'application/json',
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer ' . $this->accessToken,
                                ],
                                'body' => json_encode($records),
                            ]
                        );
                        if (200 == $res->getStatusCode() || 201 == $res->getStatusCode()) {
                            $body = $res->getBody();
                            $response = json_decode($body, true);

                            if ($this->option('debug')) {
                                print_r($response);
                            }

                            $jd = new JsonDocument();
                            $jd->ref_id = $interaction->id;
                            $jd->document = [
                                'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                                'response' => $response,
                                'response-headers' => $res->getHeaders(),
                                'request-data' => $records
                            ];
                            $jd->document_type = 'atlantic-live-enroll';
                            $jd->save();

                            $interaction->enrolled = $response['results'][0]['id'];
                            $interaction->save();
                        } else {
                            $jd = new JsonDocument();
                            $jd->ref_id = $interaction->id;
                            $jd->document = [
                                'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                                'response' => $res->getBody(),
                                'response-headers' => $res->getHeaders(),
                                'request-data' => $records
                            ];
                            $jd->document_type = 'atlantic-live-enroll';
                            $jd->save();
                        }
                    } catch (\Exception $e) {
                        $this->log('!!Exception: ' . $e->getMessage());
                        $jd = new JsonDocument();
                        $jd->ref_id = $interaction->id;
                        if ($e instanceof RequestException && $e->hasResponse()) {
                            $res = $e->getResponse();
                            $this->log('!!Response: ' . Psr7\str($res));

                            $jd->document = [
                                'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                                'response' => $res->getBody(),
                                'response-headers' => $res->getHeaders(),
                                'request-data' => $records
                            ];
                        } else {
                            $jd->document = [
                                'error' => $e->getMessage(),
                                'code' => $e->getCode(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'request-data' => $records
                            ];
                        }
                        $jd->document_type = 'atlantic-live-enroll';
                        $jd->save();
                    }
                } else {
                    $jds = JsonDocument::where('ref_id', $interaction->id)->where('document', json_encode(['error' => 'No Records']))->first();
                    if ($jds === null) {
                        $jd = new JsonDocument();
                        $jd->ref_id = $interaction->id;
                        $jd->document = [
                            'error' => 'No Records'
                        ];
                        $jd->document_type = 'atlantic-live-enroll';
                        $jd->save();
                    } else {
                        $jds->updated_at = now();
                        $jds->save();
                    }
                }
            }
        }
    }
}
