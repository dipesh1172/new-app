<?php

namespace App\Console\Commands;

use \SoapClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;

use App\Models\UtilitySupportedFuel;
use App\Models\Utility;
use App\Models\Rate;
use App\Models\Product;
use App\Models\JsonDocument;
use App\Models\Brand;

class TXURates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'txu:rates
        {--defaults : Use default defined areas}
        {--cache : Cache rate output}
        {--areas-update= : Update area json}
        {--showRequest : Show XML Request to getProductOffer}
        {--areasjson : Dump area json for updating areas in db}
        {--dryrun : Don\'t make permanent changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'TXU Rates API';

    /**
     * Create a new command instance.
     *
     * @return void
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
        // Staging: http://services.test.txu.com/ThirdPartyEnrollmentServiceV2.svc
        // Prod:    https://services.txu.com/ThirdPartyEnrollmentServiceV2.svc
        if ($this->option('areasjson')) {
            $this->line(json_encode($this->get_txu_areas()));
            return 0;
        }

        if ($this->option('areas-update') !== null) {
            $this->info('Updating Area JSON');
            $this->update_txu_areas($this->option('areas-update'));
            return 0;
        }

        $brand = Brand::where('name', 'TXU Energy')->first();

        if (file_exists('output.json') && $this->option('cache')) {
            $this->info('Loading Rates from Cache file');
            $info = json_decode(file_get_contents('output.json'), true);
        } else {
            $info = $this->soap_get_rates();
        }
        $utilityNames = array_keys($info);
        $utilities = array_map(function ($item) {
            return $item['info'];
        }, $info);

        $this->info('Cleaning up rates...');
        $info = $this->fix_multi_utility_zips($info);
        if ($this->option('dryrun')) {
            file_put_contents('clean.json', json_encode($info));
            $this->info('Cleaned Rates written to clean.json');
        }

        $this->info('Processing Rates...');
        $products = $this->normalize_rates($utilityNames, $info);

        if ($this->option('dryrun')) {
            file_put_contents('normalized.json', json_encode($products));
            $this->info('Normalized Rates written to normalized.json');
            die;
        }

        DB::transaction(function () use ($brand, $products, $utilities) {
            $existingProducts = Product::where('brand_id', $brand->id)->withTrashed()->get();
            $existingProductIds = $existingProducts->pluck('id')->toArray();

            Product::where('brand_id', $brand->id)->delete();
            Rate::whereIn('product_id', $existingProductIds)->delete();

            foreach ($products as $productName => $rawRates) {
                $newProduct = Product::where(
                    'name',
                    $productName
                )->where(
                    'brand_id',
                    $brand->id
                )->orderBy(
                    'created_at',
                    'desc'
                )->withTrashed()->first();
                if ($newProduct) {
                    if ($newProduct->trashed()) {
                        $newProduct->restore();
                    }
                } else {
                    $newProduct = new Product();
                    $newProduct->brand_id = $brand->id;
                    $newProduct->name = $productName;
                    $newProduct->channel = 'DTD';
                    $newProduct->market = 'Residential';
                    $newProduct->home_type = 'Single|Multi-Family|Apartment';
                    $newProduct->rate_type_id = 1;
                    $newProduct->term = $rawRates[0]['term'];
                    $newProduct->term_type_id = 3;
                    $newProduct->prepaid = $rawRates[0]['prepaid'];
                    $newProduct->save();
                }

                foreach ($rawRates as $rawRate) {
                    $actualUtility = Cache::remember(
                        'txu-util-supported-' . $utilities[$rawRate['utility']]['id'],
                        60,
                        function () use ($utilities, $rawRate) {
                            return UtilitySupportedFuel::select(
                                'id'
                            )->where(
                                'utility_id',
                                $utilities[$rawRate['utility']]['id']
                            )->where(
                                'utility_fuel_type_id',
                                1
                            )->first();
                        }
                    );

                    if ($actualUtility) {
                        $newRate = Rate::where(
                            'product_id',
                            $newProduct->id
                        )->where(
                            'program_code',
                            $rawRate['code']
                        )->where(
                            'utility_id',
                            $actualUtility->id
                        )->where(
                            'rate_currency_id',
                            1
                        )->where(
                            'rate_uom_id',
                            2
                        )->where(
                            'cancellation_fee',
                            $rawRate['cancel_fee']
                        )->where(
                            'external_rate_id',
                            $rawRate['code']
                        )->where(
                            'rate_amount',
                            $rawRate['rate']
                        )->where(
                            'postalcode_validation',
                            $rawRate['area_regex']
                        )->where(
                            'raw_postalcodes',
                            implode(',', $rawRate['area'])
                        )->orderBy(
                            'created_at',
                            'desc'
                        )->withTrashed()->first();
                        if ($newRate) {
                            if ($newRate->trashed()) {
                                $newRate->restore();
                            }
                        } else {
                            $newRate = new Rate();
                            $newRate->product_id = $newProduct->id;
                            $newRate->utility_id = $actualUtility->id;
                            $newRate->rate_currency_id = 1;
                            $newRate->rate_uom_id = 2;
                            $newRate->cancellation_fee = $rawRate['cancel_fee'];
                            $newRate->cancellation_fee_currency = 1;
                            $newRate->external_rate_id = $rawRate['code'];
                            $newRate->program_code = $rawRate['code'];
                            $newRate->rate_amount = $rawRate['rate'];
                            $newRate->custom_data_1 = $rawRate['rate_guides']['500'];
                            $newRate->custom_data_2 = $rawRate['rate_guides']['1000'];
                            $newRate->custom_data_3 = $rawRate['rate_guides']['2000'];
                            $newRate->postalcode_validation = $rawRate['area_regex'];
                            $newRate->raw_postalcodes = implode(',', $rawRate['area']);
                            $newRate->save();
                        }
                    }
                }
            }
        });

        Cache::forget('get_program_codes');
    }

    private function normalize_rates($utilities, $info)
    {
        $products = [];
        foreach ($utilities as $utility) {
            $areas = array_keys($info[$utility]['areas']);
            foreach ($areas as $area) {
                if (isset($info[$utility]['areas'][$area]['Offers']['Offer'])) {
                    $offers = $info[$utility]['areas'][$area]['Offers']['Offer'];
                    foreach ($offers as $offer) {
                        $productName = trim(
                            $this->strip_tags_content($offer['Name'])
                        );
                        $rate = [
                            'area' => [$area],
                            'utility' => $utility,
                            'rate' => $offer['NumericBaseRate'],
                            'term' => $offer['NumericTerm'],
                            'cancel_fee' => preg_replace('/[^0-9]/', '', $offer['CancellationFee']),
                            'code' => $offer['Id'],
                            'prepaid' => $offer['IsPrePaidPlan'],
                            'rate_guides' => [
                                '500' => preg_replace(
                                    '/[^0-9\.]/',
                                    '',
                                    $offer['Rates']['Rate'][0]['AveragePriceperkWh']
                                ),
                                '1000' => preg_replace(
                                    '/[^0-9\.]/',
                                    '',
                                    $offer['Rates']['Rate'][1]['AveragePriceperkWh']
                                ),
                                '2000' => preg_replace(
                                    '/[^0-9\.]/',
                                    '',
                                    $offer['Rates']['Rate'][2]['AveragePriceperkWh']
                                ),
                            ],
                        ];
                        if (!isset($products[$productName])) {
                            $products[$productName] = [];
                            $products[$productName][] = $rate;
                        } else {
                            $found = false;
                            for ($i = 0, $len = count($products[$productName]); $i < $len; $i += 1) {
                                if ($this->rates_are_equal($rate, $products[$productName][$i])) {
                                    $products[$productName][$i]['area'][] = $rate['area'][0];
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $products[$productName][] = $rate;
                            }
                        }
                    }
                };
            }
        }
        $productNames = array_keys($products);
        for ($i = 0, $len = count($productNames); $i < $len; $i += 1) {
            for ($n = 0, $nlen = count($products[$productNames[$i]]); $n < $nlen; $n += 1) {
                $regex = $this->__compile_csv_to_regex($products[$productNames[$i]][$n]['area']);
                $products[$productNames[$i]][$n]['area_regex'] = $regex;
            }
        }
        return $products;
    }

    private function rates_are_equal($rateA, $rateB): bool
    {
        if (
            $rateA['utility'] == $rateB['utility'] &&
            $rateA['rate'] == $rateB['rate'] &&
            $rateA['term'] == $rateB['term'] &&
            $rateA['cancel_fee'] == $rateB['cancel_fee'] &&
            $rateA['code'] == $rateB['code'] &&
            $rateA['prepaid'] == $rateB['prepaid'] &&
            $rateA['rate_guides']['500'] == $rateB['rate_guides']['500'] &&
            $rateA['rate_guides']['1000'] == $rateB['rate_guides']['1000'] &&
            $rateA['rate_guides']['2000'] == $rateB['rate_guides']['2000']
        ) {
            return true;
        }
        return false;
    }

    private function strip_tags_content($text, $tags = '', $invert = false)
    {
        preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
        $tags = array_unique($tags[1]);

        if (is_array($tags) and count($tags) > 0) {
            if ($invert == false) {
                return preg_replace('@<(?!(?:' . implode('|', $tags) . ')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
            } else {
                return preg_replace('@<(' . implode('|', $tags) . ')\b.*?>.*?</\1>@si', '', $text);
            }
        } elseif ($invert == false) {
            return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
        }
        return $text;
    }

    private function update_txu_areas($file)
    {
        $abbrMap = [
            'tnmp' => 'Texas New Mexico Power',
            'oncor' => 'Oncor',
            'aepnorth' => 'AEP North',
            'aepcentral' => 'AEP Central',
            'centerpoint' => 'Centerpoint'
        ];

        $idata = [
            'Centerpoint' => [
                'info' => null,
                'areas' => [],
            ],
            'Texas New Mexico Power' => [
                'info' => null,
                'areas' => [],
            ],
            'Oncor' => [
                'info' => null,
                'areas' => [],
            ],
            'AEP North' => [
                'info' => null,
                'areas' => [],
            ],
            'AEP Central' => [
                'info' => null,
                'areas' => [],
            ],
        ];

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);

        while ($row = fgetcsv($handle)) {
            $data = array_combine($headers, $row);
            $utility1 = strtolower(trim($data['TDSP1']));
            $utility2 = trim($data['TDSP2']);
            if ($utility2 == '') {
                $utility2 = null;
            } else {
                $utility2 = strtolower($utility2);
            }
            $zip = intval(trim($data['PREM_ZIP_CODE']), 10);

            if (isset($abbrMap[$utility1])) {
                if (!isset($idata[$abbrMap[$utility1]]['areas'][$zip])) {
                    $idata[$abbrMap[$utility1]]['areas'][$zip] = null;
                }
            }

            if ($utility2 !== null && isset($abbrMap[$utility2])) {
                if (!isset($idata[$abbrMap[$utility2]]['areas'][$zip])) {
                    $idata[$abbrMap[$utility2]]['areas'][$zip] = null;
                }
            }
        }

        fclose($handle);

        JsonDocument::where('document_type', 'rate-import-control')->where('ref_id', 'TXU Energy')->delete();
        $jd = new JsonDocument();
        $jd->document = $idata;
        $jd->document_type = 'rate-import-control';
        $jd->ref_id = 'TXU Energy';
        $jd->save();
    }

    private function get_txu_areas()
    {
        if (!$this->option('defaults')) {
            $dblookup = JsonDocument::where(
                'document_type',
                'rate-import-control'
            )->where(
                'ref_id',
                'TXU Energy'
            )->first();
            if ($dblookup) {
                return $dblookup->document;
            }
        }

        $default = [
            'Centerpoint' => [
                'info' => null,
                'areas' => [
                    '77015' => null,
                    '77018' => null,
                    '77022' => null,
                    '77024' => null,
                    '77026' => null,
                    '77027' => null,
                    '77029' => null,
                    '77037' => null,
                    '77042' => null,
                    '77044' => null,
                ],
            ],
            'AEP Central' => [
                'info' => null,
                'areas' => [
                    '77414' => null,
                    '77437' => null,
                    '77465' => null,
                    '77901' => null,
                    '77905' => null,
                    '77951' => null,
                    '77957' => null,
                    '77963' => null,
                    '77968' => null,
                    '77979' => null,
                ],
            ],
            'Oncor' => [
                'info' => null,
                'areas' => [
                    '75009' => null,
                    '75043' => null,
                    '75050' => null,
                    '75056' => null,
                    '75061' => null,
                    '75068' => null,
                    '75090' => null,
                    '75092' => null,
                    '75093' => null,
                    '75098' => null,
                ],
            ],
            'Texas New Mexico Power' => [
                'info' => null,
                'areas' => [
                    '75440' => null,
                    '75442' => null,
                    '76255' => null,
                    '76372' => null,
                    '76455' => null,
                    '77511' => null,
                    '77539' => null,
                    '77568' => null,
                    '77590' => null,
                    '77591' => null,
                ],
            ],
            'AEP North' => [
                'info' => null,
                'areas' => [
                    '76384' => null,
                ],
            ],
        ];

        if (!$this->option('defaults')) {
            $x = new JsonDocument();
            $x->document_type = 'rate-import-control';
            $x->ref_id = 'TXU Energy';
            $x->document = $default;
            $x->save();
        }

        return $default;
    }

    private function soap_get_rates()
    {
        $url = config('services.clients.txu.rate_api.url');
        $username = config('services.clients.txu.rate_api.username');
        $password = config('services.clients.txu.rate_api.password');

        if ($url == null /*|| $username == null || $password == null*/) {
            $this->error('Please check the configuration for TXU Rate API');
        }

        $info = $this->get_txu_areas();

        $userAgent =  'TPV.com Focus Rate Import/0.9';

        $context = [
            'http' => [
                'ignore_errors' => true,
                'user_agent' => $userAgent,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                // 'crypto_method' => \STREAM_CRYPTO_METHOD_TLS_CLIENT
            ]
        ];

        $client = new SoapClient(
            $url,
            [
                'user_agent' => $userAgent,
                'soap_version' => \SOAP_1_1,
                'trace' => true,
                'exception' => true,
                'cache_wsdl' => \WSDL_CACHE_NONE,
                'stream_context' => stream_context_create($context)
            ]
        );

        $cnt = 0;
        $utilities = array_keys($info);

        foreach ($utilities as $utility) {
            $info[$utility]['info'] = Cache::remember(
                'txu-util-lookup' . $utility,
                60,
                function () use ($utility) {
                    return Utility::where('name', 'like', $utility . '%')->first();
                }
            );

            $cnt += count($info[$utility]['areas']);
        }

        $progressBar = $this->output->createProgressBar($cnt);
        $this->info('Retrieving Offers...');
        $progressBar->start();

        foreach ($utilities as $utility) {
            $postalCodes = array_keys($info[$utility]['areas']);
            for ($i = 0, $len = count($postalCodes); $i < $len; $i += 1) {
                try {
                    $start = microtime(true);
                    $result = $client->GetProductsOffers([
                        'PromoCode' => 'D2DMASTERS',
                        'ServiceAddress' => [
                            'PostalCode' => $postalCodes[$i],
                        ],
                    ]);

                    if ($this->option('showRequest')) {
                        echo $client->__getLastRequest() . "\n";
                    }

                    $end = microtime(true);
                    if (!isset($info[$utility]['timing'])) {
                        $info[$utility]['timing'] = [];
                    }
                    $info[$utility]['timing'][$postalCodes[$i]] = $end - $start;
                    $info[$utility]['areas'][$postalCodes[$i]] = $result;
                } catch (\SoapFault $e) {
                    $this->info('Error processing postal code ' . $postalCodes[$i]);
                    $this->info($e->getMessage());
                    $this->line($client->__getLastRequest());
                    $this->line($client->__getLastRequest());
                } finally {
                    $progressBar->advance();
                }
            }
        }

        $progressBar->finish();

        if ($this->option('cache')) {
            file_put_contents('output.json', json_encode($info));
        }

        return json_decode(json_encode($info), true);
    }

    private function __compile_csv_to_regex($data)
    {
        $raw = is_array($data) ? $data : explode(',', $data);
        $clean = array_map(
            function ($item) {
                return trim($item);
            },
            $raw
        );
        sort(
            $clean,
            \SORT_STRING
        );
        $grouped = [];
        foreach ($clean as $item) {
            $group = substr($item, 0, 3);
            if (isset($grouped[$group])) {
                $grouped[$group][] = str_replace($group, '', $item);
            } else {
                $grouped[$group] = [str_replace($group, '', $item)];
            }
        }
        $grouped2 = [];
        foreach ($grouped as $group => $list) {
            $grouped2[$group] = [];
            $current = [];
            foreach ($list as $item) {
                $iitem = intval($item, 10);
                if (0 == count($current)) {
                    $current[] = $iitem;
                    //echo "first item: $iitem\n";
                    continue;
                }
                if (1 == $iitem - $current[count($current) - 1]) {
                    //echo "next item: $iitem\n";
                    $current[] = $iitem;

                    continue;
                }
                //echo "last item: $iitem\n";
                $ret = $this->__filter_current($current, $grouped2[$group]);
                if (null !== $ret) {
                    //echo $group.' sent '.implode(',', $current)." got null\n";
                    $grouped2[$group][] = $ret;
                }
                $current = [$iitem];
            }
            //echo 'finish up';
            $ret = $this->__filter_current($current, $grouped2[$group]);
            if (null !== $ret) {
                //echo $group.' sent '.implode(',', $current)." got null\n";
                $grouped2[$group][] = $ret;
            }
        }
        $out = [];
        foreach ($grouped2 as $key => $values) {
            $out[] = '(?:' . $this->__group_to_regex($key, $values) . ')';
        }
        $out = implode('|', $out);

        return $out;
    }

    private function __first_char($item)
    {
        if (1 == strlen($item)) {
            return '0';
        }

        return substr(strval($item), 0, 1);
    }

    private function __filter_current($current, &$inspoint = null)
    {
        // not catching [79,80] and similar
        if (count($current) > 2) {
            if ($this->__first_char($current[0]) === $this->__first_char($current[count($current) - 1])) {
                //echo '1. count of current('.print_r($current, true).') is '.count($current)."\n";
                return [$current[0], $current[count($current) - 1]];
            }
        } else {
            //echo '2. count of current('.print_r($current, true).') is '.count($current)."\n";
        }
        if (count($current) > 2) {
            $tout = [];
            $first = $this->__first_char($current[0]);
            foreach ($current as $item) {
                if ($this->__first_char($item) === $first) {
                    $tout[] = $item;
                } else {
                    $inspoint[] = $this->__filter_current($tout);
                    $tout = [];
                    $first = $this->__first_char($item);
                    $tout[] = $item;
                }
            }
            if (count($tout) > 0) {
                $inspoint[] = $this->__filter_current($tout);
            }
        } else {
            if (2 == count($current)) {
                if ($this->__first_char($current[0]) !== $this->__first_char($current[1])) {
                    $inspoint[] = [$current[0]];

                    return [$current[1]];
                }
            }

            return $current;
        }
    }

    private function __group_to_regex($prefix, $items)
    {
        $out = $prefix;
        $inner = '';
        for ($n = 0, $len = count($items); $n < $len; ++$n) {
            $item = $items[$n];
            if ($n > 0) {
                $inner .= '|';
            }
            if (1 == count($item)) {
                if ('0' == $this->__first_char($item[0])) {
                    $inner .= '(?:0' . $item[0] . ')';
                } else {
                    $inner .= '(?:' . $item[0] . ')';
                }
            } else { // support if item[0] is only 1 char
                $realFirstItem = $item[0];
                $realLastItem = $item[1];
                if (1 == strlen($realFirstItem)) {
                    $realFirstItem = '0' . $item[0];
                }
                if (1 == strlen($realLastItem)) {
                    $realLastItem = '0' . $item[1];
                }
                $inner .= '(?:' . $this->__first_char($realFirstItem) . '[' . substr($realFirstItem, 1, 1) . '-' . substr($realLastItem, 1, 1) . '])';
            }
        }
        $out = $out . '(?:' . $inner . ')';

        return $out;
    }

    private function fix_multi_utility_zips($info)
    {
        $utilToPrefix = [
            'Centerpoint' => 'CPX',
            'Texas New Mexico Power' => 'NMX',
            'Oncor' => 'ONX',
            'AEP Central' => 'TCX',
            'AEP North' => 'TNX',
        ];

        $prefixToUtil = [
            'CPX' => 'Centerpoint',
            'NMX' => 'Texas New Mexico Power',
            'ONX' => 'Oncor',
            'TCX' => 'AEP Central',
            'TNX' => 'AEP North',
        ];

        $toMove = [];
        $out = [];
        foreach ($info as $utilityName => $rinfo) {
            $out[$utilityName] = ['info' => $rinfo['info']];
            $out[$utilityName]['areas'] = [];
            $areas = $rinfo['areas'];
            foreach ($areas as $zipCode => $areaInfo) {
                $out[$utilityName]['areas'][$zipCode] = ['IsSuccess' => true, 'Offers' => []];
                if (isset($areaInfo['Offers']) && isset($areaInfo['Offers']['Offer'])) {
                    $out[$utilityName]['areas'][$zipCode]['Offers']['Offer'] = [];
                    $offers = $areaInfo['Offers']['Offer'];
                    foreach ($offers as $offer) {
                        $programCode = $offer['Id'];
                        if (Str::startsWith($programCode, $utilToPrefix[$utilityName])) {
                            $out[$utilityName]['areas'][$zipCode]['Offers']['Offer'][] = $offer;
                        } else {
                            if (!isset($toMove[$zipCode])) {
                                $toMove[$zipCode] = [];
                            }
                            $toMove[$zipCode][] = $offer;
                        }
                    }
                }
            }
        }

        foreach ($toMove as $zipCode => $offers) {
            foreach ($offers as $offer) {
                $prefix = substr($offer['Id'], 0, 3);
                if (isset($prefixToUtil[$prefix])) {
                    $utilityName = $prefixToUtil[$prefix];
                    if (isset($out[$utilityName]['areas'][$zipCode])) {
                        // check if this already exists
                        if (!isset($out[$utilityName]['areas'][$zipCode]['Offers'])) {
                            $out[$utilityName]['areas'][$zipCode]['Offers'] = ['Offer' => []];
                        }
                        if (!isset($out[$utilityName]['areas'][$zipCode]['Offers']['Offer'])) {
                            $out[$utilityName]['areas'][$zipCode]['Offers']['Offer'] = [];
                        }
                        $include = true;
                        foreach ($out[$utilityName]['areas'][$zipCode]['Offers']['Offer'] as $existingOffer) {
                            if ($existingOffer['Id'] === $offer['Id']) {
                                $include = false;
                            }
                        }
                        if (!$include) {
                            continue;
                        }
                    } else {
                        $out[$utilityName]['areas'][$zipCode] = ['IsSuccess' => true, 'Offers' => ['Offer' => []]];
                    }
                    $out[$utilityName]['areas'][$zipCode]['Offers']['Offer'][] = $offer;
                } else {
                    $this->error('Unknown prefix on offer, program code is: ' . $offer['Id']);
                }
            }
        }

        return $out;
    }
}
