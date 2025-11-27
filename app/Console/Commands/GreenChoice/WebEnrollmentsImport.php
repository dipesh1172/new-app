<?php

namespace App\Console\Commands\GreenChoice;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Exception;
use App\Models\Vendor;
use App\Models\UtilityAccountType;
use App\Models\Utility;
use App\Models\RateUom;
use App\Models\Rate;
use App\Models\ProviderIntegration;
use App\Models\Product;
use App\Models\Office;
use App\Models\Interaction;
use App\Models\Eztpv;
use App\Models\EventProductIdentifier;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\Brand;
use App\Models\AddressLookup;

class WebEnrollmentsImport extends Command
{
    private $brand_id = null;
    private $defaultVendor = 'Web Enrollments';
    private $defaultOffice = 'Web Enrollments';
    private $noticePrepend = 'Green Choice Energy - Web Enrollments';
    private $env = 'staging';
    private $errors = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'green-choice:web-enrollments-import {--debug} {--file=} {--single} {--dryrun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'GCE Web Enrollments import job';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->env = (config('app.env') === 'production')
            ? 'production'
            : 'staging';
    }

    /**
     * Lookup Vendor by identifier.
     *
     * @return Vendor
     */
    public function lookupVendor($vendor_label)
    {
        return Cache::remember(
            'vendor-' . $this->brand_id . '-' . $vendor_label,
            60 * 5,
            function () use ($vendor_label) {
                return Vendor::select(
                    'vendors.id',
                    'brands.id AS vendor_id'
                )->leftJoin(
                    'brands',
                    'vendors.vendor_id',
                    'brands.id'
                )->where(
                    'brand_id',
                    $this->brand_id
                )->where(
                    function ($query) use ($vendor_label) {
                        $query->where(
                            'vendor_label',
                            $vendor_label
                        )->orWhere(
                            'grp_id',
                            $vendor_label
                        );
                    }
                )->first();
            }
        );
    }

    /**
     * Lookup Office by identifier.
     *
     * @param string $vendor_id - vendor identifier
     * @param string $label     - office identifier
     *
     * @return Office
     */
    public function lookupOffice(string $vendor_id, string $label)
    {
        return Cache::remember(
            'office-' . $label . '-' . $this->brand_id . '-' . $vendor_id,
            60 * 5,
            function () use ($vendor_id, $label) {
                return Office::where(
                    'brand_id',
                    $this->brand_id
                )->where(
                    'vendor_id',
                    $vendor_id
                )->where(
                    'label',
                    $label
                )->with(
                    [
                        'config',
                    ]
                )->first();
            }
        );
    }

    /**
     * Lookup FTP credentials
     */
    public function providerIntegration($brand_id)
    {
        $pi = ProviderIntegration::where(
            'brand_id',
            $brand_id
        )->where(
            'provider_integration_type_id',
            7
        )->first();
        if ($pi) {
            if (is_string($pi->notes)) {
                $pi->notes = json_decode($pi->notes, true);
            }

            return $pi;
        }

        return null;
    }

    /**
     * Create EZTPV record
     */
    public function createEztpv($data)
    {
        $eztpv = new Eztpv();

        $eztpv->brand_id = $this->brand_id;
        $eztpv->contract_type = 2;
        $eztpv->eztpv_contract_delivery = (isset($data['contract_delivery']))
            ? strtolower($data['contract_delivery'])
            : null;
        $eztpv->version = 2;
        $eztpv->webenroll = 2;

        $eztpv->save();

        return $eztpv;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->brand_id = (config('app.env') !== 'production')
            ? 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e' // ID is for non-clustered staging DB (old staging)
            : '7b08b19d-32a5-4906-a320-6a2c5d6d6372';

        $brand = Brand::find($this->brand_id);

        if ($brand) {
            $pi = $this->providerIntegration($brand->id);
            if (empty($pi)) {
                echo "No Provider Integration found.";
                exit();
            }

            if ($this->option('debug')) {
                info(print_r($pi->toArray(), true));
            }

            $config = [
                'hostname' => $pi->hostname,
                'username' => $pi->username,
                'password' => $pi->password,
                'root' => '/WebEnrollments',
                'port' => ($pi->provider_integration_type_id)
                    ? 21 : 22,
                'ssl' => true,
                'passive' => true,
            ];

            if ($this->option('debug')) {
                info(print_r($config, true));
            }

            $this->info('Starting download...');
            $this->ftpDownload($config);
        } else {
            $this->error('The Green Choice Energy brand was not found.');
        }
    }

    /**
     * Create Event record
     */
    public function createEvent($data, $eztpv_id, $vendor_id, $office_id)
    {
        $event = new Event();

        $event->generateConfirmationCode();

        $event->external_id = trim($data['external_id']);
        $event->event_category_id = 1; // Energy
        $event->brand_id = $this->brand_id;

        $event->language_id = (trim($data['language']) === 'Spanish')
            ? 2
            : 1;

        $event->sales_agent_id = (config('app.env') === 'production')
            // Mgmt -> Green Choice -> Login to Clients, then just paste: https://clients.tpvhub.com/vendor_mgmt/c9691300-94fe-4509-87ba-e26d92706076/edit?vendor_id=62071f3e-0d61-4da7-8e56-3eeb28f82576
            ? '62071f3e-0d61-4da7-8e56-3eeb28f82576' // TODO: Populate
            : 'dd85c211-be34-4522-bd03-6c963ee983c9'; // ID is for non-clustered staging DB (old staging)

        $event->channel_id = 2;
        $event->eztpv_id = $eztpv_id;
        $event->vendor_id = $vendor_id;
        $event->office_id = $office_id;

        $event->save();

        return $event;
    }

    /**
     * Lookup utility in database
     */
    public function lookupUtility(string $name, int $fuel_type)
    {
        $usf = Cache::remember(
            'brand-utility-' . trim($name) . $fuel_type . $this->brand_id,
            30,
            function () use ($name, $fuel_type) {
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
                    trim($name)
                )->where(
                    'brand_utilities.brand_id',
                    $this->brand_id
                )->whereNull(
                    'utilities.deleted_at'
                )->whereNull(
                    'utility_supported_fuels.deleted_at'
                )->first();
            }
        );

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
     * Create Product and Rate records
     * */
    // public function addProductRate($programCode)
    public function getRate($programCode)
    {
        $rate = Rate::select(
            'rates.id',
            'rates.utility_id'
        )->join('products', 'rates.product_id', 'products.id')
        ->where('products.brand_id', $this->brand_id)
        ->where('rates.program_code', $programCode)
        ->where('products.deleted_at') // Including in case we find an undeleted rate with a deleted product (Laravel automatically ignores deleted rates)
        ->get();
        
        return $rate;
    }

    /**
     * Create Interaction record
     */
    public function createInteraction($event_id)
    {
        $interaction = new Interaction();

        $interaction->event_id = $event_id;
        $interaction->interaction_type_id = 19; // web_enroll
        $interaction->event_source_id = 23;     // Web Enrollment
        $interaction->event_result_id = 1;      // Sale

        $interaction->save();
    }

    /**
     * Add Event Product.
     *
     * @param array  $post         - array of data
     * @param string $event_id     - event id
     * @param int    $market_id    - market id
     * @param string $rate_id      - rate id
     * @param string $utility_id   - utility id
     * @param int    $rate_type_id - rate type id
     * @param int    $home_type_id - home type id
     * @param string $linked_to    - dual fuel linking
     *
     * @return bool
     */
    public function addEventProduct(
        array $data,
        string $event_id,
        int $market_id,
        string $rate_id,
        string $utility_id
    ) {
        $ep = new EventProduct();

        $ep->event_id = $event_id;
        $ep->event_type_id = (isset($data['fuel_type']) && strtolower($data['fuel_type']) === 'electric')
            ? 1 : 2;
        $ep->market_id = $market_id;
        $ep->home_type_id = 1;
        $ep->rate_id = $rate_id;
        $ep->utility_id = $utility_id;
        $ep->auth_relationship = $data['auth_relationship'];

        if (isset($data['billing_first_name'])) {
            $ep->bill_first_name = $data['billing_first_name'];
        }

        if (isset($data['billing_last_name'])) {
            $ep->bill_last_name = $data['billing_last_name'];
        }

        if (isset($data['authorizing_first_name'])) {
            $ep->auth_first_name = $data['authorizing_first_name'];
        }

        if (isset($data['authorizing_last_name'])) {
            $ep->auth_last_name = $data['authorizing_last_name'];
        }

        $ep->save();

        // Add Service Address
        $sa = addAddress(
            [
                'line_1' => trim($data['service_address']),
                'line_2' => null,
                'city' => $data['service_city'],
                'state_province' => strtoupper($data['service_state']),
                'zip' => str_pad($data['service_zip'], 5, "0", STR_PAD_LEFT),
            ]
        );

        $sal = new AddressLookup();
        $sal->id_type = 'e_p:service';
        $sal->type_id = $ep->id;
        $sal->address_id = $sa;

        $sal->save();

        // Add Billing Address (if available)
        if (
            isset($data['billing_address'])
            && !isset($data['billing_city'])
            && !isset($data['billing_state'])
            && !isset($data['billing_zip'])
        ) {
            $ba = addAddress(
                [
                    'line_1' => $data['billing_address'],
                    'line_2' => null,
                    'city' => $data['billing_city'],
                    'state_province' => strtoupper($data['billing_state']),
                    'zip' => str_pad($data['billing_zip'], 5, "0", STR_PAD_LEFT),
                ]
            );

            $sal = new AddressLookup();

            $sal->id_type = 'e_p:billing';
            $sal->type_id = $ep->id;
            $sal->address_id = $ba;

            $sal->save();
        }

        if (isset($data['account_number1']) && strlen(trim($data['account_number1'])) > 0) {
            $epi = new EventProductIdentifier();

            $epi->event_product_id = $ep->id;
            $epi->utility_account_type_id = $this->lookupUtilityAccountType(
                $data['account_number_1_label']
            );
            $epi->identifier = $data['account_number1'];
            $epi->utility_account_number_type_id = 1;

            $epi->save();
        }

        if (isset($data['account_number2']) && strlen(trim($data['account_number2'])) > 0) {
            $epi = new EventProductIdentifier();

            $epi->event_product_id = $ep->id;
            $epi->utility_account_type_id = $this->lookupUtilityAccountType(
                $data['account_number_2_label']
            );
            $epi->identifier = $data['account_number2'];
            $epi->utility_account_number_type_id = 2;
            
            $epi->save();
        }
    }

    /**
     * Lookup utility account type by name
     */
    public function lookupUtilityAccountType($account_type)
    {
        $uat = UtilityAccountType::select(
            'utility_account_types.id'
        )->where(
            'account_type',
            trim($account_type)
        )->first();
        if ($uat) {
            return $uat->id;
        }

        // Default to Account Number
        return 1;
    }

    /**
     * Process row from CSV file
     */
    public function processData($row, $data)
    {
        if ($this->option('debug')) {
            print_r($data);
        }

        // External ID must be provided
        if (!$data['external_id']) {
            $msg = "Row {$row}: external_id cannot be blank. Skipping record.";
            $this->errors[] = $msg;

            SendTeamMessage(
                'monitoring',
                $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
            );

            return;
        }

        info('Starting new data...');

        // Must find vendor before processing record
        $vendor = $this->lookupVendor($this->defaultVendor);
        if (!$vendor) {
            $msg = "Row {$row}: {$data['external_id']} Vendor was not found. Skipping record.";
            $this->errors[] = $msg;

            SendTeamMessage(
                'monitoring',
                $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
            );

            return;
        }

        // Must find office before processing record
        $office = $this->lookupOffice($vendor->id, $this->defaultOffice);
        if (!$office) {
            $msg = "Row {$row}: {$data['external_id']} Office was not found. Skipping record.";
            $this->errors[] = $msg;

            SendTeamMessage(
                'monitoring',
                $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
            );

            return;
        }

        DB::beginTransaction();

        try {

            // Must find rate before processing record
            $rate = $this->getRate($data['program_code']);

            if(count($rate) == 0) {
                $msg = "Row {$row}: Unable to locate any active rates for program code {$data['program_code']}. Skipping record.";
                $this->errors[] = $msg;

                SendTeamMessage(
                    'monitoring',
                    $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
                );

                DB::rollBack();
                return;
            }

            // Only one rate must have been returned
            if(count($rate) > 1) {
                $msg = "Row {$row}: Rate lookup for program code {$data['program_code']} returned more than one rate. Skipping record.";
                $this->errors[] = $msg;

                SendTeamMessage(
                    'monitoring',
                    $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
                );

                DB::rollBack();
                return;
            }

            $rate = $rate[0];

            if (
                !$data['billing_address']
                || !$data['billing_city']
                || !$data['billing_state']
                || !$data['billing_zip']
            ) {
                $msg = "Row {$row}: Billing address, city, state, and zip are required. Skipping record.";
                $this->errors[] = $msg;

                SendTeamMessage(
                    'monitoring',
                    $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
                );

                DB::rollBack();
                return;
            }

            // Check if an event with this external_id already exists
            $event_check = Event::where(
                'brand_id',
                $this->brand_id
            )->where(
                'external_id',
                $data['external_id']
            )->first();

            if ($event_check) {
                $msg = "Row {$row}: External ID {$data['external_id']} already has a sale, on {$event_check->created_at->format('Y-m-d')}. Skipping record.";
                $this->errors[] = $msg;

                SendTeamMessage(
                    'monitoring',
                    $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
                );

                DB::rollBack();
                return;
            }

            // Create the EZTPV record
            $eztpv = $this->createEztpv($data);
            if ($eztpv) {
                $event = $this->createEvent($data, $eztpv->id, $vendor->vendor_id, $office->id);

                $this->createInteraction($event->id);
            } else {
                $msg = "Row {$row}: Could not create an EZTPV record. Skipping record.";
                $this->errors[] = $msg;

                SendTeamMessage(
                    'monitoring',
                    $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
                );

                DB::rollBack();
                return;
            }

            if (
                isset($data['email_address'])
                && filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)
            ) {
                generateEmailAddressRecords(
                    $data['email_address'],
                    3,
                    $event->id
                );
            }

            if (isset($data['btn']) && strlen(trim($data['btn'])) > 0) {
                $data['btn'] = preg_replace("/[^0-9]/", "", $data['btn']);
                generatePhoneNumberRecords(
                    $data['btn'],
                    3,
                    $event->id
                );
            }

            $this->addEventProduct(
                $data,
                $event->id,
                (isset($data['market']) && strtolower($data['market']) === 'Commercial')
                    ? 2
                    : 1,
                $rate->id,
                $rate->utility_id
            );

            info('Created confirmation code = ' . $event->confirmation_code);

            if (!$this->option('dryrun')) {
                DB::commit();
            }

        } catch (Exception $e) {
            $msg = "Row {$row}: {$e->getMessage()}. Skipping record.";
            $this->errors[] = $msg;

            SendTeamMessage(
                'monitoring',
                $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
            );

            DB::rollBack();
        }    
    }

    /**
     * File FTP Download
     *
     * @param array  $config - configuration needed to perform FTP upload
     * @param string $type   - file type
     *
     * @return array
     */
    public function ftpDownload($config)
    {
        $root = (isset($config['root'])) ? trim($config['root']) : '/';
        try {
            $adapter = new Ftp(
                [
                    'host' => trim($config['hostname']),
                    'username' => trim($config['username']),
                    'password' => trim($config['password']),
                    'port' => (isset($config['port'])) ? $config['port'] : 21,
                    'root' => $root,
                    'passive' => (isset($config['passive'])) ? $config['passive'] : false,
                    'ssl' => (isset($config['ssl'])) ? trim($config['root']) : false,
                    'timeout' => 20,
                ]
            );

            $filesystem = new Filesystem($adapter);
            $filename = ($this->option('file'))
                ? $this->option('file')
                : 'file_' . date('mdY') . '.csv';

            try {
                $exists = $filesystem->has($filename); // TODO: Restore
                // $exists = file_exists($filename); // TODO: Remove
                if (!$exists) {
                    $msg = 'Filename ' . $filename . ' does not exist on FTP.';

                    info($msg);

                    SendTeamMessage(
                        'monitoring',
                        $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
                    );
                } else {
                    $contents = $filesystem->read($filename); // TODO: Restore
                    // $contents = file_get_contents($filename); // TODO: Remove
                    if ($contents) {
                        $lines = explode(PHP_EOL, utf8_encode($contents));
                        $header = null;
                        $csv = [];

                        foreach ($lines as $line) {
                            if (strlen(trim($line)) > 0) {
                                $csv[] = str_getcsv($line);
                            }
                        }
                        if (!$this->option('single')) {
                            SendTeamMessage(
                                'monitoring',
                                $this->noticePrepend . ' (' . $this->env . ') :: Start processing of file "' . $filename . '" with ' . count($csv) . ' records.'
                            );
                        }

                        $count = 0;
                        foreach ($csv as $row) {
                            if ($header === null) {
                                $header = $row;
                                continue;
                            }

                            $count++;

                            $this->processData(($count + 1), array_combine($header, $row));

                            if ($this->option('single')) {
                                // For Debugging;  Only run 1 row and quit.
                                exit();
                            }

                            if ($count > 500) {
                                SendTeamMessage(
                                    'monitoring',
                                    $this->noticePrepend
                                        . ' (' . $this->env . ') :: File "'
                                        . $filename . '" had more than 500 rows. (' . count($csv) . ')'
                                );
                                break;
                            }
                        }

                        SendTeamMessage('monitoring', $this->noticePrepend . ' (' . $this->env . ') :: File "' . $filename . '" processing finished.');
                    } else {
                        $msg = 'Filename ' . $filename . ' is empty.';

                        info($msg);

                        SendTeamMessage(
                            'monitoring',
                            $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
                        );
                    }
                }

                if (!empty($this->errors)) {
                    $this->error(print_r($this->errors));
                }
            } catch (Exception $e) {
                $this->errors[] = $e;
            }
        } catch (\Exception $e) {
            $this->errors[] = 'Error! The reason reported is: ' . $e;
        }

        return null;
    }
}
