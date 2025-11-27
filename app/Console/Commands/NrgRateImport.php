<?php

namespace App\Console\Commands;

use App\Models\Brand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\VendorRate;
use App\Models\Vendor;
use App\Models\Utility;
use App\Models\Rate;
use App\Models\Product;
use Log;

class NrgRateImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nrg:rate:import {--file=} {--groupname}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Custom Rate Import process for NRG, expected to be v2 generic rate:import';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Lookup Utility.
     *
     * @param string $name      - utility name
     * @param int    $fuel_type - utility type (1 = electric, 2 = gas)
     * @param string $brand_id  - brand id
     *
     * @return array
     */
    public function lookupUtility(string $name, int $fuel_type, string $brand_id)
    {
        $usf = Cache::remember('brand-utility-' . $name . $fuel_type . $brand_id, 30, function () use ($name, $fuel_type, $brand_id) {
            // $brand = Brand::find($brand_id);
            // Log::debug($brand ? $brand->toArray() : "empty brand");

            return  Utility::select(
                'utility_supported_fuels.id',
                'states.name AS state_name'
            )->join(
                'brand_utilities',
                'utilities.id',
                'brand_utilities.utility_id'
            )->leftJoin(
                'utility_supported_fuels',
                'utilities.id',
                'utility_supported_fuels.utility_id'
            )->leftJoin(
                'states',
                'utilities.state_id',
                'states.id'
            )->where(
                'utility_supported_fuels.utility_fuel_type_id',
                $fuel_type
            )->where(
                'utilities.name',
                'LIKE',
                '%' . $name . '%'
            )->where(
                'brand_utilities.brand_id',
                $brand_id
            )->whereNull(
                'utilities.deleted_at'
            )->whereNull(
                'utility_supported_fuels.deleted_at'
            )->whereNull(
                'brand_utilities.deleted_at'
            )->first();
        });
        if ($usf) {
            return [
                'id' => $usf->id,
                'state_name' => $usf->state_name,
            ];
        } else {
            return null;
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('auto_detect_line_endings', true);

        // if (!$this->option('brand') || !$this->option('file')) {
        // Disable checking brand for custom process
        if (!$this->option('file')) {
            // $this->error('Syntax: php artisan rate:import --file=<path to file> --brand=<brand id>');
            $this->error('Syntax: php artisan rate:import --file=<path to file>');

            return 2;
        }

        $rate_file = $this->option('file');

        $brand = Brand::where('name', 'NRG')->whereNotNull('client_id')->first();
        if (!$brand) {
            $this->error("Unable to find Brand entry for NRG");
            return -1;
        }

        $brand_id = $brand->id;

        if (!file_exists($rate_file)) {
            $this->error('The file: "' . $rate_file . '" does not exist.');

            return 3;
        }

        // if (0 === count($array)) {
        //     $this->error('No rates were found in the specified file.');

        //     return 4;
        // }

        $util_not_found = [];
        $vendor_not_found = [];
        $vendor_list = [];
        $headers = [
            'Product/Plan Name',
            'State/Province',
            'Utility',
            'Country',
            'Fuel Type',
            'Market',
            'Channel',
            'Home Type',
            'Program Code',
            'Product Green Percentage',
            'Rate Type',
            'Intro Rate',
            'Intro Rate Currency',
            'Intro Rate UOM',
            'Intro Term',
            'Intro Term Type',
            'Rate Amount',
            'Rate Currency',
            'Rate UOM',
            'Term',
            'Term Type',
            'Service Fee',
            'Service Fee Currency',
            'Service Fee Type',
            'Admin Fee',
            'Daily Fee',
            'Monthly Fee',
            'Cancellation Fee',
            'Cancellation Currency',
            'Cancellation Type',
            'Account Type',
            'Validation',
            'Start Date',
            'End Date',
            'Promo Code',
            'External ID',
            'Vendors Allowed',
            'Rate Allowed Vendors',
            'Status',
            'Source Code',
            'Renewal Plan',
            'Channel Source',
            'Transaction Fee',
            'Transaction Fee Currency',
            'Dual Fuel Only',
            'Synchronized',
            'Prepaid',
            'Time of Use',
            'Time of Use Rates',
            'Rescission',
            'Rescission Calendar Type ID',
            'Custom Data 1',
            'Custom Data 2',
            'Custom Data 3',
            'Custom Data 4',
            'Custom Data 5',
            'English Scripting 1',
            'Spanish Scripting 1',
            'English Scripting 2',
            'Spanish Scripting 2',
            'English Scripting 3',
            'Spanish Scripting 3',
            'English Recission 1',
            'Spanish Recission 2',
            'Brand Name'
        ];

        // specific headers of extra fields for NRG
        $headers_extra = [];

        if (function_exists('mb_detect_encoding')) {
            $encoding_check_file = file_get_contents($rate_file);
            $isUTF8 = mb_detect_encoding($encoding_check_file, 'UTF-8', true);
            if (false === $isUTF8) {
                $output_str = shell_exec('file --mime-encoding ' . $rate_file);
                $output_str_a = explode(':', $output_str);
                if (count($output_str_a) > 1) {
                    if ('unknown-8bit' == trim($output_str_a[1])) {
                        $iconv_out = shell_exec('iconv -f mac -t UTF-8 ' . $rate_file . ' -o ' . $rate_file . 'c');
                        $encoding_check_file = file_get_contents($rate_file . 'c');
                        $isUTF8 = mb_detect_encoding($encoding_check_file, 'UTF-8', true);
                        if ($isUTF8) {
                            unlink($rate_file);
                            $rate_file = $rate_file . 'c';
                        }
                    }
                }
                if (false === $isUTF8) {
                    $this->error('Error: files must be UTF-8 encoded');

                    return 42;
                }
            }
        }

        $expected_fields = count($headers);
        $expected_fields_extra = count($headers_extra);
        $lineNumber = 0;
        $handle = fopen($rate_file, 'r');
        $all_headers = fgetcsv($handle);
        $_headers = [];
        $_headers_extra = [];
        foreach ($all_headers as $key => $value) {
            if ($key < $expected_fields)
                $_headers[trim($value)] = $key;
            else
                $_headers_extra[trim($value)] = $key;
        }

        $headers = $_headers;

        // print_r($headers);
        // exit();

        // [DOUBT/William] Do we have to purge trashed products in rate import process?
        $products = Product::where('brand_id', $brand_id)->get();
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

        while (false !== ($data = fgetcsv($handle))) {
            if (
                isset($data[$headers['Product/Plan Name']])
                && 'Product/Plan Name' === trim($data[$headers['Product/Plan Name']])
            ) {
                continue;
            }

            if (empty($data[$headers['Product/Plan Name']])) {
                continue;
            }

            $lineNumber = ++$lineNumber;

            $utility_name = @trim($data[$headers['Utility']]);
            $commodity = @trim($data[$headers['Fuel Type']]);
            $vendors_allowed = $data[$headers['Vendors Allowed']];
            $vendors_allowed_rate_level = $data[$headers['Rate Allowed Vendors']];

            // print_r($data);
            // echo 'Commodity = ' . $commodity . "\n";
            // exit();

            switch (strtolower($commodity)) {
                case 'electric':
                    $fuel = 1;
                    break;
                case 'natural gas':
                case 'gas':
                    $fuel = 2;
                    break;
                default:
                    $this->error('Fuel Type must be one of electric or gas; (' . ($commodity) . ') given.');

                    return 5;
            }

            $uLookup = $this->lookupUtility($utility_name, $fuel, $brand_id);
            if (null == $uLookup) {
                $com = (1 === $fuel) ? 'Electric' : 'Gas';
                $this->error('Unable to locate Utility: ' . $utility_name . ' for fuel type: ' . $com);

                // return 13;
                continue;
            }

            $utility_id = $uLookup['id'];
            if (null === $utility_id) {
                $utility = trim($utility_name);
                $util_not_found[] = $utility;
            }

            if ('all' != $vendors_allowed) {
                $vendor_list[] = $vendors_allowed;
            }

            $vendors = Cache::remember(
                'vendor_rate_import' . $brand_id,
                60,
                function () use ($brand_id) {
                    return Vendor::select(
                        'vendors.id',
                        'brands.name'
                    )->leftJoin(
                        'brands',
                        'brands.id',
                        'vendors.vendor_id'
                    )->where(
                        'brand_id',
                        $brand_id
                    )->get();
                }
            );

            if (count($util_not_found) > 0) {
                $this->info('Missing utilities found:');
                foreach (array_unique($util_not_found) as $value) {
                    $this->info(' -- ' . $value);
                }

                return 6;
            }

            if (count($vendor_list) > 0) {
                foreach (array_unique($vendor_list) as $key => $value) {
                    $explode = explode('|', trim($value));
                    for ($i = 0; $i < count($explode); ++$i) {
                        $new_vendor_list[] = trim($explode[$i]);
                    }
                }

                $compare_vendors = [];
                foreach ($vendors as $vendor) {
                    $compare_vendors[] = trim($vendor->name);
                }

                $vendor_not_found = array_filter(array_unique($new_vendor_list), function ($item) {
                    return strlen($item) > 0;
                });
                foreach ($vendor_not_found as $key => $value) {
                    if (in_array(trim($value), $compare_vendors)) {
                        unset($vendor_not_found[$key]);
                    }
                }

                if (!empty($vendor_not_found)) {
                    $this->info('Missing vendors found:');
                    foreach ($vendor_not_found as $value) {
                        $this->info(' -- "' . $value . '"');
                    }

                    return 7;
                }
            }

            if ($this->option('verbose')) {
                info('Rate: ' . ($lineNumber) . ' of ' . (count($data) - 1));
            }

            if (count($data) != $expected_fields + $expected_fields_extra) {
                $this->info('Expected ' . ($expected_fields + $expected_fields_extra)
                    . ' fields.  ' . count($data) . ' found.');

                $this->info('Template Headers should be:');
                $this->info(implode(',', $headers));

                return 8;
            }

            switch (strtolower($data[$headers['Fuel Type']])) {
                case 'electric':
                    $fuel = 1;
                    break;
                case 'natural gas':
                case 'gas':
                    $fuel = 2;
                    break;
                default:
                    $this->error('Fuel Type must be one of electric or gas; ('
                        . strtolower($data[$headers['Fuel Type']]) . ') given.');

                    return 9;
            }

            switch (strtolower($data[$headers['Rate Type']])) {
                case 'fixed':
                    $rate_type_id = 1;
                    break;
                case 'variable':
                    $rate_type_id = 2;
                    break;
                case 'tiered':
                    $rate_type_id = 3;
                    break;

                default:
                    $this->info('Rate type should be one of: Fixed,Variable,Tiered');
                    $this->error('Unknown rate type: ' . $data[$headers['Rate Type']]);

                    return 10;
            }

            $term_type_id = 3;
            switch (strtolower($data[$headers['Term Type']])) {
                case 'day':
                case 'days':
                    $term_type_id = 1;
                    break;
                case 'week':
                case 'weeks':
                    $term_type_id = 2;
                    break;
                case 'month':
                case 'months':
                    $term_type_id = 3;
                    break;
                case 'year':
                case 'years':
                    $term_type_id = 4;
                    break;
                default:
                    $term_type_id = 3;
            }

            $intro_term_type_id = null;
            if (isset($data[$headers['Intro Term Type']])) {
                switch (strtolower($data[$headers['Intro Term Type']])) {
                    case 'day':
                    case 'days':
                        $intro_term_type_id = 1;
                        break;
                    case 'week':
                    case 'weeks':
                        $intro_term_type_id = 2;
                        break;
                    case 'year':
                    case 'years':
                        $intro_term_type_id = 4;
                        break;
                    default:
                        $intro_term_type_id = 3;
                        break;
                        // default:
                        // $this->info('Intro Term type should be one of: day, days, week, weeks, month, months, year, years');
                        // $this->error('Unknown term type: ' . $data[$headers['Intro Term Type']]);

                        // return 11;
                }
            }

            $rate_uom = trim($data[$headers['Rate UOM']]);
            if (strlen($rate_uom) === 0) {
                $rate_uom = $data[$headers['Intro Rate UOM']];
            }

            $rate_uom_id = 3;
            switch (strtolower($rate_uom)) {
                case 'therm':
                    $rate_uom_id = 1;
                    break;
                case 'kwh':
                    $rate_uom_id = 2;
                    break;
                case 'ccf':
                    $rate_uom_id = 4;
                    break;
                case 'mcf':
                    $rate_uom_id = 7;
                    break;
                case 'gj':
                    $rate_uom_id = 6;
                    break;
                case 'mcf':
                    $rate_uom_id = 7;
                    break;
                case 'day':
                    $rate_uom_id = 8;
                    break;
                default:
                    $rate_uom_id = 3;
                    break;
            }

            $rate_currency_id = 1;
            switch (strtolower($data[$headers['Rate Currency']])) {
                case 'cents':
                    $rate_currency_id = 1;
                    break;
                case 'dollars':
                    $rate_currency_id = 2;
                    break;
                default:
                    $rate_currency_id = 1;
                    break;
            }

            $daily_fee = (!empty($data[$headers['Daily Fee']]))
                ? $data[$headers['Daily Fee']]
                : null;
            $green_percentage = (!empty($data[$headers['Product Green Percentage']]))
                ? $data[$headers['Product Green Percentage']]
                : null;
            $cancel_fee = (!empty($data[$headers['Cancellation Fee']]))
                ? $data[$headers['Cancellation Fee']]
                : null;
            $term = (!empty($data[$headers['Term']]))
                ? $data[$headers['Term']]
                : null;
            $home_type = (!empty($data[$headers['Home Type']]))
                ? $data[$headers['Home Type']]
                : null;
            $channels = (!empty($data[$headers['Channel']]))
                ? $data[$headers['Channel']]
                : null;
            $service_fee = (!empty($data[$headers['Service Fee']]))
                ? $data[$headers['Service Fee']]
                : null;

            $product = Product::where(
                'name',
                $data[$headers['Product/Plan Name']]
            )->where(
                'brand_id',
                $brand_id
            )->where(
                'green_percentage',
                $green_percentage
            )->where(
                'term',
                $term
            )->where(
                'daily_fee',
                $daily_fee
            )->where(
                'home_type',
                $home_type
            )->where(
                'channel',
                $channels
            )->where(
                'service_fee',
                $service_fee
            );

            if (isset($data[$headers['End Date']]) && strlen(trim($data[$headers['End Date']])) > 0) {
                $product = $product->whereDate(
                    'date_to',
                    Carbon::parse($data[$headers['End Date']])->format('Y-m-d')
                );
            } else {
                $product = $product->whereNull(
                    'date_to'
                );
            }

            $product = $product->orderBy(
                'created_at',
                'desc'
            )->withTrashed()->first();
            if ($product) {
                if ($this->option('verbose')) {
                    $this->info('Found product: ' . $data[$headers['Product/Plan Name']]);
                }

                $product->restore();
            } else {
                if ($this->option('verbose')) {
                    $this->info('Adding product: ' . $data[$headers['Product/Plan Name']]);
                }

                $product = new Product();
                $product->brand_id = $brand_id;
                $product->name = (!empty($data[$headers['Product/Plan Name']]))
                    ? $data[$headers['Product/Plan Name']]
                    : null;
                $product->channel = $channels;
                $product->market = (!empty($data[$headers['Market']]))
                    ? $data[$headers['Market']]
                    : null;
                $product->home_type = $home_type;
                $product->daily_fee = $daily_fee;
                $product->rate_type_id = $rate_type_id;
                $product->green_percentage = $green_percentage;
                $product->term = $term;
                $product->prepaid = 'Yes' == $data[$headers['Prepaid']];
                $product->term_type_id = $term_type_id;
                $product->service_fee = $service_fee;
                $product->transaction_fee = (!empty($data[$headers['Transaction Fee']]))
                    ? $data[$headers['Transaction Fee']]
                    : null;
                $product->transaction_fee_currency_id = ($data[$headers['Transaction Fee']] > 0)
                    ? 1 : null;
                $product->intro_term = (!empty($data[$headers['Intro Term']]))
                    ? $data[$headers['Intro Term']]
                    : null;
                $product->intro_term_type_id = $intro_term_type_id;
                $product->date_from = (isset($data[$headers['Start Date']])
                    && strlen(trim($data[$headers['Start Date']])) > 0)
                    ? Carbon::parse($data[$headers['Start Date']])
                    : Carbon::now();
                $product->date_to = (isset($data[$headers['End Date']])
                    && strlen(trim($data[$headers['End Date']])) > 0)
                    ? Carbon::parse($data[$headers['End Date']])
                    : null;
                $product->save();
            }

            if ('all' == $vendors_allowed) {
                foreach ($vendors as $vendor) {
                    $vr = VendorRate::where(
                        'vendors_id',
                        $vendor->id
                    )->where(
                        'product_id',
                        $product->id
                    )->withTrashed()->first();
                    if ($vr) {
                        $vr->restore();
                    } else {
                        $vr = new VendorRate();
                    }

                    $vr->vendors_id = $vendor->id;
                    $vr->product_id = $product->id;
                    $vr->save();
                }
            } else {
                $explode = explode('|', $vendors_allowed);
                for ($n = 0; $n < count($explode); ++$n) {
                    foreach ($vendors as $vendor) {
                        if (strtolower($vendor->name) == trim(strtolower($explode[$n]))) {
                            $vr = VendorRate::where(
                                'vendors_id',
                                $vendor->id
                            )->where(
                                'product_id',
                                $product->id
                            )->withTrashed()->first();
                            if ($vr) {
                                $vr->restore();
                            } else {
                                $vr = new VendorRate();
                            }

                            $vr->vendors_id = $vendor->id;
                            $vr->product_id = $product->id;
                            $vr->save();
                        }
                    }
                }
            }

            $uLookup = $this->lookupUtility($utility_name, $fuel, $brand_id);
            if (null == $uLookup) {
                $this->error('Unable to locate Utility: ' . $utility_name . ' for fuel type: ' . $fuel);

                // return 13;
                continue;
            }

            $rate = Rate::where(
                'product_id',
                $product->id
            )->where(
                'program_code',
                $data[$headers['Program Code']]
            )->where(
                'utility_id',
                $uLookup['id']
            )->where(
                'rate_currency_id',
                $rate_currency_id
            )->where(
                'rate_uom_id',
                $rate_uom_id
            );

            if (empty($cancel_fee)) {
                $rate = $rate->whereNull(
                    'cancellation_fee'
                );
            } else {
                $rate = $rate->where(
                    'cancellation_fee',
                    $cancel_fee
                );
            }

            if (empty($data[$headers['Admin Fee']])) {
                $rate = $rate->whereNull(
                    'admin_fee'
                );
            } else {
                $rate = $rate->where(
                    'admin_fee',
                    $data[$headers['Admin Fee']]
                );
            }

            if (empty($data[$headers['External ID']])) {
                $rate = $rate->whereNull(
                    'external_rate_id'
                );
            } else {
                $rate = $rate->where(
                    'external_rate_id',
                    $data[$headers['External ID']]
                );
            }

            if (empty($data[$headers['Rate Amount']])) {
                $rate = $rate->whereNull(
                    'rate_amount'
                );
            } else {
                $rate = $rate->where(
                    'rate_amount',
                    $data[$headers['Rate Amount']]
                );
            }

            if (empty($data[$headers['Monthly Fee']])) {
                $rate = $rate->whereNull(
                    'rate_monthly_fee'
                );
            } else {
                $rate = $rate->where(
                    'rate_monthly_fee',
                    $data[$headers['Monthly Fee']]
                );
            }

            if (empty($data[$headers['Intro Rate']])) {
                $rate = $rate->whereNull(
                    'intro_rate_amount'
                );
            } else {
                $rate = $rate->where(
                    'intro_rate_amount',
                    $data[$headers['Intro Rate']]
                );
            }

            if (empty($data[$headers['Brand Name']])) {
                $rate = $rate->whereNull(
                    'brand_name'
                );
            } else {
                $rate = $rate->where(
                    'brand_name',
                    $data[$headers['Brand Name']]
                );
            }

            $rate = $rate->orderBy('created_at', 'desc')->withTrashed()->first();
            if (!$rate) {
                if ($this->option('verbose')) {
                    $this->info('--- Creating new rate: ' . $data[$headers['Program Code']]);
                }
                $rate = new Rate();
            } else {
                if ($this->option('verbose')) {
                    $this->info('--- Updating rate: ' . $data[$headers['Program Code']]);
                }

                $rate->restore();
            }

            $date_from = (!empty($data[$headers['Start Date']])) ? Carbon::parse($data[$headers['Start Date']]) : null;
            $date_to = (!empty($data[$headers['End Date']])) ? Carbon::parse($data[$headers['End Date']]) : null;

            if ($date_from !== null && !$date_from->isValid()) {
                $this->error('The specified Start Date for Program Code ' . $data[$headers['Program Code']] . ' is not valid.');
                return 143;
            }
            if ($date_to !== null && !$date_to->isValid()) {
                $this->error('The specified End Date for Program Code ' . $data[$headers['Program Code']] . ' is not valid.');
                return 144;
            }

            if (
                $date_from !== null
                && $date_to !== null
                && $date_from->isValid()
                && $date_to->isValid()
                && !$date_from->isBefore($date_to)
            ) {
                $this->error('Specified Start Date for Program Code ' . $data[$headers['Program Code']] . ' is not before the end date.');
                return 145;
            }

            $rate->hidden = 0;
            $rate->product_id = $product->id;
            $rate->program_code = $data[$headers['Program Code']];
            $rate->utility_id = $uLookup['id'];
            $rate->rate_currency_id = $rate_currency_id;
            $rate->rate_uom_id = $rate_uom_id;
            $rate->cancellation_fee = (!empty($data[$headers['Cancellation Fee']]))
                ? $data[$headers['Cancellation Fee']]
                : null;
            $rate->admin_fee = (!empty($data[$headers['Admin Fee']]))
                ? $data[$headers['Admin Fee']]
                : null;
            $rate->external_rate_id = (!empty($data[$headers['External ID']]))
                ? $data[$headers['External ID']]
                : null;
            $rate->rate_promo_code = (!empty($data[$headers['Promo Code']]))
                ? $data[$headers['Promo Code']]
                : null;
            $rate->rate_source_code = (!empty($data[$headers['Source Code']]))
                ? $data[$headers['Source Code']]
                : null;
            $rate->rate_renewal_plan = (!empty($data[$headers['Renewal Plan']]))
                ? $data[$headers['Renewal Plan']]
                : null;
            $rate->rate_channel_source = (!empty($data[$headers['Channel Source']]))
                ? $data[$headers['Channel Source']]
                : null;
            $rate->rate_amount = (!empty($data[$headers['Rate Amount']]))
                ? trim($data[$headers['Rate Amount']])
                : null;
            $rate->rate_monthly_fee = (!empty($data[$headers['Monthly Fee']]))
                ? $data[$headers['Monthly Fee']]
                : null;
            $rate->date_from = Carbon::now('America/Chicago');
            $rate->date_to = null;
            $rate->time_of_use = 'Yes' == $data[$headers['Time of Use']];
            $rate->time_of_use_rates = !empty($data[$headers['Time of Use Rates']]) ? $data[$headers['Time of Use Rates']] : null;
            $rate->intro_rate_amount = (!empty($data[$headers['Intro Rate']]))
                ? $data[$headers['Intro Rate']]
                : null;
            $rate->dual_only = (!empty($data[$headers['Dual Fuel Only']]))
                ? $data[$headers['Dual Fuel Only']]
                : 0;
            $rate->custom_data_1 = (!empty($data[$headers['Custom Data 1']]))
                ? $data[$headers['Custom Data 1']]
                : null;
            $rate->custom_data_2 = (!empty($data[$headers['Custom Data 2']]))
                ? $data[$headers['Custom Data 2']]
                : null;
            $rate->custom_data_3 = (!empty($data[$headers['Custom Data 3']]))
                ? $data[$headers['Custom Data 3']]
                : null;
            $rate->custom_data_4 = (!empty($data[$headers['Custom Data 4']]))
                ? $data[$headers['Custom Data 4']]
                : null;
            $rate->custom_data_5 = (!empty($data[$headers['Custom Data 5']]))
                ? $data[$headers['Custom Data 5']]
                : null;

            $rate->brand_name = (!empty($data[$headers['Brand Name']]))
                ? $data[$headers['Brand Name']]
                : null;

            // Build scripting
            $scriptingEng = [];
            $scriptingSpa = [];
            $rescissionEng = [];
            $rescissionSpa = [];

            if (!empty($data[$headers['English Scripting 1']])) {
                $scriptingEng[] = $data[$headers['English Scripting 1']];
            } else {
                $scriptingEng[] = "";
            }

            if (!empty($data[$headers['Spanish Scripting 1']])) {
                $scriptingSpa[] = $data[$headers['Spanish Scripting 1']];
            } else {
                $scriptingSpa[] = "";
            }

            if (!empty($data[$headers['English Scripting 2']])) {
                $scriptingEng[] = $data[$headers['English Scripting 2']];
            } else {
                $scriptingEng[] = "";
            }

            if (!empty($data[$headers['Spanish Scripting 2']])) {
                $scriptingSpa[] = $data[$headers['Spanish Scripting 2']];
            } else {
                $scriptingSpa[] = "";
            }

            if (!empty($data[$headers['English Scripting 3']])) {
                $scriptingEng[] = $data[$headers['English Scripting 3']];
            } else {
                $scriptingEng[] = "";
            }

            if (!empty($data[$headers['Spanish Scripting 3']])) {
                $scriptingSpa[] = $data[$headers['Spanish Scripting 3']];
            } else {
                $scriptingSpa[] = "";
            }


            if (!empty($data[$headers['English Recission']])) {
                $rescissionEng[] = $data[$headers['English Recission']];
            } else {
                $rescissionEng[] = "";
            }

            if (!empty($data[$headers['Spanish Recission']])) {
                $rescissionSpa[] = $data[$headers['Spanish Recission']];
            } else {
                $rescissionSpa[] = "";
            }

            $scripting = [
                "english" => $scriptingEng,
                "spanish" => $scriptingSpa
            ];

            $rescission = [
                "english" => $rescissionEng,
                "spanish" => $rescissionSpa
            ];

            $rate->scripting = json_encode($scripting);
            $rate->rescission = json_encode($rescission);

            // Get extra fields
            // $old_extra_field_data = json_decode($rate->extra_fields, true) ?? [];
            $key_value_pair = [];
            // foreach($old_extra_field_data as $pw) {
            //     if(!$pw && !empty($pw['name']) && !empty($pw['value']))
            //         $key_value_pair[$pw['name']] = $pw['value'];
            // }

            foreach ($headers_extra as $he) {
                // if(!empty($data[$_headers_extra[$he]])) {
                $new_he = strtolower(strToFieldName($he));
                $key_value_pair[$new_he] = $data[$_headers_extra[$he]];
                // }
            }

            $extra_fields = [];
            foreach ($key_value_pair as $key => $value) {
                $extra_fields[] = [
                    'name' => $key,
                    'value' => $value
                ];
            }

            // Log::debug($extra_fields);

            $rate->extra_fields = json_encode($extra_fields);
            $rate->save();

            if ('all' !== $vendors_allowed_rate_level) {
                $explode = explode('|', $vendors_allowed_rate_level);
                for ($n = 0; $n < count($explode); ++$n) {
                    foreach ($vendors as $vendor) {
                        if (strtolower($vendor->name) == trim(strtolower($explode[$n]))) {
                            $vr = VendorRate::where(
                                'vendors_id',
                                $vendor->id
                            )->where(
                                'rate_id',
                                $rate->id
                            )->withTrashed()->first();
                            if ($vr) {
                                $vr->restore();
                            } else {
                                $vr = new VendorRate();
                            }

                            $vr->vendors_id = $vendor->id;
                            $vr->rate_id = $rate->id;
                            $vr->save();
                        }
                    }
                }
            }
        }

        if ($this->option('verbose')) {
            $this->info('Import Complete');
        }
    }
}
