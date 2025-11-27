<?php

namespace App\Console\Commands\VendorLiveEnrollment\BrandHandlers;

use App\Models\JsonDocument;

class Residents extends Generic {
    
    private $brand;

    function __construct($brand)
    {
        $this->brand = $brand;
    }

    public function applyCustomFilter($query)
    {
        return $query->whereIn(
            'stats_product.result',
            ['sale', 'no sale']
        );
    }

    public function handleSubmission($sps, $options)
    {
        if (!is_null($options['vendorCode'])) {
            $this->log("Command parameter 'vendorCode' required!", "error");
            return;
        }

        $vendorCode = $options['vendorCode'];

        if (
            $vendorCode !== '005' &&
            $vendorCode !== '323' &&
            $vendorCode !== '182' &&
            $vendorCode !== '189'
        ) {
            $this->log("Invalid vendorCode value '" . $vendorCode . "'", "error");
            return;
        }

        if ($vendorCode == '005') {
            // Genius POST

            $totalRecords = count($sps);
            $currentRecord = 1;

            foreach ($sps as $sp) {

                $this->log("------------------------------------------------------------");
                $this->log("[ " . $currentRecord . " / " . $totalRecords . " ]\n");
                $this->log("  Confirmation Code: " . $sp->confirmation_code);
                $this->log("  TPV Date: " . $sp->interaction_created_at);
                $this->log("  TPV Status: " . $sp->result);
                $this->log("  Acct #: " . $sp->account_number1);
                $this->log("  Commodity: " . $sp->commodity);
                $this->log("  Sales Rep ID: " . $sp->sales_agent_rep_id . "\n");

                // Ignore records with empty account numbers
                if (empty($sp->account_number1)) {
                    $this->log("  Empty account number. Skipping...\n");
                    $currentRecord++;
                    continue;
                }

                // Redo mode skips doc lookup and sets $j to null to indicate a doc was not found.
                if (!($options['redo']??0)) {
                    $this->log("  Performing doc lookup...\n");
                    $j = JsonDocument::where(  // See if this record was already posted
                        'ref_id',
                        $sp->event_product_id
                    )->where(
                        'document_type',
                        'vendor-live-enrollment'
                    )->first();
                } else {
                    $this->log("  Redo mode. Skipping doc lookup...\n");
                    $j = null;
                }

                if (!$j) {
                    $body =
                        'rep_code=' . $sp->sales_agent_rep_id
                        . '&util_type=' . (strtolower($sp->commodity) == 'electric' ? 'E' : (strtolower($sp->commodity) == 'natural gas' ? 'G' : ''))
                        . '&p_date=' . $sp->interaction_created_at->format("m/d/Y H:i:s")
                        . '&acc_number=' . $sp->account_number1
                        . '&status_txt=' . (strtolower($sp->result) == 'sale' ? 'good sale' : strtolower($sp->result));

                    $this->log("  Payload:");
                    $this->log("  " . $body . "\n");

                    // For dry runs, skip client creation and data post. Create a fake response for console output.
                    if ($options['dry-run'] ?? 0) {
                        $this->log("  Dry run. Skipping client setup and post...\n");

                        $response = '{"success":true,"message":"Dry Run. This is a fake response"}';;
                    } else {
                        $this->log("  Creating HTTP client...");

                        $client = new \GuzzleHttp\Client(
                            [
                                'verify' => false,
                            ]
                        );

                        $this->log("  Posting data...\n");
                        $res = $client->post(
                            'https://worknet.geniussales.com/api/sales',
                            [
                                'debug' => $options['debug'] ?? 0,
                                'http_errors' => false,
                                'headers' => [
                                    'Content-Type' => 'application/x-www-form-urlencoded',
                                    'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                ],
                                'body' => $body,
                            ]
                        );

                        $response = $res->getBody();
                        // $this->log($body);
                    }

                    $this->log("  Response:");
                    $this->log("  " . $response . "\n");

                    // Prepare the JSON doc for the log record.
                    $jsonDoc = [
                        "request" => $body,
                        "response" => $response
                    ];

                    $this->log("  JSON Doc:");
                    $this->log("  " . json_encode($jsonDoc) . "\n");

                    // For dry runs we don't want to save the JSON doc, as this will prevent live runs from submitting the TPV record.
                    if ($options['dry-run'] ?? 0) {
                        $this->log("  Dry run. JSON doc will NOT be saved.\n");
                    } else {
                        $this->log("  Saving JSON doc...\n");

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

                $creds = null;
                if ($sp->vendor_code == '323') {
                    $creds = [
                        "campIndex" => "148",
                        "username" => "residents@platinum",
                        "password" => "Residents123"
                    ];
                } else if ($sp->vendor_code == '182') {
                    $creds = [
                        "campIndex" => "RESIDENTS",
                        "username" => "DXC@TERRIBITE.COM",
                        "password" => "1234"
                    ];
                } else if ($sp->vendor_code == '189') {
                    $creds = [
                        "campIndex" => "RESIDENTS 2",
                        "username" => "DXC@TERRIBITE.COM",
                        "password" => "1234"
                    ];
                }

                if ($creds) {
                    $j = JsonDocument::where(
                        'ref_id',
                        $sp->event_product_id
                    )->where(
                        'document_type',
                        'vendor-live-enrollment'
                    )->first();
                    if (!$j) {
                        $body = [
                            'campIndex' => $creds['campIndex'],
                            'Username' => $creds['username'],
                            'Password' => $creds['password'],
                            '1' => $sp->event_created_at->format('m-d-Y'),
                            '2' => $sp->vendor_code,
                            '5' => $sp->language,
                            '11' => ($sp->vendor_code == '323' ? $sp->auth_first_name : $sp->bill_first_name . ' ' . $sp->bill_last_name),
                            '12' => ($sp->vendor_code == '323' ? $sp->auth_last_name : $sp->bill_last_name),
                            '16' => ltrim(
                                trim(
                                    $sp->btn
                                ),
                                '+1'
                            ),
                            '17' => $sp->account_number1,
                            '18' => $sp->service_address1,
                            '19' => $sp->service_address2,
                            '20' => $sp->service_city,
                            '21' => $sp->service_state,
                            '22' => $sp->service_zip,
                            '28' => ($sp->email_address)
                                ? $sp->email_address
                                : '',
                            '29' => ($sp->vendor_code != '323' ? $sp->market : ''),
                            '30' => ($sp->vendor_code != '323' ? $sp->utility_commodity_ldc_code : ''),
                            '31' => $sp->commodity,
                            '32' => ($sp->vendor_code == '323' && $sp->product_green_percentage = 100 ? 'Yes' : ''),
                            '36' => ($sp->vendor_code == '323' ? $sp->confirmation_code : $sp->id),
                            '37' => $sp->sales_agent_rep_id,
                            '38' => (strtolower($sp->result) == 'sale' ? 'good sale' : 'no sale'),
                            '40' => $sp->disposition_reason,
                            '41' => $sp->sales_agent_name,
                            '43' => $sp->interaction_time,
                            '53' => ($sp->vendor_code == '323' ? $sp->id : $sp->confirmation_code),
                            '56' => ($sp->vendor_code == '323' ? ltrim(trim($sp->ani), '+1') : ''),
                            '60' => $sp->office_grp_id,
                            '100' => ($sp->vendor_code == '182' ? '150' : ($sp->vendor_code == '189' ? '100' : '')),
                        ];

                        // $this->log(json_encode($body));
                        // print_r($body);
                        // exit();

                        $client = new \GuzzleHttp\Client(
                            [
                                'verify' => false,
                            ]
                        );

                        $res = $client->post(
                            ($sp->vendor_code == '323' ? 'https://apps.terribite.com/platinum/api/SaveTPV' : 'https://apps.terribite.com/api/SaveTPV'),
                            [
                                'debug' => $options['debug'] ?? 0,
                                'http_errors' => false,
                                'headers' => [
                                    'Content-Type' => 'text/plain',
                                    'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                ],
                                'body' => json_encode($body),
                            ]
                        );

                        // $body = $res->getBody();
                        // $this->log($body);                                

                        $j = new JsonDocument();
                        $j->document_type = 'vendor-live-enrollment';
                        $j->ref_id = $sp->event_product_id;
                        $j->document = $body;
                        $j->save();
                    }
                } else {
                    $this->log('Could not find any provider integration credentials.', "error");
                }
            }
        }
    }

}
