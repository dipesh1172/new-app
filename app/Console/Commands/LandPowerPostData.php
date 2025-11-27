<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

use App\Models\Brand;
use App\Models\Interaction;
use App\Models\JsonDocument;

class LandPowerPostData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landpower:post:data  {--nopost} {--debug} {--ignore_brand} {--limit=} {--confirmation_code=} {--post_url=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Land Power Post Data Back to the client';

    protected $landPowerURL = [
        'prod' => 'https://prod.landpower.net/api/enrollment/orderservice/externalupdateorderstatus',
        'stage' =>  'https://int2.landpower.net/api/enrollment/orderservice/externalupdateorderstatus'
    ];

    protected $document_type = 'landpower-tpv-post';

    private $logger;

    private $teamPrepend = '[LandpowerPostData]';

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
        $this->logger->info("[LandPowerPostData] " . $msg);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $env_id = (config('app.env') === 'production') ? 1 : 2;

            // Get brand ID
            $brandId = ($env_id == 1 ? '70d88b23-650e-4d36-813d-575e894f2412' : 'c74a626a-a676-4ac4-a7e4-6e270e1719c8');

            // Search for brand record
            $brand = Brand::where('id', $brandId)->first();

            if (!$brand) {
                $this->log("Cannot find $brandId in the brands table.\n");

                exit();
            }

            // Set up HTTP client
            $client = new \GuzzleHttp\Client(['verify' => false]);

            // Set up record limit
            $limit = ($this->option('limit')) ? $this->option('limit') : 99;

            // Search for data to submit to API
            $interactions = Interaction::select(
                'events.confirmation_code as orderConfirmationCode',
                'interactions.event_result_id as statusId',
                'interactions.id',
                'dispositions.brand_label as dispositionId',
                'dispositions.description',
                'tpv_staff.username as user',
                DB::raw('date_format(events.created_at,"%m-%d-%Y %H:%i:%S") as statusDate')
            )->join(
                'events',
                'interactions.event_id',
                'events.id'
            )->join(
                'dispositions', 
                'interactions.disposition_id', 
                'dispositions.id'
            )->join(
                'tpv_staff',
                'interactions.tpv_staff_id',
                'tpv_staff.id'
            )->whereIn(
                'interactions.event_result_id',
                [
                    1, // Sale
                    2, // No Sale
                ]
            )->whereNotNull(
                'dispositions.id'
            )->whereIn(
                'interactions.interaction_type_id',
                [
                    1, // call_inbound
                    2, // call_outbound
                ]
            );

            if (!$this->option('ignore_brand')) {
                $interactions = $interactions->where('events.brand_id', $brand->id);
            } else {
                $this->log("Ignoring the brand " . $this->option('ignore_brand'));
            }

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

            $interactions = $interactions->limit($limit)->get();

            if (!$interactions || $interactions->count() === 0) {
                $this->log("No interactions were found.\n");
                exit();
            }

            // Build URL to post to.
            if ($this->option('post_url')) {
                $postUrl = $this->option('post_url');
            } else {
                $postUrl = $env_id === 1 ? $this->landPowerURL['prod'] : $this->landPowerURL['stage'];
            }

            // Submit the records
            $payload = [];
            foreach ($interactions as $interaction) {

                try {
                    // Build payload
                    $payload = [
                        "orderConfirmationCode" => $interaction->orderConfirmationCode,
                        "statusId" => $interaction->statusId === 1 ? 2 : 3,
                        "dispositionId" => $interaction->dispositionId, //dispositionId == 88 ? 17 : dispositionId,
                        "notes" => "",
                        "user" => $interaction->user,
                        "app" => "AnswerNet",
                        "statusDate" => $interaction->statusDate,
                    ];

                    if ($this->option('debug')) {
                        $this->log("Interaction: #" . $interaction->id . "\n");
                        info("Interaction: #" . $interaction->id);
                        info("body =>" . print_r($payload, true));
                    }

                    // Post the record to API
                    if (!$this->option('nopost')) {

                        $res = $client->post(
                            $postUrl,
                            [
                                'verify' => false,
                                'debug' => $this->option('debug'),
                                'headers' => [
                                    'User-Agent' => 'DXCLandPowerPostData/' . config('app.version', 'debug'),
                                    'Accept' => 'application/json',
                                    'Content-Type' => 'application/json'
                                ],
                                'body' => json_encode($payload),
                            ]
                        );

                        // Parse result
                        if (200 == $res->getStatusCode() || 201 == $res->getStatusCode()) {
                            $body = $res->getBody();
                            $response = json_decode($body, true);

                            if ($this->option('debug')) {
                                print_r($response);
                            }

                            // Record result in JSON document
                            $jd = new JsonDocument();
                            $jd->ref_id = $interaction->id;
                            $jd->document = [
                                'StatusCode' => $res->getStatusCode(),
                                'response' => $response,
                                'response-headers' => $res->getHeaders(),
                                'request-data' => $payload
                            ];
                            $jd->document_type = $this->document_type;
                            $jd->save();
                        }
                        else {
                            $jd = new JsonDocument();
                            $jd->ref_id = $interaction->id;
                            $jd->document = [
                                'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                                'response' => $res->getBody(),
                                'response-headers' => $res->getHeaders(),
                                'request-data' => $payload
                            ];
                            $jd->document_type = $this->document_type;
                            $jd->save();
                        }
                        
                        $interaction->enrolled = $res->getStatusCode();
                        $interaction->save();
                    }
                } catch (\Exception $e) {
                    $this->log('!!Exception: ' . $e->getMessage());

                    // Record error in JSON document
                    $jd = new JsonDocument();
                    $jd->ref_id = $interaction->id;
        
                    if ($e instanceof RequestException && $e->hasResponse()) {
                        $res = $e->getResponse();
                        $this->log('!!Response: ' . Psr7\str($res));
        
                        $jd->document = [
                            'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                            'response' => $res->getBody(),
                            'response-headers' => $res->getHeaders(),
                            'request-data' => $payload
                        ];

                        SendTeamMessage(
                            'monitoring', 
                            $this->teamPrepend . "[$interaction->orderConfirmationCode][$interaction->id] Error posting to Landpower's API. Status Code: " . $res->getStatusCode(). ", Message: " . $res->getReasonPhrase()
                        );
                    } else {
                        $jd->document = [
                            'error' => $e->getMessage(),
                            'code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'request-data' => $payload
                        ];

                        SendTeamMessage(
                            'monitoring', 
                            $this->teamPrepend . "[$interaction->orderConfirmationCode][$interaction->id] An exception occurred. Line: " . $e->getLine() . ", Message: " . $e->getMessage()
                        );
                    }
        
                    $jd->document_type = $this->document_type;
                    $jd->save();
                }
            }

        } catch (\Exception $e) {
            SendTeamMessage(
                'monitoring', 
                $this->teamPrepend . " An exception occurred. Line: " . $e->getLine() . ", Message: " . $e->getMessage()
            );
        }
    }
}

