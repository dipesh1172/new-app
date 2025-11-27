<?php

namespace App\Console\Commands\VendorLiveEnrollment\BrandHandlers;

use Illuminate\Support\Str;

use Carbon\Carbon;

use App\Models\JsonDocument;
use App\Models\ProviderIntegration;

class NRG extends Generic
{
    private $brand;
    private $cmd;

    function __construct($brand, $command)
    {
        $this->brand = $brand;
        $this->cmd = $command;
    }

    public function applyCustomFilter($query)
    {
        $this->cmd->info('Applying custom query filter for ' . $this->brand->name . '...');

        return $query->whereIn(
            'stats_product.result',
            ['sale', 'no sale']
        );
    }

    public function getCredentials($options) {
        $creds =  ProviderIntegration::where(
            'brand_id',
            $options['brand']
        )->where(
            'service_type_id',
            32 // NRG API
        )->where(
            'provider_integration_type_id',
            2 // API
        )->where(
            'env_id',
            (config('app.env') == 'production' ? 1 : 2)
        );
        
        // Display SQL query.        
        if ($this->cmd->option('show-sql')) {
            $queryStr = getSqlQueryString($creds);

            $this->cmd->info("\nQUERY:");
            $this->cmd->info("\n" . $queryStr . "\n");
        }

        $creds = $creds->first();

        return $creds;
    }

    public function handleSubmission($sps, $options)
    {
        $flag_debug = $options['debug'] ?? FALSE;
        $dry_run = $options['dry-run'] ?? FALSE;

        // Get credentials for the UpdateTrans() operation
        $this->cmd->info('Retrieveing credentials for TransUpdate() API...');

        $nrg_api_credentials = $this->getCredentials($options);
        if(!$nrg_api_credentials) {
            $this->cmd->error('Unable to retrieve credentials. Program will continue but all TransUpdate() API calls will be skipped');
        }

        $this->cmd->info('Processing records...');

        $recordCtr = 1;
        $totalRecrods = count($sps);

        foreach ($sps as $sp) {

            $this->cmd->info("\n--------------------------------------------------------------------------------");
            $this->cmd->info('[ ' . $recordCtr . ' / ' . $totalRecrods .' ]');
            $this->cmd->info('Current Time: ' . Carbon::now('America/Chicago')->format('Y-m-d H:i:s'));
            $this->cmd->info('');
            $this->cmd->info('Channel:        ' . $sp->channel);
            $this->cmd->info('Record Locator: ' . $sp->event_external_id);
            $this->cmd->info('TPV Result:     ' . $sp->result);
            $this->cmd->info('');

            // Only apply to TM channel
            if ($sp->channel != 'TM') {
                $this->cmd->info('Not a TM record. Skipping...');
                continue;
            }

            $event_external_id = $sp->event_external_id;

            if (empty($event_external_id)) {
                $this->cmd->info('No record locator. Skipping...');                
                continue;
            }

            if (Str::startsWith($event_external_id, 'S')) {

                $this->cmd->info('Record locator begins with "S". UpdateTransID() API endpoint will be used.');

                // TransUpdate
                if(!$nrg_api_credentials) {
                    echo "No credentials. Skipping\n";
                    continue;
                }

                // Ignore no sale - pending records
                if($sp->result === 'No Sale' && $sp->disposition_reason === 'Pending')
                    continue;

                $j = JsonDocument::where(
                    'ref_id',
                    $sp->event_product_id
                )->where(
                    'document_type',
                    'nrg-live-enrollment'
                )->first();

                if (!$j) {
                    $body = [
                        'IvAcctNum' => $sp->account_number1,
                        'IvDateTime' => $sp->interaction_created_at->format("Y-m-d H:i:s"),
                        'IvResult' => $sp->result == 'Sale' ? 'GS' : 'NS',
                        'IvTransactionid' => $event_external_id,
                        'IvReason' => $sp->disposition_reason
                    ];

                    if($dry_run) {
                        $this->cmd->info("Dry run. Skipping API call.");
                        continue;
                    }

                    $result = $this->transUpdate($body, $nrg_api_credentials, $flag_debug);

                    // 2022-07-28 - Alex K - Changed logging to log both errors and success. This means we won't retry records if we get an error back,
                    //                       but we will see the error in the log.
                    $logBody = [
                        'record_locator' => $event_external_id,
                        'confirmation_code' => $sp->confirmation_code,
                        'request_url' => $result->request_url,
                        'request' => $result->request,
                        'result' => $result->result,
                        'response' => $result->response
                    ];

                    $jd = new JsonDocument();
                    $jd->ref_id = $sp->event_product_id;
                    $jd->document_type = 'nrg-live-enrollment';
                    $jd->document = $logBody;
                    $jd->save();
                } else {
                    $this->cmd->info('Record already submitted. Skipping.');
                }

            } else {

                $this->cmd->info('Record locator does NOT begins with "S". SendTransID() API endpoint will be used.');

                if ($sp->result == 'Sale') { // Only run SendTransID for good sales
                    
                    $this->cmd->info('Checking if record was already submitted...');

                    // SendTransID
                    $j = JsonDocument::where(
                        'ref_id',
                        $event_external_id
                    )->where(
                        'document_type',
                        'nrg-live-enrollment'
                    )->first();

                    if (!$j) {

                        if($dry_run) {
                            $this->cmd->info("Dry run. Skipping API call.");
                            continue;
                        }

                        $this->cmd->info('Submitting record...');

                        $result = $this->sendTransID($event_external_id, $flag_debug);
                        
                        // 2022-07-28 - Alex K - Changed logging to log both errors and success. This means we won't retry records if we get an error back,
                        //                       but we will see the error in the log.
                        $logBody = [
                            'record_locator' => $event_external_id,
                            'confirmation_code' => $sp->confirmation_code,
                            'result' => $result->result,
                            'response' => $result->response
                        ];

                        $jd = new JsonDocument();
                        $jd->ref_id = $event_external_id;
                        $jd->document_type = 'nrg-live-enrollment';
                        $jd->document = $logBody;
                        $jd->save();
                    } else {
                        $this->cmd->info('Record already submitted. Skipping.');
                    }
                } else {
                    $this->cmd->info('Not a good sale. Skipping record.');
                }
            }

            $recordCtr++;
        }
    }

    /**
     * EnergyPlus / authorize
     * - Runs once per TPV.
     * - Only runs if the TPV was good saled.
     * - Only runs if a transaction ID (event_external_id) that does NOT begin with 'S' was provided.
     * @param $record_locator
     * @return mixed
     */
    private function sendTransID($record_locator, $debug = FALSE)
    {
        $this->cmd->info("\nSetting up Guzzle Client...");
        $client = new \GuzzleHttp\Client(
            [
                'verify' => false,
            ]
        );

        $this->cmd->info('Making SendTransID() API call...');

        $result = (object)[
            'result' => '',
            'request_url' => '',
            'request' => '',
            'response' => ''
        ];

        $response = null;

        try {
            $url = "https://www.energypluscompany.com/api/enrollment/authorize/" . $record_locator;
            $this->cmd->info('URL: ' . $url);

            $res = $client->post(
                $url,
                [
                    'debug' => $debug,
                    'http_errors' => false,
                    'headers' => [
                        'Content-Type' => 'text/plain',
                        'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                    ]
                ]
            );
            
            $body = $res->getBody();

            // Check for non-JSON resopnse
            if( !str_starts_with(trim($body), '{') && !str_starts_with(trim($body), '[')) {
                $body = '{"error":"' . $body . '"}';
            }

        } catch(\Exception $e) {
            $body = '{"exception":"Exception encountered when trying to post data: ' . $e->getMessage() . '"}';
        }

        $this->log($body);

        $this->cmd->info('Parsing response...');
        $response = json_decode($body);

        $result->result = ($response && isset($response->success) && $response->success ? 'Success' : 'Error');
        $result->request_url = $url;
        $result->response = $response;

        return $result;
    }

    /**
     * EnergyPlus / tpvApiTransUpdate
     * - Runs once per account (event_product).
     * - Only runs if at least one event product was saved.
     * - Runs for both good sales and no sales.
     * - Only runs if a transaction ID (event_external_id) beginning with 'S' was provided.
     * @param $body: Request body
     * @return mixed
     */
    private function transUpdate($body, $credentials, $debug = FALSE)
    {
        print_r([
            'debug' => $debug,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'text/plain',
                'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                'Authorization' => 'BASIC ' . base64_encode($credentials['username'] . ':' . $credentials['password'])
            ],
            'body' => json_encode($body),
        ]);

        $client = new \GuzzleHttp\Client(
            [
                'verify' => false,
            ]
        );

        $this->cmd->info('Making TransUpdate() API call...');

        $result = (object)[
            'result' => '',
            'request_url' => '',
            'request' => '',
            'response' => ''
        ];

        $response = null;

        try {
            $url = (config('app.env') == 'production' ? "https://api.nrg.com/NRGREST/rest/tpvApi/tpvApiTransUpdate" : "https://stg-api.nrg.com/NRGREST/rest/tpvApi/tpvApiTransUpdate");
            $this->cmd->info('URL: ' . $url);

            $res = $client->post(
                $url,
                [
                    'debug' => $debug,
                    'http_errors' => false,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                        'Authorization' => 'BASIC ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
                        'Cookie' => 'SMCHALLENGE=YES'
                    ],        
                    'body' => json_encode($body)                
                ]
            );
            $responseBody = $res->getBody();

            // Check for non-JSON resopnse
            if( !str_starts_with(trim($responseBody), '{') && !str_starts_with(trim($responseBody), '[')) {
                $responseBody = '{"error":"' . $responseBody . '"}';
            }

        } catch (\Exception $e) {
            $responseBody = '{"exception":"Exception encountered when trying to post data: ' . $e->getMessage() . '"}';
        }

        $this->log($responseBody);

        $this->cmd->info('Parsing response...');
        $response = json_decode($responseBody);

        $result->result = ($response && isset($response->type) && $response->type == 'S' ? 'Success' : 'Error');
        $result->request_url = $url;
        $result->request = $body;
        $result->response = $response;
        
        return $result;
    }
}
