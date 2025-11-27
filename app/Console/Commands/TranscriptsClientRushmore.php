<?php

namespace App\Console\Commands;
//
use Barryvdh\DomPDF\Facade as PDF;
//use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
//use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;
use App\Models\CustomFieldStorage;
use App\Models\ProviderIntegration;
use App\Models\StatsProduct;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\EventProductIdentifier;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\State;
use App\Models\EztpvConfigAdditional;
use App\Models\Upload;
use App\Models\Brand;
use App\Models\Interaction;
use App\Models\ScriptQuestions;
use App\Models\ScriptAnswer;
use App\Models\Script;

class TranscriptsClientRushmore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TranscriptsClientRushmore {--mode=} {--noftp} {--noemail} {--start-date=} {--end-date=}';

    /**
     * 
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transcripts Clients';
    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Rushmore transcripts upload to ftp';

     /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = 'faeb80e2-16ce-431c-bb54-1ade365eec16'; //  prod ID Rushmore
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
           // 'test' => ['curt.cadwell@answernet.com','curt@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
            'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['curt.cadwell@answernet.com','curt@tpv.com']
        ]
    ];

    /**
     * FTP Settings
     *
     * @var array
     */

     protected $ftpSettings = [
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 21,
        'root' => '/Contracts',
        'passive' => true,
        'ssl' => true,
        'timeout' => 30,
        'directoryPerm' => 0755,
    ];

    /**
     * Report start date
     *
     * @var mixed
     */
    protected $startDate = null;

    /**
     * Report end date
     *
     * @var mixed
     */
    protected $endDate = null;

    /**
     * Report mode: 'live' or 'test'.
     *
     * @var string
     */
    protected $mode = 'live'; // live mode by default.


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
        $this->startDate = Carbon::today('America/Chicago');
        $this->endDate = Carbon::tomorrow('America/Chicago')->add(-1, 'second');

        // Check mode. Leave in 'live' mode if not provided or an invalid value was provided.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            }
        }

        // Check for and validate custom report dates, but only if both start and end dates are provided
        if ($this->option('start-date') && $this->option('end-date')) {
            // TODO: We're trusting the dates the user is passing. Add validation for:
            // 1) valid dates were provided
            // 2) start date <= end date
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));
            $this->info('Using custom dates...');
        }

        // Get FTP details
        $pi = ProviderIntegration::where(
            'brand_id',
            $this->brandId
        )->where(
            'provider_integration_type_id',
            1
        )->where(
            'service_type_id',
            56
        )->first();

        if (empty($pi)) {
            $this->error("No credentials were found.");
            return -1;
        }

        $this->ftpSettings['host'] = $pi->hostname; 
        $this->ftpSettings['username'] = $pi->username;
        $this->ftpSettings['password'] = $pi->password;
      
        $adapter = new ftp(
            [
                'host' =>  $this->ftpSettings['host'],
                'port' => $this->ftpSettings['port'],
                'username' => $this->ftpSettings['username'],
                'password' => $this->ftpSettings['password'],
                'root' => $this->ftpSettings['root'],
                 'timeout' => $this->ftpSettings['timeout'],
                'directoryPerm' => $this->ftpSettings['directoryPerm'],
               // 'passive' => $this->ftpSettings['passive'],
                'ssl' => $this->ftpSettings['ssl'],
        
            ]
        );
        $filesystem = new Filesystem($adapter);
        // Build file name
         $this->info("Retrieving TPV data...");
         $transcriptData = StatsProduct::select(
            'stats_product.confirmation_code',
            'stats_product.interaction_created_at',
            'stats_product.eztpv_id',
            'stats_product.event_id',
            'stats_product.brand_id'
       )->leftJoin(
           'interactions',
           'stats_product.event_id',
           'interactions.event_id'
       )->whereDate(
           'stats_product.interaction_created_at',
           '>=',
           $this->startDate
       )->whereDate(
           'stats_product.interaction_created_at',
           '<=',
           $this->endDate
       )->where(
           'stats_product.brand_id',
           $this->brandId
        // )->where(
        //     'stats_product.confirmation_code',
        //     '02941374307'
       )->where(
            'interactions.interaction_type_id',
            6
    //    )->where(
    //         'stats_product.service_state',
    //         '<>',
    //         'IL'
       )->whereIn(
           'stats_product.result',
           ['sale', 'no sale']
       )->orderBy(
           'stats_product.interaction_created_at'
       )->distinct()->get();

       if (count($transcriptData) == 0) {
            $this->info('No records found. Exiting...');
            return 0;
        } else {
            $this->info(count($transcriptData) . ' Record(s) found.');
        }

           //          print_r($data->toArray());
           //          print_r(DB::getQueryLog());
        // $curt = $transcriptData->toArray();
        // Format and populate data CSV file
       $this->info('Formatting data...');
       foreach ($transcriptData as $transcriptRec) {

            // Create date string to use in filenames
            $file_date = Carbon::parse(
                $transcriptRec->interaction_created_at,
                'America/Chicago'
            )->format('YmdHis');

            // Create date string to use in foldername
            $folder_date = Carbon::parse(
                $transcriptRec->interaction_created_at,
                'America/Chicago'
            )->format('Y-m-d');
            

            $filename = $file_date . '_' . $transcriptRec->confirmation_code . '.pdf';
            $this->info('Processing ' . $transcriptRec->confirmation_code);

            /**
             * Transcript Summary Builder.
             *
             * @param string $eztpv_id - eztpv id
             */
            $result ='';
                $eztpv_id = $transcriptRec->eztpv_id;  // live transcript for test
                $logged_in = true;
                $works_for_id = null;

                if (!function_exists('array_key_first')) {
                    function array_key_first(array $arr)
                    {
                        foreach ($arr as $key => $unused) {
                            return $key;
                        }

                        return null;
                    }
                }

                $summary = [];
                $eps = EventProduct::whereHas(
                    'event',
                    function ($q) use ($eztpv_id) {
                        $q->where('eztpv_id', $eztpv_id);
                    }
                )->with(
                    [
                        'event',
                        'event.brand',
                        'addresses.address',
                        'promotion',
                        'identifiers',
                        'identifiers.utility_account_type',
                        'home_type',
                        'event.phone.phone_number',
                        'event.email.email_address',
                        'event.language',
                        'event.sales_agent',
                        'event.sales_agent.user',
                        'event_type',
                        'rate.product',
                        'rate.utility',
                        'rate.utility.utility',
                        'rate.product.rate_type',
                        'rate.product.term_type',
                        'rate.product.intro_term_type',
                        'rate.rate_currency',
                        'rate.rate_uom',
                        'rate.cancellation_fee_term_type'
                    ]
                )->orderBy(
                    'created_at'
                )->get();

                if ($eps->count() === 0) {
                    abort(400);
                }

                // echo "<pre>";
                // print_r($eps->toArray());
                // echo "</pre>";
                // exit();

                $custom_fields = [];

                foreach ($eps as $key => $ep) {
                    if (!$ep->event) {
                        return response()->json(
                            [
                                'error' => 'Unable to access this record. 1002'
                            ]
                        );
                    }
                    foreach ($ep->event->customFieldStorage as $cfs) {
                        $name = $cfs->customField['output_name'];
                        $custom_fields[$name] = $cfs->value;
                    }

                    if (!isset($summary['customer_service'])) {
                        $summary['customer_service'] = $ep->rate->utility->utility->customer_service ? $this->formatPhoneNumber($ep->rate->utility->utility->customer_service) : 'N/A';
                    }


                    if (!isset($summary['last_name'])) {
                        $summary['last_name'] = $ep->bill_last_name ? $ep->bill_last_name : 'N/A';
                    }

                    if (!isset($summary['admin_fee'])) {
                        $summary['admin_fee'] = $ep->rate->admin_fee ? $ep->rate->admin_fee : 'N/A';
                    }

                    if (!isset($summary['esi_id'])) {
                        $summary['esi_id'] = $ep->identifiers[0]->identifier ? $ep->identifiers[0]->identifier : 'N/A';
                    }

                    if (!isset($summary['commodity'])) {
                        if (isset($ep->utility_supported_fuel->utility_fuel_type->utility_type)) {
                            $summary['commodity'] = $ep->utility_supported_fuel->utility_fuel_type->utility_type;
                        }
                    }

                    if (!isset($summary['puct_license'])) {
                        if (isset($ep->event->brand->puct_license)) {
                            $summary['puct_license'] = $ep->event->brand->puct_license;
                        }
                    }

                    if (!isset($summary['event_date'])) {
                        if (isset($ep->event->created_at)) {
                            $summary['event_date'] = $ep->event->created_at->format('F j, Y');
                        }
                    }

                    if (!isset($summary['event_time'])) {
                        if (isset($ep->event->created_at)) {
                            $summary['event_time'] = $ep->event->created_at->format('g:i a');
                        }
                    }

                    if (!isset($summary['event_id'])) {
                        if (isset($ep->event->id)) {
                            $summary['event_id'] = $ep->event->id;
                        }
                    }

                    if (!isset($summary['brand_id'])) {
                        if (isset($ep->event->brand->id)) {
                            $summary['brand_id'] = $ep->event->brand->id;
                        }
                    }

                    if (!isset($summary['brand_name'])) {
                        if (isset($ep->event->brand->name)) {
                            $summary['brand_name'] = $ep->event->brand->name;
                        }
                    }

                    if (!isset($summary['brand_logo_id'])) {
                        if (isset($ep->event->brand->logo_path)) {
                            $summary['brand_logo_id'] = $ep->event->brand->logo_path;
                        }
                    }

                    if (!isset($summary['brand_address'])) {
                        if (isset($ep->event->brand->address)) {
                            $summary['brand_address'] = $ep->event->brand->address;
                        }
                    }

                    if (!isset($summary['brand_city'])) {
                        if (isset($ep->event->brand->city)) {
                            $summary['brand_city'] = $ep->event->brand->city;
                        }
                    }

                    if (!isset($summary['brand_state'])) {
                        if (isset($ep->event->brand->state)) {
                            $state = State::find($ep->event->brand->state);
                            if ($state) {
                                $summary['brand_state'] = $state->state_abbrev;
                            }
                        }
                    }

                    if (!isset($summary['brand_zip'])) {
                        if (isset($ep->event->brand->zip)) {
                            $summary['brand_zip'] = $ep->event->brand->zip;
                        }
                    }

                    if (!isset($summary['brand_email'])) {
                        if (isset($ep->event->brand->email_address)) {
                            $summary['brand_email'] = $ep->event->brand->email_address;
                        }
                    }

                    if (!isset($summary['brand_email'])) {
                        if (isset($ep->event->brand->email_address)) {
                            $summary['brand_email'] = $ep->event->brand->email_address;
                        }
                    }

                    if (!isset($summary['brand_service_number'])) {
                        if (isset($ep->event->brand->service_number)) {
                            $summary['brand_service_number'] = $this->formatPhoneNumber(
                                $ep->event->brand->service_number
                            );
                        }
                    }

                    if (!isset($summary['eztpv_id'])) {
                        if (isset($ep->event->eztpv->id)) {
                            $summary['eztpv_id'] = $ep->event->eztpv->id;
                        }
                    }

                    if (!isset($summary['signature'])) {
                        if (
                            isset($ep->event->eztpv->signature_customer)
                            && isset($ep->event->eztpv->signature_customer->signature)
                        ) {
                            $summary['signature'] = $ep->event->eztpv->signature_customer->signature;
                        } elseif (
                            isset($ep->event->eztpv->signature)
                        ) {
                            $summary['signature'] = $ep->event->eztpv->signature;
                        }
                    }

                    if (!isset($summary['signature_date'])) {
                        if (
                            isset($ep->event->eztpv->signature_customer)
                            && isset($ep->event->eztpv->signature_customer->updated_at)
                        ) {
                            $summary['signature_date'] = $ep->event->eztpv->signature_customer->updated_at;
                        } elseif (isset($ep->event->eztpv->signature_date)) {
                            $summary['signature_date'] = $ep->event->eztpv->signature_date;
                        }
                    }

                    if (
                        !isset($summary['signature2'])
                        && isset($ep->event->eztpv->signature_agent)
                        && isset($ep->event->eztpv->signature_agent->signature)
                    ) {
                        $summary['signature2'] = $ep->event->eztpv->signature_agent->signature;
                    }

                    if (
                        !isset($summary['signature2_date'])
                        && isset($ep->event->eztpv->signature_agent)
                        && isset($ep->event->eztpv->signature_agent->updated_at)
                    ) {
                        $summary['signature2_date'] = $ep->event->eztpv->signature_agent->updated_at;
                    }

                    if (!isset($summary['created_at'])) {
                        $summary['created_at'] = $ep->event->created_at->toDateTimeString();
                    }

                    if (!isset($summary['ip_addr'])) {
                        $summary['ip_addr'] = $ep->event->ip_addr;
                    }

                    if (
                        (empty($summary['ip_addr']) || $summary['ip_addr'] === '0.0.0.0')
                        && !empty($ep->event->eztpv->ip_addr)
                        && $ep->event->eztpv->ip_addr !== '0.0.0.0'
                    ) {
                        $summary['ip_addr'] = $ep->event->eztpv->ip_addr;
                    }

                    if (!isset($summary['gps_coords'])) {
                        $summary['gps_coords'] = $ep->event->gps_coords;
                    }

                    if (!isset($summary['confirmation_code'])) {
                        $summary['confirmation_code'] = $ep->event->confirmation_code;
                    }

                    if (!isset($summary['auth_name'])) {
                        if (!$logged_in) {
                            $summary['auth_name'] = $ep->auth_first_name
                                . ' ' . substr($ep->auth_last_name, 0, 1);
                        } else {
                            $summary['auth_name'] = $ep->auth_first_name
                                . ' ' . $ep->auth_last_name;
                        }
                    }

                    if (!isset($summary['auth_last_name'])) {
                        if (!$logged_in) {
                            $summary['auth_last_name'] = substr($ep->auth_last_name, 0, 1);
                        } else {
                            $summary['auth_last_name'] = $ep->auth_last_name;
                        }
                    }

                    if (!isset($summary['auth_first_name'])) {
                        $summary['auth_first_name'] = $ep->auth_first_name;
                    }

                    if (!isset($summary['bill_name'])) {
                        if (!$logged_in) {
                            $summary['bill_name'] = $ep->bill_first_name
                                . ' ' . substr($ep->bill_last_name, 0, 1);
                        } else {
                            $summary['bill_name'] = $ep->bill_first_name
                                . ' ' . $ep->bill_last_name;
                        }
                    }

                    if (!isset($summary['bill_last_name'])) {
                        if (!$logged_in) {
                            $summary['bill_last_name'] = substr($ep->bill_last_name, 0, 1);
                        } else {
                            $summary['bill_last_name'] = $ep->bill_last_name;
                        }
                    }

                    if (!isset($summary['bill_first_name'])) {
                        $summary['bill_first_name'] = $ep->bill_first_name;
                    }

                    if (!isset($summary['auth_relationship'])) {
                        $summary['auth_relationship'] = $ep->auth_relationship;
                    }

                    if (
                        !isset($summary['email'])
                        && isset($ep->event->email->email_address->email_address)
                    ) {
                        $summary['email'] = $ep->event
                            ->email->email_address->email_address;
                    }

                    if (!isset($summary['phone'])) {
                        if (isset($ep->event->phone->phone_number->phone_number)) {
                            if (!$logged_in) {
                                $summary['phone'] = $this->maskNumber(
                                    str_replace(
                                        '-',
                                        '',
                                        $this->formatPhoneNumber($ep->event->phone->phone_number->phone_number)
                                    )
                                );
                            } else {
                                $summary['phone'] = str_replace(
                                    '-',
                                    '',
                                    $this->formatPhoneNumber($ep->event->phone->phone_number->phone_number)
                                );
                            }
                        }
                    }

                    if (!isset($summary['language'])) {
                        $summary['language'] = $ep->event->language->language;
                    }

                    if (!isset($summary['sales_agent_name'])) {
                        $summary['sales_agent_name'] = @$ep->event->sales_agent->user->first_name
                            . ' ' . @$ep->event->sales_agent->user->last_name;
                    }

                    if (!isset($summary['sales_agent_id'])) {
                        $summary['sales_agent_id']
                            = @$ep->event->sales_agent->tsr_id;
                    }

                    $state_id = @$ep->rate->utility->utility->state_id;
                    if ($ep->event->office_id && $ep->event->channel_id) {
                        $eca = EztpvConfigAdditional::where(
                            'office_id',
                            $ep->event->office_id
                        )->where(
                            'channel_id',
                            $ep->event->channel_id
                        )->where(
                            'state_id',
                            $state_id
                        )->first();
                        if ($eca) {
                            $summary['tcs'] = $eca->tcs;
                        }
                    }

                    $summary['product'][$key] = $ep->toArray();
                }

                // echo "<pre>";
                // print_r($custom_fields);
                // exit();

                $language = (isset($summary['language']) && 'Spanish' == $summary['language'])
                    ? 'es'
                    : 'en';

                // echo "<pre>";
                // print_r($summary);
                // echo "</pre>";
                // exit();

                if (isset($summary['brand_logo_id'])) {
                    $logoPath = Upload::select(
                        'filename'
                    )->where(
                        'uploads.id',
                        $summary['brand_logo_id']
                    )->withTrashed()->first();

                    // echo "<pre>";
                    // print_r($logoPath->toArray());
                    // exit();
                }

                $addresses = '';
                $identifiers = [];
                $dual = false;
                $promo = null;

                $summary['state'] = null;
                if (isset($summary['product'])) {
                    foreach ($summary['product'] as $product) {
                        $promo = $product['promotion'];
                        $billing_address = null;
                        $service_address = null;
                        $ids = [];

                        foreach ($product['identifiers'] as $identifier) {
                            if (!$logged_in) {
                                $ids[] = $this->maskNumber($identifier['identifier'])
                                    . ' (' . $this->identifierHydrate(
                                        $language,
                                        $identifier['utility_account_type']['account_type']
                                    ) . ')<br />';
                            } else {
                                $ids[] = $identifier['identifier']
                                    . ' (' . $this->identifierHydrate(
                                        $language,
                                        $identifier['utility_account_type']['account_type']
                                    ) . ')<br />';
                            }
                        }

                        
                        foreach ($product['addresses'] as $address) {
                            if (empty($summary['state'])) {
                                $summary['state'] = $address['address']['state_province'];
                            }

                            $address_string = $address['address']['line_1'];

                            if (isset($address['address']['line_2'])) {
                                $address_string .= ' ' . $address['address']['line_2'];
                            }

                            $address_string .= ' ' . $address['address']['city']
                                . ', ' . $address['address']['state_province']
                                . ' ' . $address['address']['zip'];

                            if ('e_p:service' == $address['id_type']) {
                                $service_address = $address_string;
                            } else {
                                $billing_address = $address_string;
                            }
                        }

                        $type = ('Electric' == $product['event_type']['event_type'])
                            ? 'electric' : 'gas';

                        if (!$dual) {
                            $dual = (isset($product['linked_to']))
                                ? true : false;
                        }

                        $ramaining_term[$type] = (isset($product['rate']['product']['term']) ? $product['rate']['product']['term'] - (isset($product['rate']['product']['intro_term']) ? $product['rate']['product']['intro_term'] : 0) : 'N/A' ) . ' ' . (isset($product['rate']['product']['term_type']['term_type']) ? $product['rate']['product']['term_type']['term_type'] : '');

                        $identifiers[$service_address]['dual'] = $dual;
                        $identifiers[$service_address][$type] = [
                            'linked_to' => $product['linked_to'],
                            'type' => $product['event_type']['event_type'],
                            'identifiers' => $ids,
                            'service_address' => (!$logged_in)
                                ? 'REDACTED'
                                : $service_address,
                            'billing_address' => (!$logged_in)
                                ? 'REDACTED'
                                : $billing_address,
                            'rate' => $product['rate'],
                        ];
                    }
                }

                ksort($identifiers);

                // echo "<pre>";
                // print_r($identifiers);
                // exit();

                if (isset($promo) && isset($promo['promo_text_english'])) {
                    $promo_text = ($language === 'en')
                        ? $promo['promo_text_english']
                        : $promo['promo_text_spanish'];
                    $promo_code = $promo['promotion_code'];
                    $promo_key = $promo['promotion_key'];
                    $promo_name = $promo['name'];
                    $promo_reward = $promo['reward'];
                } else {
                    $promo_text = null;
                    $promo_code = null;
                    $promo_name = null;
                    $promo_reward = null;
                    $promo_key = null;
                }

                $list = [
                    'client.name' => Brand::select('name')->where(
                        'id',
                        $summary['brand_id']
                    )->first()->name,
                    'user.name' => $summary['auth_name'],
                    'date' => $summary['event_date'],
                    'time' => $summary['event_time'],
                    'user.phone' => $summary['phone'],
                    'user.email' => (isset($summary['email']) && $logged_in)
                        ? $summary['email']
                        : 'REDACTED',
                    'account.bill_name' => $summary['bill_name'],
                    'event.confirmation' => $summary['confirmation_code'],
                    'client.service_phone' => $summary['brand_service_number'],
                    'account.state' => $summary['state'],
                    'event.id_state' => $summary['state'],
                    'product.fuel' => $summary['commodity'],
                    'account.type' => $summary['commodity'],
                    'user.last_name' => $summary['last_name'],
                    'product.fuel.electric' => 'Electric',
                    'product.admin_fee' => $summary['admin_fee'],
                    'product.fuel.gas' => 'Natural Gas',
                    'product.promo_text' => $promo_text,
                    'product.promo_code' => $promo_code,
                    'product.promo_key' => $promo_key,
                    'product.promo_name' => $promo_name,
                    'product.promo_reward' => $promo_reward,
                    'account.number.esi_id' => $summary['esi_id'],
                    'utility.customer_service' => $summary['customer_service'],
                    'account.bill_name.electric' => $summary['bill_name'],
                    'user.relationship' => $summary['auth_relationship'],
                ];

                if(isset($ramaining_term['electric']))
                {
                    $list['product.remaining_term.electric'] = $ramaining_term['electric'];
                }

                if(isset($ramaining_term['gas'])){
                    $list['product.remaining_term.gas'] = $ramaining_term['gas'];
                }

                foreach ($custom_fields as $key => $value) {
                    $list['custom.' . $key] = $value;
                }

                if (isset($service_address)) {
                    $list['account.service_address'] = (!$logged_in)
                        ? 'REDACTED'
                        : $service_address;
                }

                if (isset($billing_address)) {
                    $list['account.billing_address'] = (!$logged_in)
                        ? 'REDACTED'
                        : $billing_address;
                }

                if (isset($ids) && isset($ids[0])) {
                    $list['account.number'] = strip_tags($ids[0]);
                }

                // echo "<pre>";
                // print_r($list);
                // exit();

                $showTranscript = false;
                $digitalInteraction = Interaction::where(
                    'interactions.interaction_type_id',
                    6
                )->where(
                    'interactions.event_id',
                    $summary['event_id']
                )->first();
                if ($digitalInteraction) {
                    $showTranscript = true;

                    $answers = ScriptAnswer::select(
                        'script_answers.created_at',
                        'script_answers.question_id',
                        'script_answers.answer',
                        'script_questions.section_id',
                        'script_questions.subsection_id',
                        'script_questions.question_id as sq_question_id'
                    )->leftJoin(
                        'script_questions',
                        'script_answers.question_id',
                        'script_questions.id'
                    )->where(
                        'script_answers.interaction_id',
                        $digitalInteraction->id
                    )->groupBy(
                        'script_answers.question_id'
                    )->orderBy('script_questions.section_id', 'ASC')
                        ->orderBy('script_questions.subsection_id', 'ASC')
                        ->orderBy('script_questions.question_id', 'ASC')->get();

                    foreach ($answers as $answer) {
                        $out = [];
                        preg_match_all(
                            '/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89aAbB][a-f0-9]{3}-[a-f0-9]{12}/',
                            $answer->question_id,
                            $out
                        );

                        $guid = (isset($out[0][0]))
                            ? $out[0][0]
                            : null;
                        $answer->showIdents = false;
                        if ($guid) {
                            $answer->question_id_cleaned = $guid;
                            $question = ScriptQuestions::withTrashed()->find($guid);
                            if ($question) {
                                $answer->section_id = $question->section_id;
                                $answer->subsection_id = $question->subsection_id;
                                $answer->script_question_id = $question->question_id;
                                if (isset($question->question['confirmIdents'])) {
                                    $answer->showIdents = $question->question['confirmIdents'] == true;
                                }

                                if ('es' == $language) {
                                    $answer->question = $question->question['spanish'];
                                } else {
                                    $answer->question = $question->question['english'];
                                }
                            }
                        } else {
                            $answer->question_id_cleaned = str_replace(
                                '----',
                                '',
                                preg_replace(
                                    '/[0-9]+/',
                                    '',
                                    $answer->question_id
                                )
                            );
                        }

                        $answer = $this->hydrateVars($answer, $list);
                    }

                    $sections = [];
                    $verifyIndex = 0;
                    $dupeArray = [];

                    // echo "<pre>";
                    // print_r($answers);
                    // exit();

                    // echo "<pre>";
                    // print_r($identifiers);
                    // exit();

                    $answers = $answers->sortBy('section_id')->sortBy('subsection_id')->sortBy('script_question_id');

                    foreach ($answers as $answer) {
                        if (isset($answer->section_id) && in_array($answer->section_id, [0, 4, 100])) {
                            if (!isset($list['utility.name.energy'])) {
                                $key = array_key_first($identifiers);
                                $current_address = $identifiers[$key];

                                $list['utility.name.electric']
                                    = (isset($current_address['electric'])) ? $current_address['electric']['rate']['utility']['utility']['name'] : null;
                            }

                            switch ($answer->section_id) {
                                case 0:
                                    $sections['preamble'][] = $this->hydrateVars($answer, $list)->toArray();

                                    break;
                                case 4:
                                    $sections['postverify'][] = $this->hydrateVars($answer, $list)->toArray();

                                    break;
                                case 100:
                                    $sections['postamble'][] = $this->hydrateVars($answer, $list)->toArray();

                                    break;
                                default:
                                    $ret = $this->hydrateVars($answer, $list);
                                    if ($ret->showIdents) {
                                        $ret->question .= '<br>&nbsp;<br>' . $identifier_html;
                                    }
                                    $sections['verify'][$verifyIndex][] = $ret->toArray();

                                    break;
                            }
                        } else {
                            if (in_array($answer->question_id_cleaned, $dupeArray)) {
                                $dupeArray = [];
                                ++$verifyIndex;
                            }

                            $keys = array_keys($identifiers);

                            if (!isset($keys[$verifyIndex])) {
                                $verifyIndex = --$verifyIndex;
                            }

                            $current_address = $identifiers[$keys[$verifyIndex]];

                            // info('CURRENT ADDRESS is ' . print_r($current_address, true));

                            $identifier_html = '<table class="table">';
                            $addresses = [];
                            if ($current_address['dual']) {
                                foreach ($current_address as $k => $v) {
                                    if ('dual' !== $k) {
                                        if (
                                            !empty($current_address[$k]['service_address'])
                                            && !in_array($current_address[$k]['service_address'], $addresses)
                                        ) {
                                            $addresses[] = $current_address[$k]['service_address'];
                                        }

                                        if (
                                            !empty($current_address[$k]['billing_address'])
                                            && !in_array($current_address[$k]['billing_address'], $addresses)
                                        ) {
                                            $addresses[] = $current_address[$k]['billing_address'];
                                        }

                                        $identifier_html .= '<tr>';

                                        $identifier_html .= $this->addressHydrate($language, $current_address[$k]['type']);

                                        $identifier_html .= '<td>';

                                        if (isset($current_address[$k]['identifiers'])) {
                                            foreach ($current_address[$k]['identifiers'] as $ids) {
                                                $identifier_html .= $ids . '<br />';
                                            }
                                        }

                                        $identifier_html .= '</td>';
                                        $identifier_html .= '</tr>';
                                    }
                                }

                                $list['product.fuel'] = 'electric & gas';

                                if (empty($current_address['electric'])) {
                                    info('Field `electric` is not set', [$current_address]);
                                    return response()->json(
                                        [
                                            'error' => 'Unable to access this record. 1003'
                                        ]
                                    );
                                } else {
                                    $list['product.monthly_fee.electric']
                                        = $current_address['electric']['rate']['rate_monthly_fee'] ? $current_address['electric']['rate']['rate_monthly_fee'] : 'N/A';
                                    $list['product.term.electric']
                                        = $current_address['electric']['rate']['product']['term'];
                                    $list['product.intro_term.electric']
                                        = $current_address['electric']['rate']['product']['intro_term'];
                                    $list['product.term_type.electric']
                                        = @$current_address['electric']['rate']['product']['term_type']['term_type'];
                                    $list['product.intro_term_type.electric']
                                        = @$current_address['electric']['rate']['product']['intro_term_type']['term_type'];
                                    $list['product.amount.electric']
                                        = $current_address['electric']['rate']['rate_amount'];
                                    $list['product.cancellation_fee.electric']
                                        = $current_address['electric']['rate']['cancellation_fee'] ? $current_address['electric']['rate']['cancellation_fee'] : 'N/A';
                                    $list['product.intro_amount.electric']
                                        = $current_address['electric']['rate']['intro_rate_amount'] ? $current_address['electric']['rate']['intro_rate_amount'] : 'N/A';
                                    $list['product.currency.electric']
                                        = $current_address['electric']['rate']['rate_currency']['currency'];
                                    $list['product.uom.electric']
                                        = $current_address['electric']['rate']['rate_uom']['uom'];
                                    $list['utility.name']
                                        = $current_address['electric']['rate']['utility']['utility']['name'];
                                    $list['utility.name.electric']
                                        = (isset($current_address['electric'])) ? $current_address['electric']['rate']['utility']['utility']['name'] : null;
                                    $list['utility.service_phone.electric']
                                        = $this->formatPhoneNumber($current_address['electric']['rate']['utility']['utility']['customer_service']);
                                    $list['utility.customer_service.electric']
                                        = $this->formatPhoneNumber($current_address['electric']['rate']['utility']['utility']['customer_service']);
                                    $list['utility.customer_service']
                                        = $this->formatPhoneNumber($current_address['electric']['rate']['utility']['utility']['customer_service']);

                                    // estimated cost
                                    $rateAmount = $current_address['electric']['rate']['rate_amount'];
                                    $currency = $current_address['electric']['rate']['rate_currency_id'];
                                    if ($currency === 1) {
                                        $rateAmount /= 100;
                                    }
                                    $monthlyFee = $current_address['electric']['rate']['rate_amount'] !== null ? $current_address['electric']['rate']['rate_amount'] : 0;

                                    $list['product.estimated_total_cost.electric.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)));
                                    $list['product.estimated_total_cost.electric.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)));
                                    $list['product.estimated_total_cost.electric.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)));
                                    $list['product.estimated_total_cost_x100.electric.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)) * 100);
                                    $list['product.estimated_total_cost_x100.electric.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)) * 100);
                                    $list['product.estimated_total_cost_x100.electric.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)) * 100);

                                    $list['product.estimated_cost.electric.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500));
                                    $list['product.estimated_cost.electric.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000));
                                    $list['product.estimated_cost.electric.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500));
                                    $list['product.estimated_cost_x100.electric.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500) * 100);
                                    $list['product.estimated_cost_x100.electric.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000) * 100);
                                    $list['product.estimated_cost_x100.electric.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500) * 100);

                                    $list['product.custom_data_1.electric'] = $current_address['electric']['rate']['custom_data_1'];
                                    $list['product.custom_data_2.electric'] = $current_address['electric']['rate']['custom_data_2'];
                                    $list['product.custom_data_3.electric'] = $current_address['electric']['rate']['custom_data_3'];
                                    $list['product.custom_data_4.electric'] = $current_address['electric']['rate']['custom_data_4'];
                                    $list['product.custom_data_5.electric'] = $current_address['electric']['rate']['custom_data_5'];
                                }

                                if (empty($current_address['gas'])) {
                                    info('Field `gas` is not set', [$current_address]);
                                    return response()->json(
                                        [
                                            'error' => 'Unable to access this record. 1004'
                                        ]
                                    );
                                } else {
                                    $list['product.monthly_fee.gas']
                                        = $current_address['gas']['rate']['rate_monthly_fee'] ? $current_address['gas']['rate']['rate_monthly_fee'] : 'N/A';
                                    $list['product.term.gas']
                                        = $current_address['gas']['rate']['product']['term'];
                                    $list['product.intro_term.gas']
                                        = $current_address['gas']['rate']['product']['intro_term'];
                                    $list['product.term_type.gas']
                                        = @$current_address['gas']['rate']['product']['term_type']['term_type'];
                                    $list['product.intro_term_type.gas']
                                        = @$current_address['gas']['rate']['product']['intro_term_type']['term_type'];
                                    $list['product.amount.gas']
                                        = $current_address['gas']['rate']['rate_amount'];
                                    $list['product.cancellation_fee.gas']
                                        = $current_address['gas']['rate']['cancellation_fee'] ? $current_address['gas']['rate']['cancellation_fee'] : 'N/A';
                                    $list['product.intro_amount.gas']
                                        = $current_address['gas']['rate']['intro_rate_amount'] ? $current_address['gas']['rate']['intro_rate_amount'] : 'N/A';
                                    $list['product.currency.gas']
                                        = $current_address['gas']['rate']['rate_currency']['currency'];
                                    $list['product.uom.gas']
                                        = $current_address['gas']['rate']['rate_uom']['uom'];
                                    $list['utility.name']
                                        = $current_address['gas']['rate']['utility']['utility']['name'];
                                    $list['utility.name.gas']
                                        = $current_address['gas']['rate']['utility']['utility']['name'];
                                    $list['utility.service_phone.gas']
                                        = $this->formatPhoneNumber($current_address['gas']['rate']['utility']['utility']['customer_service']);
                                    $list['utility.customer_service.gas']
                                        = $this->formatPhoneNumber($current_address['gas']['rate']['utility']['utility']['customer_service']);
                                    $list['utility.customer_service']
                                        = $this->formatPhoneNumber($current_address['gas']['rate']['utility']['utility']['customer_service']);

                                    // estimated cost
                                    $rateAmount = $current_address['gas']['rate']['rate_amount'];
                                    $currency = $current_address['gas']['rate']['rate_currency_id'];
                                    if ($currency === 1) {
                                        $rateAmount /= 100;
                                    }
                                    $monthlyFee = $current_address['gas']['rate']['rate_amount'] !== null ? $current_address['gas']['rate']['rate_amount'] : 0;

                                    $list['product.estimated_total_cost.gas.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)));
                                    $list['product.estimated_total_cost.gas.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)));
                                    $list['product.estimated_total_cost.gas.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)));
                                    $list['product.estimated_total_cost_x100.gas.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)) * 100);
                                    $list['product.estimated_total_cost_x100.gas.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)) * 100);
                                    $list['product.estimated_total_cost_x100.gas.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)) * 100);

                                    $list['product.estimated_cost.gas.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500));
                                    $list['product.estimated_cost.gas.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000));
                                    $list['product.estimated_cost.gas.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500));
                                    $list['product.estimated_cost_x100.gas.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500) * 100);
                                    $list['product.estimated_cost_x100.gas.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000) * 100);
                                    $list['product.estimated_cost_x100.gas.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500) * 100);

                                    $list['product.custom_data_1.gas'] = $current_address['gas']['rate']['custom_data_1'];
                                    $list['product.custom_data_2.gas'] = $current_address['gas']['rate']['custom_data_2'];
                                    $list['product.custom_data_3.gas'] = $current_address['gas']['rate']['custom_data_3'];
                                    $list['product.custom_data_4.gas'] = $current_address['gas']['rate']['custom_data_4'];
                                    $list['product.custom_data_5.gas'] = $current_address['gas']['rate']['custom_data_5'];
                                }
                            } else {
                                $array = (isset($current_address['gas']))
                                    ? $current_address['gas']
                                    : $current_address['electric'];

                                if ($array['service_address']) {
                                    $addresses[] = $array['service_address'];
                                }

                                if ($array['billing_address']) {
                                    $addresses[] = $array['billing_address'];
                                }

                                $identifier_html .= '<tr>';

                                $identifier_html .= $this->addressHydrate($language, $array['type']);

                                $identifier_html .= '<td>';

                                foreach ($array['identifiers'] as $ids) {
                                    $identifier_html .= $ids . '<br />';
                                }

                                $identifier_html .= '</td>';
                                $identifier_html .= '</tr>';

                                $list['product.amount']
                                    = @$array['rate']['rate_amount'];
                                $list['product.term']
                                    = @$array['rate']['product']['term'];
                                $list['product.intro_term']
                                    = @$array['rate']['product']['intro_term'];
                                $list['product.cancellation_fee']
                                    = @$array['rate']['cancellation_fee'];
                                $list['product.monthly_fee']
                                    = @$array['rate']['rate_monthly_fee'];
                                $list['product.term_type']
                                    = @$array['rate']['product']['term_type']['term_type'];
                                $list['product.intro_term_type']
                                    = @$array['rate']['product']['intro_term_type']['term_type'];
                                $list['product.intro_amount']
                                    = $array['rate']['intro_rate_amount'];
                                $list['product.currency']
                                    = $array['rate']['rate_currency']['currency'];
                                $list['product.uom']
                                    = $array['rate']['rate_uom']['uom'];
                                $list['utility.name']
                                    = $array['rate']['utility']['utility']['name'];
                                $list['utility.customer_service']
                                    = $this->formatPhoneNumber($array['rate']['utility']['utility']['customer_service']);
                                $list['utility.service_phone']
                                    = $this->formatPhoneNumber($array['rate']['utility']['utility']['customer_service']);
                                // estimated cost
                                $rateAmount = $array['rate']['rate_amount'];
                                $currency = $array['rate']['rate_currency_id'];
                                if ($currency === 1) {
                                    $rateAmount /= 100;
                                }
                                $monthlyFee = $array['rate']['rate_amount'] !== null ? $array['rate']['rate_amount'] : 0;

                                $list['product.estimated_total_cost.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)));
                                $list['product.estimated_total_cost.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)));
                                $list['product.estimated_total_cost.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)));
                                $list['product.estimated_total_cost_x100.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)) * 100);
                                $list['product.estimated_total_cost_x100.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)) * 100);
                                $list['product.estimated_total_cost_x100.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)) * 100);

                                $list['product.estimated_cost.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500));
                                $list['product.estimated_cost.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000));
                                $list['product.estimated_cost.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500));
                                $list['product.estimated_cost_x100.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500) * 100);
                                $list['product.estimated_cost_x100.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000) * 100);
                                $list['product.estimated_cost_x100.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500) * 100);

                                $list['product.custom_data_1'] = $array['rate']['custom_data_1'];
                                $list['product.custom_data_2'] = $array['rate']['custom_data_2'];
                                $list['product.custom_data_3'] = $array['rate']['custom_data_3'];
                                $list['product.custom_data_4'] = $array['rate']['custom_data_4'];
                                $list['product.custom_data_5'] = $array['rate']['custom_data_5'];

                                if (isset($current_address['gas'])) {
                                    $list['product.fuel'] = 'gas';
                                    $list['product.amount.gas']
                                        = $current_address['gas']['rate']['rate_amount'];
                                    $list['product.term.gas']
                                        = $current_address['gas']['rate']['product']['term'];
                                    $list['product.term_type.gas']
                                        = @$current_address['gas']['rate']['product']['term_type']['term_type'];
                                    $list['product.intro_term.gas']
                                        = $current_address['gas']['rate']['product']['intro_term'];
                                    $list['product.intro_term_type.gas']
                                        = @$current_address['gas']['rate']['product']['intro_term_type']['term_type'];
                                    $list['product.cancellation_fee.gas']
                                        = $current_address['gas']['rate']['cancellation_fee'] ? $current_address['gas']['rate']['cancellation_fee'] : 'N/A';
                                    $list['product.intro_amount.gas']
                                        = $current_address['gas']['rate']['intro_rate_amount'] ? $current_address['gas']['rate']['intro_rate_amount'] : 'N/A';
                                    $list['product.currency.gas']
                                        = $current_address['gas']['rate']['rate_currency']['currency'];
                                    $list['product.uom.gas']
                                        = $current_address['gas']['rate']['rate_uom']['uom'];
                                    $list['utility.name.gas']
                                        = $current_address['gas']['rate']['utility']['utility']['name'];
                                    $list['product.monthly_fee.gas']
                                        = $current_address['gas']['rate']['rate_monthly_fee'] ? $current_address['gas']['rate']['rate_monthly_fee'] : 'N/A';
                                    $list['utility.service_phone.gas']
                                        = $this->formatPhoneNumber($current_address['gas']['rate']['utility']['utility']['customer_service']);
                                    $list['utility.customer_service.gas']
                                        = $this->formatPhoneNumber($current_address['gas']['rate']['utility']['utility']['customer_service']);
                                    $list['utility.customer_service']
                                        = $this->formatPhoneNumber($current_address['gas']['rate']['utility']['utility']['customer_service']);

                                    // estimated cost
                                    $rateAmount = $current_address['gas']['rate']['rate_amount'];
                                    $currency = $current_address['gas']['rate']['rate_currency_id'];
                                    if ($currency === 1) {
                                        $rateAmount /= 100;
                                    }
                                    $monthlyFee = $current_address['gas']['rate']['rate_amount'] !== null ? $current_address['gas']['rate']['rate_amount'] : 0;

                                    $list['product.estimated_total_cost.gas.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)));
                                    $list['product.estimated_total_cost.gas.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)));
                                    $list['product.estimated_total_cost.gas.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)));
                                    $list['product.estimated_total_cost_x100.gas.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)) * 100);
                                    $list['product.estimated_total_cost_x100.gas.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)) * 100);
                                    $list['product.estimated_total_cost_x100.gas.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)) * 100);

                                    $list['product.estimated_cost.gas.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500));
                                    $list['product.estimated_cost.gas.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000));
                                    $list['product.estimated_cost.gas.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500));
                                    $list['product.estimated_cost_x100.gas.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500) * 100);
                                    $list['product.estimated_cost_x100.gas.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000) * 100);
                                    $list['product.estimated_cost_x100.gas.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500) * 100);

                                    $list['product.custom_data_1.gas'] = $current_address['gas']['rate']['custom_data_1'];
                                    $list['product.custom_data_2.gas'] = $current_address['gas']['rate']['custom_data_2'];
                                    $list['product.custom_data_3.gas'] = $current_address['gas']['rate']['custom_data_3'];
                                    $list['product.custom_data_4.gas'] = $current_address['gas']['rate']['custom_data_4'];
                                    $list['product.custom_data_5.gas'] = $current_address['gas']['rate']['custom_data_5'];
                                } else {
                                    $list['product.fuel'] = 'electric';
                                    $list['product.amount.electric']
                                        = $current_address['electric']['rate']['rate_amount'];
                                    $list['product.term.electric']
                                        = $current_address['electric']['rate']['product']['term'];
                                    $list['product.term_type.electric']
                                        = @$current_address['electric']['rate']['product']['term_type']['term_type'];
                                    $list['product.intro_term.electric']
                                        = $current_address['electric']['rate']['product']['intro_term'];
                                    $list['product.intro_term_type.electric']
                                        = @$current_address['electric']['rate']['product']['intro_term_type']['term_type'];
                                    $list['product.cancellation_fee.electric']
                                        = $current_address['electric']['rate']['cancellation_fee'];
                                    $list['product.intro_amount.electric']
                                        = $current_address['electric']['rate']['intro_rate_amount'];
                                    $list['product.currency.electric']
                                        = $current_address['electric']['rate']['rate_currency']['currency'];
                                    $list['product.uom.electric']
                                        = $current_address['electric']['rate']['rate_uom']['uom'];
                                    $list['utility.name']
                                        = @$current_address['electric']['rate']['utility']['utility']['name'];
                                    $list['utility.name.electric']
                                        = (isset($current_address['electric'])) ? $current_address['electric']['rate']['utility']['utility']['name'] : null;
                                    $list['product.monthly_fee.electric']
                                        = $current_address['electric']['rate']['rate_monthly_fee'] ? $current_address['electric']['rate']['rate_monthly_fee'] : 'N/A';
                                    $list['utility.service_phone.electric']
                                        = $this->formatPhoneNumber($current_address['electric']['rate']['utility']['utility']['customer_service']);
                                    $list['utility.customer_service.electric']
                                        = $this->formatPhoneNumber($current_address['electric']['rate']['utility']['utility']['customer_service']);
                                    $list['utility.customer_service']
                                        = $this->formatPhoneNumber($current_address['electric']['rate']['utility']['utility']['customer_service']);

                                    // estimated cost
                                    $rateAmount = $current_address['electric']['rate']['rate_amount'];
                                    $currency = $current_address['electric']['rate']['rate_currency_id'];
                                    if ($currency === 1) {
                                        $rateAmount /= 100;
                                    }
                                    $monthlyFee = $current_address['electric']['rate']['rate_amount'] !== null ? $current_address['electric']['rate']['rate_amount'] : 0;

                                    $list['product.estimated_total_cost.electric.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)));
                                    $list['product.estimated_total_cost.electric.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)));
                                    $list['product.estimated_total_cost.electric.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)));
                                    $list['product.estimated_total_cost_x100.electric.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee)) * 100);
                                    $list['product.estimated_total_cost_x100.electric.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee)) * 100);
                                    $list['product.estimated_total_cost_x100.electric.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee)) * 100);

                                    $list['product.estimated_cost.electric.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500));
                                    $list['product.estimated_cost.electric.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000));
                                    $list['product.estimated_cost.electric.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500));
                                    $list['product.estimated_cost_x100.electric.500'] = sprintf('%01.2f', ((($rateAmount * 500) + $monthlyFee) / 500) * 100);
                                    $list['product.estimated_cost_x100.electric.1000'] = sprintf('%01.2f', ((($rateAmount * 1000) + $monthlyFee) / 1000) * 100);
                                    $list['product.estimated_cost_x100.electric.1500'] = sprintf('%01.2f', ((($rateAmount * 1500) + $monthlyFee) / 1500) * 100);

                                    $list['product.custom_data_1.electric'] = $current_address['electric']['rate']['custom_data_1'];
                                    $list['product.custom_data_2.electric'] = $current_address['electric']['rate']['custom_data_2'];
                                    $list['product.custom_data_3.electric'] = $current_address['electric']['rate']['custom_data_3'];
                                    $list['product.custom_data_4.electric'] = $current_address['electric']['rate']['custom_data_4'];
                                    $list['product.custom_data_5.electric'] = $current_address['electric']['rate']['custom_data_5'];
                                }
                            }

                            $address_html = '<table class="table">';

                            foreach ($addresses as $address) {
                                $address_html .= '<tr>';
                                $address_html .= '<td>';
                                $address_html .= $address;
                                $address_html .= '</td>';
                                $address_html .= '</tr>';
                            }

                            $address_html .= '</table>';
                            $identifier_html .= '</table>';

                            $list['account.address_table'] = $address_html;

                            switch ($answer->question_id_cleaned) {
                                case 'default-svc-bill-confirm':
                                    if ('es' == $language) {
                                        $answer->question = 'Está cambiando su (s) cuenta (s) a {{client.name}} con lo siguiente:<br /><br />' . $identifier_html;
                                    } else {
                                        $answer->question = 'You are switching your account(s) to {{client.name}} with the following:<br /><br />' . $identifier_html;
                                    }

                                    break;
                                case 'default-sf-single-bill-name-confirm':
                                    if ('es' == $language) {
                                        $answer->question = 'Muestro {{account.bill_name}} como el nombre que aparece en la factura de esta cuenta.';
                                    } else {
                                        $answer->question = 'I show {{account.bill_name}} as the name that appears on the bill for this account.';
                                    }

                                    break;
                                case 'default-idents-confirm':
                                    if ('es' == $language) {
                                        $answer->question = 'Os muestro los siguientes datos de cuenta:<br /><br />' . $address_html;
                                    } else {
                                        $answer->question = 'I show the following account details:<br /><br />' . $address_html;
                                    }

                                    break;

                                case 'default-dual-bill-name-confirm-same':
                                case 'default-dual-bill-name-confirm':
                                    if ('es' == $language) {
                                        $answer->question = 'Muestro {{account.bill_name}} como el nombre que aparece en las cuentas de electricidad y gas natural para estas cuentas.';
                                    } else {
                                        $answer->question = 'I show {{account.bill_name}} as the name that appears on the electic and natural gas bills for these accounts.';
                                    }

                                    break;
                            }

                            $ret = $this->hydrateVars($answer, $list);
                            if ($ret->showIdents) {
                                $ret->question .= '<br>&nbsp;<br>' . $identifier_html;
                            }

                            $sections['verify'][$verifyIndex][] = $ret->toArray();
                            $dupeArray[] = $answer->question_id_cleaned;
                        }
                    }
                }

                $result = [
                    'componentName' => 'contract-summary-index',
                    'title' => 'Create Transcripts',
                    'eztpvid' => $eztpv_id,
                    'logged_in' => $logged_in,
                'logo_path' => (isset($logoPath) && $logoPath)
                    ? config('services.aws.cloudfront.domain') . '/' . $logoPath->filename
                        : null,
                'sections' => $sections,
                'summary' => $summary,
                ];
            //    if (View::exists('eztpv.transcripts')) {
            //         $this->info("VIEW eztpv.transcripts FOUND!");
            //     } else {
            //         $this->info("VIEW eztpv.summary NOT FOUND!");
            //         return;   
            //     }

                $pdf1 = PDF::loadView('eztpv.transcripts', $result);
                //   $pdf1->setOptions(['default_paper_orientation' => "landscape",]);
                //   $pdf1->setOptions(['dpi' => 150, 'isRemoteEnabled' => true]);
                $pdf1->save(public_path('tmp/' . $filename));
                        // Upload the file to FTP server
                if (!$this->option('noftp')) {
                    $this->info('Uploading file...');
                    $this->info($filename);
                    $ftpResult = 'FTP at ' . Carbon::now() . '. Status: ';
                    try {
                        $ftpFileExist = $filesystem->has($folder_date . '/' . $filename);
                        if ($ftpFileExist) {
                            $filesystem->delete($folder_date . '/' . $filename);
                        }
                        $stream = fopen(public_path('tmp/' . $filename), 'r+');
                        $filesystem->writeStream(
                            $folder_date . '/' . $filename,
                            $stream
                        );

                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                        $ftpResult .= 'Success!';
                    } catch (\Exception $e) {
                        $ftpResult .= 'Error! The reason reported is: ' . $e;
                        $this->info($ftpResult);
                    }
                
                    $this->info($ftpResult);

                    if (isset($ftpResult)) {
                        if (strpos(strtolower($ftpResult),'success') > 0) {
                            $this->info('Upload succeeded.');

                            //   $this->sendEmail('File ' . $filename . ' has been successfully uploaded.', $this->distroList['ftp_success'][$this->mode]);
                        } else {
                            $this->info('Upload failed.');
                            // $this->sendEmail(
                            //     'Error uploading file ' . $filename . ' to FTP server ' . $this->ftpSettings['host'] .
                            //         "\n\n FTP Result: " . $ftpResult,
                            //     $this->distroList['ftp_error'][$this->mode]
                            //);

                            return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
                        }
                    }
                }


                // // Regardless of FTP result, also email the file as an attachment
                // if (!$this->option('noemail')) {
                //     $attachments = [public_path('tmp/' . $filename)];

                //     $this->info("Emailing file...");
                //     $this->sendEmail('Transcripts for ' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
                // }
                // Delete tmp file
                unlink(public_path('tmp/' . $filename));
 
            }


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

        if ($this->mode == 'test') {
            $subject = '(TEST) ' . $subject;
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

            $status .= 'Success!';
            $uploadStatus[] = $status;
        }

        return $uploadStatus;
    }

    /**
     * @method FormatPhoneNumber
     * Formats a E.164 phone number for human friendly display i.e. takes +12345678900 to (234) 567-8900
     *
     * @param string $phone - the phone number to format
     *
     * @return string|null
     */
    function FormatPhoneNumber(string $phone)
    {
        if ($phone === null || strlen($phone) === 0) {
            return null;
        }
        return preg_replace('/^\\+?[1]?\\(?([0-9]{3})\\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/', '($1) $2-$3', $phone);
    }
    private function addressHydrate($language, $type)
    {
        if ('es' === $language) {
            if ('Electric' === $type) {
                return '<td>Eléctrico</td>';
            } else {
                return '<td>Gas Natural</td>';
            }
        }

        return '<td>' . $type . '</td>';
    }

    private function identifierHydrate($language, $identifier)
    {
        if ('es' === $language) {
            switch ($identifier) {
                case 'Account Number':
                    return 'Número de cuenta';
            }
        }

        return $identifier;
    }

    public function formatRate($ratetoformat)
    {
    return $ratetoformat;
    }
    public function hydrateVars($answer, $list)
    {
        // info($answer);
        // info(print_r($list, true));

        if (isset($list['utility.name.electric']) && !isset($list['utility.name'])) {
            $list['utility.name'] = $list['utility.name.electric'];
        }

        $matches = [];
        preg_match_all(
            "/(?<=\{\{)(.*?)(?=\}\})/",
            $answer->question,
            $matches
        );

        // info($matches);

        if (count($matches) > 0) {
            $vars = array_unique($matches[1]);

            // info('vars');
            // info(print_r($vars, true));

            foreach ($vars as $value) {
                // info('value is ' . $value);
                if (array_key_exists($value, $list)) {
                    info('here for ' . $list[$value] . ' -- ' . $value);
                    $answer->question = str_replace(
                        '{{' . $value . '}}',
                        '<u><b>'
                            . $list[$value]
                            . '</b></u>',
                        $answer->question
                    );
                }
            }
        }

        return $answer;
    }

}
