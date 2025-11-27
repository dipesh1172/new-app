<?php

namespace App\Console\Commands\CleanChoiceEnergy;

use Illuminate\Console\Command;

use Carbon\Carbon;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response as HttpResponse;

use App\Models\Brand;
use App\Models\Event;
use App\Models\CustomFieldStorage;
use App\Models\Interaction;
use App\Models\JsonDocument;
use App\Models\ProviderIntegration;
use App\Models\StatsProduct;

class CleanChoiceLiveEnroll extends Command
{
    const BRAND_ID           = '37cb9600-76e7-45cd-b24b-d0e2ccff8032';
    const PI_SERVICE_TYPE_ID = 47; // Clean Choice TPVSTatus API;
    const PI_TYPE_ID         = 2;  // API
    const EVENT_RESULT_SALE    = 1;
    const EVENT_RESULT_NO_SALE = 2;
    const INTERACTION_TYPE_INBOUND_CALL  = 1;
    const INTERACTION_TYPE_OUTBOUND_CALL = 2;
    const INTERACTION_TYPE_DIGITAL       = 6;

    public $providerIntegration;
    public $httpClient;
    public $accessToken;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanchoice:live:enrollments
        {--confirmation-code=}    
        {--overwrite}
        {--debug}
        {--forever}
        {--show-sql}
        {--fake-http}
        {--hours-ago=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean Choice Energy Live Enrollments (at the event level instead of event product)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->httpClient = new HttpClient(['verify' => false]);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("Running in " . config('app.env'));

        // Retrieve brand record
        $brand = $this->getBrand();

        if (!$brand) {
            $this->error("Cannot find Clean Choice Energy in the brands table.");
            exit -1;
        }

        // Retrieve Bearer tokens from Salesforce.
        // Cleansky chooses which environment to send the record to via a custom field value.
        // So generated tokens for both prod and UAT.
        $prodTokenResult = $this->getToken('prod');
        $uatTokenResult  = $this->getToken('uat');

        if($prodTokenResult->result != "success") {
            $this->error($prodTokenResult->message);
            exit -1;
        }

        if($uatTokenResult->result != "success") {
            $this->error($uatTokenResult->message);
            exit -1;
        }

        $this->accessToken['prod'] = $prodTokenResult->data->access_token;
        $this->accessToken['uat']  = $uatTokenResult->data->access_token;

        // Retrieve data we need to send to API
        $data = $this->getData();

        // Process the records
        foreach ($data as $r) {

            // Parse custom fields to determine if record should be submitted to prod or UAT endpoint.
            // We're looking for a value of 'Prod' in custom field 'environment'.
            // If the custom field contains some other value or doesn't exist, UAT endpoint should be used.
            $cfsEnv = CustomFieldStorage::select(
                'custom_fields.name',
                'custom_field_storages.value'
            )->join('custom_fields', 'custom_field_storages.custom_field_id', 'custom_fields.id')
            ->where('custom_field_storages.event_id', $r->event_id)
            ->where('custom_fields.output_name', 'environment')
            ->whereNull('custom_field_storages.product_id')
            ->first();

            $env = 'uat'; // Default it, in case no custom fields were found

            if($cfsEnv && $cfsEnv->value) {
                $env = $cfsEnv->value;

                $env = ($env && in_array(strtolower($env), ['prod', 'production']) ? 'prod' : 'uat');
            }

            // Map TPV result from Sale/No Sale to Pass, Fail, or Incomplete.
            // Check for good sale first, and map to 'Pass'.
            $status = ($r->event_result_id == self::EVENT_RESULT_SALE ? 'Pass' : 'Fail');

            // For no sales, we need to map Fail or Incomplete.
            // Fail - All no sales that are not Digital TPVs, or Digital TPVs with a final no sale reason.
            // Incomplete - Digital TPVs that have a 'Pending' no sale reason.
            // Here, we'll check if a 'Fail' status stays as 'Fail', or needs to be changed to 'Incomplete'
            if(
                $status == "Fail"
                && strtolower($r->interaction_type_id) == self::INTERACTION_TYPE_DIGITAL
                && strtolower($r->reason) == "pending"
            ) {
                $status = "Incomplete";
            }

            // Build request URL
            $url = $this->getUrl($env);

            // Build request body
            $body = [
                'conf_no' => $r->confirmation_code,
                'status' => $status,
                'description' => $r->reason
            ];
            $interaction = Interaction::where('id', $r->interaction_id)->first();

            //Count previous Sales for the same interaction
            $PreviousSaleInteraction = Interaction::where('event_id', $interaction->event_id)
                ->where('id','!=',$r->interaction_id)
                ->where('event_result_id',1)
                ->first();
            $PreviousEventID="";

            if(!$PreviousSaleInteraction){

                $PreviousSaleEvent = Event::select('events.id')
                    ->join('event_product', 'event_product.event_id', 'events.id')
                    ->join('event_product_identifiers', 'event_product_identifiers.event_product_id', 'event_product.id')
                    ->join('interactions', 'interactions.event_id', 'events.id')
                    ->where('events.brand_id',self::BRAND_ID)
                    ->where('events.id','!=',$r->event_id)
                    ->where('event_result_id',1)
                    ->where('event_product_identifiers.identifier',$r->identifier)
                    ->first();

                if ($PreviousSaleEvent) {
                    $PreviousEventID=$PreviousSaleEvent->eventID;
                }

            }
            else{
                $PreviousEventID=$PreviousSaleInteraction->event_id;
            }

            // Make the API call only if the account number was not previously enrolled
            if ($PreviousEventID===""){
                $res = $this->httpPost($url, $body, $this->accessToken[$env]);

                // Result to store in live_enroll file for all event_products related to the confirmation code being processed
                $result = ($res->result == 'success' ? 'Success' : 'Error');

                // Change result to 'Success - Pending' if this was for a No Sale Digital TPV with a 'Pending' disposition
                if(
                    $res->result == 'success'
                    && strtolower($r->event_result_id) == self::EVENT_RESULT_NO_SALE
                    && strtolower($r->interaction_type_id) == self::INTERACTION_TYPE_DIGITAL
                    && strtolower($r->reason) == 'pending'
                ) {
                    $result = 'Success - Pending';
                }

            }else{
                $result = 'Skipped - Previously enrolled';
                $res='';
            }


            // Update result on event product records related to this confirmation code


            $interaction->enrolled = $result;
            $interaction->save();

            // Also log in json_documents, for reference
            try {
                $log = [
                    'ConfirmationCode' => $r->confirmation_code,
                    'Environment' => $env,
                    'TPVStatusURL' => $url,
                    'TPVStatusResult' => $result,
                    'TPVStatusRequest' => $body,
                    'TPVStatusResponse' => $res,
                    'PreviousEventID' => $PreviousEventID
                ];

                $jd = new JsonDocument();

                $jd->document_type = 'clean-choice-live-enroll';
                $jd->ref_id = $r->confirmation_code;
                $jd->document = $log;

                $jd->save();
            } catch (\Exception $e) {
                $this->error('Error saving JSON doc log for confirmation code ' . $r->confirmation_code);
            }
        }
    }

    /**
     * Retrieve enrollment data from database
     */
    public function getData()
    {
        // Determine if custom or default number of hours should be used for date range
        $hoursAgo = ($this->option('hours-ago') ? $this->option('hours-ago') : 48);

        $data = Interaction::select(
            'interactions.created_at',
            'interactions.id AS interaction_id',
            'interactions.interaction_type_id',
            'events.id AS event_id',
            'events.confirmation_code',           
            'interactions.enrolled',            
            'interactions.event_result_id',
            'dispositions.reason',            
            'vendors.vendor_label',
            'vendors.live_enroll_enabled'
        )->leftJoin('events', 'interactions.event_id', 'events.id')
        ->leftJoin('dispositions', 'interactions.disposition_id', 'dispositions.id')
        ->leftJoin('vendors', function($join) {
            $join->on('events.brand_id', 'vendors.brand_id');
            $join->on('events.vendor_id', 'vendors.vendor_id');
        })
        ->where('events.brand_id', self::BRAND_ID)
        ->whereIn('interactions.event_result_id', [self::EVENT_RESULT_SALE, self::EVENT_RESULT_NO_SALE])
         ->whereIn('interactions.interaction_type_id', [self::INTERACTION_TYPE_INBOUND_CALL, self::INTERACTION_TYPE_OUTBOUND_CALL, self::INTERACTION_TYPE_DIGITAL])
        ->where('vendors.live_enroll_enabled', 1);

        if(!$this->option('overwrite')) {

            $data = $data->where(function($query) {
                $query->whereNull('interactions.enrolled') // Only pull in records that have not yet been submitted, or...
                    ->orWhere('interactions.enrolled', 'Success - Pending'); // Have been submitted with pending status, so we can see if a final disposition has been selected and we need to submit it again.
            });
        }

        // If confirmation code is provided, ignore date filter
        if ($this->option('confirmation-code')) {
            $data = $data->where(
                'events.confirmation_code',
                $this->option('confirmation-code')
            );
        } else {

            // If 'forever' arg was provided, ignore date filter
            if (!$this->option('forever')) {                
                $data = $data->where('interactions.created_at', '>=', Carbon::now()->subHours($hoursAgo));
            }
        }

        if($this->option('show-sql')) {
            $this->info("QUERY:");
            $this->info($data->toSql());
            $this->info("BINDINGS:");
            print_r($data->getBindings());
        }

        $data = $data->orderBy('interactions.created_at')->get();

        return $data;
    }

    /**
     * Retrieve the brand record from database
     */
    public function getBrand()
    {
        $brand = Brand::find(self::BRAND_ID)->first();        

        return $brand;
    }

    /**
     * Retrieve the provider integration record from database
     */
    public function getProviderIntegration($envId)
    {
        return ProviderIntegration::where(
            'brand_id',
            self::BRAND_ID
        )->where(
            'service_type_id',
            self::PI_SERVICE_TYPE_ID
        )->where(
            'provider_integration_type_id',
            self::PI_TYPE_ID
        )->where(
            'env_id',
            $envId
        )->first();
    }

    /**
     * Convenience function for finding the value of a specified custom field from the custom fields array.
     */
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

    /**
     * getToken()
     *
     * Gets credentials for the specified environment, then calls the Salesforce OAuth API to get a Bearer token
     * for subsequent API calls.
     */
    protected function getToken($env)
    {
        // Fake the response?
        if($this->option('fake-http')) {
            return $this->newResult(
                'success',
                '',
                (object)[
                    'access_token' => 'xyz',
                    'instance_url' => null,
                    'id' => null,
                    'token_type' => 'Bearer',
                    'issued_at' => time(),
                    'signature' => null
                ]
            );
        }

        $env   = (strtolower($env) == 'prod' ? 'prod' : 'uat');
        $envId = (strtolower($env) == 'prod' ? 1 : 2);

        try {
            // Retrieve API settings from provider_integrations table
            $this->providerIntegration[$env] = $this->getProviderIntegration($envId);

            if (!$this->providerIntegration) {
                return $this->newResult('error', 'Unable to find Clean Choice TPVStatus API provider integration record.');
            }

            // Parse JSON in provider_integration notes field to get consumer key/secret
            $keys = json_decode($this->providerIntegration[$env]->notes);
            
            if(!$keys) {
                return $this->newResult('error', 'Unable parse provider integration notes value for consumer key and secret');
            }

            // Build request URL
            $url = $this->providerIntegration[$env]->hostname . '/services/oauth2/token?'
                . "client_id={$keys->consumer_key}"
                . "&client_secret={$keys->consumer_secret}"
                . "&username={$this->providerIntegration[$env]->username}"
                . "&password={$this->providerIntegration[$env]->password}"
                . '&grant_type=password';

            // Make the API call
            $res = $this->httpClient->post($url);

            return $this->newResult('success', '', json_decode($res->getBody()->getContents()));

        } catch (\Exception $e) {
            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /**
     * httpPost()
     *
     * Convenience function for making an HTTP GET request
     */
    protected function httpPost($url, $data, $token)
    {
        // Fake the response?
        if($this->option('fake-http')) {
            return $this->newResult(
                'success',
                '',
                (object)["Status" => true, "Message" => "Test. API call skipped."]
            );
        }
        
        // Set up options
        $options = [
            "headers" => [
                "Authorization" => "Bearer " . $token,
                "Content-Type"  => "application/json"
            ],
        ];

        // Include body if data was provided. Not all POSTS will have data (ex, data provided as search query params)
        if($data) {
            $options["body"] = json_encode($data);
        }

        try {
            $res = $this->httpClient->post(
                $url,
                $options
            );

            return $this->newResult("success", "", json_decode($res->getBody()->getContents()));

        } catch (ServerException $e) {
            return $this->newResult("error", $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (ClientException $e) {
            return $this->newResult("error", $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (\Exception $e) {
            return $this->newResult("error", $e->getMessage(), $e);
        }
    }

    /**
     * Builds the URL for the data post
     */
    protected function getUrl($env)
    {
        // Fake the URL?
        if($this->option('fake-http')) {
            return 'https://localhost/services/apexrest/TPVStatus';
        }

        return $this->providerIntegration[$env]->hostname . '/services/apexrest/TPVStatus';
    }

    /**
     * newResult()
     *
     * Convenience function for creating a simple result object
     */
    protected function newResult($result, $message = "", $data = null)
    {
        return (object) [
            "result"  => $result,
            "message" => $message,
            "data"    => $data
        ];
    }
}
