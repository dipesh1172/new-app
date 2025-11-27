<?php

namespace App\Console\Commands\Southstar;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

use Carbon\Carbon;

use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

use App\Models\Product;
use App\Models\ProviderIntegration;
use App\Models\Rate;
use App\Models\Utility;
use App\Models\Vendor;
use App\Models\VendorRate;

class SouthStarRatesImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SouthStar:RatesImport {--dry-run} {--mode=} {--env=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'SouthStar rates file import process';

    /**
     * Job name.
     */
    protected $jobName = "SouthStar - Rates File Import";

    /**
     * The brand ID
     *
     * @var array
     */
    protected $brandId = '4436027c-39dc-48cb-8b7f-4d55b739c09e';

    /**
     * DB environment: 'prod' or 'stage'.
     *
     * @var string
     */
    protected $env = 'prod'; // prod env by default.

    /**
     * Job mode: 'live' or 'test'.
     *
     * @var string
     */
    protected $mode = 'live'; // live by default.

    /**
     * Whether or not to show console messages.
     */
    protected $verbose = false;

    /**
     * Distribution lists.
     */
    protected $distroList = [
        'live' => ['Stacy.Worthy@southstarenergy.com', 'nhill@southstarenergy.com', 'Kimberly.Phinazee@southstarenergy.com','shallber@southstarenergy.com'],
        'test' => ['engineering@tpv.com'],
        'error' => ['engineering@tpv.com']
    ];

    protected $expectedHeader = 'sales_st,utility_nm,ldc_code,promo_code,product,prod_type,rate_code,rate,rate_type,'
        . 'cs_number,unit_measu,term,act_or_pod,act_nm_len,incentive,cn_fee_res,cn_fee_sc,customer_charge';

    /**
     * Will contain imported rates from all files.
     */
    protected $ratesToImport = [];

    /**
     * FTP settings for file system adapter.
     */
    protected $ftpSettings = null;

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
        $this->verbose = $this->option('verbose');

        // Check mode.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));

                if ($this->mode == 'test') {
                    $this->env = 'stage'; // Stage env is default for test mode, but can be overridden.
                }
            } else {
                $this->error('Invalid --mode arg: ' . $this->option('mode'));
                return -1;
            }
        }

        // Check env.
        if ($this->option('env')) {
            if (
                strtolower($this->option('env')) == 'prod' ||
                strtolower($this->option('env')) == 'stage'
            ) {
                $this->env = strtolower($this->option('env'));
            } else {
                $this->error('Invalid --env arg: ' . $this->option('env'));
                return -1;
            }
        }

        // Get FTP settings
        $this->info('Getting FTP settings...');
        $this->ftpSettings = $this->getFtpSettings();

        if(!$this->ftpSettings) {
            $this->error('Unable to retrieve FTP settings. Exiting...');
            exit -1;
        }

        // Find rate files to import
        $root = '/';

        $adapter = new SftpAdapter($this->ftpSettings);

        $this->msg('Connecting to FTP and getting files list...');

        $fs = new Filesystem($adapter);

        $files = $fs->listContents($root);
        $this->msg('Remote Files:');
        $this->msg(print_r($files, true));

        // We're mimicking legacy code, where we can import one or more file at a time.
        $filesToImport = []; // list of rates file to import. Also used to determine which files to move to processed folder when done.
        $filesToMove = [];   // same as $filesToImport but with more info so we can rename and move the files later.

        // Identify rates files
        foreach ($files as $f) {
            if (
                $f['type'] == 'file' &&
                strtolower(substr($f['path'], -17)) == '_rates_import.csv'
            ) {
                $filesToImport[] = $f['path'];
                $filesToMove[] = $f;
            }
        }

        if (count($filesToImport) > 0) {
            $this->msg('Found ' . count($filesToImport) . ' file(s)...');
            $this->msg('  ' . implode("\n  ", $filesToImport));
        } else {
            $this->msg('No files to import. Quitting.');
            return 0;
        }

        // Import rate files and format data
        $this->msg('Processing files...');
        foreach ($filesToImport as $file) {

            $this->msg($file . ':');

            // Get file contents
            $contents = $fs->read($file);

            // Format the data and link all related data.
            $formatResult = $this->processFile($file, $contents);
            if ($formatResult['result'] != 'success') {

                $this->err('  Error: ' . $formatResult['message']);
                $this->err('  Exiting program.');

                $message = "Validation error occurred when importing rates. No rates were imported.\n\n"
                    . "Files to Import:\n"
                    . implode("\n", $filesToImport) . "\n\n"
                    . "Failed file:\n"
                    . $file . "\n\n"
                    . "Error Message: \n"
                    . $formatResult['message'];

                $this->sendEmail($message, $this->distroList['error']);
                return -1;
            }
        }

        $this->msg('Inserting rates...');
        $insertResult = $this->insertRates();
        if ($insertResult['result'] == 'success') { // Send off the normal 'rates imported...' email
            $this->msg('Sending success email...');

            $message = "The following rate file(s) were successfully imported:\n\n"
                . implode("\n", $filesToImport);

            $this->sendEmail($message, $this->distroList[$this->mode]);

            // Move files to processed folder
            $this->msg('Moving imported files...');
            foreach ($filesToMove as $f) {
                $newPath = '/rates_imported/' . $f['filename'] . '_completed_' . Carbon::now('America/Chicago')->format('Ymd_his') . '.' .  $f['extension'];
                $fs->rename($f['path'], $newPath);
            }
        } else { // Send an internal error email
            $this->err('Error importing rates: ' . $insertResult['message']);

            $message = "Error occurred when inserting rates to SQL. Rates may have been partially imported.\n\n"
                . "Files to Import:\n"
                . implode("\n", $filesToImport) . "\n\n"
                . "Failed file:\n"
                . $file . "\n\n"
                . "Error Message: \n"
                . $insertResult['message'];

            $this->sendEmail($message, $this->distroList['error']);
        }
    }

    /**
     * Result object.
     */
    private function newResult($result = '', $message = '', $data = null)
    {
        return [
            'result' => $result,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Display's a console message, but only if in verbose mode.
     */
    public function msg($str)
    {
        if ($this->verbose) {
            $this->info($str);
        }
    }

    /**
     * Display's a console error message, but only if in verbose mode.
     */
    public function err($str)
    {
        if ($this->verbose) {
            $this->error($str);
        }
    }

    /**
     * Checks if a program code exists in a rates array.
     */
    public function findProgramCode($programCode, $rateAmt)
    {
        if (!$programCode || !$rateAmt) {
            return false;
        }

        foreach ($this->ratesToImport as $rate) {
            if (
                strtoupper(trim($rate['rate_code'])) == strtoupper(trim($programCode)) &&
                number_format(trim($rate['rate']), 4) == number_format(trim($rateAmt), 4)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inserts the imported rates to DB.
     */
    public function insertRates()
    {
        $importCounter = 0;

        $this->msg('  Getting utility list...');
        $utilityList = $this->getUtilities();

        // Delete existing products and rates for this brand
        if (!$this->option('dry-run')) {
            $this->msg('Deleting existing SouthStar products...');

            $products = Product::where('brand_id', $this->brandId)->get();

            foreach ($products as $product) {
                Rate::where('product_id', $product->id)->withTrashed()->update(
                    [
                        'hidden' => 1,
                    ]
                );
                Rate::where('product_id', $product->id)->delete();
                VendorRate::where('product_id', $product->id)->delete();

                $product->delete();
            }
        } else {
            $this->msg('Dry run. Skipping product deletion...');
        }

        foreach ($this->ratesToImport as $rateRec) {

            $importCounter = ++$importCounter;

            $this->msg($importCounter . ' of ' . count($this->ratesToImport));

            // Utility Lookup
            $ldcCode = $rateRec['ldc_code'];
            $commodity = (strtolower($rateRec['uom']) == 'kwh' ? 1 : 2);
            $commodityStr = (strtolower($rateRec['uom']) == 'kwh' ? 'Electric' : 'Gas');

            $this->msg('  Rate Code: ' . $rateRec['rate_code'] . ', Rate: ' . number_format($rateRec['rate'], 4) . ', State: ' . $rateRec['sales_state'] . ', LDC Code: ' . $ldcCode . ', Fuel Type: ' . $commodityStr);

            $vendorsAllowed = 'all';

            $this->msg('  Utility ID lookup...');
            $uLookup = $this->lookupUtility($utilityList, $rateRec['sales_state'], $ldcCode, $commodity);

            if ($uLookup == null) {
                return $this->newResult(
                    'error',
                    'Unable to determine utility: '
                        . 'Rate Code: ' . $rateRec['rate_code']
                        . ', Rate: ' . number_format($rateRec['rate'], 4)
                        . ', State: ' . $rateRec['sales_state']
                        . ', LDC code: ' . $ldcCode
                        . ', Fuel Type: ' . $commodityStr
                );
            }

            // Cache vendors list
            if ('all' != $vendorsAllowed) {
                $vendorList[] = $vendorsAllowed;
            }

            $vendors = Cache::remember(
                'vendor_rate_import' . $this->brandId,
                60,
                function () {
                    return Vendor::select(
                        'vendors.id',
                        'brands.name'
                    )->leftJoin(
                        'brands',
                        'brands.id',
                        'vendors.vendor_id'
                    )->where(
                        'brand_id',
                        $this->brandId
                    )->get();
                }
            );

            // Determine Rate Type
            $this->msg('  Determine rate type...');
            switch (strtolower($rateRec['rate_type'])) {
                case 'fixed':
                    $rateTypeId = 1;
                    break;
                case 'variable':
                    $rateTypeId = 2;
                    break;

                default:
                    $this->err('   Rate type should be "Fixed" or "Variable"');
                    $this->err('   Unknown rate type: ' . $rateRec['rate_type']);

                    return $this->newResult('error', 'Unknown rate type: ' . $rateRec['rate_type']);
            }

            // Parse term and term type
            $term = (strtolower(trim($rateRec['term'])) == 'month to month'
                ? null
                : explode(' ', $rateRec['term'])[0]);

            if ($term) {
                switch (strtolower(explode(' ', $rateRec['term'])[1])) {
                    case 'months':
                        $termTypeId = 3;
                        break;

                    case 'years':
                    case 'year':
                        $termTypeId = 2;
                        break;

                    default:
                        $this->err('  Unrecongized term type: ' . explode(' ', $rateRec['term'])[1]);

                        return $this->newResult('error', 'Unrecongized term type: ' . explode(' ', $rateRec['term'])[1]);
                }
            } else { // Month to Month term type
                $termTypeId = null;
            }

            // Determine UOM            
            $this->msg('  Determine UOM...');

            switch (strtolower($rateRec['uom'])) {
                case 'kwh':
                    $rateUomId = 2;
                    break;

                case 'ccf':
                    $rateUomId = 4;
                    break;

                case 'mcf':
                    $rateUomId = 7;
                    break;

                case 'therm':
                    $rateUomId = 1;
                    break;

                default:
                    $this->err('  Unrecognized UOM: ' . $rateRec['rate_measu']);

                    return $this->newResult('error', 'Unrecognized UOM: ' . $rateRec['rate_measu']);
            }

            $rateCurrencyId = 2; // Dollars

            // Parse out cancellation fee(s)
            $cancelFee1 = (strtolower($rateRec['product_type']) == 'residential'
                ? strtolower(trim($rateRec['cancel_fee_res']))
                : strtolower(trim($rateRec['cancel_fee_sc'])));

            if ($cancelFee1 == 'na' || empty($cancelFee1)) {
                $cancelFee1 = null;
            }

            $cancelFee2 = null;

            // Check for second cancellation fee and rearrange values, if needed
            if ($cancelFee1) {

                // Remove spaces and dollar signs from middle of string
                $cancelFee1 = implode('', explode(' ', $cancelFee1));
                $cancelFee1 = implode('', explode('$', $cancelFee1));

                if (strpos($cancelFee1, ',')) { // Two cancellation fees
                    $cancelFee2 = explode(',', $cancelFee1)[1];
                    $cancelFee1 = explode(',', $cancelFee1)[0];
                }
            }

            // Tag off-shoot product types for verbiage routing logic
            $promoCode = null;

            if (strpos(strtolower($rateRec['product']), 'variable')) {
                if (strpos(strtolower($rateRec['product']), 'standard variable')) {
                    $promoCode = 'STANDARD';
                } elseif (strpos(strtolower($rateRec['product']), 'variable with introductory discount')) {
                    $promoCode = 'INTRODISCOUNT';
                } elseif (strpos(strtolower($rateRec['product']), 'discounted variable')) {
                    $promoCode = 'DISCOUNTED';
                } elseif (strpos(strtolower($rateRec['product']), 'greener life')) {
                    $promoCode = 'GREENERLIFE';
                }
            }

            if (strpos(strtolower($rateRec['product']), 'fixed')) {
                if (strpos(strtolower($rateRec['product']), 'greener life')) {
                    $promoCode = 'GREENERLIFE';
                } elseif (strpos(strtolower($rateRec['product']), 'supplier customer charge')) {
                    $promoCode = 'SUPPLIERCHARGE';
                }
            }

            // Check if product exists
            $this->msg('  Product lookup: ' . $rateRec['product']);
            $product = Product::where(
                'name',
                trim($rateRec['product'])
            )->where(
                'brand_id',
                $this->brandId
            )->orderBy('created_at', 'desc')->withTrashed()->first();

            // If it exists use it, else create it.
            if ($product && !$this->option('dry-run')) { // For dry runs, we always want to run the else case, to test field mappings
                $this->msg('    Found. Using existing record...');
                $product->restore();
            } else {
                $this->msg('    Not found. Creating...');

                $product = new Product();
                $product->brand_id = $this->brandId;
                $product->name = $rateRec['product'];
                $product->channel = 'DTD|TM'; // Open up to all channels. Restrctions management will handle which vendors can sell via which channel.
                $product->market = (strtolower($rateRec['product_type']) == 'residential' ? 'Residential' : 'Commercial');
                $product->home_type = 'N/A'; // SouthStar doesn't deal with home types
                $product->rate_type_id = $rateTypeId;
                $product->green_percentage = (strpos(strtolower($rateRec['product']), 'greener life') ? '100' : '');
                $product->term = $term;
                $product->term_type_id = $termTypeId;
                $product->service_fee = null;
                $product->transaction_fee = null;
                $product->transaction_fee_currency_id = null;
                $product->intro_term = null;
                $product->intro_term_type_id = null;
                $product->date_from = null;
                $product->date_to = null;

                if (!$this->option('dry-run')) {
                    $this->msg('    Saving product...');
                    $product->save();
                } else {
                    $this->msg('    Dry run. Product not saved');
                }
            }

            // SouthStar program codes are not unique. Concat with price to make it unique.
            // We'll stored their original code in the external_rate_id field for reference.
            $programCode = trim($rateRec['rate_code']) . ' - ' . number_format(trim($rateRec['rate']), 4);

            // Search for existing rate with same info
            $this->msg('  Rate lookup: ' . $rateRec['rate_code'] . ' - ' . number_format($rateRec['rate'], 4));
            $rate = null;

            $rate = Rate::where(
                'product_id',
                $product->id
            )->where(
                'program_code',
                $programCode
            )->where(
                'utility_id',
                $uLookup['id']
            )->where(
                'rate_currency_id',
                $rateCurrencyId
            )->where(
                'rate_uom_id',
                $rateUomId
            )->where(
                'cancellation_fee',
                ''
            )->where(
                'admin_fee',
                ''
            )->where(
                'external_rate_id',
                ''
            )->where(
                'rate_amount',
                number_format(trim($rateRec['rate']), 4)
            )->where(
                'rate_monthly_fee',
                ''
            )->where(
                'intro_rate_amount',
                ''
            )->orderBy('created_at', 'desc')->withTrashed()->first();

            if (!$rate || $this->option('dry-run')) { // For dry runs, we always want to create a new record, to test field mapping
                $this->msg('    Not found. Creating new record... ');
                $rate = new Rate();
            } else {
                $this->msg('    Found. Updating existing record... ');

                $rate->restore();
            }

            $rate->hidden = 0;
            $rate->product_id = $product->id;
            $rate->program_code = $programCode;
            $rate->utility_id = $uLookup['id'];
            $rate->rate_currency_id = $rateCurrencyId;
            $rate->rate_uom_id = $rateUomId;

            if ($cancelFee1) {
                $rate->cancellation_fee = $cancelFee1;
                $rate->custom_data_1 = $cancelFee2;
            } else {
                $rate->cancellation_fee = null;
                $rate->custom_data_1 = null;
            }

            $rate->admin_fee = null;
            $rate->external_rate_id = $rateRec['rate_code'];
            $rate->rate_promo_code = $promoCode;
            $rate->rate_source_code = null;
            $rate->rate_renewal_plan = null;
            $rate->rate_channel_source = null;
            $rate->intro_rate_amount = null;
            $rate->rate_amount = (!empty($rateRec['rate']))
                ? number_format(trim($rateRec['rate']), 4)
                : null;
            $rate->rate_monthly_fee = $rateRec['customer_charge'];

            $rate->date_from = Carbon::now('America/Chicago');
            $rate->date_to = null;
            $rate->dual_only = 0;
            $rate->custom_data_2 = null;
            $rate->custom_data_3 = null;
            $rate->custom_data_4 = null;
            $rate->custom_data_5 = null;

            $rate->scripting = null;

            if (!$this->option('dry-run')) {
                $this->msg('    Saving rate...');
                $rate->save();
            } else {
                $this->msg('    Dry run. Rate not saved');
            }
        }

        $this->msg('Import complete');

        return $this->newResult('success');
    }

    /**
     * Formats imported data and preps it for DB insert.
     */
    public function processFile($fileName, $contents)
    {
        // Convert file contents to CSV
        $this->msg('  Converting to CSV...');
        $lines = explode(PHP_EOL, $contents);
        $csv = [];
        foreach ($lines as $line) {
            if (strlen(trim($line)) > 0) { // Ignore empty lines
                $csv[] = str_getcsv($line);
            }
        }

        // Validate file layout, based on header row
        $this->msg('  Validating file layout...');
        $fileHeader = strtolower(implode(",", $csv[0]));

        if (strtolower($this->expectedHeader) != $fileHeader) {
            return $this->newResult(
                'error',
                "  Unexpected file layout.\n"
                    . "  Expected header:\n    " . strtolower($this->expectedHeader) . "\n"
                    . "  Found:\n    " . $fileHeader
            );
        }

        // remove header row
        unset($csv[0]);
        $this->msg('  Records: ' . count($csv));

        // Format the data
        $h = $this->flipKeysAndValues(explode(",", $this->expectedHeader));

        $record = 1;
        foreach ($csv as $c) {
            $row = [
                'filename' => $fileName,
                'result' => '',
                'sales_state' => $c[$h['sales_st']],
                'utility_name' => $c[$h['utility_nm']],
                'ldc_code' => $c[$h['ldc_code']],
                'promo_code' => $c[$h['promo_code']],
                'product' => $c[$h['product']],
                'product_type' => $c[$h['prod_type']],
                'rate' => $c[$h['rate']],
                'rate_code' => $c[$h['rate_code']],
                'rate_type' => $c[$h['rate_type']],
                'cs_number' => $c[$h['cs_number']],
                'uom' => $c[$h['unit_measu']],
                'term' => $c[$h['term']],
                'account_label' => $c[$h['act_or_pod']],
                'account_length' => $c[$h['act_nm_len']],
                'incentive' => $c[$h['incentive']],
                'cancel_fee_res' => $c[$h['cn_fee_res']],
                'cancel_fee_sc' => $c[$h['cn_fee_sc']],
                'customer_charge' => $c[$h['customer_charge']]
            ];

            $this->ratesToImport[] = $row;
            $record++;
        }

        return $this->newResult('success');
    }


    /**
     * Retrieve utility list.
     *
     * @return array
     */
    public function getUtilities()
    {
        $usf = Utility::select(
            'utilities.name AS utility_name',
            'brand_utilities.utility_label',
            'states.state_abbrev AS state',
            'utility_supported_fuels.utility_fuel_type_id',
            'utility_supported_fuels.id'
        )->leftJoin(
            'utility_supported_fuels',
            'utilities.id',
            'utility_supported_fuels.utility_id'
        )->leftJoin(
            'brand_utilities',
            'utilities.id',
            'brand_utilities.utility_id'
        )->leftJoin(
            'states',
            'utilities.state_id',
            'states.id'
        )->where(
            'brand_utilities.brand_id',
            $this->brandId
            // )->where(
            //     'states.state_abbrev',
            //     $salesState
            // )->where(
            //     'brand_utilities.utility_label',
            //     $ldcCode
            // )->where(
            //     'utility_supported_fuels.utility_fuel_type_id',
            //     $fuel_type
        )->whereNull(
            'utility_supported_fuels.deleted_at'
        )->whereNull(
            'utilities.deleted_at'
        )->whereNull(
            'brand_utilities.deleted_at'
        )->get();

        if (!$usf) {
            return null;
        }

        return $usf;
    }

    /**
     * Lookup Utility.
     */
    public function lookupUtility($utilities, $salesState, $ldcCode, $fuel_type)
    {
        if (!$utilities) {
            return null;
        }

        foreach ($utilities as $util) {
            if (
                strtolower($util->state) == strtolower($salesState) &&
                strtolower($util->utility_label) == strtolower($ldcCode) &&
                strtolower($util->utility_fuel_type_id) == strtolower($fuel_type)
            ) {
                return [
                    'id' => $util->id,
                    'state_name' => $util->state,
                    'utility_name' => $util->utility_name,
                ];
            }
        }

        return null;
    }


    /**
     * Key's become values and values become keys. It's assumed that the values are unique.
     *
     * @return mixed
     */
    private function flipKeysAndValues($inputArray)
    {
        $tempArray = [];

        foreach ($inputArray as $key => $value) {
            $tempArray[trim($value)] = $key;
        }

        return $tempArray;
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
    public function sendEmail(string $message, array $distro, array $files = array())
    {
        $uploadStatus = [];
        $email_address = $distro;

        // Build email subject
        if ('production' != env('APP_ENV')) {
            $subject = $this->jobName . ' (' . env('APP_ENV') . ') '
                . Carbon::now();
        } else {
            $subject = $this->jobName . ' ' . Carbon::now();
        }

        $data = [
            'subject' => '',
            'content' => $message
        ];

        for ($i = 0; $i < count($email_address); ++$i) {
            $status = 'Email to ' . $email_address[$i]
                . ' at ' . Carbon::now() . '. Status: ';

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

            $status .= 'success!';
            $uploadStatus[] = $status;
        }

        return $uploadStatus;
    }

    /**
     * Retrieve FTP settings from provider_integrations table
     */
    private function getFtpSettings() {

        $pi = ProviderIntegration::select(
            'username',
            'password',
            'hostname'
        )
        ->where('brand_id', $this->brandId)
        ->where('service_type_id', 37) // Southstar TPV Prod SFTP
        ->where('provider_integration_type_id', 1) // SFTP
        ->where('env_id', (config('app.env') === 'production' ? 1 : 2))
        ->first();

        if(!$pi) {
            return null;
        }

        $settings = [
            'host' => $pi->hostname,
            'username' => $pi->username,
            'password' => $pi->password,
            'port' => 22,
            'root' => '/incoming/rate_files/',
            'timeout' => 30
        ];

        return $settings;
    }        
}
