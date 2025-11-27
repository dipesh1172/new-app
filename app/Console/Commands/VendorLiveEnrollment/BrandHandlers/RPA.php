<?php

namespace App\Console\Commands\VendorLiveEnrollment\BrandHandlers;

use App\Models\JsonDocument;
use App\Models\ProviderIntegration;

class RPA extends Generic {
    
    private $brand;

    function __construct($brand)
    {
        $this->brand = $brand;
    }

    public function applyCustomFilter($query)
    {
        return $query;
    }

    public function handleSubmission($sps, $options)
    {
        $pi = ProviderIntegration::where(
            'service_type_id',
            11
        )->where(
            'brand_id',
            $this->brand->id
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

                    // $this->log(json_encode($body));
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
                            'debug' => $options['debug'],
                            'http_errors' => false,
                            'headers' => [
                                'Content-Type' => 'text/plain',
                                'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                            ],
                            'body' => json_encode($body),
                        ]
                    );

                    $body = $res->getBody();
                    // $this->log($body);
                }
            }
        } else {
            $this->log('Could not find any provider integration credentials.', "error");
        }
    }
}
