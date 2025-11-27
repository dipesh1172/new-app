<?php

namespace App\Console\Commands\Symmetry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Client as HttpClient;
use Exception;
use Carbon\Carbon;

use App\Traits\ExportableTrait;

use App\Models\Brand;
use App\Models\BrandState;
use App\Models\BrandUtility;
use App\Models\Product;
use App\Models\ProviderIntegration;
use App\Models\Rate;
use App\Models\RateUom;
use App\Models\Vendor;
use App\Models\VendorRate;

class SymmetryRateSynch extends Command
{
    use ExportableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'symmetry:rates:sync
        {--dry-run : Perform all actions except writing to the database}
        {--force-production : Use the Production API}
        {--force-dev : Use the Development API}
        {--download-to-csv : Download rates from API into a CSV file. This will not import rates into Focus}
        {--no-email : Prevent email notifications}
        {--email-to=* : Email distribution list}
        {--error-distro=* : The email distribution list for errors}        
        ';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Symmetry Energy - Rate Sync';

    private $prodURI = 'https://rpm.symmetryenergy.com/api';
    private $prodForced = false;

    private $devURI = 'https://rpm-staging.symmetryenergy.com/api';
    private $devForced = false;

    private $apiToken = null;

    private $dryRun = false;
    private $symmetry = null;

    private $focusUtilities = [];
    private $symmetryUtilities = [];

    private $distro = ['engineering@tpv.com', 'accountmanagers@answernet.com'];
    private $errorDistro = ['engineering@tpv.com', 'accountmanagers@answernet.com'];

    // Define rates to look for. 
    // Here, we are building a list of queries to run.
    // See Symmetry's API reference for details: https://rpm.symmetryenergy.com/docs/api
    // The search values are case sensitive.
    private $searchQueries = [
       # 'state=MI&channel=dtd&broker_id=280', // MI DTD rates for vendor 280 (Removed per Chris Jerez Request)
     //   'state=MI&channel=dtd&broker_id=291', // MI DTD rates for vendor 291
      //  'state[]=OH&state[]=MI&channel[]=csr_in&channel[]=tm'   // For CSR/TM, pull in OH and MI rates only
    ];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes Symmetrys Rates with their API';

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
        $this->setDistroList();
        $this->setErrorDistroList();

        $this->dryRun = $this->option('dry-run');
        $this->prodForced = $this->option('force-production');
        $this->devForced = $this->option('force-dev');

        // Get brand model
        $this->info("Retrieving brand...");

        $this->symmetry = Brand::where('name', 'Symmetry Energy Solutions')->whereNotNull('client_id')->first();

        if (empty($this->symmetry)) {
            $this->failWithEmail("Unable to locate Symmetry brand entry. Rate sync not completed.");            

            return -1;
        }

        // Check and validate 'force prod/dev' command args
        if ($this->prodForced && $this->devForced) {
            $this->failWithEmail("--force-dev and --force-production args may only be used one at a time. Rate sync not completed.");

            return -2;
        }

        // Retrieve API token
        $this->info("Retrieving API token...");

        $this->getApiToken();

        if(empty($this->apiToken)) {
            $this->failWithEmail("Unable to locate token to use for API calls. Rate sync not completed.");

            return -3;
        }  
        
        // Check if we only want to download rates to CSV
        if($this->option('download-to-csv')) {

            // Retrieve rates and export to CSV
            $this->downloadRatesToCsv();

            // Done. No need to proceed further.
            return 0;
        }

        // Retrieve Focus utilities configured for Symmetry
        // We'll need these to map the Focus utility ID to each rate returned from Symmetry's API.
        $this->info("Retrieving Focus utilities...");

        $this->focusUtilities = $this->getFocusUtilities();

        if(count($this->focusUtilities) == 0) {
            $this->failWithEmail("There are no utilities configured in Focus for Symmetry");

            return -4;
        }

        // Retrieve utilities list from Symmetry
        $this->info("Retrieving Symmetry utilities...");

        $utilityResult = $this->getSymmetryUtilities();

        if($utilityResult->result == 'error') {
            $this->failWithEmail($utilityResult->message);

            return -5;
        }

        $this->symmetryUtilities = $utilityResult->data;

        // Retrieve rates from Symmetry's API and format them.
        $rates = []; // Each result will append to this array

        $this->info("");
        $this->info("Retrieving Rates from Symmetry...");

     //   foreach($this->searchQueries as $searchQuery) {
            
            $this->info("");
            $this->info("--------------------------------------------------------------------------------");

            $ratesResult = $this->getRatesData();

            // Check for error
            if($ratesResult->result != "success") {

                // Build Error message
                $message = "Error retrieving rates from API. Rate sync not completed.\n\n";
                // TODO: Rework to use CSV file for error list
                foreach($ratesResult->data as $error) {
                    $message .= " - " 
                        . $error['program_name'] . " (" . $error['program_code'] . ") - " 
                        . $error['utility_id'] . " - " 
                        . $error['state'] . " - " 
                        . $error['commodity'] . " - " 
                        . $error['utility_code'] . " - "
                        . $error['error'] . "\n";
                }

                $this->failWithEmail($message);

                return -6;
            }

            // Append to rates list
            foreach($ratesResult->data as $rate) {
                $rates[] = $rate;
            }
    //    }

        $this->info("");
        $this->info("Downloaded " . count($rates) . " Rate(s) in total");

        if(count($rates) == 0) {
            $this->failWithEmail("No rates were returned by the API. Rate sync not completed. No changes were made to the existing rates in Focus.");

            return -7;
        }

        // Import rates into Focus
        $result = $this->importRates($rates);

        // Output/Email final result
        $message = '';
        $distro = '';

        if($result->result == 'success') {
            $message = ("Successfully processed " . count($rates) . " rates.");
            $distro = $this->distro;
        } else {
            $message = "An error occurred synching rates. Rate sync not completed. Any changes that have been made have been rolled back.\n\n";

            if(str_contains(strtolower($result->message), "an exception occurred")) {
                $message .= "Error: " . $result->data->getLine() . "\n";
                $message .= "Error: " . $result->data->getMessage();
            } else {
                $message .= "Error: " . $result->message;
            }

            $distro = $this->errorDistro;
        }

        $this->info($message);

        $this->sendEmail(
            $message,
            $distro
        );
    }

    /**
     * Outputs error then sends an email to the error distro list.
     */
    private function failWithEmail($message)
    {
        $this->error($message);

        $this->sendEmail(
            $message,
            $this->errorDistro
        );
    }

    /**
     * Create products and rates in Focus from the imported rate data.
     */
    private function importRates($rates)
    {
        // This result will be returned unless an error is encountered
        $result = $this->newResult('success');

        try {
            if(!$this->dryRun) {
                DB::beginTransaction();
            }

            // First soft-delete all active Symmetry products/rates
            $this->info("");
            $this->info("Clearing existing Symmetry products and rates...");

            $this->clearExistingProducts();

            $this->info("");
            $this->info("Processing rates...");

            // Process rates
            $rateCount = count($rates);
            $rateCtr = 0;

            // Main processing loop. Create/restore products and rates.
            foreach($rates as $rate) {
                
                $rateCtr++;

                $this->info("");
                $this->info("--------------------------------------------------------------------------------");
                $this->info("[ " . $rateCtr . " / " . $rateCount . " ]");
                $this->info("");
                $this->info("Utility: " . $rate['utility_name']);
                $this->info("Program Code: " . $rate['id']);

                // Find or create product
                $product = $this->findOrCreateProduct($rate);
                
                // Find or create rate
                $focusRate = $this->findOrCreateRate($product->id, $rate);

                // Assign vendor(s) to rate
                if(strtolower($rate['channel']) == 'call center inbound') {
                    $this->assignVendor('245', $focusRate->id); // Assign S4 communications

                    // Per Lauren H, also assign to vendors 100 and 200
                    $this->assignVendor('100', $focusRate->id); // Symmetry Energy Solutions Test Vendor
                    $this->assignVendor('200', $focusRate->id); // TPV.com Test Vendor
                } else {
                    if($rate['broker_id'] && $rate['broker_id'] != '249' && $rate['broker_id'] != '278') { // TODO: Remove 249 and 278 from IF
                        $this->assignVendor($rate['broker_id'], $focusRate->id);

                        // Per Lauren H, also assign to vendors 100 and 200
                        $this->assignVendor('100', $focusRate->id); // Symmetry Energy Solutions Test Vendor
                        $this->assignVendor('200', $focusRate->id); // TPV.com Test Vendor
                    }
                }                
            }

            if(!$this->dryRun) {
                DB::commit();
            }

        } catch (\Exception $e) {
            if(!$this->dryRun) {
                DB::rollback();
            }

            // Pass exception back up to calling function to deal with
            $result = $this->newResult(
                'error',
                'An exception occurred while processing rates',
                $e
            );
        }
        
        return $result;
    }

    /**
     * Retrieve rates from Symmetry's API.
     * This function will also map the utilities configured for Symmetry in Focus to the downloaded rates.
     */
    private function getRatesData()
    {
        $apiResult = $this->apiHttpGet('/products');

        if($apiResult->result == 'error') {
            return $apiResult; // Pass error back to the calling functio to deal with.
        }

        $this->info(count($apiResult->data) . " rate(s) downloaded.");

        // Now, append Focus utility IDs to the rates
        $rates = $this->mapFocusUtilities($apiResult->data);

        return $rates; // Pass success or error back to calling function to deal with
    }

    /**
     * Retrieve utilities list from Symmetry's API.
     */
    private function getSymmetryUtilities()
    {
        $apiResult = $this->apiHttpGet('/utilities');

        if($apiResult->result == 'error') {
            return $apiResult; // Pass error back to the calling functio to deal with.
        }

        $this->info(count($apiResult->data) . " utility(ies) downloaded.");

        return $this->newResult('success', null, $apiResult->data);
    }

    /**
     * Retrieve API token from DB
     */
    private function getApiToken() 
    {
        $pi = ProviderIntegration::select('password')
            ->where('brand_id', $this->symmetry->id)
            ->where('service_type_id', 42) // Symmetry RPM API
            ->where('env_id', config('app.env') === 'production' ? 1 : 2)
            ->first();

        if (!empty($pi)) {
            $this->apiToken = $pi->password;
        }
    }

    /**
     * Perform HTTP GET against Symmetry's API for the specified resource.
     */
    private function apiHttpGet($resource)
    {
        if(!str_starts_with($resource, '/')) {
            $resource = '/' . $resource;
        }

        $this->info("GET: " . $resource);

        // Build URL
        $baseUrl = $this->getBaseApiUrl();

        if(!$baseUrl) {
            $errMsg = 'Error retrieving base API url';
            $this->error($errMsg);
            
            return $this->newResult('error', $errMsg);
        }

        $start = hrtime(true);
        $client = new HttpClient();

        try {
            $res = $client->request('GET', ($baseUrl . $resource), [
                'headers' => [
                    'Authorization' => 'Token token=' . $this->apiToken
                ]
            ]);

            if ($res->getStatusCode() === 200) {
                $end = hrtime(true);

                $this->info("Request took " . (($end - $start) / 1e+6) . " ms");

                $rawBody = trim($res->getBody());

                if (!empty($rawBody)) {
                    return $this->newResult('success', null, json_decode($rawBody, true));
                }

            }
        } catch (TransferException $e) {
            $end = hrtime(true);
            $code = $e->getCode();
            $msg = $e->getMessage();
            
            $this->info("Request took " . (($end - $start) / 1e+6) . " ms");

            $errMsg = 'Error while retrieving data from ' . $resource . ' (' . $code . '): ' . $msg;
            $this->error($errMsg);
            return $this->newResult('error', $errMsg);

        } catch (Exception $e) {
            $end = hrtime(true);
            $code = $e->getCode();
            $msg = $e->getMessage();

            $this->info("Request took " . (($end - $start) / 1e+6) . " ms");

            $errMsg = 'Error while retrieving data from ' . $resource . ' (' . $code . '): ' . $msg;
            $this->error($errMsg);
            return $this->newResult('error', $errMsg);
        }

        // We'll only reach this if no exceptions, and we got an empty object from json decode of results
        $errMsg = 'No data returned from api call to ' . $resource;
        $this->error($errMsg);
        return $this->newResult('error', $errMsg);
    }

    /** 
     * Retrieve product record from Focus with matching product info from utility/rate data.
     * If found, restore product and return its ID.
     * If not found, crete product and return its ID.
     */
    private function findOrCreateProduct($rate)
    {
        $this->info("In createProduct with state = " . $rate['utility_state']);

        $brand_id = $this->symmetry->id;
        
        $markets = strtolower($rate['segment']) == "residential" ? "Residential" : "Commercial"; // Values provided by Symmetry: Residential, Small Commercial, Large Commercial
        $channels = $this->mapChannel($rate['channel']);

        $productName = 
            $rate['name']
            . (str_contains($rate['name'], $rate['term_months']) ? '' : ' - ' . $rate['term_months']) // Only append term to name if named doesn't already contain it
            . ' - ' . $rate['utility_state']
            . ' - ' . $markets
            . ' - ' . $channels;

        $rateTypeId = 1; // Per Lauren H, always 'Fixed' in Focus.        

        // Check if a product with matching details aleady exists
        $this->info("Searching for Focus product with matching info...");
        
        $p = Product::where('brand_id', $brand_id)
            ->where('name', $productName)
            ->where('rate_type_id', $rateTypeId)
            ->whereNull('intro_service_fee')
            ->whereNull('intro_daily_fee')
            ->whereNull('intro_term')
            ->whereNull('intro_term_type_id')
            ->whereNull('service_fee')
            ->whereNull('transaction_fee')
            ->whereNull('daily_fee')
            ->whereNull('green_percentage');

        if ($rateTypeId == 2) {
            $p = $p->where('term', 0)
                ->whereNull('term_type');
        } else {
            $p = $p->where('term', $rate['term_months'])
                ->where('term_type_id', 3);
        }

        $p = $p->whereNull('green_percentage')
            ->where('prepaid', 0)
            ->where('market', $markets)
            ->where('channel', $channels)
            ->where('home_type', "Single|Multi-Family|Apartment|N/A");

        $p = $p->withTrashed()
            ->orderBy('updated_at', 'desc')
            ->first();

        // Yep; restore it and return its ID
        if ($p) {
            $this->info("Found! Restoring and using this product.");

            if(!$this->dryRun) {
                $p->restore();
                $p->hidden = 0;
                $p->save();
            } else {
                $this->warn("Dry-run. Product was not restored.");
            }

            return $p;
        }

        // Product doesn't exist. Create it and return its ID
        $this->info("Not Found. Creating new product...");

        $p = new Product();

        $p->brand_id = $this->symmetry->id;
        $p->name = $productName;
        $p->rate_type_id = $rateTypeId;

        if ($rateTypeId == 2) {
            $p->term = 0;
        } else {
            $p->term = $rate['term_months'];
            $p->term_type_id = 3;
        }

        $p->prepaid = 0;
        $p->market = $markets;
        $p->channel = $channels;
        $p->home_type = "Single|Multi-Family|Apartment|N/A";

        if(!$this->dryRun) {
            $p->save();
        } else {
            $p->id = "xyz"; // Assign a dummy ID so that when we search for rates, we don't accidentally pull in unrelated orphaned rates by using a null id.
            $this->warn("Dry-Run. New product not saved.");
        }

        return $p;
    }

    /**
     * Map a Symmetry channel value to it's Focus equivalent
     */
    private function mapChannel($channel)
    {
        if(!$channel) {
            return null;
        }

        switch(strtolower($channel))
        {
            case 'call center inbound':
                return 'TM';
            case 'dtd':
            case 'door to door':
                return 'DTD';
            case 'tm':
            case 'telemarketing':
                return 'TM';
            case 'all':
                return 'DTD|TM';
        }
    }

    /**
     * Assigns a vendor to a rate
     */
    private function assignVendor($id, $focusRateId)
    {
        $vendor = Vendor::select(
            'id',
            'vendor_label'
        )->where(
            'brand_id',
            $this->symmetry->id
        )->where(
            'vendor_label',
            $id
        )->first();

        if(!$vendor) {
            throw new \Exception('Unable to locate a vendor record for vendor ID ' . $id);
        }

        // Vendor found, assign to rate
        // Reuse assignment if possilbe, else create a new assignment record
        $vr = VendorRate::where(
            'vendors_id',
            $vendor->id
        )->where(
            'rate_id',
            $focusRateId
        )->withTrashed()->first();

        if ($vr) {
            $vr->restore();
        } else {
            $vr = new VendorRate();
        }

        $vr->vendors_id = $vendor->id;
        $vr->rate_id = $focusRateId;

        $vr->save();
    }

    /** 
     * Retrieve rate record from Focus with matching rate info from utility/rate data.
     * If found, restore rate and return its ID.
     * If not found, crete rate and return its ID.
     */
    private function findOrCreateRate($productId, $rate)
    {
        $rateUomId = $this->getRateUomId($rate['price_units']);
        
        $cancellationFeeTypeId = 5; // From 'term_types' table. Always 'one time'
        $rateCurrencyId = (strtolower($rate['price_units']) == 'mcf' ? 2 : 1); // Dollars for Mcf, else Cents 

        $rateAmount = $rate['latest_price']; // Always provided to us in dollars
        if($rateCurrencyId == 1) { // For currency 'Cents' we'll need to convert rate amount to cents
            $rateAmount = round($rateAmount * 100, 2);
        }

        $this->info("Searching for Focus rate with matching info...");

        $r = Rate::where('product_id', $productId)  // Including product ID check, as we only want to restore rates for products that have been restored.
            ->where('program_code', $rate['id'])
            ->where('rate_amount', $rateAmount)
            ->where('cancellation_fee', $rate['etf_amount'])
            ->where('cancellation_fee_term_type_id', $cancellationFeeTypeId)
            ->where('dual_only', 0)
            ->where('tlp', 0)
            ->where('rate_currency_id', $rateCurrencyId)
            ->where('rate_uom_id', $rateUomId)
            ->where('date_from', Carbon::parse($rate['term_start_date']))
            ->where('utility_id', $rate['focus_utility_id'])
            ->where('external_rate_id', $rate['code']);
        
        $r = $r->withTrashed()->first();
            
        // Found rate? Restore and return. No further action needed.
        if($r) {
            $this->info("Found! Restoring and using this rate.");

            if(!$this->dryRun) {
                $r->restore();
                $r->hidden = 0;
                $r->save();
            } else {
                $this->warn("Dry-run. Rate was not restored.");
            }
            return $r;
        }

        // Rate not found. Create it and return.
        $this->info("Not found. Creating new rate...");

        $r = new Rate();

        $r->product_id = $productId;
        $r->program_code = $rate['id'];
        $r->rate_amount = $rateAmount;
        $r->cancellation_fee = $rate['etf_amount'];
        $r->cancellation_fee_term_type_id = $cancellationFeeTypeId;
        $r->dual_only = 0;                  // Always 'Off'
        $r->tlp = 0;                        // 'Tablet Publish' field in UI. Always 'Off'
        $r->rate_currency_id = $rateCurrencyId;
        $r->rate_uom_id = $rateUomId;
        $r->date_from = Carbon::parse($rate['term_start_date']);
        $r->utility_id = $rate['focus_utility_id'];
        $r->external_rate_id = $rate['code'];
        
        if(!$this->dryRun) {
            $r->save();
        } else {
            $this->warn("Dry-Run. New rate not saved.");
        }

        return $r;
    }

    /** 
     * Lookup rate UOM text to get the rate uom ID.
     */
    private function getRateUomId($rateUom)
    {
        $uom = RateUom::where(
            'uom',
            $rateUom
        )->first();

        if(!$uom) {
            return null;
        }

        return $uom->id;
    }

    /**
     * Soft delete existing rates.
     */
    private function clearExistingProducts()
    {
        $pcnt = 0;
        $rcnt = 0;

        // Retrieve products list
        $products = Product::where('brand_id', $this->symmetry->id)->get();

        // For each product, locate associated rates and delete them.
        // Also delete any vendor assignments for those rates.
        foreach ($products as $product) {
            $rates = Rate::where('product_id', $product->id)->withTrashed()->get();

            if (!$this->dryRun) {
                foreach ($rates as $rate) {
                    // Delete any rate vendor assignments
                    VendorRate::where('rate_id', $rate->id)->delete();

                    $rate->hidden = 1;
                    $rate->save();

                    $rate->delete();
                }

                // Delete any product vendor assignments
                VendorRate::where('product_id', $product->id)->delete();

                $this->info("Removing Product '" . $product->name . "'");

                $product->delete();
            } else {
                $this->warn('Removing Product "' . $product->name . '" would remove ' . $rates->count() . ' rates');

                $pcnt += 1;
                $rcnt += ($rates->count());
            }
        }

        if ($this->dryRun) {
            $this->warn('Clearing Products would remove ' . $pcnt . ' products and ' . $rcnt . ' associated rates');
        }
    }

    /**
     * Returns based URL based on environment and command args
     */
    private function getBaseApiUrl() {

        $url = null;
        if ($this->prodForced || (!$this->devForced && config('app.env') === 'production')) {
            $this->warn('Environment: Production');

            $url = $this->prodURI;

            $this->warn('URL: ' . $url);
        }
        if ($this->devForced || (!$this->prodForced && config('app.env') !== 'production')) {
            $this->warn('Environment: Development');

            $url = $this->devURI;

            $this->warn('URL: ' . $url);
        }

        return $url;
    }

    /**
     * Retrieves from Focus the configured utilities for Symmetry and matches them to the rates retrieved from 
     * Symmetry's API.
     * 
     * In Focus, a utility can sell Electricity, Natural Gas, or both. To facilitate that relationship, the
     * utility and fuel type relationship is stored in the utility_supported_fuels table. Due to this, the ID
     * we need is actually not the ID from the utility record, but the ID from the utility_supported_fuels record.
     */
    private function mapFocusUtilities($rates) 
    {
        $errors = [];

        // Match Symmetry utilities to the Focus counterpart.
        // Ignore utilities that are not for the brand's configured states
        // Look up utility by code and commodity to obtain the correct ID from the utility_supported_fuels table            
        foreach($rates as $index => $rate) {            
            $rates[$index]['focus_utility_id'] = null; // New properties to track Focus utility ID and utility state for the rate.
            $rates[$index]['utility_state'] = null;

            $symmetryUtility = null;

            // Find Symmetry utility associated with the rate.
            // We need the state and commodity so we can search for the Focus counterpart
            foreach($this->symmetryUtilities as $utility) {
                if($utility['id'] == $rate['utility_id']) {
                    $symmetryUtility = $utility;
                    $rates[$index]['utility_state'] = $utility['state'];
                    break;
                }
            }

            if(!$symmetryUtility) { // Technically, this should never happen. All Symmetry rates should be mapped to a Symmetry utility.
                $errors[] = [
                    'program_code' => $rate['code'],
                    'program_name' => $rate['name'],
                    'utility_id' => $rate['utility_id'],
                    'state' => null,
                    'commodity' => null,
                    'utility_code' => null,
                    'error' => 'Unable to map Symmetry rate.utility_id to a Symmetry utility'
                ];

                continue;
            }

            // Find the Focus utility        
            foreach($this->focusUtilities as $utility) {
                if(
                    strtolower($utility['utility_label']) == strtolower($symmetryUtility['code'])
                    && $utility['utility_fuel_type_id'] == (strtolower($symmetryUtility['commodity']) == 'gas' ? 2 : 1)
                    && strtolower($utility['state']) == strtolower($symmetryUtility['state'])
                ) {
                    $rates[$index]['focus_utility_id'] = $utility['utility_supported_fuels_id'];
                    break;
                }
            }

            if(!$rates[$index]['focus_utility_id']) {
                $errors[] = [
                    'program_code' => $rate['code'],
                    'program_name' => $rate['name'],
                    'utility_id' => $rate['utility_id'],
                    'state' => $symmetryUtility['state'],
                    'commodity' => $symmetryUtility['commodity'],
                    'utility_code' => $symmetryUtility['code'],
                    'error' => 'Unable to map Symmetry utility to a Focus utility'
                ];
            }
        }

        $result = $this->newResult(
            count($errors) == 0 ? 'success' : 'error',
            count($errors) == 0 ? '' : 'Errors encountered when mapping Symmetry utilities to Focus utilities',
            count($errors) == 0 ? $rates : $errors
        );

        return $result;
    }

    /**
     * Retrieve list of utilities configured fro Symmetry.
     */
    private function getFocusUtilities()
    {
        return BrandUtility::select(
            'brand_utilities.utility_label',
            'utilities.name',
            'utilities.id',
            'states.state_abbrev AS state',
            'utility_supported_fuels.utility_fuel_type_id',
            'utility_supported_fuels.id as utility_supported_fuels_id'
        )->join(
            'utilities',
            function($join) {
                $join->on('brand_utilities.utility_id', 'utilities.id');
                $join->whereNull('utilities.deleted_at');
            }
        )->join(
            'utility_supported_fuels',
            function($join) {
                $join->on('utilities.id', 'utility_supported_fuels.utility_id');
                $join->whereNull('utility_supported_fuels.deleted_at');
            }
        )->join(
            'states',
            function($join) {
                $join->on('utilities.state_id', 'states.id');
                $join->whereNull('states.deleted_at');
            }
        )->where(
            'brand_utilities.brand_id',
            $this->symmetry->id
        )->get()->toArray();
    }

    /**
     * Retrieve Symmetry rates from API as-is and export to CSV file
     */
    private function downloadRatesToCsv()
    {
        $rates = [];

        //foreach($this->searchQueries as $searchQuery) {

            $result = $this->apiHttpGet('/products');

            if($result->result == 'error') {
                $this->error($result->message); // Pass error back to the calling functio to deal with.

                exit -900;
            }

            // Append to rates list
            foreach($result->data as $rate) {
                $rate['customer_charges'] = "";
                $rates[] = $rate;
            }
      //  }

        if(count($rates) == 0) {
            $this->error("No rates were retrieved from Symmetry's API");

            exit -901;
        }

        $header = array_keys($rates[0]);

        $this->writeCsvFile(public_path('tmp/') . "Symmetry Rates - " . Carbon::now("America/Chicago")->format("Ymd-His") . ".csv", $rates, $header);
    }

    /**
     * Sends and email.
     *
     * @param string $message - Email body.
     * @param array  $distro  - Distribution list.
     * @param array  $files   - Optional. List of files to attach.
     *
     * @return string - Status message
     */
    private function sendEmail(string $message, array $distro, array $files = array())
    {
        if($this->option('no-email')) {
            return "";
        }

        $uploadStatus = [];
        $email_address = $distro;

        // Build email subject
        if ('production' != env('app.env')) {
            $subject = $this->jobName . ' (' . config('app.env') . ') ' . Carbon::now("America/Chicago");
        } else {
            $subject = $this->jobName . ' ' . Carbon::now("America/Chicago");
        }

        $data = [
            'subject' => '',
            'content' => $message
        ];

        for ($i = 0; $i < count($email_address); ++$i) {
            $status = 'Email to ' . $email_address[$i]
                . ' at ' . Carbon::now("America/Chicago") . '. Status: ';

            try {
                Mail::send(
                    'emails.generic',
                    $data,
                    function ($message) use ($subject, $email_address, $i, $files) {
                        $message->subject($subject);
                        $message->from('no-reply@tpvhub.com');
                        $message->to(trim($email_address[$i]));

                        // add attachments
                        foreach ($files as $file) {
                            $message->attach($file);
                        }
                    }
                );
            } catch (\Exception $e) {
                $status .= 'Error! The reason reported is: ' . $e;
                $uploadStatus[] = $status;
            }

            $status .= 'Success!';
            $uploadStatus[] = $status;
        }

        return $uploadStatus;
    }

    /**
     * Helper function for creating a result object
     */
    private function newResult($result, $message = null, $data = null)
    {
        return (object)[
            'result' => $result,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Set the email distribution list
     */
    private function setDistroList() 
    {       
        if ($this->option('email-to')) {
            $this->distro = $this->option('email-to');
        } 
    }

    /**
     * Set the email distribution list for errors
     */
    private function setErrorDistroList() 
    {        
        if ($this->option('error-distro')) {
            $this->errorDistro = $this->option('error-distro');
        } 
    }
}
