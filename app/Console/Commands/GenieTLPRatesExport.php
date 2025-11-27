<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\Product;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;

class GenieTLPRatesExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Genie:TLPRatesExport {--mode=} {--noftp} {--noemail}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'TLP rates export';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandIds = [
        '0e80edba-dd3f-4761-9b67-3d4a15914adb', // Residents Energy
        '77c6df91-8384-45a5-8a17-3d6c67ed78bf'  // IDT Energy
    ];

    /**
     * Report mode: 'live' or 'test'.
     * 
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

    /**
     * Errors distro list
     * 
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [
            'live' => ['dxc_autoemails@tpv.com'],
            'test' => ['dxcit@dxc-inc.com', 'engineering@tpv.com'],
        ],
        'ftp_error' => [
            'live' => ['dxc_autoemails@tpv.com'],
            'test' => ['dxcit@dxc-inc.com', 'engineering@tpv.com'],
        ],
        'error' => ['dxcit@dxc-inc.com', 'engineering@tpv.com'],
    ];

    /**
     * FTP Settings
     *
     * @var array
     */
    protected $ftpSettings = [
        'live' => [
            'host' => 'ftp.dxc-inc.com',
            'username' => 'TLP_ID_DXC',
            'password' => 'xFtp!Dxc74134',
            'passive' => true,
            'root' => '/',
            'ssl' => true,
            'timeout' => 10
        ],
        'test' => [
            'host' => 'ftp.dxc-inc.com',
            'username' => 'dxctest',
            'password' => 'xchangeWithUs!',
            'passive' => true,
            'root' => '/',
            'ssl' => true,
            'timeout' => 10
        ]
    ];

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
        // Check mode. Leave in 'live' mode if not provided or an invalide value was provided.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == "live" ||
                strtolower($this->option('mode')) == "test"
            ) {
                $this->mode = strtolower($this->option('mode'));
            } else {
                $this->error('Unrecognized --mode: ' . $this->option('mode'));
                return -1;
            }
        }

        $this->info('Mode: ' . $this->mode);

        $csvFilename = "IDT_RATE_LIST.csv";
        if ($this->mode == "test") {
            $csvFilename = "TEST_" . $csvFilename;
        }

        // Data layout for rates export file
        $csvHeader = $this->flipKeysAndValues([
            'active', 'sales_st', 'src_code', 'cust_type', 'vendor', 'brand_name', 'promo_stmt', 'utility',
            'util_name', 'zone', 'util_code', 'util_type', 'energytype', 'ratetype', 'offstart', 'offend',
            'fee', 'term', 'etf', 'rate', 'offerid', 'dt_added', 'added_by', 'dt_deactiv', 'deactiv_by',
            'auth_by', 'rec_id'
        ]);

        $this->info("Pulling active rates...");
        $rates = Product::select(
            'states.state_abbrev',
            'rates.rate_source_code',
            'products.market',
            'brands.name AS brand_name',
            'products.name AS product_name',
            'brand_utilities.utility_label',
            'utility_supported_fuels.utility_fuel_type_id',
            'products.green_percentage',
            'rate_types.rate_type',
            'rates.date_from',
            'rates.date_to',
            'products.term',
            'rates.cancellation_fee',
            'rates.rate_amount',
            'rates.program_code',
            'products.created_at',
            'rates.custom_data_1' // For Zone
        )->leftJoin(
            'brands',
            'products.brand_id',
            'brands.id'
        )->leftJoin(
            'rates',
            'products.id',
            'rates.product_id'
        )->leftJoin(
            'utility_supported_fuels',
            'rates.utility_id',
            'utility_supported_fuels.id'
        )->leftJoin(
            'brand_utilities',
            function ($join) {
                $join->on('products.brand_id', '=', 'brand_utilities.brand_id');
                $join->on('utility_supported_fuels.utility_id', '=', 'brand_utilities.utility_id');
            }
        )->leftJoin(
            'utilities',
            'utility_supported_fuels.utility_id',
            'utilities.id'
        )->leftJoin(
            'states',
            'utilities.state_id',
            'states.id'
        )->leftJoin(
            'rate_types',
            'products.rate_type_id',
            'rate_types.id'
        )->whereIn(
            'products.brand_id',
            $this->brandIds
        )->whereNull(
            'products.deleted_at'
        )->whereNull(
            'rates.deleted_at'
        )->where(
            'products.hidden',
            0
        )->where(
            'rates.hidden',
            0
        )->orderBy(
            'products.name',
            'asc'
        )->orderBy(
            'rates.program_code',
            'asc'
        )->get();


        if (!isset($rates)) { // TODO: Review for better solutions (try/catch?)
            $this->sendEmail(
                "Error querying for IDTE/Residents rates. ",
                $this->errDistroList
            );

            return -1;
        }

        if (count($rates) == 0) {
            return 0; // No rates to export
        }


        $this->info("Formatting rate data...");
        $csv = [];

        $records = 0;

        foreach ($rates as $rate) {

            $commodity = ($rate->utility_fuel_type_id == 1 ? 'ELE' : ($rate->utility_fuel_type_id == 2 ? 'GAS' : ''));
            $productName = rtrim(trim($rate->product_name), " - " . trim($rate->state_abbrev));

            $row = [
                'active' => 'T',        // Always 'T'
                'sales_st' => $rate->state_abbrev,
                'src_code' => $rate->rate_source_code,
                'cust_type' => $rate->market,
                'vendor' => '',         // Always blank
                'brand_name' => $rate->brand_name,
                'promo_stmt' => $productName,
                'utility' => $rate->utility_label,
                'util_name' => '',      // Always blank
                'zone' => $rate->custom_data_1,
                'util_code' => '',      // Always blank
                'util_type' => $commodity,
                'energytype' => ($rate->green_percentage > 0 ? 'Green' : 'Brown'),
                'ratetype' => $rate->rate_type,
                'offstart' => $rate->date_from,
                'offend' => $rate->date_to,
                'fee' => '',            // Always blank
                'term' => $rate->term,
                'etf' => ($rate->cancellation_fee > 0 ? 'Yes' : 'No'),
                'rate' => $rate->rate_amount,
                'offerid' => $rate->program_code,
                'dt_added' => '',
                'added_by' => '',
                'dt_deactiv' => '',
                'deactiv_by' => '',
                'auth_by' => '',
                'rec_id' => '0'         // Doesn't exists in Focus
            ];

            $csv[] = implode(",", $row);
            $records++;
        }


        // Create the file
        $this->info('Creating file on FTP server...');

        $adapter = new Ftp($this->ftpSettings[$this->mode]); // Adapter for moving the created file(s) to the FTP server.
        $fs = new Filesystem($adapter);

        try {
            if ($this->option('noftp')) {
                $file = fopen(public_path("tmp/" . $csvFilename), 'w');
                fputs($file, implode("\r\n", $csv));
                fclose($file);
            } else {
                $fs->put($csvFilename, implode("\r\n", $csv));

                if (!$this->option('noemail')) {
                    $message = "Successfully created file " . $csvFilename . " on FTP server.\n\n"
                        . "Records: " . $records . "\n\n"
                        . "URL: " . $this->ftpSettings[$this->mode]['host'] . "\n"
                        . "Account: " . $this->ftpSettings[$this->mode]['username'];

                    $this->sendEmail($message, $this->distroList['ftp_success'][$this->mode]);
                }
            }
        } catch (\Exception $e) {
            $message = "Error createing file " . $csvFilename . "\n\n"
                . "Error Message: \n"
                . $e->getMessage();

            $this->sendEmail($message, $this->distroList['ftp_error'][$this->mode]);
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
            $subject = 'Genie Retail - D2D - Email File Generation (' . env('APP_ENV') . ') '
                . Carbon::now();
        } else {
            $subject = 'Genie Retail - D2D - Email File Generation '
                . Carbon::now();
        }

        if ($this->mode == "test") {
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
     * Keys become values and values become keys. It's assumed that the values are unique.
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
}
