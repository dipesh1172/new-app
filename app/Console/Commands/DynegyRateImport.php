<?php

namespace App\Console\Commands;

use App\Models\BrandUtility;
use App\Models\Product;
use App\Models\ProviderIntegration;
use App\Models\Rate;
use App\Models\UtilitySupportedFuel;
use Illuminate\Console\Command;
use Carbon\Carbon;

class DynegyRateImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dynegy:rates {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dynegy Rate Importing from API';

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
        $brand_id = '306e48d7-6ed2-41d2-a355-55adf79506a5';

        // Cleanup existing
        echo "Cleaning up existing products/rates.\n";
        $products = Product::where(
            'brand_id',
            $brand_id
        )->get();
        if ($products) {
            foreach ($products as $product) {
                echo '-- Removing product '.$product->id."\n";

                $rates = Rate::where(
                    'product_id',
                    $product->id
                )->get();
                if ($rates) {
                    foreach ($rates as $rate) {
                        echo '-- Removing rate '.$rate->id."\n";
                        $rate->delete();
                    }
                }

                $product->delete();
            }
        }

        echo "\nStarting API rate pull...\n";
        $pi = ProviderIntegration::where(
            'service_type_id',
            6
        )->where(
            'brand_id',
            $brand_id
        )->first();
        if ($pi) {
            $url = 'https://services-qa.txmkt.txu.com/getUtilityByZipOrState?State=PA,OH,IL';
            $client = new \GuzzleHttp\Client();
            $res = $client->request(
                'GET',
                $url,
                [
                    'auth' => [
                        $pi->username,
                        $pi->password,
                    ],
                    'debug' => true,
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSLVERSION => CURL_SSLVERSION_DEFAULT,
                    ],
                    'headers' => [
                        'Accept' => 'text/xml',
                    ],
                ]
            );

            if (200 == $res->getStatusCode()) {
                // info($res->getBody());
                // exit();

                $body = json_decode($res->getBody(), true)['Response'];
                for ($i = 0; $i < count($body); ++$i) {
                    if ($this->option('debug')) {
                        print_r($body[$i]);
                    }

                    $bu = BrandUtility::where(
                        'brand_id',
                        $brand_id
                    )->where(
                        'utility_external_id',
                        $body[$i]['LDCVendorName']
                    )->first();
                    if ($bu) {
                        $bu->utility_label = $body[$i]['UtilityCode'];
                        $bu->service_territory = $body[$i]['ServiceTerritory'];
                        $bu->save();

                        $purl = 'https://services-qa.txmkt.txu.com/getOffersForREx?ServiceTerritory='
                            .rawurlencode(trim($bu->service_territory));

                        if ($this->option('debug')) {
                            echo 'PRODUCT URL '.$purl."\n";
                            echo "\n-------------\n";
                        }

                        $pres = $client->get(
                            $purl,
                            [
                                'auth' => [
                                    $pi->username,
                                    $pi->password,
                                ],
                                'debug' => false,
                                'curl' => [
                                    CURLOPT_SSL_VERIFYPEER => false,
                                    CURLOPT_SSLVERSION => CURL_SSLVERSION_DEFAULT,
                                ],
                            ]
                        );

                        if ($this->option('debug')) {
                            print_r($pres);
                        }

                        if (200 == $pres->getStatusCode()) {
                            echo $body[$i]['LDCVendorName']."\n";
                            $pbody = json_decode($pres->getBody(), true);

                            if ($this->option('debug')) {
                                print_r($pbody);
                            }

                            if (isset($pbody['Response'])) {
                                echo "\tPulling rates:\n";

                                $pbody = $pbody['Response'];
                                for ($x = 0; $x < count($pbody); ++$x) {
                                    if ('Dynegy' == $pbody[$x]['Brand']) {
                                        $p = Product::where(
                                            'name',
                                            $pbody[$x]['Product']
                                        )->where(
                                            'brand_id',
                                            $brand_id
                                        )->withTrashed()->first();
                                        if ($p && $p->trashed()) {
                                            $p->restore();
                                        }

                                        if (!$p) {
                                            $p = new Product();
                                            $p->brand_id = $brand_id;
                                            $p->name = $pbody[$x]['Product'];
                                            $p->channel = 'DTD';
                                            $p->market = 'Residential';
                                            $p->home_type = 'Single|Multi-Family';
                                            $p->rate_type_id = 1;
                                            $p->term = $pbody[$x]['Term'];
                                            $p->term_type_id = 3;
                                            $p->save();
                                        }

                                        $usf = UtilitySupportedFuel::where(
                                            'utility_id',
                                            $bu->utility_id
                                        )->where(
                                            'utility_fuel_type_id',
                                            1
                                        )->first();
                                        if ($usf) {
                                            $r = Rate::where(
                                                'product_id',
                                                $p->id
                                            )->where(
                                                'program_code',
                                                $pbody[$x]['ProductId']
                                            )->where(
                                                'utility_id',
                                                $usf->id
                                            )->withTrashed()->first();
                                            if ($r && $r->trashed()) {
                                                $r->restore();
                                            }

                                            if ($r) {
                                                if ($r->rate_amount != $pbody[$x]['Price']) {
                                                    $r->delete();

                                                    $r = new Rate();
                                                    $r->product_id = $p->id;
                                                    $r->utility_id = $usf->id;
                                                    $r->rate_currency_id = 1;
                                                    $r->rate_uom_id = 2;
                                                    $r->program_code
                                                        = $pbody[$x]['ProductId'];
                                                    $r->rate_amount
                                                        = $pbody[$x]['Price'];
                                                    $r->date_from = Carbon::now();
                                                    $r->date_to = date(
                                                        'Y-m-d H:i:s',
                                                        strtotime($pbody[$x]['TermEndDate'])
                                                    );
                                                    $r->save();

                                                    echo "\t\t-- Price changed for ("
                                                        .$pbody[$x]['ProductId']
                                                        .") versioning...\n";
                                                } else {
                                                    echo "\t\t-- No change for ("
                                                        .$pbody[$x]['ProductId']
                                                        .") skipping...\n";
                                                }
                                            } else {
                                                $r = new Rate();
                                                $r->product_id = $p->id;
                                                $r->utility_id = $usf->id;
                                                $r->rate_currency_id = 1;
                                                $r->rate_uom_id = 2;
                                                $r->program_code
                                                    = $pbody[$x]['ProductId'];
                                                $r->rate_amount = $pbody[$x]['Price'];
                                                $r->date_from = Carbon::now();
                                                $r->date_to = date('Y-m-d H:i:s', strtotime($pbody[$x]['TermEndDate']));
                                                $r->save();

                                                echo "\t\t-- New rate found ("
                                                    .$pbody[$x]['ProductId'].") adding...\n";
                                            }
                                        } else {
                                            // nothing
                                        }
                                    }
                                }
                            } else {
                                if (isset($pbody['Error'])) {
                                    echo "\t-- ".$pbody['Error']."\n";

                                    // echo "Brand Utility ID = " . $bu->id . "\n";
                                }
                            }
                        }
                    } else {
                        echo $body[$i]['LDCVendorName']."\n";
                        echo "\t-- No match for '".$body[$i]['LDCVendorName']."' in focus.\n";
                    }

                    // if ($this->option('debug')) {
                    //     echo "-----------------\n";
                    // }
                }
            }
        } else {
            echo "Unable to find provider_integration.\n";
        }
    }
}
