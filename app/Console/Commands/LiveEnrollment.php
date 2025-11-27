<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use Exception;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\ProviderIntegration;
use App\Models\Lead;
use App\Models\JsonDocument;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\CustomFieldStorage;
use App\Models\BrandEnrollmentFile;

class LiveEnrollment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live:enrollments
        {--brand=}
        {--confirmation_code=}
        {--overwrite}
        {--debug}
        {--forever}
        {--hoursAgo=}
        {--showJSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process live enrollments';

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
        $time_start = microtime(true);

        if (App::environment(['production'])) {
            $env_id = 1;
        } else {
            $env_id = 2;
        }

        $les = BrandEnrollmentFile::select(
            'brand_enrollment_files.brand_id',
            'brands.name',
            'brand_enrollment_files.live_enroll_sale_types'
        )->leftJoin(
            'brands',
            'brand_enrollment_files.brand_id',
            'brands.id'
        )->where(
            'brand_enrollment_files.live_enroll',
            1
        );

        if ($this->option('brand')) {
            $les = $les->where(
                'brands.id',
                $this->option('brand')
            );
        }

        $les = $les->get();

        if ($this->option('debug')) {
            echo 'Time to lookup eligible brands: ' . (microtime(true) - $time_start) . " second(s) \n";
        }

        $time_start = microtime(true);
        // print_r($les->toArray());
        // exit();

        foreach ($les as $le) {
            printf("The selected brand is: %s\n", $le->name);

            $sps = Event::select(
                'events.id',
                'event_product.id AS event_product_id',
                'events.confirmation_code',
                'events.brand_id',
                'events.vendor_id',
                'events.created_at',
                'event_product.live_enroll',
                'events.external_id',
                'stats_product.product_time',
                'stats_product.interaction_time',
                'stats_product.account_number1',
                'stats_product.id AS stats_product_id',
                'stats_product.lead_id AS stats_product_lead_id',
                'stats_product.result',
                'stats_product.disposition_reason'
            )->leftJoin(
                'event_product',
                'events.id',
                'event_product.event_id'
            )->leftJoin(
                'stats_product',
                'event_product.id',
                'stats_product.event_product_id'
            )->whereNull(
                'stats_product.deleted_at'
            )->where(
                'events.brand_id',
                $le->brand_id
            )->whereNull(
                'event_product.deleted_at'
            );


            if ($le->name == 'Santanna Energy Services') {
                $sps = $sps->whereIn(
                    'stats_product.result',
                    ['Sale', 'No Sale']
                );
            } else {
                $sps = $sps->where(
                    'stats_product.result',
                    'Sale'
                );
            }

            if (!$this->option('overwrite')) {
                $sps = $sps->whereNull(
                    'event_product.live_enroll'
                );
            }

            if ($this->option('confirmation_code')) {
                $sps = $sps->where(
                    'events.confirmation_code',
                    $this->option('confirmation_code')
                );
            } else {
                if (!$this->option('forever')) {
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

            $sps = $sps->groupBy(
                'event_product.id'
            )->orderBy(
                'events.created_at',
                'desc'
            )->get();

            // print_r($sps->toArray());
            // exit();

            if ($this->option('debug')) {
                echo 'Time to lookup sale data records: ' . (microtime(true) - $time_start) . " second(s) \n";
            }

            switch ($le->name) {
                case 'Santanna Energy Services':

                    // Get API info
                    $pi = ProviderIntegration::where(
                        'service_type_id',
                        99
                    )->where(
                        'provider_integration_type_id',
                        2
                    )->where(
                        'brand_id',
                        $le->brand_id
                    )->where('env_id', $env_id)->first();

                    if ($pi) {

                        foreach ($sps as $spi) {

                            $sp = StatsProduct::where('event_product_id', $spi->event_product_id)->first();

                            // Ignore No Sale 'Pending' status records
                            if (strtolower($sp->result) === 'no sale' && strtolower($sp->disposition_reason) === 'pending') {
                                continue;
                            }

                            // API submission
                            $this->info("Processing data for API post... ");
                            $this->info("URL: " . $pi->hostname);

                            // Get the RIN from custom field storage.
                            $rin = $this->getCustomFieldValue('rin', $sp->event_id, $sp->event_product_id);

                            if (!$rin) { // Fallback. Use the RIN stored in event->external_id. We won't have a unique RIN per account this way, but better than nothing.
                                $rin = $spi->external_id;
                            }

                            // Build request string
                            $request = json_encode([
                                "tpvCode" => $rin,
                                "tpvStatusCode" => $sp->result === "Sale" ? "good" : "bad",
                                "tpvIdentifier" => $sp->confirmation_code
                            ]);

                            info("Santanna Post", [$request]);

                            $client = new \GuzzleHttp\Client();
                            $res = $client->post(
                                $pi->hostname,
                                [
                                    'debug' => false,
                                    'http_errors' => false,
                                    'body' => $request,
                                    'headers' => [
                                        'Content-Type' => 'application/json',
                                    ]
                                ]
                            );

                            $body = (string)$res->getBody();
                            $parsedResult = json_decode($body, true);

                            info('Santanna Response', [$body]);

                            $result = null;

                            if ($res->getStatusCode() === 200) {

                                $pass = ($parsedResult['message'] == false); // Null message for 'success' responses

                                $result = ($pass ? "Pass" : "Fail");
                            } else {
                                $result = "Error";

                                info('API: Failed to send ' . $sp->confirmation_code . ', Error: (' . $res->getStatusCode() . ') ' . $res->getReasonPhrase(), [$parsedResult]);
                                $this->info('API: Failed to send ' . $sp->confirmation_code . ', Error: (' . $res->getStatusCode() . ') ' . $res->getReasonPhrase());
                            }

                            // Logging
                            $result = $result . " -- " . Carbon::now('America/Chicago')->toISOString();

                            // Only log result when both versions successfully posted.
                            // This will allow failed API calls to retry until they fall out of the query date range.
                            if (!strpos($result, "Error")) {

                                // Only update live_enroll if this is a sale.
                                // This is to allow a no saledd RIN that was good saled on a subsequent TPV to be submitted.
                                if (strtolower($sp->result) === 'sale') {
                                    $this->info("Updating live_enroll field in EventProduct.");

                                    $epupdate = EventProduct::find($sp->event_product_id);
                                    if ($epupdate) {
                                        $epupdate->live_enroll = $result;
                                        $epupdate->save();
                                    }
                                }
                            } else {
                                $this->info("Error posting data. EventProduct live_enroll will not be updated.");
                            }
                        }
                    }

                    break;

                case 'Dynegy LLC':
                    foreach ($sps as $sp) {
                        $body = [
                            'LastName' => $sp->auth_last_name,
                            'FirstName' => $sp->auth_first_name,
                            'Company' => $sp->company_name,
                            'Phone' => ltrim(
                                trim(
                                    $sp->btn
                                ),
                                '+1'
                            ),
                            'Email' => $sp->email_address,
                            'ServiceStreet' => trim($sp->service_address1 . ' ' . $sp->service_address2),
                            'ServiceCity' => $sp->service_city,
                            'ServiceState' => $sp->service_state,
                            'ServiceCountry' => ('United States' == $sp->service_country)
                                ? 'US'
                                : 'CA',
                            'ServiceZip' => $sp->service_zip,
                            'MailingStreet' => ($sp->billing_address1)
                                ? trim($sp->billing_address1 . ' ' . $sp->billing_address2)
                                : trim($sp->service_address1 . ' ' . $sp->service_address2),
                            'MailingCity' => ($sp->billing_city)
                                ? $sp->billing_city
                                : $sp->service_city,
                            'MailingState' => ($sp->billing_state)
                                ? $sp->billing_state
                                : $sp->service_state,
                            'MailingCountry' => ('United States' == $sp->billing_country)
                                ? 'US'
                                : 'CA',
                            'MailingZip' => ($sp->billing_zip)
                                ? $sp->billing_zip
                                : $sp->service_zip,
                            'LDCAccountNumber' => $sp->account_number1,
                            'ProductId' => $sp->rate_program_code,
                            'Channel' => ('DTD' == $sp->channel)
                                ? 'D2D'
                                : 'TM',
                            'ServiceTerritory' => $sp->service_territory,
                            'UtilityCode' => (isset($sp->utility_commodity_ldc_code))
                                ? $sp->utility_commodity_ldc_code
                                : $sp->utility_label,
                            'CustomerType' => $sp->market,
                            'SubmitDate' => $sp->event_created_at
                                ? date('Ymd', strtotime($sp->event_created_at))
                                : null,
                            'Enroll' => true,
                            'AgentId' => $sp->sales_agent_rep_id,
                            'VendorId' => $sp->vendor_label,
                            'PromoCode' => null,
                            'DwellingType' => 'Single' == $sp->structure_type
                                ? 'Single Family Home'
                                : 'Multi Family Home',
                        ];

                        // print_r($body);
                        // exit();

                        $j = new JsonDocument();
                        $j->document_type = 'live-enrollment';
                        $j->ref_id = $sp->event_product_id;
                        $j->document = $body;
                        $j->save();

                        $pi = ProviderIntegration::where(
                            'service_type_id',
                            6
                        )->where(
                            'brand_id',
                            $le->brand_id
                        )->first();

                        $url = 'https://services-qa.txmkt.txu.com/createCustomerEnroll';
                        $client = new \GuzzleHttp\Client();
                        $res = $client->post(
                            $url,
                            [
                                'auth' => [
                                    $pi->username,
                                    $pi->password,
                                ],
                                'json' => $body,
                            ]
                        );

                        echo 'Confirmation Code = ' . $sp->confirmation_code . "\n";
                        echo ' -- Sent = ' . print_r($body) . "\n";
                        echo ' -- Status Code = ' . $res->getStatusCode() . "\n";
                        $body = $res->getBody();
                        $stringBody = (string) $body;
                        echo ' -- BODY: ' . $stringBody . "\n";

                        if (200 == $res->getStatusCode()) {
                            $response = json_decode($body, true);
                            if (isset($response['Status']) && 'Error' == $response['Status']) {
                                if (isset($sp->event_product_id)) {
                                    echo $sp->event_product_id . "\n";
                                }

                                echo "Error attempting to submit product.\n";
                            } else {
                                // Update event_product.live_enroll to 1
                                $epupdate = EventProduct::find($sp->event_product_id);
                                if ($epupdate) {
                                    $epupdate->live_enroll = (isset($response['Message']))
                                        ? $response['Message'] : 1;
                                    $epupdate->save();
                                }
                            }
                        }

                        echo "---------\n";
                    }

                    break;

                case 'APG&E':
                case 'APGE':
                    foreach ($sps as $sp) {
                        if (!is_array($sp->custom_fields)) {
                            $sp->custom_fields = json_decode($sp->custom_fields);
                        }

                        // print_r($sp->toArray());
                        // exit();

                        $notification_preference = 'Paper';
                        $enrollment_type = 'Move In';
                        $security_question = null;
                        $security_answer = null;
                        $bill_language_pref = null;
                        $start_date = Carbon::tomorrow('America/Chicago')->format('m/d/Y');
                        foreach ($sp->custom_fields as $key => $value) {
                            if ('user_notification_preference' === $value->output_name) {
                                $notification_preference = $value->value;
                            }

                            if ('movein_or_switch' === $value->output_name) {
                                $enrollment_type = $value->value;
                            }

                            if ('security_question' === $value->output_name) {
                                $security_question = $value->value;
                            }

                            if ('security_answer' === $value->output_name) {
                                $security_answer = $value->value;
                            }

                            if ('bill_language_pref' === $value->output_name) {
                                $bill_language_pref = $value->value;
                            }

                            if ('start_date' === $value->output_name) {
                                $start_date = $value->value;
                            }
                        }

                        $body = [
                            'Esid' => $sp->account_number1,
                            'RevenueClass' => $sp->market,
                            'FirstName' => $sp->auth_first_name,
                            'LastName' => $sp->auth_last_name,
                            'OfferCode' => $sp->product_name,
                            'HomePhoneNumber' => ltrim(
                                trim(
                                    $sp->btn
                                ),
                                '+1'
                            ),
                            'WorkPhoneNumber' => null,
                            'EmailAddress' => $sp->email_address,
                            'CreditScore' => null,
                            'ContactName' => null,
                            'ServiceAddress' => $sp->service_address1,
                            'ServiceAddress2' => $sp->service_address2,
                            'ServiceCity' => $sp->service_city,
                            'ServiceState' => $sp->service_state,
                            'ServiceCounty' => $sp->service_county,
                            'ServiceZipCode' => $sp->service_zip,
                            'DistributorName' => $sp->product_utility_external_id,
                            'EnrollmentType' => $enrollment_type,
                            'MailingAddress' => ($sp->billing_address1)
                                ? trim($sp->billing_address1 . ' ' . $sp->billing_address2)
                                : trim($sp->service_address1 . ' ' . $sp->service_address2),
                            'MailingAddress2' => null,
                            'MailingCity' => ($sp->billing_city)
                                ? $sp->billing_city
                                : $sp->service_city,
                            'MailingState' => ($sp->billing_state)
                                ? $sp->billing_state
                                : $sp->service_state,
                            'MailingCounty' => ($sp->billing_county)
                                ? $sp->billing_county
                                : $sp->service_county,
                            'MailingZipCode' => ($sp->billing_zip)
                                ? $sp->billing_zip
                                : $sp->service_zip,
                            'SecurityQuestion' => $security_question,
                            'SecurityAnswer' => $security_answer,
                            'BirthDate' => '01/01/1900',
                            'NotificationPreference' => $notification_preference,
                            'BillDeliveryMethod' => 'Paper',
                            'DepositAmount' => null,
                            'SocialSecurity' => null,
                            'RequestedStartDate' => $start_date,
                            'Price' => number_format($sp->product_rate_amount * 10, 4),
                            'Language' => strtoupper($sp->language),
                            'ServiceTypeDescription' => strtoupper($sp->commodity),
                            'AuthorizationToken' => null,
                            'TpvConfirmationNumber' => $sp->confirmation_code,
                        ];

                        // print_r($body);
                        // exit();

                        $j = JsonDocument::where(
                            'ref_id',
                            $sp->event_product_id
                        )->first();
                        if (!$j) {
                            $j = new JsonDocument();
                        }
                        $j->document_type = 'live-enrollment';
                        $j->ref_id = $sp->event_product_id;
                        $j->document = $body;
                        $j->save();

                        $pi = ProviderIntegration::where(
                            'service_type_id',
                            8
                        )->where(
                            'brand_id',
                            $le->brand_id
                        )->where(
                            function ($query) use ($env_id) {
                                $query->where(
                                    'env_id',
                                    $env_id
                                )->orWhereNull(
                                    'env_id'
                                );
                            }
                        )->first();

                        $url = $pi->hostname;
                        $client = new \GuzzleHttp\Client();
                        $res = $client->post(
                            $url . '/v1/SpeedEnrollment',
                            [
                                'debug' => $this->option('debug'),
                                'headers' => [
                                    'ApiKey' => $pi->password,
                                    'Content-Type' => 'application/json',
                                ],
                                'json' => $body,
                            ]
                        );

                        // echo 'Confirmation Code = '.$sp->confirmation_code."\n";
                        // echo ' -- Sent = '.print_r($body)."\n";
                        // echo ' -- Status Code = '.$res->getStatusCode()."\n";
                        $body = $res->getBody();
                        // $stringBody = (string) $body;
                        // echo ' -- BODY: '.$stringBody."\n";

                        if (200 == $res->getStatusCode()) {
                            $response = json_decode($body, true);
                            if (isset($response['result']) && 'fail' == $response['result']) {
                                if (isset($sp->event_product_id)) {
                                    echo $sp->event_product_id . "\n";
                                }

                                echo 'Error attempting to submit product: '
                                    . $response['message']
                                    . "\n";
                            } else {
                                // Update event_product.live_enroll to 1
                                $epupdate = EventProduct::find($sp->event_product_id);
                                if ($epupdate) {
                                    $live_enroll = 1;
                                    if (isset($response['data'])) {
                                        //$data = json_decode($response['data'], true);
                                        $live_enroll = $response['data']['dbs'];
                                    }

                                    $epupdate->live_enroll = $live_enroll;
                                    $epupdate->save();
                                }
                            }
                        }

                        echo "---------\n";
                    }

                    break;
                case 'Clearview Energy':
                    foreach ($sps as $spx) {
                        $time_start = microtime(true);

                        $sp = StatsProduct::select(
                            'stats_product.event_product_id',
                            'stats_product.event_created_at',
                            'stats_product.language',
                            'stats_product.market_id',
                            'stats_product.channel',
                            'stats_product.auth_first_name',
                            'stats_product.auth_last_name',
                            'stats_product.bill_first_name',
                            'stats_product.bill_last_name',
                            'stats_product.auth_relationship',
                            'stats_product.btn',
                            'stats_product.source',
                            'stats_product.company_name',
                            'stats_product.email_address',
                            'stats_product.service_address1',
                            'stats_product.service_address2',
                            'stats_product.service_city',
                            'stats_product.service_state',
                            'stats_product.service_zip',
                            'stats_product.service_country',
                            'stats_product.billing_address1',
                            'stats_product.billing_address2',
                            'stats_product.billing_city',
                            'stats_product.billing_state',
                            'stats_product.billing_zip',
                            'stats_product.billing_country',
                            'stats_product.account_number1',
                            'stats_product.account_number2',
                            'stats_product.rate_promo_code',
                            'stats_product.product_name',
                            'stats_product.rate_program_code',
                            'stats_product.commodity',
                            'stats_product.product_name',
                            'stats_product.name_key',
                            'stats_product.product_rate_type',
                            'stats_product.product_rate_amount',
                            'stats_product.utility_commodity_ldc_code',
                            'stats_product.rate_uom',
                            'stats_product.product_term',
                            'stats_product.confirmation_code',
                            'stats_product.sales_agent_rep_id',
                            'stats_product.sales_agent_name',
                            'stats_product.result',
                            'stats_product.disposition_label',
                            'stats_product.disposition_reason',
                            'stats_product.tpv_agent_label',
                            'stats_product.interaction_time',
                            'stats_product.tpv_agent_call_center_id',
                            'stats_product.tpv_agent_label',
                            'stats_product.vendor_label',
                            'stats_product.vendor_name',
                            'brand_utilities.service_territory',
                            'brand_utilities.utility_label',
                            'brand_utilities.utility_external_id'
                        )->leftJoin(
                            'brand_utilities',
                            function ($join) {
                                $join->on(
                                    'stats_product.utility_id',
                                    'brand_utilities.utility_id'
                                )->where(
                                    'stats_product.brand_id',
                                    'brand_utilities.brand_id'
                                );
                            }
                        )->where(
                            'stats_product.event_product_id',
                            $spx->event_product_id
                        )->first();

                        if ($sp) {
                            $utility = (isset($sp->utility_commodity_ldc_code)
                                && $sp->utility_commodity_ldc_code !== null)
                                ? $sp->utility_commodity_ldc_code
                                : $sp->utility_external_id;

                            $body = [
                                'p_date' => $sp->event_created_at->format('m/d/Y'),
                                'sell_date' => $sp->event_created_at->format('m/d/Y'),
                                'dt_insert' => date('m/d/Y H:i'),
                                'dt_date' => date('m/d/Y H:i'),
                                'dt_scan' => null,
                                'center_id' => $sp->vendor_label,
                                'vendor_name' => $sp->vendor_label,
                                'vendor_code' => 'DXC',
                                'source' => $sp->source,
                                'language' => $sp->language,
                                'record_id' => null,
                                'lead_found' => '0',
                                'sales_state' => $sp->service_state,
                                'dual_fuel' => 'no',
                                'contract' => null,
                                'channel' => $sp->channel,
                                'customer_type' => $sp->market_id == 1 ? 'RES' : 'SC',
                                'btn' => ltrim(
                                    trim(
                                        $sp->btn
                                    ),
                                    '+1'
                                ),
                                'email_address' => $sp->email_address,
                                'email_notifications' => null,
                                'tax_exempt' => null,
                                'budget_cust' => 'No',
                                'current_customer' => null,
                                'auto_delivery' => null,
                                'referral_id' => null,
                                'promo_code' => $sp->rate_promo_code,
                                'promotion' => null,
                                'product' => $sp->product_name,
                                'auth_fname' => $sp->auth_first_name,
                                'auth_lname' => $sp->auth_last_name,
                                'OrderDetails' => [
                                    [
                                        'BillingInformation' => [
                                            'bill_fname' => $sp->bill_first_name,
                                            'bill_lname' => $sp->bill_last_name,
                                            'company_name' => $sp->company_name,
                                            'relationship' => $sp->auth_relationship,
                                            'billing_address1' => (strlen($sp->billing_address1) > 50)
                                                ? substr($sp->billing_address1, 0, 49)
                                                : $sp->billing_address1,
                                            'billing_address2' => $sp->billing_address2,
                                            'billing_city' => $sp->billing_city,
                                            'billing_state' => $sp->billing_state,
                                            'billing_zip' => $sp->billing_zip,
                                            'acct_num' => $sp->account_number1,
                                        ],
                                        'ServiceAddress' => [
                                            'service_address1' => (strlen($sp->service_address1) > 50)
                                                ? substr($sp->service_address1, 0, 49)
                                                : $sp->service_address1,
                                            'service_address2' => $sp->service_address2,
                                            'service_city' => $sp->service_city,
                                            'service_state' => $sp->service_state,
                                            'service_zip' => $sp->service_zip,
                                            'service_county' => $sp->service_county,
                                        ],
                                        'home_type' => 'House',
                                        'product_code' => $sp->rate_program_code,
                                        'fuel_type' => $sp->commodity,
                                        'meter_no' => ($sp->account_number2)
                                            ? $sp->account_number2
                                            : null,
                                    ],
                                ],
                                'name_key' => $sp->name_key,
                                'variable_or_fixed' => ucfirst($sp->product_rate_type),
                                'rate' => $sp->product_rate_amount,
                                'utility' => $utility,
                                'unit_measurement' => $sp->rate_uom,
                                'term' => $sp->product_term,
                                'exp_date' => null,
                                'ver_code' => $sp->confirmation_code,
                                'tsr_id' => $sp->sales_agent_rep_id,
                                'tsr_name' => $sp->sales_agent_name,
                                'tsr_dt_added' => null,
                                'status_txt' => $sp->result,
                                'status_id' => $sp->disposition_label,
                                'reason' => $sp->disposition_reason,
                                'ScanDetail' => [
                                    'scan_filename' => null,
                                    'scan_status_txt' => null,
                                    'scan_status_id' => null,
                                    'scan_queue_name' => null,
                                    'scan_reason' => null,
                                ],
                                'dxc_rep_id' => $sp->tpv_agent_label,
                                'call_time' => $sp->interaction_time,
                                'activewav' => null,
                                'audited' => null,
                                'station_id' => $sp->tpv_agent_call_center_id . '-' . $sp->tpv_agent_label,
                                'cic_call_id' => null,
                                'form_name' => null,
                                'rec_id' => null,
                                'contract_emailed' => null,
                                'contract_smsed' => null,
                                'contract_confirmed' => null,
                                'dt_contract_confirmed' => null,
                            ];

                            if ($this->option('showJSON')) {
                                echo json_encode($body, JSON_PRETTY_PRINT) . "\n";
                                die();
                            }

                            if ($this->option('debug')) {
                                echo 'Time to prepare data post: ' . (microtime(true) - $time_start) . "second(s) \n";
                            }

                            $time_start = microtime(true);

                            $pi = Cache::remember(
                                'provider_integration_8_' . $env_id . '-' . $le->brand_id,
                                60,
                                function () use ($env_id, $le) {
                                    return ProviderIntegration::where(
                                        'service_type_id',
                                        8
                                    )->where(
                                        'brand_id',
                                        $le->brand_id
                                    )->where(
                                        function ($query) use ($env_id) {
                                            $query->where(
                                                'env_id',
                                                $env_id
                                            )->orWhereNull(
                                                'env_id'
                                            );
                                        }
                                    )->first();
                                }
                            );

                            if ($pi) {
                                $url = $pi->hostname;
                                $auth_endpoint = '/token';
                                $api_endpoint = '/api/UploadSalesData';
                                $client = new \GuzzleHttp\Client(['verify' => false]);
                                $res = $client->post($url . $auth_endpoint, [
                                    'verify' => false,
                                    'debug' => $this->option('debug'),
                                    'headers' => [
                                        'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                    ],
                                    'form_params' => [
                                        'grant_type' => 'password',
                                        'username' => $pi->username,
                                        'password' => $pi->password,
                                    ]
                                ]);

                                if ($this->option('debug')) {
                                    echo 'Time to API auth: ' . (microtime(true) - $time_start) . " second(s) \n";
                                }

                                $time_start = microtime(true);
                                if ($res->getStatusCode() == 200) {
                                    $authBody = $res->getBody();
                                    $authPayload = json_decode($authBody, true);
                                    $authToken = null;
                                    if (isset($authPayload['access_token'])) {
                                        $authToken = $authPayload['access_token'];
                                    }

                                    if ($authToken !== null) {
                                        $res = $client->post(
                                            $url . $api_endpoint,
                                            [
                                                'debug' => $this->option('debug'),
                                                'http_errors' => false,
                                                'headers' => [
                                                    'Authorization' => 'Bearer ' . $authToken,
                                                    'Content-Type' => 'text/plain',
                                                    'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                                ],
                                                'body' => json_encode($body),
                                            ]
                                        );

                                        if ($this->option('debug')) {
                                            echo 'Time to POST to API: ' . (microtime(true) - $time_start) . " second(s) \n";
                                        }

                                        $time_start = microtime(true);

                                        echo 'Confirmation Code = ' . $sp->confirmation_code . "\n";
                                        // // echo ' -- Sent = '.print_r($body)."\n";
                                        // // echo ' -- Status Code = '.$res->getStatusCode()."\n";
                                        $body = $res->getBody();
                                        $stringBody = (string) $body;
                                        echo ' -- BODY: ' . $stringBody . "\n";

                                        //if (200 == $res->getStatusCode()) {
                                        $response = json_decode($body, true);
                                        if ($res->getStatusCode() >= 400 || (isset($response['Status']) && 'Failed' == $response['Status'])) {
                                            echo '[' . $res->getStatusCode() . '] Error attempting to submit product ('
                                                . $sp->event_product_id . ') with program code ' . $sp->rate_program_code
                                                . ':' . $response['Message'] . "\n";
                                            $errorList = [];
                                            if (isset($response['ErrorList'])) {
                                                if (isset($sp->event_product_id)) {
                                                    echo $sp->event_product_id . "\n";
                                                }

                                                foreach ($response['ErrorList'] as $error) {
                                                    $errorList[] = '(ErrorCode ' . $error['ErrorCode'] . ') ' . $error['Error'];
                                                }

                                                $jdocErrors = new JsonDocument();
                                                $jdocErrors->created_at = Carbon::now('America/Chicago');
                                                $jdocErrors->updated_at = Carbon::now('America/Chicago');
                                                $jdocErrors->ref_id = $sp->event_product_id;
                                                $jdocErrors->document_type = 'live-enrollment-errors';
                                                $jdocErrors->document = $response['ErrorList'];
                                                $jdocErrors->save();

                                                echo implode("\n", $errorList) . "\n";
                                            } else {
                                                $jdocErrors = new JsonDocument();
                                                $jdocErrors->ref_id = $sp->event_product_id;
                                                $jdocErrors->document_type = 'live-enrollment-errors';
                                                $jdocErrors->document = ['response' => '[' . $res->getStatusCode() . '] Error attempting to submit product (' . $sp->event_product_id . ') :' . $response['Message']];
                                                $jdocErrors->save();
                                            }

                                            $epupdate = EventProduct::find($sp->event_product_id);
                                            if ($epupdate) {
                                                $epupdate->live_enroll = "Live Enroll Response Error - skipping";
                                                $epupdate->extra_fields = sprintf("RequestId (%s)\n%s\n%s", $response['RequestId'] ?? '', $response['Message'], implode("\n", $errorList)); 
                                                $epupdate->save();
                                            }
                                        } else {
                                            // Update event_product.live_enroll to 1
                                            $epupdate = EventProduct::find($sp->event_product_id);
                                            if ($epupdate) {
                                                $live_enroll = 1;
                                                $jresponse = json_encode($response);
                                                $cleanRes = json_decode($jresponse, true);

                                                echo '!! Success Response';
                                                echo $jresponse;
                                                echo '!! End Success Response';

                                                if (isset($response['RequestId'])) {
                                                    $live_enroll = $response['RequestId'];
                                                } else {
                                                    $jdocErrors = new JsonDocument();
                                                    $jdocErrors->ref_id = $sp->event_product_id;
                                                    $jdocErrors->document_type = 'live-enrollment-errors';
                                                    $jdocErrors->document = ['message' => 'No Result ID', 'response' => $cleanRes];
                                                    $jdocErrors->save();
                                                }

                                                $epupdate->live_enroll = $live_enroll;
                                                $epupdate->save();
                                            }
                                        }

                                        echo "---------\n";

                                        if ($this->option('debug')) {
                                            echo 'Time to complete: ' . (microtime(true) - $time_start) . " second(s) \n";
                                        }
                                    }
                                }
                            }
                        }
                    }

                    break;

                case 'TXU Energy':
                    // NOTE:
                    //   Normally we'd run ConfirmEnrollment or CancelEnrollment APIs depending on the status of the TPV.
                    //   We're only pulling in the good sales, so only ConfirmEnrollment should ever be run.
                    //   This is intentional. It gives us one less API method to worry about, and the pending records cancel out of TXU's system after a time, so no need to explicity cancel them.
                    $this->info('Processing TXU Energy:');
                    $this->info('Getting API config...');

                    $pi = ProviderIntegration::where(
                        'service_type_id',
                        9
                    )->where(
                        'provider_integration_type_id',
                        2
                    )->where(
                        'brand_id',
                        $le->brand_id
                    )->where(
                        function ($query) use ($env_id) {
                            $query->where(
                                'env_id',
                                $env_id
                            )->orWhereNull(
                                'env_id'
                            );
                        }
                    )->first();

                    if ($pi) {
                        $wsdl = $pi->hostname;

                        $this->info('Processing data...');

                        foreach ($sps as $sp) {
                            $this->info('Event: ' . $sp->id);
                            $this->info('Conf#: ' . $sp->confirmation_code);

                            $customFields = CustomFieldStorage::where(
                                'event_id',
                                $sp->id
                            )->whereNull(
                                'product_id'
                            )->with(
                                'customField'
                            )->get();

                            $customerNumber = null;

                            foreach ($customFields as $item) {
                                if ($item->customField->output_name == 'txu_customer_number') {
                                    $customerNumber = trim($item->value);
                                }
                            }

                            // Must have a customer number
                            if ($customerNumber !== null && $customerNumber !== '') {

                                $this->info('Getting ProvideOrderInfo log record...');

                                $poLog = JsonDocument::select(
                                    'id',
                                    'document'
                                )->whereNull(
                                    'deleted_at'
                                )->where(
                                    'ref_id',
                                    $sp->confirmation_code
                                )->where(
                                    'document_type',
                                    'txu-credit-check RES'
                                )->orderBy(
                                    'created_at',
                                    'desc'
                                )->first();

                                if ($poLog) {
                                    $this->info('Found. Extracting cookies from header data...');

                                    $log = $poLog->document;
                                    $headerStr = $log['headers'];
                                    $headers = explode("\r\n", $headerStr);

                                    // Search for and parse cookies
                                    $cookies = [];

                                    foreach ($headers as $header) {
                                        $parts = explode(': ', $header);   // split header name from value. Cookie name resides in the value.

                                        if ($parts[0] == 'Set-Cookie') {
                                            $cookieParts = explode('=', $parts[1], 2); // split cookie name and value.
                                            $cookieValue = explode(';', $cookieParts[1], 2)[0]; // split out the value from the other stuff, like expiration, domain, etc...

                                            if (
                                                $cookieParts[0] == "AWSALBTG"
                                                || $cookieParts[0] == "AWSALBTGCORS"
                                                || $cookieParts[0] == "AWSALB"
                                                || $cookieParts[0] == "AWSALBCORS"
                                                || $cookieParts[0] == "ASP.NET_SessionId"
                                            ) {
                                                $alreadyEntered = false; // ASP.NET_SessionId is duplicated in TXU API's response. Added this generic duplicate check in case of other duplicates in the future.
                                                foreach ($cookies as $c) {
                                                    if ($c['name'] == $cookieParts[0]) {
                                                        $alreadyEntered = true;
                                                    }
                                                }

                                                if (!$alreadyEntered) {
                                                    $cookies[] = [
                                                        'name' => $cookieParts[0],
                                                        'value' => $cookieValue
                                                    ];
                                                }
                                            }
                                        }
                                    }

                                    $this->info('Posting data...');
                                    $response = SoapCall(
                                        $wsdl,
                                        $sp->result == 'Sale' ? 'ConfirmEnrollment' : 'CancelEnrollment', // Leaving this in here, but in reality CancelEnrollment should never get used since we're only pulling in good sales.
                                        [
                                            'TxuCustomerNumber' => $customerNumber
                                        ],
                                        'production' != config('app.env'),
                                        1,
                                        [],
                                        ['prefix' => 'txu-' . ($sp->result == 'Sale' ? 'confirm' : 'cancel') .  '-enrollment', 'ref' => $sp->confirmation_code],
                                        $cookies
                                    );

                                    $epupdate = EventProduct::find($sp->event_product_id);
                                    if ($epupdate) {
                                        $live_enroll = is_string($response) ? $response : json_encode($response);

                                        $epupdate->live_enroll = $live_enroll;
                                        $epupdate->save();
                                    }
                                } else {
                                    $this->info('Unable to find a ProvideOrderInfo log entry. Skipping TPV record.');

                                    $epupdate = EventProduct::find($sp->event_product_id);
                                    if ($epupdate) {
                                        $live_enroll = 'REQ log not found';
                                        $epupdate->live_enroll = $live_enroll;
                                        $epupdate->save();
                                    }
                                }
                            }
                        }
                    } else {
                        $this->info('API config not found. Skipping TXU.');
                    }

                    break;

                case 'Gexa Energy':
                    $wsdl = ($env_id === 1)
                        ? 'https://apiprod.gexaenergy.com/WebAPI.asmx?WSDL'
                        : 'https://apitest.gexaenergy.com/WebAPI.asmx?WSDL';

                    foreach ($sps as $sp) {
                        $pi = ProviderIntegration::where(
                            'service_type_id',
                            27
                        )->where(
                            'provider_integration_type_id',
                            2
                        )->where(
                            'brand_id',
                            $sp->brand_id
                        )->where(
                            'vendor_id',
                            $sp->vendor_id
                        )->where(
                            function ($query) use ($env_id) {
                                $query->where(
                                    'env_id',
                                    $env_id
                                )->orWhereNull(
                                    'env_id'
                                );
                            }
                        )->first();
                        if (!$pi) {
                            echo "Gexa - unable to find vendor provider integration credentials...\n";
                        }

                        $customFields = CustomFieldStorage::where(
                            'event_id',
                            $sp->id
                        )->whereNull(
                            'product_id'
                        )->with(
                            'customField'
                        )->get();

                        $transactionID = null;
                        $ebill = false;
                        $tcpa = 'N';
                        foreach ($customFields as $item) {
                            if ($item->customField->output_name == 'transaction_id') {
                                $transactionID = trim($item->value);
                            }

                            if ($item->customField->output_name == 'bill_delivery') {
                                $ebill = trim($item->value);
                            }

                            if ($item->customField->output_name == 'tcpa') {
                                $tcpa = trim($item->value);
                            }
                        }

                        if ($this->option('debug')) {
                            echo "Processing " . $sp->confirmation_code . "\n";
                            echo "Authing...\n";
                        }

                        $auth = SoapCall(
                            $wsdl,
                            'Authentication',
                            [
                                'Authenticate' => [
                                    'UserName' => $pi->username,
                                    'Password' => $pi->password,
                                ]
                            ]
                        );

                        if ($this->option('debug')) {
                            print_r($auth);
                        }

                        if (
                            empty($auth['error'])
                            && isset($auth['response']->AuthenticationResult->SessionID)
                        ) {
                            $body = [
                                'CreditCardDetails' => [
                                    'VendorTransactionID' => '99' . $sp->confirmation_code,
                                    'SessionID' => $auth['response']->AuthenticationResult->SessionID,
                                    'EBill' => $ebill,
                                    'PaperlessDocumentsYN' => $tcpa,
                                ]
                            ];

                            if (!empty($transactionID)) {
                                $body['CreditCardDetails']['TransactionID'] = $transactionID;
                            } else {
                                //$body['CreditCardDetails']['TransactionID'] = null;

                                if (!empty($sp->stats_product_lead_id)) {
                                    $lead = Lead::find($sp->stats_product_lead_id);
                                    if ($lead) {
                                        $body['CreditCardDetails']['ProspectID'] = $lead->external_lead_id;
                                    }
                                }
                            }

                            if ($this->option('showJSON')) {
                                echo json_encode($body) . "\n";
                            }

                            $response = SoapCall(
                                $wsdl,
                                'ConfirmAPIEnrollment',
                                $body,
                                false, // debug
                                1, // soap version
                                [], // context options
                                [ // storage options
                                    'prefix' => 'gexa-live-enroll',
                                    'ref' => $sp->confirmation_code,
                                ]
                            );

                            if ($this->option('debug')) {
                                print_r($response);
                            }

                            if (
                                empty($response['error'])
                                && isset($response['response'])
                                && $response['response']->ConfirmAPIEnrollmentResult->RequestMessage === 'Success'
                            ) {
                                $trans_id = (!empty($transactionID))
                                    ? $transactionID
                                    : Carbon::now('America/Chicago');
                                $epupdate = EventProduct::find($sp->event_product_id);
                                if ($epupdate) {
                                    $epupdate->live_enroll = $trans_id;
                                    $epupdate->save();
                                }

                                if ($this->option('debug')) {
                                    echo " --- Successfully posted...\n";
                                }
                            } else {
                                $jd = new JsonDocument();
                                $jd->ref_id = $sp->event_product_id;
                                $jd->document = $response;
                                $jd->document_type = 'gexa-live-enroll-error';
                                $jd->save();
                            }
                        } else {
                            $jd = new JsonDocument();
                            $jd->ref_id = $sp->event_product_id;
                            $jd->document = $auth;
                            $jd->document_type = 'gexa-live-enroll-error';
                            $jd->save();
                        }
                    }

                    break;

                case 'Southstar Energy Services LLC':
                    $pi = ProviderIntegration::where('service_type_id', 24)
                        ->where('provider_integration_type_id', 2)
                        ->where('brand_id', $le->brand_id)
                        ->where(function ($query) use ($env_id) {
                                $query
                                    ->where('env_id', $env_id)
                                    ->orWhereNull('env_id');
                            })
                        ->first();

                    if ($pi) {
                        $pi->notes = json_decode($pi->notes, true);

                        foreach ($sps as $sp) {
                            $xml = view('xml.southstar.tpvpost')
                                ->with([
                                    'pi' => $pi,
                                    'sp' => $sp
                                ])
                                ->render();
                            $xml = $xml_body = trim($xml);

                            if ($this->option('debug')) {
                                $this->info($xml);
                            }

                            $client = new \GuzzleHttp\Client();
                            $res = $client->post(
                                $pi->hostname,
                                [
                                    'debug' => true,
                                    'http_errors' => false,
                                    'body' => $xml,
                                    'headers' => [
                                        'Content-Type' => 'text/xml; charset=utf-8',
                                    ]
                                ]
                            );

                            try {
                                $body = (string)$res->getBody();
                                $xml = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $body);

                                $this->info($xml);

                                $xml = simplexml_load_string($xml);
                                $json = json_encode($xml);
                                $array = json_decode($json, true);

                                $this->info($body);

                                // Used for figuring out why we fall into an Else block, this is logged to json_documents
                                // values cascade, so if soapBody isset is false, anything that follows will also be false
                                $soapBodyCheck = [
                                    'soapBody' => isset($array['soapBody']),
                                    'TPVDispositionNotificationResponse' => isset($array['soapBody']['TPVDispositionNotificationResponse']),
                                    'TPVDispositionNotificationResult' => isset($array['soapBody']['TPVDispositionNotificationResponse']['TPVDispositionNotificationResult']),
                                    'ResponseMessage' => isset($array['soapBody']['TPVDispositionNotificationResponse']['TPVDispositionNotificationResult']['ResponseMessage'])
                                ];

                                if (
                                    isset($array['soapBody'])
                                    && isset($array['soapBody']['TPVDispositionNotificationResponse'])
                                    && isset($array['soapBody']['TPVDispositionNotificationResponse']['TPVDispositionNotificationResult'])
                                    && isset($array['soapBody']['TPVDispositionNotificationResponse']['TPVDispositionNotificationResult']['ResponseMessage'])
                                ) {
                                    $msg = $array['soapBody']['TPVDispositionNotificationResponse']['TPVDispositionNotificationResult']['ResponseMessage'];
                                    switch ($msg) {
                                        case 'Request Success':
                                            $epupdate = EventProduct::find($sp->event_product_id);
                                            if ($epupdate) {
                                                $epupdate->live_enroll = Carbon::now('America/Chicago');
                                                $epupdate->save();
                                            }
                                            break;

                                        case 'TPV Case Not Found':
                                            $epupdate = EventProduct::find($sp->event_product_id);
                                            if ($epupdate) {
                                                $epupdate->live_enroll = 'Case not Found - ' . Carbon::now('America/Chicago');
                                                $epupdate->save();
                                            }
                                            break;

                                        default:
                                            // don't mark complete just leave alone for retry later

                                            // This will log the event as not having a case statement for troubleshooting
                                            $epupdate = EventProduct::find($sp->event_product_id);
                                            if ($epupdate) {
                                                $epupdate->live_enroll = 'Default (case not listed) - ' . Carbon::now('America/Chicago');
                                                $epupdate->save();
                                            }

                                            // Log all data in the JSON Documents table
                                            $jd = new JsonDocument();
                                            $jd->ref_id = $sp->event_product_id;
                                            $jd->document = [
                                                'request' => $xml_body, // Unmodified XML payload to client as $xml value is altered after it is sent to client API
                                                'result' => $array,     // Array is the Modified XML payload Response from their server
                                                'pi' => $pi,            // Used to populate values on XML View - Provider Integration data 
                                                'sp' => $sp,            // Used to populate values on XML View - One row of Event query result Left Joined to Stats Product
                                                'file' => 'LiveEnrollment.php',
                                                'client' => 'Case for: Southstar Energy Services LLC',
                                                'line' => __LINE__,
                                                'switch_msg' => $msg, // $msg here is a string, used in the switch case that is unhandled resulting in switch default being called
                                            ];
                                            $jd->document_type = 'southstar-live-enroll-default';
                                            $jd->save();                                            
                                            
                                            break;
                                    }
                                }
                                else {
                                    // Log all data in the JSON Documents table
                                    $jd = new JsonDocument();
                                    $jd->ref_id = $sp->event_product_id;
                                    $jd->document = [
                                        'soapBodyCheck' => $soapBodyCheck, // one of the 'if' checks that caused this 'else' block to trigger, so we can see each of the values
                                        'request' => $xml_body,            // Unmodified XML Payload sent to Southstar API
                                        'result' => $array,                // Modified XML Payload as Response from API
                                        'file' => 'LiveEnrollment.php',
                                        'client' => 'Case for: Southstar Energy Services LLC',
                                        'line' => __LINE__,
                                        
                                    ];
                                    $jd->document_type = 'southstar-live-enroll-else';
                                    $jd->save();
                                }
                            } catch (Exception $e) {
                                $this->info('Error = ' . $e);

                                $jd = new JsonDocument();
                                $jd->ref_id = $sp->event_product_id;
                                $jd->document = [
                                    'code' => $e->getCode(),
                                    'file' => $e->getFile(),
                                    'message' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ];
                                $jd->document_type = 'southstar-live-enroll-error';
                                $jd->save();

                                $epupdate = EventProduct::find($sp->event_product_id);
                                if ($epupdate) {
                                    $epupdate->live_enroll = 'Bad API Response - skipping live enroll';
                                    $epupdate->save();
                                }
                            }
                        }
                    }

                    break;
            }
        }
    }

    /**
     * getCustomFieldValue()
     *
     * @param string $output_name      - The output name of the custom field being queried.
     * @param string $event_id         - ID of the event the custom field value is tied to.
     * @param string $event_product_id - Id of the event product the value is tied to (optional).
     *
     * @return $value
     */
    private function getCustomFieldValue(string $output_name, string $event_id, string $event_product_id = null)
    {
        $value = null;

        $customFields = CustomFieldStorage::where(
            'event_id',
            $event_id
        )->where(
            'product_id',
            $event_product_id
        )->with(
            'customField'
        )->get();

        // Extract the requested value
        foreach ($customFields as $item) {
            if (strtolower($item->customField->output_name) == strtolower($output_name)) {
                $value = trim($item->value);
            }
        }

        return $value;
    }
}
