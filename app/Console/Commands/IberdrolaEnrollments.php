<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\Recording;
use App\Models\JsonDocument;
use App\Models\Interaction;
use App\Models\EventProduct;
use App\Models\Event;

class IberdrolaEnrollments extends Command
{
    public $accessToken;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iberdrola:enrollment
        {--debug}
        {--nightly}
        {--forever}
        {--hoursAgo=}
        {--confirmation_code=}
        {--ignore_live_enroll}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Iberdrola Enrollment process';

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
        $posturl = (config('app.env') === 'production')
            ? 'https://iberdrola.my.salesforce.com/services/apexrest/tpvhub/confirm'
            : 'https://iberdrola--uat2.my.salesforce.com/services/apexrest/tpvhub/confirm';
        $brand_id = (config('app.env') === 'production')
            ? '45e7ab90-c80b-4814-bd9d-8e17db2d6a33'
            : 'b8cbdfdb-4b23-401c-80d2-3ef1c5f16992';

        $time_start = microtime(true);
        $sps = Event::select(
            'events.id',
            'event_product.id AS event_product_id',
            'events.confirmation_code',
            'events.created_at',
            'event_product.live_enroll'
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
            $brand_id
        );

        if (!$this->option('ignore_live_enroll')) {
            $sps = $sps->whereNull(
                'event_product.live_enroll'
            );
        }

        $sps = $sps->whereNull(
            'event_product.deleted_at'
        );

        if ($this->option('confirmation_code')) {
            $sps = $sps->where(
                'events.confirmation_code',
                $this->option('confirmation_code')
            );
        } else {
            if (!$this->option('nightly')) {
                $sps = $sps->where(
                    'stats_product.result',
                    'Sale'
                );
            } else {
                $sps = $sps->where(
                    'stats_product.result',
                    'No Sale'
                );
            }

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
        )->with(
            [
                'customFieldStorage.customField',
            ]
        )->get();

        if ($this->option('debug')) {
            print_r($sps->toArray());

            echo 'Time to lookup sale data records: ' . (microtime(true) - $time_start) . " second(s) \n";
        }

        if (count($sps) > 0) {
            if (config('app.env') === 'production') {
                $provider = new \Stevenmaguire\OAuth2\Client\Provider\Salesforce([
                    // Production
                    'clientId' => '3MVG98_Psg5cppyYZrs0OL.N_QuLNmr3Fz7B9M1dQlquig6IOAumvJ8j7dQM5P7Gnos_z7ASaxe8BIeUcwHIu',    // The client ID assigned to you by the provider
                    'clientSecret' => '876FD0D9DF1FBADC12F9FC219F337A30ACC24A8C9CDAD9600F59A84EAE2A3CAC',   // The client password assigned to you by the provider
                    'urlAuthorize' => 'https://iberdrola.my.salesforce.com/services/apexrest/oauth2/authorize',
                    'urlAccessToken' => 'https://iberdrola.my.salesforce.com/services/apexrest/oauth2/token',
                    'urlResourceOwnerDetails' => 'https://iberdrola.my.salesforce.com/services/apexrest/oauth2/resource',
                    'domain' => 'https://iberdrola.my.salesforce.com',
                ]);
            } else {
                $provider = new \Stevenmaguire\OAuth2\Client\Provider\Salesforce([
                    // UAT
                    'clientId' => '3MVG9sSN_PMn8tjScBtF1nbZHBRDM3jfwd.B3Gzp0Um9bEvKgMvkV97e3N8BkOmG6a321bSdJPuQwTgPyWb.n',    // The client ID assigned to you by the provider
                    'clientSecret' => 'ABC0E3FDD4BF6FE8133758E23E221E22E65E465A315F5DB650C64416A9B40183',   // The client password assigned to you by the provider
                    'urlAuthorize' => 'https://iberdrola--uat2.my.salesforce.com/services/apexrest/oauth2/authorize',
                    'urlAccessToken' => 'https://iberdrola--uat2.my.salesforce.com/services/apexrest/oauth2/token',
                    'urlResourceOwnerDetails' => 'https://iberdrola--uat2.my.salesforce.com/services/apexrest/oauth2/resource',
                    'domain' => 'https://iberdrola--uat2.my.salesforce.com',
                ]);
            }

            // print_r($provider);

            try {
                // Try to get an access token using the resource owner password credentials grant.
                if (config('app.env') === 'production') {
                    $accessToken = $provider->getAccessToken('password', [
                        // Production
                        'username' => 'txintegration@iberdrola.es',
                        'password' => 'Salesforce2020*',
                    ]);
                } else {
                    $accessToken = $provider->getAccessToken('password', [
                        // UAT
                        'username' => 'adminwsaffiliates@deloitte.uat2',
                        'password' => 'Deloitte20',
                    ]);
                }
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                // Failed to get the access token
                exit('ipe: ' . $e->getMessage());
            } catch (\Exception $e) {
                exit('e:' . $e->getMessage());
            }

            // echo 'Access Token: ' . $accessToken->getToken() . "\n";
            // echo 'Refresh Token: ' . $accessToken->getRefreshToken() . "<br>";
            // echo 'Expired in: ' . $accessToken->getExpires() . "<br>";
            // echo 'Already expired? ' . ($accessToken->hasExpired() ? 'expired' : 'not expired') . "<br>";

            $this->accessToken = $accessToken->getToken();
        }

        $client = new \GuzzleHttp\Client(['verify' => false]);

        foreach ($sps as $spx) {
            $sp = StatsProduct::select(
                'stats_product.event_id',
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
                'brand_utilities.utility_label'
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
                $interactions = Interaction::select(
                    'id'
                )->where(
                    'event_id',
                    $sp->event_id
                )->pluck('id');
                if ($interactions) {
                    $recordings = Recording::select(
                        DB::raw('CONCAT("' . config('services.aws.cloudfront.domain') . '", "/", recordings.recording) as url')
                    )->whereIn(
                        'interaction_id',
                        $interactions->toArray()
                    )->get();
                    if (count($recordings) > 0 || $this->option('nightly')) {
                        $body = [
                            'confirmation_code' => $sp->confirmation_code,
                            'created_at' => $sp->event_created_at->toDateTimeString(),
                            'status' => $sp->result,
                            //'email_address' => $sp->email_address,
                            'disposition_reason' => $sp->disposition_reason,
                            'recordings' => $recordings->toArray(),
                        ];

                        echo json_encode($body) . "\n";
                        // exit();
                        // // print_r($body);
                        // // exit();

                        try {
                            $res = $client->post(
                                $posturl,
                                [
                                    'verify' => false,
                                    'debug' => $this->option('debug'),
                                    'headers' => [
                                        'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                        'Accept' => 'application/json',
                                        //'Content-Type' => 'application/x-www-form-urlencoded',
                                        'Authorization' => 'Bearer ' . $this->accessToken,
                                    ],
                                    'body' => json_encode($body),
                                ]
                            );
                            if (200 == $res->getStatusCode() || 201 == $res->getStatusCode()) {
                                // $response = json_decode($body, true);
                                // Update event_product.live_enroll to 1
                                $epupdate = EventProduct::find($sp->event_product_id);
                                if ($epupdate) {
                                    $epupdate->live_enroll = Carbon::now()->toDateTimeString();
                                    $epupdate->save();
                                }
                            }
                            $jd = new JsonDocument();
                            $jd->ref_id = $sp->event_id;
                            $jd->document = [
                                'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                                'response' => $res->getBody(),
                                'response-headers' => $res->getHeaders(),
                                'request-data' => $body
                            ];
                            $jd->document_type = 'iberdrola-live-enroll';
                            $jd->save();
                        } catch (\Exception $e) {
                            $jd = new JsonDocument();
                            $jd->ref_id = $sp->event_id;
                            if ($e instanceof RequestException && $e->hasResponse()) {
                                $res = $e->getResponse();
                                echo '!!Response: ' . Psr7\str($res);

                                $jd->document = [
                                    'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                                    'response' => $res->getBody(),
                                    'response-headers' => $res->getHeaders(),
                                    'request-data' => $body
                                ];
                            } else {
                                $jd->document = [
                                    'error' => $e->getMessage(),
                                    'code' => $e->getCode(),
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine(),
                                    'request-data' => $body
                                ];
                            }
                            $jd->document_type = 'iberdrola-live-enroll';
                            $jd->save();
                            echo '!!Exception: ' . $e->getMessage();
                            if ($e instanceof RequestException && $e->hasResponse()) {
                                echo '!!Response: ' . Psr7\str($e->getResponse());
                            }
                        }
                    } else {
                        echo 'Skipping (' . $sp->confirmation_code . ") because there are no recordings.\n";
                    }

                    // print_r($res->getBody()->getContents());
                    // print_r($body);
                    // exit();
                }
            }
        }
    }
}
