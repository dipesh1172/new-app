<?php

namespace App\Console\Commands;

use Twilio\Exceptions\TwilioException;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Exception;
use Carbon\Carbon;
use App\Models\BrandCustomField;
use App\Models\CustomField;
use App\Models\CustomFieldStorage;
use App\Models\Vendor;
use App\Models\UtilityAccountType;
use App\Models\Utility;
use App\Models\RateUom;
use App\Models\Rate;
use App\Models\ProviderIntegration;
use App\Models\Product;
use App\Models\Office;
use App\Models\Interaction;
use App\Models\EventProductIdentifier;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\Brand;
use App\Models\AddressLookup;

class RpaWelcomeEmail30 extends Command
{
    private $brand_id = null;
    private $defaultVendor = 'TM Fulfillment';
    private $defaultOffice = 'TM Fulfillment';
    private $noticePrepend = 'Green Choice Energy Email Notifications';
    private $env = 'staging';
    private $errors = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rpa:welcome:email30 {--debug} {--file=} {--single} {--dryrun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RPA Welcome Kit Fulfillment job (emails)';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->brand_id = (config('app.env') !== 'production')
            ? 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e'
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
                $this->info("Provider Integraion:");
                $this->info(print_r($pi->toArray(), true));
            }

            $config = [
                'hostname' => $pi->hostname,
                'username' => $pi->username,
                'password' => $pi->password,
                'root' => '/30_Letter_DE',
                'port' => ($pi->provider_integration_type_id)
                    ? 21 : 22,
                'ssl' => true,
                'passive' => true,
            ];

            if ($this->option('debug')) {
                info(print_r($config, true));
                $this->info("FTP Config:");
                $this->info(print_r($config, true));
            }

            $this->info('Starting download...');
            $this->ftpDownload($config);
        } else {
            $this->error('The RPA Brand was not found.');
        }
    }

    public function createEvent($data, $vendor_id, $office_id)
    {
        $event = new Event();
        $event->created_at = Carbon::now('America/Chicago');
        $event->generateConfirmationCode();
        $event->external_id = trim($data['external_id']);
        $event->event_category_id = 3; // Fulfilment
        $event->brand_id = $this->brand_id;
        $event->language_id = (strtolower(trim($data['language'])) === 'spanish')
            ? 2
            : 1;
        $event->sales_agent_id = (config('app.env') === 'production')
            ? 'a3c6e17e-a7fa-469e-95fb-abc2ad44c262'
            : '388b555d-6264-4b40-b3e1-8ebd03bfed65';
        $event->channel_id = 2;
        $event->vendor_id = $vendor_id;
        $event->office_id = $office_id;
        $event->save();

        return $event;
    }

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

    public function addProductRate($info)
    {
        $p = Product::where(
            'brand_id',
            $this->brand_id
        )->where(
            'name',
            $info['product']['name']
        )->where(
            'channel',
            $info['product']['channel']
        )->where(
            'rate_type_id',
            $info['product']['rate_type_id']
        )->where(
            'term',
            $info['product']['term']
        )->withTrashed()->first();
        if ($p) {
            if ($p->trashed()) {
                $p->restore();
            }
        } else {
            $p = new Product();
            $p->brand_id = $this->brand_id;
            $p->name = $info['product']['name'];
            $p->channel = $info['product']['channel'];
            $p->rate_type_id = $info['product']['rate_type_id'];
            $p->term = $info['product']['term'];
            $p->term_type_id = 3;
            $p->market = 'Residential|Commercial';
            $p->home_type = 'Single|Multi-Family';
            $p->save();
        }

        $usf = $this->lookupUtility(
            $info['rate']['utility_ldc_code'],
            ($info['rate']['fuel_type'] === 'electric')
                ? 1 : 2,
            $this->brand_id
        );
        if (isset($usf)) {
            $program_code = substr(
                md5(
                    $p->id
                        . $info['rate']['rate_amount']
                ),
                0,
                15
            );

            if (strtolower($info['rate']['rate_uom']) == 'therms') {
                $info['rate']['rate_uom'] = 'therm';
            }

            $ru = RateUom::where(
                'uom',
                strtolower($info['rate']['rate_uom'])
            )->first();
            if (!$ru) {
                $rate = [];
                $rate['error'] = 'Unable to find UOM (' . $info['rate']['rate_uom'] . ')';

                return (object) $rate;
            }

            $rate = Rate::where(
                'product_id',
                $p->id
            )->where(
                'rate_currency_id',
                2
            )->where(
                'rate_uom_id',
                $ru->id
            )->where(
                'utility_id',
                $usf['id']
            )->where(
                'program_code',
                $program_code
            )->where(
                'rate_amount',
                $info['rate']['rate_amount']
            );

            if (isset($info['rate']['custom_data_1'])) {
                $rate = $rate->where(
                    'custom_data_1',
                    $info['rate']['custom_data_1']
                );
            }

            if (isset($info['rate']['custom_data_2'])) {
                $rate = $rate->where(
                    'custom_data_2',
                    $info['rate']['custom_data_2']
                );
            }

            if (isset($info['rate']['custom_data_3'])) {
                $rate = $rate->where(
                    'custom_data_3',
                    $info['rate']['custom_data_3']
                );
            }

            if (isset($info['rate']['custom_data_4'])) {
                $rate = $rate->where(
                    'custom_data_4',
                    $info['rate']['custom_data_4']
                );
            }

            if (isset($info['rate']['custom_data_5'])) {
                $rate = $rate->where(
                    'custom_data_5',
                    $info['rate']['custom_data_5']
                );
            }

            $rate = $rate->withTrashed()->first();
            if ($rate) {
                if ($rate->trashed()) {
                    $rate->restore();
                }
            } else {
                $rate = new Rate();
                $rate->product_id = $p->id;
                $rate->utility_id = $usf['id'];
                $rate->rate_currency_id = 2;
                $rate->program_code = $program_code;
                $rate->rate_amount = $info['rate']['rate_amount'];
                $rate->rate_uom_id = $ru->id;
                $rate->cancellation_fee_term_type_id = 5;

                if (isset($info['rate']['custom_data_1'])) {
                    $rate->custom_data_1 = $info['rate']['custom_data_1'];
                }

                if (isset($info['rate']['custom_data_2'])) {
                    $rate->custom_data_2 = $info['rate']['custom_data_2'];
                }

                if (isset($info['rate']['custom_data_3'])) {
                    $rate->custom_data_3 = $info['rate']['custom_data_3'];
                }

                if (isset($info['rate']['custom_data_4'])) {
                    $rate->custom_data_4 = $info['rate']['custom_data_4'];
                }

                if (isset($info['rate']['custom_data_5'])) {
                    $rate->custom_data_5 = $info['rate']['custom_data_5'];
                }

                $rate->save();
            }

            return $rate;
        }

        $rate = [];
        $rate['error'] = 'Unable to find Utility Label (' . $info['rate']['utility_ldc_code'] . ')';

        return (object) $rate;
    }

    public function createInteraction($event_id)
    {
        $interaction = new Interaction();
        $interaction->event_id = $event_id;
        $interaction->interaction_type_id = 24; // welcome_email
        $interaction->event_source_id = 20; // welcome_email
        $interaction->event_result_id = 1; // Good sale
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
        $rate_id = null,
        $utility_id = null
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

    public function processData($data): bool
    {
        if (isset($data['external_id'])) {
            if ($this->option('debug')) {
                print_r($data);
            }

            $this->info("External ID: " . $data['external_id']);
            $this->info("Delivery Method: " . $data['contract_delivery']);
            info('Starting new data...'); // for Laravel log

            $this->info('Vendor lookup...');
            $vendor = $this->lookupVendor($this->defaultVendor);

            if (!$vendor) {
                $this->info('  Failed.');
                $this->errors[] = $data['external_id'] . ' Vendor was not found. ';
            } else {

                $this->info('Office lookup....');
                $office = $this->lookupOffice($vendor->id, $this->defaultOffice);

                if (!$office) {

                    $this->info('  Failed.');
                    $this->errors[] = $data['external_id'] . ' Office was not found. ';
                } else {

                    $this->info('Begin DB transaction');
                    DB::beginTransaction();

                    try {
                        $this->info('  Checking billing address...');
                        if ( // Don't process records with missing address
                            !isset($data['billing_address'])
                            || !isset($data['billing_city'])
                            || !isset($data['billing_state'])
                            || !isset($data['billing_zip'])
                        ) {
                            $this->errors[] = 'Billing address, city, state, and zip are required.';

                            $this->info('  -- Incomplete or missing billing address');
                            $this->info('  -- Skipping record...');
                            $this->info('Rolling back DB transaction');

                            DB::rollBack();
                        } else {

                            // Skip record is an Event with the same external ID already exists
                            $this->info('  Checking for duplicate external_id...');
                            $new_event = true;
                            if (isset($data['external_id'])) {
                                $event_check = Event::where(
                                    'brand_id',
                                    $this->brand_id
                                )->where(
                                    'external_id',
                                    trim($data['external_id'])
                                )->first();

                                if (isset($event_check)) {
                                    $new_event = false;
                                }
                            }

                            if (!$new_event) {
                                $this->info('  -- ' . $data['external_id'] . ' already exists. Skipping...');
                                $this->info('Rolling back DB transaction');

                                DB::rollBack();
                            } else {

                                $this->info('  Creating new Event...');
                                $event = $this->createEvent($data, $vendor->vendor_id, $office->id);

                                if ($event) {
                                    $this->info('  -- Success. Event ID: ' . $event->id);
                                    $this->info('              Confirmation Code: ' . $event->confirmation_code);

                                    $this->info('  Creating new Interaction...');
                                    $this->createInteraction($event->id);
                                } else {
                                    $this->errors[] = 'Could not create an EVENT record.';

                                    $this->info('  -- Failed.');
                                    $this->info('Rolling back DB transaction');

                                    DB::rollBack();

                                    return false;
                                }

                                $this->info('  Generating email records...');
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

                                $this->info('  Generating phone number records...');
                                if (isset($data['btn']) && strlen(trim($data['btn'])) > 0) {
                                    $data['btn'] = preg_replace("/[^0-9]/", "", $data['btn']);
                                    generatePhoneNumberRecords(
                                        $data['btn'],
                                        3,
                                        $event->id
                                    );
                                }

                                $this->info('  Adding EventProduct to event...');
                                $this->addEventProduct(
                                    $data,
                                    $event->id,
                                    (isset($data['market']) && strtolower($data['market']) === 'Commercial')
                                        ? 2
                                        : 1
                                );

                                $this->addCustomField($this->brand_id, $event->id, "rate_expiration_date", $data['rate_expiration_date']);

                                $this->info('  Delivering notice...');
                                switch (strtolower($data['contract_delivery'])) {
                                    case 'email':
                                        if (isset($data['email_address'])) {
                                            try {
                                                $this->info('  -- Email to: ' . $data['email_address']);
                                                if (!$this->option('dryrun')) {
                                                    $subject = "RPA Notification Letter";
                                                    $email_address =base64_encode($data['email_address']);
                                                    $account_number =base64_encode($data['account_number1']);

                                                    // 'language_id' => $event->language_id,

                                                    Mail::send(
                                                        'emails.rpa_welcome30',
                                                        [
                                                            'event_id' => $event->id,
                                                            'language_id' => $event->language_id,
                                                            'email_address' =>$email_address,
                                                            'account_number' =>$account_number,

                                                        ],
                                                        function ($message) use ($subject, $data) {
                                                            $message->subject($subject);
                                                            $message->from('no-reply@tpvhub.com');
                                                            $message->to(trim($data['email_address']));
                                                        }
                                                    );
                                                } else {
                                                    $this->info('  -- Dry run. Email not sent.');
                                                }
                                            } catch (\Exception $e) {
                                                unset($contactError);
                                                $this->info(
                                                    '  -- Could not send email notification.' .
                                                        ' error: ' . $e
                                                );

                                                DB::rollBack();

                                                return false;
                                            }
                                        } else {
                                            $this->info(' -- No email address supplied. Nothing was sent.');
                                        }

                                        break;
                                    case 'text':
                                        $email_address =base64_encode($data['email_address']);
                                        if (empty($email_address)) {
                                            $email_address = base64_encode('None');
                                        }
                                        $account_number =base64_encode($data['account_number1']);
                                        if (isset($data['btn'])) {
                                            if ($event->language_id == 2) {
                                                $message = config('app.urls.clients') . '/rpa/welcome30/' . $event->id . '/' . $email_address . '/' . $account_number
                                                    . ' Haga clic en el enlace de arriba para ver los archivos adjuntos de Green Choice Energy.';
                                            } else {
                                                
                                                $message = config('app.urls.clients') . '/rpa/welcome30/' . $event->id . '/' . $email_address . '/' . $account_number
                                                    . ' Click the link above to see attachments from Green Choice Energy.';
                                            }

                                            try {
                                                if (!$this->option('dryrun')) {
                                                    $this->info('  -- SMS to: ' . $data['btn']);
                                                    $ret = SendSMS(
                                                        $data['btn'],
                                                        config('services.twilio.default_number'),
                                                        $message,
                                                        null,
                                                        $event->brand_id,
                                                        1
                                                    );
                                                    if (strstr($ret, 'ERROR') !== false) {
                                                        $this->info('  -- Could not send SMS notification. ' . $ret);
                                                    }
                                                } else {
                                                    $this->info('  -- Dry run. SMS not sent.');
                                                }
                                            } catch (TwilioException $e) {
                                                unset($contactError);
                                                $this->info(
                                                    'Could not send text notification.' .
                                                        ' error: ' . $e
                                                );

                                                DB::rollBack();

                                                return false;
                                            }
                                        } else {
                                            $this->info(' -- text selected but no btn');
                                        }

                                        break;

                                    default:
                                        $this->info('  -- Invalid (or no) delivery method supplied. Notice not sent.');
                                }

                                if (!$this->option('dryrun')) {
                                    $this->info('Committing DB transaction. Returning true.');
                                    DB::commit();

                                    return true;
                                } else {
                                    $this->info('Dry run. Updates not committed to DB.');
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->info('Exception: ' . $e);
                        $this->info('Rolling back DB transaction');
                        $this->errors[] = $e;

                        DB::rollBack();
                    }
                }
            }
        }

        $this->info("processData() end reached. Returning false.");
        return false;
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
                $this->info("Checking if file " . $filename . " exists");
                $exists = $filesystem->has($filename);
                if (!$exists) {
                    $msg = 'Filename ' . $filename . ' does not exist on FTP.';

                    info($msg);

                    SendTeamMessage(
                        'tech-automated-jobs',
                        $this->noticePrepend . ' (' . $this->env . ') :: ' . $msg
                    );
                } else {
                    $this->info("Reading file contents");
                    $contents = $filesystem->read($filename);
                    if ($contents) {
                        $this->info("Converting to an array");
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
                                'tech-automated-jobs',
                                $this->noticePrepend . ' (' . $this->env . ') :: Start processing of file "' . $filename . '" with ' . count($csv) . ' records.'
                            );
                        }

                        $count = 0; // Counts recrds
                        $rowCount = 0; // Counts rows, for console output

                        $this->info("Being processing loop");
                        foreach ($csv as $row) {
                            $rowCount++;

                            $this->info("----------------------------------------------------------------------");
                            $this->info("[ " . $rowCount . " / " . count($csv) . "]");

                            if ($header === null) {
                                $this->info("Header row. Skipping");
                                $header = $row;
                                continue;
                            }

                            $count++;

                            $result = $this->processData(array_combine($header, $row));

                            if ($result) {
                                $this->info('Success: ' . $row[0]);

                                if ($this->option('single')) {
                                    // For Debugging;  Only run 1 row and quit.
                                    $this->info(' --single flag was specified.');
                                    exit();
                                }
                            }

                            if ($count > 500) {
                                SendTeamMessage(
                                    'tech-automated-jobs',
                                    $this->noticePrepend
                                        . ' (' . $this->env . ') :: File "'
                                        . $filename . '" had more than 500 rows. (' . count($csv) - 1 . ')'
                                );
                                break;
                            }
                        }

                        SendTeamMessage('tech-automated-jobs', $this->noticePrepend . ' (' . $this->env . ') :: File "' . $filename . '" processing finished.');
                    } else {
                        $msg = 'Filename ' . $filename . ' is empty.';

                        info($msg);

                        SendTeamMessage(
                            'tech-automated-jobs',
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

    /**
     * Add custom field associated to the event or product
     */
    public function addCustomField($brand_id, $event_id, $name, $value, $product_id = null)
    {
        // Locate custom field
        $cf1 = CustomField::select(
            'custom_fields.id',
            'brand_custom_fields.id as bcf_id'
        )->leftJoin(
            'brand_custom_fields',
            'custom_fields.id',
            'brand_custom_fields.custom_field_id'
        )->where(
            'brand_custom_fields.brand_id',
            $brand_id
        )->where(
            'custom_fields.output_name',
            $name
        )->first();

        if (empty($cf1)) {
            $cf1 = new CustomField();
            $cf1->name = $name;
            $cf1->output_name = $name;
            $cf1->description = $name;
            $cf1->custom_field_type_id = 2;
            $cf1->save();
        }

        // Locate brand custom field
        if (empty($cf1->bcf_id)) {
            $bcf = CustomField::select(
                'custom_fields.id'
            )->leftJoin(
                'brand_custom_fields',
                'custom_fields.id',
                'brand_custom_fields.custom_field_id'
            )->where(
                'brand_custom_fields.brand_id',
                $brand_id
            );

            if (!empty($product_id)) {
                $bcf = $bcf->where(
                    'brand_custom_fields.associated_with_type',
                    'Product'
                )->where(
                    'brand_custom_fields.associated_with_id',
                    $product_id
                );
            } else {
                $bcf = $bcf->where('brand_custom_fields.associated_with_type', 'Event')
                    ->whereNull('brand_custom_fields.associated_with_id');
            }

            $bcf = $bcf->where(
                'custom_fields.output_name',
                $name
            )->first();
        } else {
            $bcf = BrandCustomField::find($cf1->bcf_id);
        }
        if (empty($bcf)) {
            $bcf = new BrandCustomField();

            if (!empty($product_id)) {
                $bcf->associated_with_type = 'Product';
                $bcf->associated_with_id = $product_id;
            } else {
                $bcf->associated_with_type = 'Event';
            }

            $bcf->custom_field_id = $cf1->id;
            $bcf->brand_id = $brand_id;
            $bcf->save();
        }

        $cfs = new CustomFieldStorage();
        $cfs->custom_field_id = $cf1->id;
        $cfs->value = $value;
        $cfs->event_id = $event_id;

        if (!empty($product_id)) {
            $cfs->product_id = $product_id;
        }

        $cfs->save();
    }
}
