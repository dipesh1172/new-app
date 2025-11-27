<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use GuzzleHttp\Client as HttpClient;
use App\Models\Vendor;
use App\Models\User;
use App\Models\Script;
use App\Models\Rate;
use App\Models\ProviderIntegration;
use App\Models\Office;
use App\Models\EztpvConfig;
use App\Models\Client;
use App\Models\BrandUtility;
use App\Models\BrandUserOffice;
use App\Models\BrandUser;
use App\Models\Brand;

class GenieVendorSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'genie:sync
        {--cache= : Use the specified file instead of retrieving the latest version}
        {--dryrun}
        {--be-verbose}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs all Genie Retail brand info';

    private $httpClient = null;
    private $token = null;
    private $isDryRun = false;
    private $verbose = false;

    private $states = [
        'IDT Energy' => [
            'DC' => 9,
            'IL' => 14,
            'MD' => 21,
            'NJ' => 31,
            'NY' => 33,
            'OH' => 36,
            'PA' => 39,
        ],
        'Residents Energy' => [
            'CT' => 7,
            'DE' => 8,
            'IL' => 14,
            'IN' => 15,
            'MA' => 22,
            'MI' => 23,
            'NJ' => 31,
            'NY' => 33,
            'OH' => 36,
            'PA' => 39,
        ],
        'Townsquare Energy' => [
            'NJ' => 31,
            'MD' => 21,
            'PA' => 39,
            'OH' => 36,
            'MA' => 22,
            'CT' => 7,
            'NH' => 30,
            'RI' => 40,
            'ME' => 20,
            'GA' => 11,
            'IL' => 14,
            'DC' => 9,
        ],
    ];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function setup()
    {
        $this->verbose = $this->option('be-verbose');
        $genie = Client::where('name', 'Genie Retail Energy')->first();

        $pi = ProviderIntegration::leftJoin('service_types', 'service_types.id', 'service_type_id')
            ->where('service_types.name', 'Genie Retail Vendor API')
            ->where('provider_integration_type_id', 2)
            ->whereNull('brand_id')
            ->where('client_id', $genie->id)
            ->first();

        if ($pi !== null) {
            $this->httpClient = new HttpClient(['base_uri' => 'https://vms.genieretail.com/api/']);
            $token = $this->getBearerToken($pi->username, $pi->password);
            if ($token !== false) {
                $this->token = $token;
                $this->httpClient = new HttpClient([
                    'base_uri' => 'https://vms.genieretail.com/api/',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ]);
            }
        }
    }

    private function getChannel(string $raw): string
    {
        switch ($raw) {
            default:
            case 'D2D':
                return 1;
            case 'General TM':
                return 2;
            case 'TableTop':
                return 3;
        }
    }

    private function getBearerToken(string $username, string $password)
    {
        $res = Cache::remember('genie-retail-bearer', 86398, function () use ($username, $password) {
            $response = $this->httpClient->request('GET', 'token', [
                'form_params' => [
                    'username' => $username,
                    'password' => $password,
                    'grant_type' => 'password',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $jres = json_decode($response->getBody()->getContents(), true);
                if (isset($jres['access_token'])) {
                    return $jres['access_token'];
                }
            } else {
                info('Unable to get Genie bearer token', [
                    'status' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'body' => $response->getBody()->getContents(),
                ]);
            }

            return false;
        });

        if ($res === false) {
            Cache::forget('genie-retail-bearer');
        }

        return $res;
    }

    private function getSyncFile()
    {
        $response = $this->httpClient->request('GET', 'TpvCompany/Agencies', [
            'stream' => true,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() === 200) {
            $tempFile = tempnam('', 'IDT');
            $FH = fopen($tempFile, 'w+');
            $body = $response->getBody();
            while (!$body->eof()) {
                fwrite($FH, $body->read(1024));
            }
            fclose($FH);

            return $tempFile;
        } else {
            info('Unable to get Genie sync file', [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $response->getBody()->getContents(),
            ]);

            return false;
        }
    }

    private function getBrandInfo()
    {
        $idt = Brand::where('name', 'IDT Energy')->whereNotNull('client_id')->first();
        $residents = Brand::where('name', 'Residents Energy')->whereNotNull('client_id')->first();
        $citizens = Brand::where('name', 'Citizens Choice Energy')->whereNotNull('client_id')->first();
        $townsq = Brand::where('name', 'Townsquare Energy')->whereNotNull('client_id')->first();

        return [
            'IDT Energy' => $idt !== null ? $idt->id : null,
            'Residents Energy' => $residents !== null ? $residents->id : null,
            'Citizens Choice Energy' => $citizens !== null ? $citizens->id : null,
            'Town Square' => $townsq !== null ? $townsq->id : null,
        ];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        info('GENIE VMS SYNCH STARTED');

        $this->setup();
        $this->isDryRun = $this->option('dryrun');

        if ($this->token === null) {
            $this->error('Unable to get bearer token for Genie API');

            return 40;
        }

        $cache = $this->option('cache');
        if ($cache === null) {
            $workFile = $this->getSyncFile();
            if ($workFile === false) {
                $this->error('Unable to retrieve sync file');

                return 41;
            } else {
                $this->info('Wrote sync file to: ' . $workFile);
            }
        } else {
            $this->info('Using ' . $cache . ' as input');
            $workFile = $cache;
        }

        $brandInfo = $this->getBrandInfo();
        $allBrandsCount = count($brandInfo);
        $nullBrandsCount = 0;
        foreach ($brandInfo as $brandName => $brandId) {
            if ($brandId === null) {
                $nullBrandsCount += 1;
            }
        }
        if ($nullBrandsCount === $allBrandsCount) {
            $this->error('Unable to locate brand info for Genie brands');

            return 42;
        }

        DB::transaction(function () use ($workFile, $brandInfo) {
            $this->processFile($workFile, $brandInfo);
            info('GENIE VMS SYNCH FINISHED');
            if ($this->isDryRun) {
                throw new \Exception('Aborting save for dry run');
            }
        });
    }

    private function processFile($file, $brandInfo)
    {
        $jData = json_decode(file_get_contents($file), true);
        $cnt = count($jData);
        $this->info('Processing ' . $cnt . ' vendors.');
        //$bar = $this->output->createProgressBar($cnt);
        //$bar->start();
        foreach ($jData as $vendor) {
        //    if (trim($vendor['AgencyName']) === '18k') {
           $ret = $this->processVendor($vendor, $brandInfo);
           if ($ret !== true) {
                // $bar->finish();

                return $ret;
            }
            //$bar->advance();
        //   }
        }
        // $bar->finish();

        return 0;
    }

    private function processVendor($vendorInfo, $brandInfo)
    {
        $vendorName = trim($vendorInfo['AgencyName']);
        $vendorCode = trim($vendorInfo['AgencyCode']);
        $vendorGrpId = $vendorInfo['AgencyID'];
        $this->info('Processing ' . $vendorName);

        // Create list of brands to filter on when searching for vendor records.
        // To create the list, used brandInfo, but filter out any brands that have a null id.
        $brands = array_filter($brandInfo, function($item) {
            return !empty($item);
        });

        // Search for vendor records by agency code
        $nameCheck = Vendor::select('brands.name', 'brands.id')
            ->join('brands', 'vendors.vendor_id', 'brands.id') // this already includes soft-deleted results
            ->whereIn('brand_id', $brands)
            ->where('vendor_code', $vendorCode)
        ->withTrashed()
        ->get();

        // When we search for vendors, we should get either 0 results, or
        // the same number as the brand count (3 as of 4/17/2024).
        // All we care about here is that we have more than 0 reults.
        if(count($nameCheck) > 0) {

            $currentVendorName = $nameCheck[0]->name;

            // Check if the vendor needs to be renamed
            if($currentVendorName != $vendorName) {
                $prefix = "[GenieVendorSync][$vendorCode][$vendorName]" . ($this->option('dryrun') ? '[dry-run]' : '');

                SendTeamMessage('monitoring', "$prefix Detected a renamed vendor. $currentVendorName -> $vendorName");

                // Locate the brand record for the vendor and rename it.
                $id = $nameCheck[0]->id;
                
                $vendorBrand = Brand::where('id', $id)->first();
                $vendorBrand->name = $vendorName;
                $vendorBrand->save();

                SendTeamMessage('monitoring', "$prefix Successfully renamed vendor '$currentVendorName' to '$vendorName'. The change will take effect when VMS sync completes.");
            }
        }

        $vendor = Brand::where('name', $vendorName)->whereNull('client_id')->withTrashed()->first();
        if ($vendor === null) {
            $vendor = new Brand();
            $vendor->name = $vendorName;
            $vendor->active = $vendorInfo['IsActive'];
            if ($this->isDryRun) {
                $this->comment('Would create new vendor: ' . $vendorName);
            }
            $vendor->save();
        }
        if (!$vendorInfo['IsActive']) {
            if ($this->isDryRun) {
                $this->comment('Would disable vendor: ' . $vendorName);
            }
            //$vendor->delete();
        } else {
            if ($vendor->trashed()) {
                if ($this->isDryRun) {
                    $this->comment('Would restore vendor: ' . $vendorName);
                }
                $vendor->restore();
            }
        }

        foreach ($brandInfo as $key => $brandId) {
            if ($brandId !== null) {
                $this->info('Brand: ' . $key . ' [' . $brandId . ']');
                $ven = Vendor::where('brand_id', $brandId)->where('vendor_id', $vendor->id)->withTrashed()->first();
                if ($ven === null) {
                    $ven = new Vendor();
                    $ven->brand_id = $brandId;
                    $ven->vendor_id = $vendor->id;
                    $ven->vendor_label = $vendorName;
                    $ven->vendor_code = $vendorCode;
                    $ven->grp_id = $vendorGrpId;

                    $ven->save();
                } else {
                    if ($key === 'IDT Energy' || $key === 'Residents Energy') { // enforce rate restrictions from offer matrix 
                        DB::table('vendor_rates')->where('vendors_id', $ven->id)->delete();
                    }
                }
                if (!$vendorInfo['IsActive']) {
                    $ven->delete();
                } else {
                    if ($key === 'IDT Energy' || $key === 'Residents Energy') { // enforce rate restrictions from offer matrix 
                        if ($ven->trashed()) {
                          $ven->restore();
                        }
                    }
                }
                $officeLookup = $this->processVendorOffices($ven->id, $brandId, $vendorInfo['Offices'],$ven->vendor_label,$ven->grp_id);
                $this->processVendorUsers($ven->vendor_id, $brandId, $officeLookup, $vendorInfo['Agents']);
                if ($key === 'IDT Energy' || $key === 'Residents Energy') { // enforce rate restrictions from offer matrix 
                    $this->processVendorMarkets($ven->id, $key, $brandId, $vendorInfo['Markets']);
                }
            }
        }

        return true;
    }

    /**
     * @method processVendorMarkets
     * This method sets up product restrictions based on what utilities the vendor is allowed to sell for this brand
     */
    private function processVendorMarkets($vendor_id, $brandName, $brand_id, $markets)
    {
        $this->info('Adding Market Restrictions for ' . $brandName . ' Id: ' . $brand_id);
        $utilities = [];
        foreach ($markets as $market) {
            if ($market['EscoBrandName'] === $brandName) {
                foreach ($market['UtilityStates'] as $state) {
                    foreach ($state['Utilities'] as $utility) {
                        if ($utility['Commodities'][0]['Zones'][0]['AssignmentActive']) { // utility must be active
                            if (!in_array($utility['UtilityName'], $utilities)) {
                                $utilities[] = $utility['UtilityCode'];
                            }
                        }
                    }
                }
            }
        }
        $this->line('UtilityCodes: (' . count($utilities) . ') ' . ($this->verbose ? json_encode($utilities) : ''));
        $fuels = [];
        $utils = BrandUtility::with(
            'utility'
        )->where(
            'brand_id',
            $brand_id
        )->whereIn(
            'utility_label',
            $utilities
        )->get();
        $this->line('BrandUtilities: (' . $utils->count() . ')' . ($this->verbose ? json_encode($utils) : ''));
        foreach ($utils as $util) {
            $util_fuels = $util->utility->supported_fuels;
            foreach ($util_fuels as $fuel) {
                $fuels[] = $fuel->id;
            }
        }

        // this should grab only rates that don't currently have a vendor_rates entry
        $rates = Rate::select('rates.id')
            ->join('products', 'products.id', 'rates.product_id')
            ->where('products.brand_id', $brand_id)
            ->whereIn('rates.utility_id', $fuels)
            ->whereNull('products.deleted_at')
            ->get();
        $toInsert = [];
        foreach ($rates as $rate) {
            $toInsert[] = ['id' => uuid_create(), 'created_at' => now(), 'updated_at' => now(), 'vendors_id' => $vendor_id, 'rate_id' => $rate->id];
        }

        DB::table('vendor_rates')->insert($toInsert);
        $this->info('Restricted to ' . count($toInsert) . ' rates');
    }

    private function processVendorOffices($vendor_id, $brand_id, $offices,$vendor_label,$vendor_grp_id)
    {
        $this->comment('Processing Vendor Offices (' . count($offices) . ')');
        $out = [];

        $brand = Brand::find($brand_id);

        foreach ($offices as $rawOffice) {
            $officeName = trim($rawOffice['Name']);
            $officeID = $rawOffice['OfficeID'];

            if ($this->verbose) {
                $this->comment('Office: ' . $officeName);
                $this->comment('Office ID: ' . $officeID);
                $this->comment(print_r($rawOffice, true));
            }

            $officeCode = trim($rawOffice['OfficeCode']);
            $office = Office::where(
                'identifier',
                $officeID
            )->where(
                'brand_id',
                $brand_id
            )->where(
                'vendor_id',
                $vendor_id
            )->withTrashed()->first();
            if ($office === null) {
                if ($this->verbose) {
                    $this->comment('Creating Office: ' . $officeName);
                }
                $office = new Office();
                $office->brand_id = $brand_id;
                $office->vendor_id = $vendor_id;
                $office->identifier = $officeID;
            } else {
                if ($this->verbose) {
                    $this->comment('Using found office: ' . $officeName);
                }
            }

            $office->eztpv_config = 2;
            $office->name = $officeName;
            $office->label = $officeCode;
            $office->save();

            if (!$rawOffice['IsActive'] || $officeName == '') {
                if ($this->verbose) {
                    $this->comment('Disabling Office: ' . $officeName);
                }
                if (!$office->trashed()) {
                    $office->delete();
                }
            } else {
                if ($office->trashed()) {
                    $office->restore();
                }
            }

            $ezc = EztpvConfig::where(
                'office_id',
                $office->id
            )->first();
            if (!$ezc) {
                $ezc = new EztpvConfig();
                $ezc->office_id = $office->id;
            }

            $channel_config = [];
            $config_channel_base = [
                'status' => 'on',
                'event_category' => '1',
                'is_productless' => '0',
                'sales_pitch_capture' => '0',
                'ivr_script' => null,
                'ivr_method' => null,
                'live_call' => '1',
                'digital' => '0',
                'digital_script' => null,
                'voice_capture' => '0',
                'voice_capture_script' => null,
                'photo' => '0',
                'identification' => [
                    'ez_capture' => '0',
                    'req_photo' => '0',
                    'max_ids' => '1',
                ],
                'preview_contract' => '0',
                'customer_signature_device' => 'customer',
            ];

            foreach ($this->states[$brand->name] as $key => $value) {
                $config_channel_base['script'] = null;
                $config_channel_base['contract'] = 0;  // No Contract
                $config_channel_base['preview_contract'] = 0;
                $config_channel_base['sales_pitch_capture'] = '0';
                $config_channel_base['contract_delivery'] = [];
                $config_channel_base['photo'] = '0';
                unset($config_channel_base['customer_photo_device']); // By default, this prop should not exist on the config. It'll be added for MD DTD in code below.
                
                switch ($brand->name) {
                    case 'Residents Energy':
                        $pattern = 'Residents Energy-SC-RES-';
                        break;
                    case 'Townsquare Energy':
                        $pattern = 'TSE - ';
                        break;
                    default:
                        $pattern = 'IDT ';
                }
                // $pattern = ($brand->name === 'Residents Energy')
                //     ? 'Residents Energy-SC-RES-'
                //     : 'IDT ';

                $index = 1;
                if (isset($rawOffice['AdvertisingMethod'])) {
                    switch ($rawOffice['AdvertisingMethod']) {
                        case 'General TM':
                            $index = 2;
                            break;
                        case 'Outbound TM':
                            $index = 2;
                            break;
                        case 'TableTop':
                            $index = 3;
                            break;
                        default:
                            $index = 1;
                            break;
                    }
                }

                $script = Cache::remember(
                    'script-by-state-' . $pattern . $key . $brand->id . $index,
                    30,
                    function () use ($key, $brand, $pattern, $index) {
                        $tomatch = null;
                        info('BRAND = ' . $brand->name);

                        if ($brand->name === 'Residents Energy') {
                            switch ($index) {
                                case 2:  // TM
                                    $tomatch = '-TM';
                                    break;
                                case ($index == 3 && $key == 'OH'):  // Tabletop)  #22109
                                    $tomatch = '-Retail';
                                    break;
                                default: 
                                    $tomatch = '-DTD';
                                    break;
                                }
                        } else {
                            if ($brand->name === 'IDT Energy' && $index == 3 && $key == 'OH') {  // Tabletop)  #22109
                                $tomatch = ' - Retail';
                            } else {
                                if ($brand->name === 'Townsquare Energy') {
                                    switch ($key) {
                                        case ($key == 'GA' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'ME' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'NJ' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'RI' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'MA' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'PA' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'OH' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'NH' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'CT' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'DC' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'IL' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'NY' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case ($key == 'MD' && intval($index) === 2):    // TM
                                            $tomatch = ' - TM LiveOp';
                                            break;
                                        case (intval($index) === 2):    // TM  
                                            $tomatch = ' - NOMATCH';  // will not find match so live scipt will not be set for all other states
                                            break;
                                        default:
                                            $tomatch = ' - LiveOp';
                                    }
                                } else {
                                    $tomatch = ($index === 2)
                                    ? ' - TM'
                                    : ' - DTD';
                                }
                            }
                        }

                        return Script::where(
                            'title',
                            $pattern . $key . $tomatch   
                        )->where(
                            'scripts.brand_id',
                            $brand->id
                        )->first();
                    }
                );
 
                if ($script) {
                    $config_channel_base['script'] = $script->id;
                }

                if ($this->verbose) {
                    $this->comment('Config for: ' . $key);
                }

                if (
                    $key === 'CT'
                    && intval($index) === 1
                ) {
                    if ($this->verbose) {
                        $this->comment('sales_pitch_capture to 1 for IDT Energy + CT + DTD');
                    }

                    $config_channel_base['sales_pitch_capture'] = '1';
                }

                if (
                    ($brand->name === 'IDT Energy'  && intval($index) === 2) // TM
                ) {
                    // IDT + TM
                     $config_channel_base['live_call'] = '1';  // all states IDT + TM should be set to live except MD
                }

                if (
                    ($brand->name === 'IDT Energy' && in_array($key, ['MD']))  
                    && intval($index) === 2  // TM 
                ) {
                    // IDT + MD + TM 
                    if($officeName == "Jamestown" && $vendor_label == "Jamestown" && $vendor_grp_id == "022") {
                        $config_channel_base['live_call'] = '1';
                        $config_channel_base['contract'] = 0;  // No Contract
                        $config_channel_base['contract_delivery'] = [];
                        $config_channel_base['preview_contract'] = 0;
                    } else {
                        $config_channel_base['live_call'] = '0';
                        $config_channel_base['preview_contract'] = 1;
                        $config_channel_base['contract'] = 2; // Custom Contract
                        $config_channel_base['contract_delivery'] = [
                            0 => '1',
                            1 => '2',
                        ];
                    }
                    // $config_channel_base['script'] = null; // removed per Paul assign regular channel script 2022-06-13 (CC)

                }

                // RESIDENTS ENEGY + MI + DTD: live TPV not required.
                if ($brand->name === 'Residents Energy' && intval($index) === 1) {
                    if (in_array($key, ['MI'])) {
                        $config_channel_base['preview_contract'] = 1;  // moving VL MI calls to focus preview required 2022-08-18 (cc)
                        $config_channel_base['live_call'] = '0';
                        $config_channel_base['contract'] = 2; // Custom Contract
                        $config_channel_base['contract_delivery'] = [
                            0 => '1',
                            1 => '2',
                        ];
                    } else {
                        $config_channel_base['live_call'] = '1';
                    }
                }

                if ($brand->name === 'IDT Energy' && intval($index) === 1) { // DTD  VL change
                    if (in_array($key, ['MD'])) { // mimic the TM Disclosures for DTD for migrating the "Digital" process off the VL Platform for MD
                        // IDT + MD + DTD 
                        $config_channel_base['live_call'] = '0';
                        // $config_channel_base['script'] = null;  // removed per Paul assign regular channel script 2022-06-13 (CC)
                    } else {
                        $config_channel_base['live_call'] = '1';
                    }
                }

                // MD + DTD - Require photo capture on customer device. Ticket 2024-07-18-45419
                // MD + DTD - Allow photo capture on Agents device. Ticket 2024-09-09-55879, revises changes in 2024-07-18-45419 above
                // MI + DTD - Require photo capture. Ticket 2024-10-24-17367
                if(in_array($key, ['MD','MI']) && intval($index) == 1) {
                    $config_channel_base['photo'] = '2';                            // 0 = Not Required, 1 = Required, 2 = Optional
                    $config_channel_base['customer_photo_device'] = 'sales_agent';  // either 'customer' or 'sales_agent'
                }

                if (
                    ($brand->name === 'IDT Energy' && in_array($key, ['OH']))  
                    && intval($index) === 2 // TM
                ) {
                    // IDT + OH + TM
                    $config_channel_base['live_call'] = '1';  // live set for OH
                }

                if (
                    (($brand->name === 'IDT Energy' && in_array($key, ['OH']))
                    || ($brand->name === 'Residents Energy' && in_array($key, ['OH'])))
                    && intval($index) === 3  // Retail
                ) {
                    // Retail channel only
                    $config_channel_base['preview_contract'] = 1;
                    $config_channel_base['contract'] = 2; // Custom Contract
                    $config_channel_base['contract_delivery'] = [
                        0 => '1',
                        1 => '2',
                    ];
                }

                if (
                    (($brand->name === 'IDT Energy' && in_array($key, ['MD','OH','PA','NJ','IL']))
                    || ($brand->name === 'Residents Energy' && in_array($key, ['DE','OH','IN','IL','MA','MI','NJ','PA'])))
                    && intval($index) === 1  // DTD
                ) {
                    // DTD channel only
                    if ($brand->name === 'IDT Energy' && in_array($key, ['PA','NJ','IL'])) {
                        $config_channel_base['preview_contract'] = 1;
                        $config_channel_base['contract'] = 4; // Signature Page w/ Custom Contract
                        $config_channel_base['contract_delivery'] = [
                            0 => '1',
                            1 => '2',
                        ];
                    } else {
                        $config_channel_base['preview_contract'] = 1;
                        $config_channel_base['contract'] = 2; // Custom Contract
                        $config_channel_base['contract_delivery'] = [
                            0 => '1',
                            1 => '2',
                        ];
                    }
                    if($brand->name === 'Residents Energy' && in_array($key, ['MA','PA','IL','NJ'])) {
                        $config_channel_base['contract'] = 4; // Signature Page w/ Custom Contract
                    }
                    if($brand->name === 'Residents Energy' && $key == 'IN'){
                        $config_channel_base['live_call'] = '0';
                    }
                }

                // Set up IDTE and Residents DTD states for Digital option.
                // Use 'Either' value to allow Live or Digital to be complete, rather than requiring both to be completed
                if(
                    $brand->name === 'IDT Energy' && in_array($key, ['PA', 'NJ', 'IL']) && intval($index) === 1  // dtd
                    || $brand->name === 'Residents Energy' && in_array($key, ['DE', 'IL', 'MA', 'NJ', 'PA']) && intval($index) === 1
                ) {
                    $config_channel_base['digital'] = '1';
                    $config_channel_base['digital_delivery_types'] = ["1", "2"];
                    $config_channel_base['digital_and_live_required'] = "Either";

                    $digitalScript = Cache::remember(
                        'digital-script-by-state-' . $key . '-' . $brand->id . '-' . $index,
                        30,
                        function () use ($key, $brand, $index) {
                            $script_name = ($brand->name === 'IDT Energy' ? 'IDT Digital' : 'Residents Digital') . ' - ' . $key;

                            info('BRAND = ' . $brand->name);
                            info('DIGITAL SCRIPT NAME = ' . $script_name);
       
                            return Script::where(
                                'title',
                                $script_name
                            )->where(
                                'scripts.brand_id',
                                $brand->id
                            )->first();
                        }
                    );

                    if ($digitalScript) {
                        $config_channel_base['digital_script'] = $digitalScript->id;
                    }
                } else {
                    $config_channel_base['digital'] = '0';
                    $config_channel_base['digital_script'] = null;
                    $config_channel_base['digital_delivery_types'] = [];
                    $config_channel_base['digital_and_live_required'] = "Both";
                }
                if ($brand->name === 'Townsquare Energy' && intval($index) === 1 && $key == 'ME') {  // dtd
                    $config_channel_base['is_productless'] = '0';
                    $config_channel_base['live_call'] = '0';
                    $config_channel_base['script'] = null;
                    $config_channel_base['contract'] = 2; // Custom Contract
                    $config_channel_base['contract_delivery'] = [
                        0 => '1',
                        1 => '2',
                    ];
                    $config_channel_base['preview_contract'] = 1;

                }

                if ($brand->name === 'Townsquare Energy' && intval($index) === 1 && in_array($key, ['NH', 'RI'])) {  // dtd
                    $config_channel_base['live_call'] = '0'; // Verification by Live Agent Required?
                    $config_channel_base['script'] = null;
                    $config_channel_base['contract'] = 2; // Custom Contract
                    $config_channel_base['contract_delivery'] = [ // email,text
                        0 => '1',
                        1 => '2',
                    ];
                    $config_channel_base['preview_contract'] = 1;
                }

                if ($brand->name === 'Townsquare Energy' && intval($index) === 2) {
                    if ($key == 'GA' OR $key == 'ME' OR $key == 'NJ' OR $key == 'RI' 
                        OR $key == 'MA' OR $key == 'PA' OR $key == 'OH' OR $key == 'NH'
                        OR $key == 'CT' OR $key == 'DC' OR $key == 'IL' OR $key == 'NY'
                        OR $key == 'MD') { 
                        $channel_config['status'] = 'on';                                            // TM config 
                        $channel_config['channels'][$index] = $config_channel_base;
                        $config[$value] = $channel_config;
                    }
                } else {
                     $channel_config['status'] = 'on';
                     $channel_config['channels'][$index] = $config_channel_base;
                     $config[$value] = $channel_config;
                }
    
            }

            if ($this->verbose) {
                $this->comment(json_encode($config));
            }

            $ezc->config = json_encode($config);
            $ezc->save();

            // disable existing user offices and they will be restored later
            BrandUserOffice::where('office_id', $office->id)->delete();

            $out[$rawOffice['OfficeID']] = $office->id;
        }

        return $out;
    }

    private function getRoleId(string $securityLevel): string
    {
        switch ($securityLevel) {
            default:
            case 'Agent':
                return 3;

            case 'Manager':
                return 2;

            case 'Admin':
                return 1;
        }
    }

    private function processVendorUsers($vendor_id, $brand_id, $officeLookup, $users)
    {
        $this->comment('Processing Vendor Users (' . count($users) . ')');
        foreach ($users as $rawUser) {
            $roleId = $this->getRoleId($rawUser['SecurityLevel']);
            if ($roleId === 1) {
                $this->comment('Ignoring Vendor Admin');
                continue;
            }
            $this->comment('Processing user: ' . $rawUser['AgentCode']);
            $user = User::where('username', $rawUser['AgentCode'])->withTrashed()->first();
            if ($user === null) {
                $this->comment('User Not Found (username): ' . $rawUser['AgentCode']);
                $user = new User();
                $user->username = $rawUser['AgentCode'];
                $user->first_name = trim($rawUser['FirstName']);
                $user->last_name = trim($rawUser['LastName']);
                $user->password_change_required = true;
                $user->password = bcrypt($rawUser['AgentCode']);

                $user->save();
            }
            $this->comment('-- UserID: ' . $user->id);
            if (!$rawUser['IsActive'] || ($user->first_name == '' && $user->last_name == '')) {
                $this->comment('-- ' . $rawUser['AgentCode'] . ' -- user not active, deleting');
                if (!$user->trashed()) {
                    $user->delete();
                }
            } else {
                if ($user->trashed()) {
                    $this->comment('-- ' . $rawUser['AgentCode'] . ' -- user reactivated');
                    $user->restore();
                }
            }

            $bu = BrandUser::where('user_id', $user->id)->where('employee_of_id', $vendor_id)->where('works_for_id', $brand_id)->withTrashed()->first();
            if ($bu === null) {
                $this->comment('-- BrandUser Not Found (username): ' . $rawUser['AgentCode']);
                $bu = new BrandUser();
                $bu->user_id = $user->id;
                $bu->works_for_id = $brand_id;
                $bu->employee_of_id = $vendor_id;
            }
            $bu->tsr_id = $rawUser['AgentCode'];
            $bu->role_id = $roleId;

            $bu->save();
            $this->comment('-- BrandUser Id: ' . $bu->id);

            if (!$rawUser['IsActive'] || ($user->first_name == '' && $user->last_name == '')) {
                $this->comment('-- ' . $rawUser['AgentCode'] . ' -- user not active, deleting (brand_user)');
                if (!$bu->trashed()) {
                    $bu->delete();
                }
            } else {
                if ($bu->trashed()) {
                    $this->comment('-- ' . $rawUser['AgentCode'] . ' -- user reactivated (brand_user)');
                    $bu->restore();
                }
            }
            if ($roleId !== 1 && $rawUser['OfficeID'] !== 0) {
                $buo = BrandUserOffice::where('office_id', $officeLookup[$rawUser['OfficeID']])->where('brand_user_id', $bu->id)->withTrashed()->first();
                if ($buo === null) {
                    $buo = new BrandUserOffice();
                    $buo->office_id = $officeLookup[$rawUser['OfficeID']];
                    $buo->brand_user_id = $bu->id;

                    $buo->save();
                }
                $this->comment('-- BrandUserOffice ID: ' . $buo->id);
                if (!$rawUser['IsActive']) {
                    $this->comment('-- ' . $rawUser['AgentCode'] . ' -- user not active, deleting (brand_user_office)');
                    $buo->delete();
                } else {
                    if ($buo->trashed()) {
                        $this->comment('-- ' . $rawUser['AgentCode'] . ' -- user reactivated (brand_user_office)');
                        $buo->restore();
                    }
                }
            } else {
                if ($roleId === 1) {
                    $this->comment('-- Admin Users are not assigned to an office');
                } else {
                    $this->comment('-- User is not assigned to an office');
                }
            }
        }
    }
}
