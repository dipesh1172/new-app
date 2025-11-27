<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Adapter\Local;
use ZipArchive;

use App\Models\Product;
use App\Models\Rate;

/**
 * // TODO: Complete the documentation with command flow outline.
 * 
 * Rate importer command for Direct Energy small commercial rtes.
 */
class DirectEnergyScRateImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DirectEnergy:SC:Rate:Import {--mode=} {--date=} {--file-num=} {--dry-run} {--local-file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports a Direct Energy rate files, for the small commercial channel.';

    /**
     * Work directory. Where the zip file is downloaded to and processed from.
     */
    protected $workDir = '';

    /**
     * The zip archive filename
     * 
     * @var string
     */
    protected $zipFilename = '';

    /**
     * The text filename
     */
    protected $txtFilename = '';

    /**
     * List of Direct Energy utilities
     */
    protected $utilityList = null;

    /**
     * Import job start date/Time.
     */
    protected $jobStartDate = null;

    /**
     * We'll track product we create via this import in this array. This is so we don't have to query.
     * The database, since we don't want to consider existing products (they'll be deleted).
     */
    protected $createdProducts = [];

    /**
     * Currency IDs.
     */
    protected const CURRENCY_IDS = [
        'cents' => 1,
        'dollars' => 2
    ];

    /**
     * UOM IDs.
     */
    protected const UOM_IDS = [
        'therm' => 1,
        'kwh' => 2,
        'unknown' => 3,
        'ccf' => 4,
        'mwhs' => 5,
        'gj' => 6,
        'mcf' => 7,
        'day' => 8,
        'month' => 9
    ];

    /**
     * Brand name
     */
    protected $brandName = 'Direct Energy';

    /**
     * Direct Energy brand ID
     */
    protected $brandId = null; // $this->brandName will be looked up to retrieve this ID

    /**
     * Mode: 'live' or 'test'
     */
    protected $mode = 'live'; // 'live' by default.
    
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Set the working directory
        $this->workDir = public_path('tmp') . '/';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Perform arg validations and global var setup
        $this->setup();

        // Perform file prep operations (download, unzip, etc...)
        $this->prepareFileForImport();

        // Check if unzipped file exists.
        // This is in case the --local-file option was used, and also serves to 
        // double check if the file unzipped successfully when the --local-file option is not used.
        if(!file_exists($this->workDir . $this->txtFilename)) {
            $errMsg = "File '" . $this->workDir . $this->txtFilename . "' doesn't exist. Possible error unzipping file. Exiting...";
            
            $this->error($errMsg);
            $this->sendErrorNotification($errMsg);

            exit(-1);
        }

        // Get a line count from the file
        $totalLines = $this->getLineCount($this->workDir . $this->txtFilename);

        // Open the file for processing.
        $file = fopen($this->workDir . $this->txtFilename, "r");

        if(!$file) {
            $errMsg = "Unable to open file '" . $this->workDir . $this->txtFilename . "' for processing!";
            $this->error($errMsg);

            $this->sendErrorNotification($errMsg);

            exit(-1);
        }
        
        // This array represents the file layout. 
        // The rates file does not contain a header row.
        // This array will be used to convert each line from the file to an object, using the array values 
        //  as the object property names, so the items in this array must appear in the same order as the data.
        $fields = [
            'sales_state', 'prod_key', 'esco_rate_desc', 'esco_id', 'gas_ldc_code', 'elec_ldc_code', 
            'gas_ldc_name', 'elec_ldc_name', 'cust_type', 'svc_type', 'gas_acct_type',
            'elec_acct_type', 'rate_script_eng', 'rate_script_spa', 'rate_amount', 'rate_currency',
            'rate_uom', 'rate_type', 'dt_end_eff', 'cancel_fee', 'cancel_fee_type',
            'disclosure_script_eng', 'disclosure_script_spa', 'promo_active1', 'promo_key1',
            'promo_cd1', 'promo_script_eng', 'promo_script_spa', 'closing_script_eng', 'closing_script_spa',
            'gas_acct_num_len', 'elec_acct_num_len', 'cs_number', 'row_delim'
        ];

        // Iterate lines in file and process them. Ignore records where fuel type is 'both' or 'combo'
        $lineCtr = 0; // Use to keep track of file line position.
        $errors = []; // For reporting records we couldn't import due to errors.

        while(($line = fgets($file)) !== false) {
            $lineCtr++;

            $this->info("--------------------------------------------------------------------------------");
            $this->info("[ $lineCtr / $totalLines ]\n");

            // Convert line from tab-delimited to CSV
            // 1 - Replace tab character [chr(9)] with ","
            // 2 - Cut out end of line characters [chr(13) + chr(10)]
            // 3 - Enclose resulting string in single quotes. This will add the missing quotes to beginning and end of string
            $line = '"' . str_replace(chr(13) . chr(10), '', str_replace(chr(9), '","', $line)) . '"';
            $line = str_getcsv($line);

            // Convert simple array to an object
            $line = $this->arrayToObject($line, $fields);

            // Show some general info
            $this->info('State:        ' . $line->sales_state);
            $this->info('Commodity:    ' . $line->svc_type);
            $this->info('Program Code: ' . $line->prod_key);
            $this->info('Time:         ' . Carbon::now('America/Chicago'));
            $this->info('');

            // Skip records with commdity 'Both' or 'Combo'
            if($line->svc_type == 'Both' || $line->svc_type == 'Combo') {
                $this->info("Commodity is NOT 'Electric' or 'Gas'. Skipping record.");
                continue;
            }

            // Import the rate record
            $importResult = $this->importRecord($line);

            if($importResult->result != 'Success') {
                $errMsg = $importResult->message;
                $this->error($errMsg);
                $errors[] = [
                    'line' => $lineCtr,
                    'prod_key' => $line->prod_key,
                    'error' => $errMsg
                ];
            }

            $this->info('Successfully imported record.');
        }

        fclose($file);

        $this->info("--------------------------------------------------------------------------------");

        // TODO: Delete old rates
        $this->deleteOldRates();        

        if(count($errors) > 0) {
            $this->info("Errors found. Exporting to file...");
            $keys = array_keys($errors[0]);

            $file = fopen($this->workDir . 'de_sc_rate_import_errors.csv', "w");

            fputcsv($file, $keys);
            foreach($errors as $err) {
                fputcsv($file, $err);
            }
            fclose($file);
        }
        // TODO: Delete Zip and TXT file after processing
    }

    /**
     * Download file
     */
    protected function prepareFileForImport() {

        // No download necessary if we're dealing with a local, unipped, file
        if($this->option('local-file')) {
            $this->info("--local-file option set. Will look for unzipped file in working directory.");

            return;
        }

        // Download the file        
        $downloadResult = $this->downloadFile($this->zipFilename);

        // If file doesn't exist, exit without notifications.
        // This is normal, we don't expect a new file every day.
        if($downloadResult->result == 'Error' && str_starts_with(strtolower($downloadResult->message), 'unable to locate file')) {
            $this->info($downloadResult->message . '. Exiting...');
            exit(0);
        }

        // Other FTP errors should trigger a notification
        if($downloadResult->result == 'Error') {
            $errMsg = "Error downloading file " . $this->zipFilename . " from FTP server: \n\n" . $downloadResult->message;
            $this->error($errMsg);

            $this->sendErrorNotification($errMsg);
            exit(-1);
        }

        // Download function returned true, but as a sanity check, look for file on our server
        if(!file_exists($this->workDir . $this->zipFilename)) {
            $errMsg = "File '" . $this->workDir . $this->zipFilename . "' doesn't exist.";
            $this->info($errMsg . " Exiting...");

            $this->sendErrorNotification($errMsg);
            exit(-1);
        }

        // Unzip the rates file.
        if(!$this->unzipFile($this->workDir . $this->zipFilename, $this->workDir)) {
            $this->sendErrorNotification("Error unzipping file '$this->zipFilename'.");
            exit(-1);
        };
    }

    /**
     * Import a rate record from the rates file into the database
     * 
     * @param object $line - The line from the file that needs to be parsed and imported
     * 
     * @return object - Result object. Result->result will contain 'Success' or 'Error', with Result->message containing any error details
     */
    protected function importRecord($line) {

        $salesState = $line->sales_state;
        $commodity = $line->svc_type; 
        $commodityId = (strtolower($commodity) == 'electric' ? 1 : 2);
        $utilityName = ($commodityId == 1 ? $line->elec_ldc_name : $line->gas_ldc_name);
        $ldcCode = ($commodityId == 1 ? $line->elec_ldc_code : $line->gas_ldc_code);

        // Get the utility 
        $utility = $this->utilityLookup($salesState, $commodityId, $ldcCode);

        if(!$utility) {
            return $this->newResult('Error', "Unable to find a utility record for State: $salesState, Commodity: $commodity, Utility: $utilityName, and LDC Code: $ldcCode");
        }

        // Find or create the product record
        $productId = $this->findOrCreateProduct($line);

        // Create the rate record
        // Prouduct ID is required for this, so for dry-run mode use a fake value here
        if($this->option('dry-run')) {
            $productId = 'abc';
        }

        $rate = $this->createRate($productId, $utility->id, $line);

        return $this->newResult('Success');
    }

    /**
     * Locate product in database. If not found, create it.
     * 
     * @param object $line - The line from the rate file.
     * 
     * @return string - The product ID of the located or created product.
     */
    protected function findOrCreateProduct($line) {

        // DE product names are too some and done account for the different term lengths (stored at the product level in Focus). Build a better product name.
        $productName = $this->buildProductName($line);
        $productNameLower = strtolower($productName); // For checking $createdProducts indexes and any other operations requiring strtolower()

        $this->info("\nLooking up product: " . $productName);

        // Have we already created this product during this import?
        if (isset($this->createdProducts[$productNameLower])) {

            $this->info('Found!');
            return $this->createdProducts[$productNameLower]; // Yes? Then return the ID.
        }

        // Nope. Create it.
        $this->info('Not Found. Creating new product...');

        $greenPct = (strpos($productNameLower, 'green product') ? 100 : 0); // Some of Direct Energy's product names have 'green product' in the names. We'll set these up as green product in Focus
        $term = $this->extractTermLength($line->rate_script_eng);

        $product = new Product();
        $product->brand_id = $this->brandId;
        $product->name = $productName;
        $product->channel = 'DTD|TM'; // TODO: Double check these values
        $product->market = 'Commercial';
        $product->home_type = null;
        $product->rate_type_id = ($line->rate_type == 'Fixed' ? 1 : 2); // DE has fixed or variable only
        $product->green_percentage = $greenPct;
        $product->term = $term;
        $product->term_type_id = 3; // Always 'Months'
        $product->service_fee = null;
        $product->transaction_fee = null;
        $product->transaction_fee_currency_id = null;
        $product->intro_term = null;
        $product->intro_term_type_id = null;
        $product->date_from = null;
        $product->date_to = null;

        if($this->option('dry-run')) {
            $this->info("Dry run. Product would have been created, but we're skipping that step.");
        } else {
            $this->info('New product saved.');
            $product->save();
        }

        // Store the products ID in our tracker array. 
        // We can reuse it for the next rate that refers to the same product.
        $this->createdProducts[$productNameLower] = $product->id;

        return $product->id;
    }

    /** 
     * Builds a product name. The product name will consist of the following.
     *     
     * @return string - The product name
     */
    protected function buildProductName($line) {
        
        // Product name is built from the following:
        //   - 'Discovery'       -- Direct Energy's CRM/Enrollment system for US North
        //   - Customer Type     -- 'SC' for small commercial, this case
        //   - Sales State       -- The utility state
        //   - Esco Rate Desc    -- The esco_rate_desc from the rate file; what we would normally use as the product name
        //   - Term [Optional]   -- The term. DE does not provide that in a data field. We should be able extract that from the rate script
        // 
        // Example: Discovery - SC OH - Fixed Rate Contract - 48
        
        $term = $this->extractTermLength($line->rate_script_eng);
        $productName = 'Discovery - SC - ' . $line->sales_state . ' - ' . $line->esco_rate_desc . (!empty($term) ? ' - ' . $term : '');

        return $productName;
    }

    /**
     * Extracts the term length from rate script verbiage.
     * 
     * @param string $rateScript - The rate script to parse
     * 
     * @return string - The term length or empty string if term length is not found
     */
    protected function extractTermLength(string $rateScript) {

        // On sampling active rates (2022-08-23), Direct Energy is quoting the term as '... for XX monthly billing cycles...' in their rate scripts
        // We can use that info to extract the number part of that string.
        //
        // With this regex matcher, we should get something that looks like this on a successful match:
        // Array
        // (
        //     [0] =>  for 48 monthly billing cycles
        //     [1] => 48
        // )

        $matches = [];
        preg_match("/ for (\d+) monthly billing cycles/i", $rateScript, $matches);

        // Matches found? Return item at index 1. This is the term lenght we're looking for.
        // No matches? Return empty string        
        return (count($matches) > 0 ? $matches[1] : "");
    }

    /**
     * Locate rate for specified product, utility, and rate details. If not found, create it.
     * 
     * @param string $productId - The product ID.
     * @param string $utilityId - The utiliyt ID.
     * @param object $line - The line from the rate file.
     */
    protected function createRate(string $productId, string $utilityId, $line) {

        $this->info("\nCreating rate record...");

        $rateCurrencyId = $this->getCurrencyId($line->rate_currency);
        $rateUomId = $this->getUomId($line->rate_uom);
        $scripting = $this->buildRateScripts($line);

        // Map all data we don't fields for into 'extra fields' array
        $extra_fields = $this->mapExtraFields(['esco_id'], $line);

        $rate = new Rate();

        $rate->hidden = 0;
        $rate->product_id = $productId;
        $rate->program_code = $line->prod_key;
        $rate->utility_id = $utilityId;                
        $rate->cancellation_fee = $line->cancel_fee;
        $rate->cancellation_fee_term_type_id = 5; // One time
        $rate->admin_fee = null;
        $rate->external_rate_id = null;
        $rate->rate_promo_code = null;
        $rate->rate_source_code = null;
        $rate->rate_renewal_plan = null;
        $rate->rate_channel_source = null;
        $rate->intro_rate_amount = null;
        $rate->rate_amount = (empty($line->rate_amount) ? '0' : $line->rate_amount);
        $rate->rate_currency_id = $rateCurrencyId;
        $rate->rate_uom_id = $rateUomId;
        $rate->rate_monthly_fee = null;
        $rate->date_from = Carbon::today("America/Chicago");
        $rate->date_to = null;
        $rate->dual_only = 0;
        $rate->custom_data_1 = $line->cs_number;
        $rate->custom_data_2 = null;
        $rate->custom_data_3 = null;
        $rate->custom_data_4 = null;
        $rate->custom_data_5 = null;
        $rate->extra_fields = $extra_fields;
        $rate->scripting = $scripting;

        if (!$this->option('dry-run')) {
            $this->info('Saving rate...');
            $rate->save();
        } else {
            $this->info('Dry run. Rate not saved.');
        }

        return $rate;
    }

    /**
     * Takes in an array of field names, then takes those fields from the current rate import line being processed and 
     * creates and array that can be stored in the extra_fields rate field, returned as a JSON object.
     * 
     * @param array $fields - The fields to map as extra fields
     * @param object $line  - Current rate import line being processed
     * 
     * @return string - The JSON string with resulting extra fields array
     */
    protected function mapExtraFields(array $fields, $line) {
        
        $extra_fields = [];

        foreach ($fields as $field_name) {
            $extra_fields[] = [
                'name' => $field_name,
                'value' => $line->$field_name
            ];
        }        

        return json_encode($extra_fields);
    }

    /**
     * Builds a rate scripts JSON object
     * 
     * @param object $line - The line from the rate import file currently being processed
     * 
     * @return string|null - The JSON string if rate scripts were provided, or null if rates scripts were not provided.
     */
    protected function buildRateScripts($line) {

        // Populate rate scripting verbiage array. We're following the same process as done by the UI (Clients App -> ProductController::rateupdate)
        // Not sure if it makes any difference in the end, but when all three scripts (rate, disclosure, closing) are provided,
        // the JSON looks something like this:
        // 
        // {
        //   "english": [
        //     "scripting verbiage1",
        //     "scripting verbiage2",
        //     "scripting verbiage3"
        //   ]
        // }
        //
        // But when a rate script is left blank, I've seen us end up with something like this:
        //
        // {
        //   "english": {
        //     "0": "scripting verbiage1",
        //     "2": "scripting verbiage3"
        //   }
        // }

        $scripting = [];
        $scriptingEng = [
            $line->rate_script_eng,
            $line->disclosure_script_eng,
            $line->closing_script_eng
        ];

        $scriptingSpa = [
            $line->rate_script_spa,
            $line->disclosure_script_spa,
            $line->closing_script_spa
        ];

        if ($scriptingEng) {
            $scripting['english'] = array_filter($scriptingEng, function ($item) {
                return $item !== null && trim($item) !== '';
            });
        }
        if ($scriptingSpa) {
            $scripting['spanish'] = array_filter($scriptingSpa, function ($item) {
                return $item !== null && trim($item) !== '';
            });
        }

        if (count($scripting) > 0) {
            return json_encode($scripting);
        }

        return null;
    }

    /**
     * Returns a currency ID based on the currency string provided.
     * 
     * @param string $currency - The currency string (ie, 'Dollars', 'Cents', etc...)
     * 
     * @return null|int - Returns null or currency ID
     */
    protected function getCurrencyId(string $currency) {
        
        $this->info('Currency lookup: ' . $currency);

        if(!$currency) {
            $this->info('Currency string not provided. Returning null.');
            return null;
        }

        $currencyId = (
            isset($this::CURRENCY_IDS[strtolower($currency)])
            ? $this::CURRENCY_IDS[strtolower($currency)]
            : null
        );

        return $currencyId;
    }

    /**
     * Returns a UOM ID based on the UOM string provided.
     * 
     * @param string $currency - The UOM string (ie, 'kwn', 'ccf', etc...)
     * 
     * @return null|int - Returns null or UOM ID
     */
    protected function getUomId(string $uom) {
        
        $this->info('UOM lookup: ' . $uom);

        if(!$uom) {
            $this->info("UOM string not provided. Returning 'unknown' as UOM type.");
            return $this::UOM_IDS['unknown'];
        }

        $uomId = (
            isset($this::UOM_IDS[strtolower($uom)])
            ? $this::UOM_IDS[strtolower($uom)]
            : $this::UOM_IDS['unknown']
        );

        return $uomId;
    }

    /**
     * Result object
     * 
     * @param string $result - 'Success' or 'Error'
     * @param string\null $message - Message, if any (typically for errors)
     * 
     * @return object - The result object
     */
    protected function newResult(string $result, string $message = '') {
        return (object)[
            'result' => $result,
            'message' => $message
        ];
    }

    /**
     * Sends an error notification email.
     * 
     * @param string $message - The notification message
     */
    protected function sendErrorNotification(string $message) {
        // TODO: Implement
        $this->comment('sendErrorNotification :: TODO: Implement');
    }

    /**
     * Find a utility using a sales state, commodity ID, and LDC code.
     * 
     * @param string $salesState  - The sales state
     * @param string $commodityId - The commodity ID
     * @param string $ldcCode     - The LDC code
     * 
     * @return object|null - An object will utility info, or null if a utility is not found
     */
    public function utilityLookup(string $salesState, int $commodityId, string $ldcCode)
    {
        $this->info('Looking up utility for State: ' . $salesState . ', Commodity ID: ' . $commodityId . ', and LDC Code: ' . $ldcCode);

        if ($this->utilityList) {
            foreach ($this->utilityList as $util) {
                if (
                    strtolower($util->state) == strtolower($salesState) &&
                    strtolower($util->brand_ldc_code) == strtolower($ldcCode) &&
                    strtolower($util->utility_fuel_type_id) == strtolower($commodityId)
                ) {

                    $this->info('Found!');

                    return (object)[
                        'id' => $util->usf_id, // The utility_id stored in the rate record is actually the ID from the utility_supported_fuels table
                        'state_name' => $util->state,
                        'utility_name' => $util->utility_name,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Converts a simple array to an object. Takes in two arrays. First array is the data, and second array
     * contains the property names to assign the values to.
     * 
     * @param array $data  - The array to convert
     * @param array $props - An array of property names.
     * 
     * @return object - The resulting object
     */
    protected function arrayToObject(array $data, array $props) {

        $newArray = [];

        // Both arrays must have same number of elements
        if(count($data) != count($props)) {
            return null;
        }

        foreach($data as $key => $value) {
            $newArray[$props[$key]] = $value;
        }

        return (object)$newArray;
    }

    /**
     * Validate command options and set up global vars
     */
    protected function setup() {
        
        $this->jobStartDate = Carbon::now("America/Chicago");
        
        // Validate mode.
        if ($this->option('mode')) {
            $mode = strtolower($this->option('mode'));

            if ($mode == 'live' || $mode == 'test') {
                $this->mode = $mode;
            } else {
                $this->error('Invalid --mode value: ' . $mode);
                exit(-1);
            }
        }

        // Set filenames
        // Default filename: gateway_sc_rates_YYYYMMDD_001.zip/txt
        $fileDate = Carbon::now("America/Chicago")->format("Ymd");
        $fileNum = "001";

        // Override with custom date/file number?
        if($this->option('date')) {
            $fileDate = Carbon::parse($this->option('date'))->format("Ymd");
        }

        if($this->option('file-num')) {
            $fileNum = $this->option('file-num');
        }
        
        // Get the Brand ID        
        $this->info("Retrieved brand ID for '$this->brandName'");
        $this->brandId = getBrandId($this->brandName);

        if(!$this->brandId) {
            $errMsg = "Unable to locate a brand record for '$this->brandName'";
            $this->error($errMsg);
            $this->sendErrorNotification($errMsg);

            exit(-1);
        }

        // Load utilities list
        $this->info("Retrieving utility list...");
        $this->utilityList = getUtilities($this->brandId);

        if(count($this->utilityList) == 0) {
            $errMsg = "Unable to locate utilities for '$this->brandName' ($this->brandId)";
            $this->error($errMsg);
            $this->sendErrorNotification($errMsg);

            exit(-1);
        }

        $this->zipFilename = "gateway_sc_rates_" . $fileDate . "_" . $fileNum . ".zip"; // TODO: Remove _test
        $this->txtFilename = "gateway_sc_rates_" . $fileDate . "_" . $fileNum . ".txt";

        $this->info("");
        $this->info('Mode:         ' . $this->mode);
        $this->info('Brand ID:     ' . $this->brandId);
        $this->info('Work Dir:     ' . $this->workDir);
        $this->info('Zip Filename: ' . $this->zipFilename . "\n");
    }

    /**
     * Unzips a file.
     */
    protected function unzipFile($zipFile, $extractTo) {
        
        $this->info("\nUnzipping file...");

        $zip = new ZipArchive;

        if(!str_ends_with($extractTo, '/')) {
            $extractTo .= '/';
        }

        $res = $zip->open($zipFile);
        if($res === true) {
            $zip->extractTo($extractTo);
            $zip->close();

            $this->info("Successfully unzipped archive '" . $zipFile . "'.");

            return true;
        } else {
            $this->error("Unable to open Zip file '" . $zipFile . "'.");
        }

        return false;
    }

    /**
     * Soft-deletes the SC TM US North rates that were active before this import
     */
    protected function deleteOldRates() {
                
        if($this->option('dry-run')) {
            $this->info('Dry run. Leaving old rates active.');

            return;
        }

        $this->info("Deactivating old products/rates");

        // TODO: Add logic. Need to ignore all DE rates except for AB/SK

        // Find the old rates.
        // Look for rates tagged for commercial customers only
        // $products = Product::where('brand_id', $this->brandId)
        //     ->where()
        //     ->get();

        // foreach ($products as $product) {
        //     Rate::where('product_id', $product->id)->withTrashed()->update(
        //         [
        //             'hidden' => 1,
        //         ]
        //     );
        //     Rate::where('product_id', $product->id)->delete();
        //     VendorRate::where('product_id', $product->id)->delete();

        //     $product->delete();
        // }

        $this->comment("deleteActiveRates :: TODO: Implement");
        $this->comment("REMINDER! DO NOT DELETE AB/SK RATES! TARGET US NORTH ONLY! AND ONLY SC RATES AT THAT!");
    }

    /**
     * Returns a text file's line count
     */
    protected function getLineCount($filePath) {

        $this->info('Counting lines...');

        $file = fopen($filePath, "r");

        $ctr = 0;
        while(fgets($file)) {
            $ctr++;
        }

        fclose($file);

        $this->info($ctr . ' line(s) in file.');

        return $ctr;
    }

    /**
     * Get FTP hostname/credentials
     */
    protected function getFtpInfo() {

        $this->info("Retrieving FTP info...");

        // If test mode, we'll search for the 'DXC Test FTP Site' entry
        if($this->mode == 'test') {
            $serviceTypeId = 35; // DXC Test FTP Site
            $piTypeId = 3; // FTP
        } else { // TODO: Updated values below after creating a PI entry for the FTP site DE will upload files to
            $serviceTypeId = 35; // DXC Test FTP Site
            $piTypeId = 3; // FTP
        }

        // Build the query
        $query = '
            SELECT
                id, hostname, username, password
            FROM provider_integrations
            WHERE ' .
                ($this->mode == 'test' ? 'brand_id IS NULL' : 'brand_id = :brand_id') . '
                AND service_type_id = :service_type_id
                AND provider_integration_type_id = :provider_integration_type_id
                AND deleted_at IS NULL
        ';

        // Build bindings
        $bindings = [];

        if($this->mode != 'test') {
            $bindings = [
                'brand_id' => $this->brandId,
            ];
        }

        $bindings = [            
            'service_type_id' => $serviceTypeId,
            'provider_integration_type_id' => $piTypeId
        ];

        // Run the query
        $info = DB::select(DB::raw($query), $bindings);

        if(count($info) > 0) {
            return $info[0]; // We should only ever get one record back.
        }

        return null;
    }

    /**
     * Downloads the rates file (zip)
     */
    protected function downloadFile($filename) {

        // Retrieve FTP info from DB
        $ftpInfo = $this->getFtpInfo();

        if(!$ftpInfo) {
            $this->error('Error! Unable to continue due to not being able to find FTP info.'); // TODO: finalize, and handle error with some sort of notification.

            exit(-1);
        }

        // TODO: Add additional logic for adapter type, depending on what final FTP site will be configured like.

        $settings = [
            'host' => $ftpInfo->hostname,
            'username' => $ftpInfo->username,
            'password' => $ftpInfo->password,
            'port' => 21,
            'root' => '/',
            'passive' => true,
            'ssl' => false,
            'timeout' => 20,
        ];

        $ftp = new Filesystem(new Ftp($settings));
        $local = new Filesystem(new Local($this->workDir));
            
        $success = false;

        try {
        
            // Check if the file exists on the FTP server
            if(!$ftp->has($filename)) {
                return $this->newResult('Error', 'Unable to locate file ' . $filename . ' on FTP server');
            }

            // Download the file
            $this->info("Downloading file from FTP server");            
            $success = $local->write($filename, $ftp->read($filename));

        } catch(\Exception $e) {
            return $this->newResult('Error', $e->getMessage());
        }

        return ($success ? $this->newResult('Success') : $this->newResult('Error', 'Error downloading file'));
    }
}
