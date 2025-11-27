<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Client as HttpClient;
use Exception;
use Carbon\Carbon;
use App\Models\VendorRate;
use App\Models\Vendor;
use App\Models\UtilitySupportedFuel;
use App\Models\Utility;
use App\Models\State;
use App\Models\Rate;
use App\Models\ProviderIntegration;
use App\Models\Product;
use App\Models\BrandUtility;
use App\Models\Brand;

class IndraRateSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate:sync:indra
        {--dryrun : Perform all actions except writing to the database}
        {--output-file= : Write Rate JSON to the specified file}
        {--output-processed-file= : Write Processed Rate JSON to this file}
        {--input-file= : Use the Rate JSON in this file instead of remote API}
        {--dump-only : Retrieve Rate JSON only and exit, print to console if file not specified }
        {--dump-processed-only : Process Rate JSON only and exit, print to console if file not specified }
        {--force-production : Use the Production API}
        {--force-dev : Use the Development API}
        {--dataToTable : Raw data to table view}
        {--limit= : Limit how many records are processed}
        {--no-email : Prevent email notifications}
        {--email-to= : Override any distro lists with what is provided here}
        ';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Indra Energy - Rate Sync';

    private $prodURI = 'https://api.columbiautilities.com/api/rates/validrates';
    private $prodForced = false;

    private $devURI = 'https://apidev.columbiautilities.com/api/rates/validrates';
    private $devForced = false;

    private $dryRun = false;
    private $outputFile = null;
    private $outputProcessedFile = null;
    private $inputFile = null;
    private $dumpOnly = false;
    private $dumpProcessed = false;
    private $beVerbose = false;
    private $indra = null;
    private $columbia = null;

    private $productCount = 0;
    private $rateCount = 0; // Rates processed for current product in loop.
    private $totalRateCount = 0; // Total rates processed across all products.
    private $recordLimit = 0;

    private $distro = ["rbennett@indraenergy.com", "pconsomer@indraenergy.com", "jmora@indraenergy.com", "autumn@tpv.com", "dxc_autoemails@dxc-inc.com"];
    private $errorDistro = ["dxc_autoemails@dxc-inc.com"];
    private $exceptionsFilename = "";
    private $skippedRates = [];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes Indras Rates with their API';

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
        $this->dryRun = $this->option('dryrun');
        $this->beVerbose = $this->option('verbose');
        $this->prodForced = $this->option('force-production');
        $this->devForced = $this->option('force-dev');
        $this->dumpOnly = $this->option('dump-only');
        $this->dumpProcessed = $this->option('dump-processed-only');

        $this->indra = Brand::where('name', 'Indra Energy')->whereNotNull('client_id')->first();
        $this->columbia = Brand::where('name', 'Columbia Utilities')->whereNotNull('client_id')->first();

        $this->recordLimit = ($this->option('limit') ? $this->option('limit') : 0);

        $this->exceptionsFilename = "rate_import_exceptions_" . Carbon::now("America/Chicago")->format("YmdHis") . ".csv";

        if ($this->option('email-to')) {
            $this->distro = []; // Clear default distro
            $this->distro[] = $this->option('email-to');
        }

        if (empty($this->indra)) {
            $this->error('Unable to locate Indra brand entry');

            if (!$this->option('no-email')) {
                $this->sendEmail(
                    'Unable to locate Indra brand entry. Rate sync not completed.',
                    $this->errorDistro
                );
            }

            return 45;
        }

        if (empty($this->columbia)) {
            $this->error('Unable to locate Columbia brand entry');

            if (!$this->option('no-email')) {
                $this->sendEmail(
                    'Unable to locate Columbia brand entry. Rate sync not completed.',
                    $this->errorDistro
                );
            }

            return 45;
        }

        if ($this->prodForced && $this->devForced) {
            $this->error('--force-dev and --force-production may only be used one at a time');

            if (!$this->option('no-email')) {
                $this->sendEmail(
                    '--force-dev and --force-production args may only be used one at a time. Rate sync not completed.',
                    $this->errorDistro
                );
            }

            return 42;
        }
        if ($this->hasOption('output-file')) {
            $this->outputFile = $this->option('output-file');
        }
        if ($this->hasOption('output-processed-file')) {
            $this->outputProcessedFile = $this->option('output-processed-file');
        }

        if ($this->hasOption('input-file')) {
            $this->inputFile = $this->option('input-file');
        }

        $rateDataToSync = $this->makeRequest();
        if (empty($rateDataToSync)) {
            $this->error('Received Empty Rate Data');

            if (!$this->option('no-email')) {
                $this->sendEmail(
                    'Received empty rate data from API. Rate sync not completed.',
                    $this->distro
                );
            }

            return 43;
        }

        if ($this->option('dataToTable')) {
            $headers = [
                'State',
                'Product Type',
                'Product',
                'Utility',
                'Fuel Type',
                'Market',
                'Channel',
                'Home Type',
                'Program Code',
                'Green Percentage',
                'Term',
                'Intro Term',
                'Rate UOM',
                'Custom Data 1',
                'Custom Data 2',
                'Custom Data 3',
                'Custom Data 4',
                'Custom Data 5',
            ];

            $results = [];

            $count = 0;

            foreach ($rateDataToSync as $data) {
                $count++;

                if ($this->recordLimit > 0 && $count > $this->recordLimit) {
                    break;
                }

                $product_type = null;
                if ($data['RateType'] === 'TIERED') {
                    if (!empty($data['SecondaryTerm'])) {
                        $product_type = 'Tiered - Fixed';
                    } else {
                        $product_type = 'Tiered - Variable';
                    }
                } else {
                    $product_type = 'Fixed';
                }

                $markets = implode('|', $data['CustomerType']);
                $channels = implode('|', $data['SalesChannels']);
                $home_types = implode('|', $data['HomeTypes']);

                $term = null;
                $intro_term = null;
                if ($data['RateType'] === 'TIERED') {
                    if (!empty($data['SecondaryTerm'])) {
                        // tiered (fixed)
                        $term = $data['Term'] + $data['SecondaryTerm'];
                        $intro_term = $data['Term'];
                    } else {
                        // tiered (variable)
                        $term = 0;
                        $intro_term = $data['Term'];
                    }
                } else {
                    // fixed
                    $term = $data['Term'];
                    $intro_term = 0;
                }

                $second_month_tiered_price = (isset($data['SecondMonthTieredPrice']))
                    ? $data['SecondMonthTieredPrice']
                    : null;
                $ptc_start_date = (isset($data['PTCStartDate']))
                    ? $data['PTCStartDate']
                    : null;
                $ptc_value = (isset($data['PTCValueDesc']))
                    ? $data['PTCValueDesc']
                    : null;
                $ptc_end_date = (isset($data['PTCEndDate']))
                    ? $data['PTCEndDate']
                    : null;
                $total_term = (!empty($data['SecondaryTerm']))
                    ? $data['SecondaryTerm'] + (!empty($data['Term']) ? $data['Term'] : 0)
                    : null;

                $rate_uom = (!empty($data['RateUnit']))
                    ? $data['RateUnit']
                    : null;

                $custom_data_1 = ($data['UtilityState'] === 'IL')
                    ? $ptc_start_date
                    : $second_month_tiered_price;
                $custom_data_2 = (isset($data['SecondaryTerm']))
                    ? $data['SecondaryTerm']
                    : null;
                $custom_data_3 = ($data['UtilityState'] === 'IL')
                    ? $ptc_value
                    : null;
                $custom_data_4 = ($data['UtilityState'] === 'IL')
                    ? $ptc_end_date
                    : null;
                $custom_data_5 = ($data['UtilityState'] === 'IL')
                    ? $total_term
                    : null;

                $results[] = [
                    $data['UtilityState'],
                    $product_type,
                    $data['DisplayName'],
                    $data['UtilityName'],
                    ($data['CommodityType'] === 'G')
                        ? 'Gas'
                        : 'Electric',
                    $markets,
                    $channels,
                    $home_types,
                    $data['ProgramCode'],
                    $data['GreenPercentage'],
                    $term,
                    $intro_term,
                    $rate_uom,
                    $custom_data_1,
                    $custom_data_2,
                    $custom_data_3,
                    $custom_data_4,
                    $custom_data_5,
                ];
            }

            $this->table($headers, $results);
            exit();
        }

        if ($this->outputFile != null) {
            $didIt = file_put_contents($this->outputFile, json_encode($rateDataToSync));
            if ($didIt === false) {
                $this->error('Could not open file for writing: ' . $this->outputFile);
                return 44;
            } else {
                if ($this->beVerbose) {
                    $this->info('Rate Data written to ' . $this->outputFile);
                }
            }
        }

        if ($this->dumpOnly) {
            if ($this->outputFile != null) {
                return 1;
            }

            $this->line(json_encode($rateDataToSync));

            return 1;
        }

        $processedProducts = $this->consolidateProductsAndRates($rateDataToSync);

        if ($this->outputProcessedFile != null) {
            $didIt = file_put_contents($this->outputProcessedFile, json_encode($processedProducts));
            if ($didIt === false) {
                $this->error('Could not open file for writing: ' . $this->outputProcessedFile);
                return 44;
            } else {
                if ($this->beVerbose) {
                    $this->info('Processed Rate Data written to ' . $this->outputProcessedFile);
                }
            }
        }

        if ($this->dumpProcessed) {
            if ($this->outputFile == null) {
                $this->line(json_encode($processedProducts));
                return 1;
            }

            return 1;
        }

        $this->currentProduct = null;
        try {
            DB::transaction(function () use ($processedProducts) {
                $this->doClearExistingProducts();

                $count = 0;
                foreach ($processedProducts as $uniqIdent => $product) {
                    $count++;

                    if ($this->recordLimit > 0 && $count > $this->recordLimit) {
                        break;
                    }

                    $this->currentProduct = $product;
                    $this->createProduct($product);
                }

                if ($this->beVerbose) {
                    $this->info('Synced ' . $this->productCount . ' products');
                }

                if ($this->dryRun) {
                    throw new Exception('Dry Run Only');
                }
            });
        } catch (Exception $e) {
            info('Error during import', ['product' => $this->currentProduct, 'exception' => $e]);
            $msg = $e->getMessage();
            if ($msg !== 'Dry Run Only') {
                $this->error($msg);
                return 99;
            }
            $this->line('');
            $this->warn('Dry Run Enabled, no changes synced to database');
        }

        // Exceptions found? Create exceptions report.
        if (!empty($this->skippedRates)) {
            if ($this->beVerbose) {
                $this->info("Exceptions found. Creating exceptions file.");
            }

            // EXPORT RECORDS
            $header = array_keys($this->skippedRates[0]);

            $file = fopen(public_path("tmp/") . $this->exceptionsFilename, "w");

            // Write header
            fputcsv($file, $header);

            // Write data
            foreach ($this->skippedRates as $rate) {
                fputcsv($file, $rate);
            }

            fclose($file);
        }

        // Send results email
        if ($this->beVerbose) {
            $this->info("Sending results email");
        }

        if (!$this->option('no-email')) {
            if (empty($this->skippedRates)) { // "Success" email

                $this->sendEmail(
                    'Rates synced successfully.<br/>'
                        . 'Products processed: ' . $this->productCount . '<br/>'
                        . 'Rates processed: ' . $this->totalRateCount,
                    $this->distro
                );
            } else { // "Error" email

                $this->sendEmail(
                    'Rate synced completed. Not all rates were imported. Skipped rate records attached.<br/>'
                        . 'Products processed: ' . $this->productCount . '<br/>'
                        . 'Rates processed: ' . $this->totalRateCount,
                    $this->distro,
                    [public_path("tmp/") . $this->exceptionsFilename]
                );

                unlink(public_path("tmp/") . $this->exceptionsFilename);
            }
        }
    }

    private function createProduct(array $product)
    {
        if ($this->beVerbose) {
            $this->info('In createProduct with state = ' . $product['UtilityState']);
        }

        $brand_id = ($product['UtilityState'] === 'NY')
            ? $this->columbia->id
            : $this->indra->id;

        $p = Product::where('name', $product['DisplayName'])
            ->where('brand_id', $brand_id)
            ->where('channel', $product['Channels'])
            ->where('market', $product['Market'])
            ->where('home_type', $product['HomeTypes'])
            ->where('rate_type_id', $product['RateType'] === 'TIERED' ? 3 : 1)
            ->where('green_percentage', $product['GreenPercentage']);

        if ($product['RateType'] === 'TIERED') {
            if (!empty($product['SecondaryTerm'])) {
                // tiered (fixed)
                $p = $p->where(
                    'term',
                    ($product['SecondaryTerm'] + $product['Term']) // For tiered Fixed, term field should contain total term length.
                )->where(
                    'intro_term',
                    $product['Term']
                );
            } else {
                // tiered (variable)
                $p = $p->whereNull(
                    'term'
                )->where(
                    'intro_term',
                    $product['Term']
                );
            }
        } else {
            // fixed
            $p = $p->whereNull(
                'intro_term'
            )->where(
                'term',
                $product['Term']
            );
        }

        $p = $p->withTrashed()
            ->orderBy('updated_at', 'desc')
            ->first();

        if (empty($p)) {
            if ($this->beVerbose) {
                $this->info('Creating new product: ' . $product['DisplayName']);
            }

            $p = new Product();
            $p->name = $product['DisplayName'];
            $p->brand_id = $brand_id;
            $p->channel = $product['Channels'];
            $p->market = $product['Market'];
            $p->home_type = $product['HomeTypes'];

            switch ($product['RateType']) {
                case 'TIERED':
                    $p->rate_type_id = 3;
                    break;

                case 'VARIABLE':
                    $p->rate_type_id = 2;
                    break;

                default:
                    $p->rate_type_id = 1;
            }

            $p->green_percentage = $product['GreenPercentage'];
            $p->term_type_id = 3; // Always 'Months'
            $p->intro_term_type_id = 3; // Always 'Months'

            if ($product['RateType'] === 'TIERED') {
                if (!empty($product['SecondaryTerm'])) {
                    // tiered (fixed)
                    $p->term = ($product['SecondaryTerm'] + $product['Term']);
                    $p->intro_term = $product['Term'];
                } else {
                    // tiered (variable)
                    $p->term = null;
                    $p->intro_term = $product['Term'];
                }
            } else {
                // fixed
                $p->intro_term = null;
                $p->term = $product['Term'];
            }

            $p->save();
        } else {
            if ($this->beVerbose) {
                $this->info('Re-using product: ' . $product['DisplayName'] . ' ' . $p->id);
            }

            if (!empty($product['Rates'])) {
                $p->restore();
            }
        }

        $p->date_from = Carbon::parse($product['StartDate'], 'America/Chicago');
        $p->save();

        $this->productCount += 1;

        foreach ($product['Rates'] as $rate) {
            $this->createRate($p->id, $rate, $brand_id, $product);
        }

        if ($this->beVerbose) {
            $this->info('Associated ' . $this->rateCount . ' out of ' . count($product['Rates']) . ' rates for product: ' . $p->name);
        }

        if ($this->rateCount === 0) {
            if ($this->beVerbose) {
                $this->info('Deleting ' . $p->name . ' with no rates associated.');
            }

            $p->delete();
        }

        $this->rateCount = 0;
    }

    private function createRate(string $product_id, array $rate, string $brand_id, array $product)
    {
        if (!empty($rate['UtilityId'])) {
            $second_month_tiered_price = (isset($rate['SecondMonthTieredPrice']))
                ? $rate['SecondMonthTieredPrice']
                : null;
            $ptc_start_date = (isset($rate['PTCStartDate']))
                ? $rate['PTCStartDate']
                : null;
            $ptc_value = (isset($rate['PTCValueDesc']))
                ? $rate['PTCValueDesc']
                : null;
            $ptc_end_date = (isset($rate['PTCEndDate']))
                ? $rate['PTCEndDate']
                : null;
            $total_term = (!empty($rate['SecondaryTerm']))
                ? $rate['SecondaryTerm'] + (!empty($rate['Term']) ? $rate['Term'] : 0)
                : null;

            $rate_uom_id = (isset($rate['UomId']))
                ? $rate['UomId']
                : null;

            $custom_data_1 = ($rate['UtilityState'] === 'IL')
                ? $ptc_start_date
                : $second_month_tiered_price;
            $custom_data_2 = (isset($rate['SecondaryTerm']))
                ? $rate['SecondaryTerm']
                : null;
            $custom_data_3 = ($rate['UtilityState'] === 'IL')
                ? $ptc_value
                : null;
            $custom_data_4 = ($rate['UtilityState'] === 'IL')
                ? $ptc_end_date
                : null;
            $custom_data_5 = ($rate['UtilityState'] === 'IL')
                ? $total_term
                : null;

            if ($this->beVerbose) {
                $this->info(print_r($rate, true));
                $this->info('custom_data_1 = ' . $custom_data_1);
                $this->info('custom_data_2 = ' . $custom_data_2);
                $this->info('custom_data_3 = ' . $custom_data_3);
                $this->info('custom_data_4 = ' . $custom_data_4);
                $this->info('custom_data_5 = ' . $custom_data_5);
            }

            $r = Rate::where('product_id', $product_id)
                ->where('utility_id', $rate['UtilityId'])
                ->where('program_code', $rate['ProgramCode'])
                ->where('external_rate_id', $rate['Code'])
                ->where('postalcode_validation', $rate['ZipCodeRegex']);

            if (strtolower($rate['RateType']) === 'tiered') {
                if (
                    !empty($rate['Rate']) && $rate['Rate'] > 0
                    && !empty($rate['SecondaryRate']) && $rate['SecondaryRate'] > 0
                ) {
                    // tiered (fixed)
                    $r = $r->where(
                        'rate_amount',
                        $rate['SecondaryRate']
                    )->where(
                        'intro_rate_amount',
                        $rate['Rate']
                    );
                } else {
                    // tiered (variable)
                    $r = $r->where(
                        'intro_rate_amount',
                        $rate['Rate']
                    )->whereNull(
                        'rate_amount'
                    );
                }
            } else {
                $r = $r->where(
                    'rate_amount',
                    $rate['Rate']
                )->whereNull(
                    'intro_rate_amount'
                );
            }

            if (is_null($rate_uom_id)) {
                $r = $r->whereNull('rate_uom_id');
            } else {
                $r = $r->where(
                    'rate_uom_id',
                    $rate_uom_id
                );
            }

            if (is_null($custom_data_1)) {
                $r = $r->whereNull('custom_data_1');
            } else {
                $r = $r->where(
                    'custom_data_1',
                    $custom_data_1
                );
            }

            if (is_null($custom_data_2)) {
                $r = $r->whereNull('custom_data_2');
            } else {
                $r = $r->where(
                    'custom_data_2',
                    $custom_data_2
                );
            }

            if (is_null($custom_data_3)) {
                $r = $r->whereNull('custom_data_3');
            } else {
                $r = $r->where(
                    'custom_data_3',
                    $custom_data_3
                );
            }

            if (is_null($custom_data_4)) {
                $r = $r->whereNull('custom_data_4');
            } else {
                $r = $r->where(
                    'custom_data_4',
                    $custom_data_4
                );
            }

            if (is_null($custom_data_5)) {
                $r = $r->whereNull('custom_data_5');
            } else {
                $r = $r->where(
                    'custom_data_5',
                    $custom_data_5
                );
            }

            $r = $r->orderBy(
                'created_at',
                'desc'
            )->withTrashed()->first();

            if (empty($r)) {
                if ($this->beVerbose) {
                    $this->info('Creating new rate: ' . $rate['ProgramCode']);
                }

                $r = new Rate();
                $r->product_id = $product_id;
                $r->utility_id = $rate['UtilityId'];
                $r->rate_currency_id = (isset($rate['ValueCurrency'])
                    && strtolower($rate['ValueCurrency']) === 'dollars')
                    ? 2 : 1;

                $r->rate_uom_id = $rate['UomId'];

                $r->program_code = $rate['ProgramCode'];
                $r->external_rate_id = $rate['Code'];

                if (strtolower($rate['RateType']) === 'tiered') {
                    if (
                        !empty($rate['Rate']) && $rate['Rate'] > 0
                        && !empty($rate['SecondaryRate']) && $rate['SecondaryRate'] > 0
                    ) {
                        // tiered (fixed)
                        $r->rate_amount = $rate['SecondaryRate'];
                        $r->intro_rate_amount = $rate['Rate'];
                    } else {
                        // tiered (variable)
                        $r->rate_amount = null;
                        $r->intro_rate_amount = $rate['Rate'];
                    }
                } else {
                    $r->rate_amount = $rate['Rate'];
                }

                $r->postalcode_validation = $rate['ZipCodeRegex'];
                $r->raw_postalcodes = json_encode($rate['ZipCodes']);
                $r->custom_data_1 = $custom_data_1;
                $r->custom_data_2 = $custom_data_2;
                $r->custom_data_3 = $custom_data_3;
                $r->custom_data_4 = $custom_data_4;
                $r->custom_data_5 = $custom_data_5;
                $r->save();
            } else {
                if ($this->beVerbose) {
                    $this->info('** Re-using rate: ' . $rate['ProgramCode'] . ' ' . $r->id);
                }

                VendorRate::where(
                    'rate_id',
                    $r->id
                )->delete();

                $r->hidden = 0;
                $r->save();
                $r->restore();
            }

            if (isset($rate['Vendors'])) {
                foreach ($rate['Vendors'] as $v) {
                    $vendor = Cache::remember(
                        'vendor-by-brand-label-' . trim($v) . '-' . $brand_id,
                        60,
                        function () use ($v, $brand_id) {
                            return Vendor::where(
                                'brand_id',
                                $brand_id
                            )->where(
                                'vendor_label',
                                trim($v)
                            )->first();
                        }
                    );
                    if ($vendor) {
                        if ($this->beVerbose) {
                            $this->info('** Found vendor = ' . $vendor->vendor_label);
                        }

                        $vr = VendorRate::where(
                            'vendors_id',
                            $vendor->id
                        )->where(
                            'rate_id',
                            $r->id
                        )->withTrashed()->first();
                        if (!$vr) {
                            $vr = new VendorRate();
                            $vr->vendors_id = $vendor->id;
                            $vr->rate_id = $r->id;
                            $vr->save();
                        } else {
                            if ($vr->trashed()) {
                                $vr->restore();
                            }
                        }
                    }
                }
            }

            $this->rateCount += 1;
            $this->totalRateCount += 1;
        } else {

            $r = [
                "error" => "Unable to find a matching utility for (Utility: " . $rate['UtilityName'] . ", State: " . $rate['UtilityState'] . ", Commodity: " . $rate['Type'] . ")",
                "product_name" => $product['DisplayName'],
                "rate_type" => $product['RateType'],
                "term" => $product['Term'],
                "secondary_term" => $product['SecondaryTerm'],
                "market" => $product['Market'],
                "status" => $product['UtilityState'],
                "program_code" => $rate['ProgramCode'],
                "rate_code" => $rate['Code'],
                "utility" => $rate['UtilityName'],
                "vendors" => implode("|", $rate['Vendors'])
            ];

            $this->skippedRates[] = $r;
        }
    }

    private function productInfoToIdentifier(array $product): string
    {
        $name = $product['DisplayName'];
        $rt = $product['RateType'];
        if ($rt === 'TIERED') {
            $remainderTerm = !empty($product['SecondaryTerm']) ? $product['SecondaryTerm'] : null;

            if ($remainderTerm == null) {
                $totalTerm = $product['Term'];
            } else {
                $totalTerm = $product['Term'] . '_' . $remainderTerm;
            }
        } else {
            $totalTerm = $product['Term'];
        }

        $green = $product['GreenPercentage'];
        if ($green == null) {
            $green = 0;
        }

        $ct = implode('_', $product['CustomerType']);
        $ch = implode('_', $product['SalesChannels']);
        $ht = implode('_', $product['HomeTypes']);

        $state = ($product['UtilityState'] === 'NY')
            ? $product['UtilityState']
            : 'OTHERS';

        return implode('-', [$name, $rt, $totalTerm, $green, $ct, $ch, $ht, $state]);
    }

    private function utilityLookup(string $name, string $state, string $fuelType)
    {
        $brand = (strtoupper($state) === 'NY')
            ? $this->columbia
            : $this->indra;

        return Cache::remember(
            'indra-rs-utility-lookup-' . $name . '-' . $state . '-' . $fuelType . '-' . $brand->id,
            60,
            function () use ($name, $state, $fuelType, $brand) {
                $bu = BrandUtility::where('utility_label', $name)->where('brand_id', $brand->id)->first();
                $stateId = $this->stateAbbreviationToId($state);

                if (!empty($bu) && !empty($stateId)) {
                    $utility_id = $bu->utility_id;
                    $utility = Utility::where('id', $utility_id)->where('state_id', $stateId)->first();
                    if (!empty($utility)) {
                        $usf = UtilitySupportedFuel::where(
                            'utility_id',
                            $utility_id
                        )->where(
                            'utility_fuel_type_id',
                            $fuelType === 'E' ? 1 : 2
                        )->first();
                        if (!empty($usf)) {
                            return $usf->id;
                        }
                        if ($this->beVerbose) {
                            $this->warn('Brand ' . $brand->name . ' has entry for utility: ' . $name . ' [' . $state . '] but does not support the fuel type: ' . $fuelType);
                        }
                    }
                    if ($this->beVerbose) {
                        $this->warn('Utility ' . $name . ' not located for ' . $state);
                    }
                }

                if ($this->beVerbose) {
                    if (empty($bu)) {
                        $this->warn('Brand ' . $brand->name . ' does not have an entry for utility: ' . $name . ' [' . $state . ']');
                    }
                    if (empty($stateId)) {
                        $this->warn('Could not resolve the abbreviation "' . $state . '" to a state id');
                    }
                }

                return null;
            }
        );
    }

    private function consolidateProductsAndRates(array $rawData): array
    {
        $out = [];

        foreach ($rawData as $rd) {
            $ident = $this->productInfoToIdentifier($rd);

            // Parse UOM (RateUnit)
            $rateUomId = null;
            if (!empty($rd['RateUnit'])) {
                switch (strtolower($rd['RateUnit'])) {
                    case 'ccf':
                        $rateUomId = 4;
                        break;

                    case 'day':
                        $rateUomId = 8;
                        break;

                    case 'gj':
                        $rateUomId = 6;
                        break;

                    case 'kwh':
                        $rateUomId = 2;
                        break;

                    case 'mcf':
                        $rateUomId = 7;
                        break;

                    case 'month':
                        $rateUomId = 9;
                        break;

                    case 'mwhs':
                        $rateUomId = 5;
                        break;

                    case 'therm':
                        $rateUomId = 1;
                        break;

                    default:
                        $rateUomId = $rd['CommodityType'] == 'E' ? 2 : 1;
                        break;
                }
            } else { // Not provided; user 'kwh' for electric, 'therm' for gas
                $rateUomId = $rd['CommodityType'] == 'E' ? 2 : 1; // kwh for electric and therms for gas
            }

            if (!isset($out[$ident])) {
                $out[$ident] = [
                    'DisplayName' => $rd['DisplayName'],
                    'StartDate' => $rd['StartDate'],
                    'GreenPercentage' => $rd['GreenPercentage'],
                    'RateType' => $rd['RateType'],
                    'Term' => $rd['Term'],
                    'SecondaryTerm' => $rd['SecondaryTerm'],
                    'Market' => implode('|', $rd['CustomerType']),
                    'HomeTypes' => implode('|', $rd['HomeTypes']),
                    'Channels' => implode('|', array_map(function ($item) {
                        switch ($item) {
                            default:
                                return strtoupper($item);

                            case 'Retail':
                                return 'Retail'; // To ensure this is saved in proper case instead of upper case.

                            case 'D2D':
                                return 'DTD';
                        }
                    }, $rd['SalesChannels'])),
                    'ProgramCodes' => [
                        $rd['ProgramCode']
                    ],
                    'UtilityState' => $rd['UtilityState'],
                    'ValueCurrency' => $rd['ValueCurrency'],
                ];

                $out[$ident]['Rates'] = [];

                $rate = [
                    'RateType' => $rd['RateType'],
                    'Type' => $rd['CommodityType'],
                    'Rate' => $rd['Value'],
                    'Uom' => $rd['RateUnit'],
                    'UomId' => $rateUomId,
                    'ProgramCode' => $rd['ProgramCode'],
                    'Code' => $rd['Code'],
                    'SecondaryRate' => $rd['SecondaryValue'],
                    'UtilityName' => $rd['UtilityName'],
                    'UtilityState' => $rd['UtilityState'],
                    'UtilityId' => $this->utilityLookup($rd['UtilityName'], $rd['UtilityState'], $rd['CommodityType']),
                    'SecondMonthTieredPrice' => (isset($rd['SecondMonthTieredPrice']))
                        ? $rd['SecondMonthTieredPrice']
                        : null,
                    'PTCStartDate' => (isset($rd['PTCStartDate']))
                        ? $rd['PTCStartDate']
                        : null,
                    'PTCEndDate' => (isset($rd['PTCEndDate']))
                        ? $rd['PTCEndDate']
                        : null,
                    'PTCValueDesc' => (isset($rd['PTCValueDesc']))
                        ? $rd['PTCValueDesc']
                        : null,
                    'SecondaryTerm' => (isset($rd['SecondaryTerm']))
                        ? $rd['SecondaryTerm']
                        : null,
                    'Term' => $rd['Term'],
                    'SecondaryTerm' => $rd['SecondaryTerm'],
                    'ZipCodes' => implode(',', $rd['ZipCodes']),
                    'ZipCodeRegex' => $this->__compile_csv_to_regex($rd['ZipCodes']),
                    'Vendors' => $rd['Vendors'],
                    'ValueCurrency' => $rd['ValueCurrency'],
                ];

                $out[$ident]['Rates'][] = $rate;
            } else {
                if (!in_array($rd['ProgramCode'], $out[$ident]['ProgramCodes'])) {
                    $out[$ident]['ProgramCodes'][] = $rd['ProgramCode'];
                    $rate = [
                        'RateType' => $rd['RateType'],
                        'Type' => $rd['CommodityType'],
                        'Rate' => $rd['Value'],
                        'Uom' => $rd['RateUnit'],
                        'UomId' => $rateUomId,
                        'ProgramCode' => $rd['ProgramCode'],
                        'Code' => $rd['Code'],
                        'SecondaryRate' => $rd['SecondaryValue'],
                        'UtilityName' => $rd['UtilityName'],
                        'UtilityState' => $rd['UtilityState'],
                        'UtilityId' => $this->utilityLookup($rd['UtilityName'], $rd['UtilityState'], $rd['CommodityType']),
                        'SecondMonthTieredPrice' => (isset($rd['SecondMonthTieredPrice']))
                            ? $rd['SecondMonthTieredPrice']
                            : null,
                        'PTCStartDate' => (isset($rd['PTCStartDate']))
                            ? $rd['PTCStartDate']
                            : null,
                        'PTCEndDate' => (isset($rd['PTCEndDate']))
                            ? $rd['PTCEndDate']
                            : null,
                        'PTCValueDesc' => (isset($rd['PTCValueDesc']))
                            ? $rd['PTCValueDesc']
                            : null,
                        'SecondaryTerm' => (!empty($rd['SecondaryTerm']))
                            ? $rd['SecondaryTerm']
                            : null,
                        'ZipCodes' => implode(',', $rd['ZipCodes']),
                        'ZipCodeRegex' => $this->__compile_csv_to_regex($rd['ZipCodes']),
                        'Vendors' => $rd['Vendors'],
                        'ValueCurrency' => $rd['ValueCurrency'],
                        'SecondaryTerm' => $rd['SecondaryTerm'],
                        'Term' => $rd['Term'],
                    ];

                    $out[$ident]['Rates'][] = $rate;
                } else {
                    $this->info('Program Code ' . $rd['ProgramCode'] . ' is duplicated');
                }
            }
        }
        return $out;
    }

    private function stateAbbreviationToId(string $abbr)
    {
        return Cache::remember('state_id-' . $abbr, 300, function () use ($abbr) {
            $state = State::where('state_abbrev', $abbr)->first();
            return optional($state)->id;
        });
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
                if (empty($current)) {
                    $current[] = $iitem;
                    continue;
                }

                if (1 == $iitem - $current[count($current) - 1]) {
                    $current[] = $iitem;

                    continue;
                }

                $ret = $this->__filter_current($current, $grouped2[$group]);
                if (null !== $ret) {
                    $grouped2[$group][] = $ret;
                }
                $current = [$iitem];
            }

            $ret = $this->__filter_current($current, $grouped2[$group]);
            if (null !== $ret) {
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

    private function doClearExistingProducts()
    {
        $pcnt = 0;
        $rcnt = 0;
        $products = Product::where(
            function ($query) {
                $query->where(
                    'brand_id',
                    $this->indra->id
                )->orWhere(
                    'brand_id',
                    $this->columbia->id
                );
            }
        )->get();
        foreach ($products as $product) {
            $rates = Rate::where('product_id', $product->id)->withTrashed()->get();

            if (!$this->dryRun) {
                foreach ($rates as $rate) {
                    $rate->hidden = 1;
                    $rate->save();

                    $rate->delete();
                }

                if ($this->beVerbose) {
                    $this->info('Removing Product "' . $product->name);
                }

                $product->delete();
            } else {
                if ($this->beVerbose) {
                    $this->warn('Removing Product "' . $product->name . '" would remove ' . $rates->count() . ' rates');
                }
                $pcnt += 1;
                $rcnt += ($rates->count());
            }
        }
        if ($this->dryRun) {
            $this->warn('Clearing Products would remove ' . $pcnt . ' products and ' . $rcnt . ' associated rates');
        }
    }

    private function makeRequest()
    {
        if ($this->inputFile !== null) {
            if ($this->beVerbose) {
                $this->info('Loading Rate Data from ' . $this->inputFile);
            }
            if (file_exists($this->inputFile)) {
                $contents = file_get_contents($this->inputFile);
                if ($contents === false) {
                    $this->error('Unable to read from ' . $this->inputFile);
                    return null;
                }
                $contents = trim($contents);
                if (empty($contents)) {
                    $this->error('InputFile ' . $this->inputFile . ' is empty');
                    return null;
                }
                try {
                    $ret = json_decode($contents, true);
                    if ($ret == null) {
                        $this->error('The JSON in ' . $this->inputFile . ' is not parsable or is invalid');
                    }
                    return $ret;
                } catch (\Exception $e) {
                    $this->error('The JSON in ' . $this->inputFile . ' is not parsable');
                }
                return null;
            } else {
                $this->error('InputFile ' . $this->inputFile . ' does not exist or is not readable');
                return null;
            }
        }

        $url = null;
        if ($this->prodForced || (!$this->devForced && config('app.env') === 'production')) {
            if ($this->beVerbose) {
                $this->warn('Environment: Production');
            }
            $url = $this->prodURI;

            if ($this->beVerbose) {
                $this->warn('URL: ' . $url);
            }
        }
        if ($this->devForced || (!$this->prodForced && config('app.env') !== 'production')) {
            if ($this->beVerbose) {
                $this->warn('Environment: Development');
            }
            $url = $this->devURI;

            if ($this->beVerbose) {
                $this->warn('URL: ' . $url);
            }
        }

        $key = null;
        $pi = ProviderIntegration::select('password')
            ->where('brand_id', $this->indra->id)
            ->where('service_type_id', config('app.env') === 'production' ? 18 : 22)
            ->where('env_id', config('app.env') === 'production' ? 1 : 2)
            ->first();

        if (!empty($pi)) {
            $key = $pi->password;
        }

        if (!empty($key)) {
            if ($this->beVerbose) {
                $this->info('Retrieving Rate File');
            }
            $start = hrtime(true);
            $client = new HttpClient();
            try {
                $res = $client->request('POST', $url, [
                    'headers' => [
                        'api-key' => $key
                    ],
                    'json' => [
                        'ZipCode' => ''
                    ]
                ]);

                if ($res->getStatusCode() === 200) {
                    $end = hrtime(true);
                    if ($this->beVerbose) {
                        $this->info('Request took ' . (($end - $start) / 1e+6) . ' ms');
                    }
                    $rawBody = trim($res->getBody());

                    if (!empty($rawBody)) {
                        return json_decode($rawBody, true);
                    }
                    $this->error('No data returned from api call');
                    return null;
                }
            } catch (TransferException $e) {
                $end = hrtime(true);
                $code = $e->getCode();
                $msg = $e->getMessage();
                if ($this->beVerbose) {
                    $this->info('Request took ' . (($end - $start) / 1e+6) . ' ms');
                }
                $this->error('Error while retrieving rate file (' . $code . '): ' . $msg);
                return null;
            } catch (Exception $e) {
                $end = hrtime(true);
                $code = $e->getCode();
                $msg = $e->getMessage();
                if ($this->beVerbose) {
                    $this->info('Request took ' . (($end - $start) / 1e+6) . ' ms');
                }
                $this->error('Error while retrieving rate file (' . $code . '): ' . $msg);
                return null;
            }
        } else {
            $this->error('Unable to locate api key for Indra');
            return null;
        }

        return null;
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
                . Carbon::now("America/Chicago");
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
}
