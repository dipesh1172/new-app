<?php

namespace App\Console\Commands;
use League\Flysystem\Filesystem;
use League\Flysystem\Config;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Sftp\SftpAdapter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\ProviderIntegration;
use App\Models\VendorRate;
use App\Models\Vendor;
use App\Models\Utility;
use App\Models\Rate;
use App\Models\Product;
use App\Models\RateUom;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


class GreenChoiceEnergyRateImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GreenChoiceEnergyRateImport {--mode=} {--noemail}';
 
     /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Green Choice Energy Rate Import ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Green Choice Energy Rate Import';

    /**
     * Report start date
     *
     * @var mixed
     */
    protected $startDate = null;
    
    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'curt.cadwell@answernet.com'],  
            //'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
            'test' => ['curt.cadwell@answernet.com','curt@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['curt.cadwell@answernet.com','curt@tpv.com'],
           // 'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
           //'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
           'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['curt.cadwell@answernet.com','curt@tpv.com','ops@greenchoiceenergy.com','donotreply@rpaenergy.com','samantha.powers@answernet.com','accountmanagers@answernet.com'],
            //'live' => ['curt.cadwell@answernet.com','dxc_autoemails@tpv.com'],
            //'test' => ['dxcit@tpv.com']
            'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ]
    ];
   
     /**
     * FTP Settings
     *
     * @var array
     */
    protected $ftpSettings = [
        'live' => [
            'host' => '',
            'username' => '',
            'password' => '',
            'passive' => true,
            'root' => '/RateDrop',
            'passive' => true,
            'ssl' => true,
            'timeout' => 30,
            'directoryPerm' => 0755,
        ],
        'test' => [
            'host' => '192.168.50.254',
            'username' => 'curt_test',
            'password' => '9iv4aQlUFe&ZzH7MU%K',
            'passive' => true,
            'root' => '/ftp/gce',
            'ssl' => true,
            'timeout' => 10
        ]
    ];
    /**
     * Report mode: 'live' or 'test'.
     * 
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

    /**
     * Create a new command instance.
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
        // Check mode. Leave in 'live' mode if not provided or an invalid value was provided.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            }
        }
    
        if (strtolower($this->mode) == 'live')  {
            $brand_id = '7b08b19d-32a5-4906-a320-6a2c5d6d6372';   // prod    
            //$brand_id = 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e';  // staging

            $pi = ProviderIntegration::where(
                'brand_id',
                $brand_id
            )->where(
                'provider_integration_type_id',
                1
            )->where(
                'service_type_id',
                63
            )->first();
    
            if (empty($pi)) {
                $this->error("No credentials were found.");
                return -1;
            }
    
            $this->ftpSettings[$this->mode]['host'] = $pi->hostname;
            $this->ftpSettings[$this->mode]['username'] = $pi->username;
            $this->ftpSettings[$this->mode]['password'] = $pi->password;
            //$adapter = new SftpAdapter($this->ftpSettings[$this->mode]);
            $adapter = new Ftp($this->ftpSettings[$this->mode]);

        } else {
            $brand_id = 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e';  // staging   

            $adapter = new Ftp($this->ftpSettings[$this->mode]);

        } 
        $this->startDate = Carbon::today('America/Chicago');
        // Get FTP details

        try {
            $fileNameToCheckFor = 'rates-';
            $timeToCheck = Carbon::now('America/Chicago')->format('H');   
            //$timeToCheck = '02';  // test 2:00am rate import process
            $startDate = Carbon::today('America/Chicago');
            $folderName = 'gce/'
            . 'rateimports/';
            $fileNameToImport = $fileNameToCheckFor . $startDate->year 
               . str_pad($startDate->month, 2, '0', STR_PAD_LEFT) 
               . str_pad($startDate->day, 2, '0', STR_PAD_LEFT) . '.csv';
            // $fileNameToImport = $fileNameToCheckFor . '20230621.csv';   // test waiting to process
            $filesystem = new Filesystem($adapter);
            if ($timeToCheck >= '07' || $timeToCheck < '02') { // validation process
                $files = $filesystem->listContents('/');
                $this->info('Processing '.count($files).' filesystem entries');
                foreach ($files as $file) {
                    if ($file['type'] === 'file' and $file['extension'] === 'csv' and substr_count($file['filename'],$fileNameToCheckFor) == 1)  {
                        // Create download directory if it doesn't exist
                        if (!file_exists(public_path($folderName))) {
                            mkdir(public_path($folderName), 0777, true);
                        }
                        if (file_exists(public_path($folderName . $file['path']))) {  // if previous file delete
                            unlink(public_path($folderName . $file['path']));
                        }
                        if (file_exists(public_path($folderName . $file['path'] . '.errors.txt'))) {
                            unlink(public_path($folderName . $file['path'] . '.errors.txt'));
                        }
                        $this->info("Found file on FTP to process " . $file['path'] );
                        $contents = $filesystem->read($file['path']);
                        $rate_file = public_path($folderName . $file['path']);
                        file_put_contents($rate_file, $contents);
                        $this->info("Copied file contents to Answernet");
                        if (file_exists(public_path($folderName . $file['path']))) {
                            $this->info("Start validation of rates file");
                            $RateErrors = $this->validateRateCsv($rate_file,$brand_id);
                            if (!empty($RateErrors['error'])) {
                                file_put_contents(public_path($folderName . $file['path'] . '.errors.txt'),'Errors:' . PHP_EOL,FILE_APPEND);
                                file_put_contents(public_path($folderName . $file['path'] . '.errors.txt'),implode(PHP_EOL,$RateErrors['error']),FILE_APPEND);
                            }
                            if (!empty($RateErrors['missing_utilities'])) {
                                file_put_contents(public_path($folderName . $file['path'] . '.errors.txt'),PHP_EOL . 'Missing Utilities:' . PHP_EOL,FILE_APPEND);
                                file_put_contents(public_path($folderName . $file['path'] . '.errors.txt'),implode(PHP_EOL,$RateErrors['missing_utilities']),FILE_APPEND);
                            }
                            if (!empty($RateErrors['missing_vendors'])) {
                                file_put_contents(public_path($folderName . $file['path'] . '.errors.txt'),PHP_EOL. 'Missing Vendors:' . PHP_EOL,FILE_APPEND);
                                file_put_contents(public_path($folderName . $file['path'] . '.errors.txt'),implode(PHP_EOL,$RateErrors['missing_vendors']),FILE_APPEND);
                            }
                            if (file_exists(public_path($folderName . $file['path'] . '.errors.txt'))) {   // errors with validation
                                $this->info("Validation errors found.");
                                // Regardless of FTP result, also email the file as an attachment
                                if (!$this->option('noemail')) {
                                    $attachments[] = public_path($folderName . $file['path']);   // send rate file
                                    $attachments[] = public_path($folderName . $file['path'] . '.errors.txt');   // send validation error file 
                                    $this->info("Emailing validation error file...");
                                    $this->sendEmail('Attached is the validation Error file ' . $file['path'] . '.errors.txt' . ' .', $this->distroList['emailed_file'][$this->mode], $attachments);
                                }
                                if ($filesystem->has('errors/'. $file['path'])) {  // if another error file with same name delete
                                    $filesystem->delete('errors/'. $file['path']);  // delete off of FTP if it exists already
                                }
                                $filesystem->copy($file['path'],'errors/'. $file['path']);  // copy to errors folder
                                if ($filesystem->has('errors/'. $file['path'])) {   // check to see if copy worked
                                    $filesystem->delete($file['path']);  // delete rate file off root of FTP 
                                }
                            } else {    // No errors found for validation 
                                $this->info("Validation No Errors");
                                if ($filesystem->has('Awaiting Processing/'. $file['path'])) {
                                    $filesystem->delete('Awaiting Processing/'. $file['path']);  // delete off of FTP if it exists already
                                }
                                $filesystem->copy($file['path'],'Awaiting Processing/'. $file['path']);  // copy to errors folder
                                if ($filesystem->has('Awaiting Processing/'. $file['path'])) {  // check to see if copy worked
                                    $filesystem->delete($file['path']);  // delete rate file off root of FTP 
                                }
                                if (!$this->option('noemail')) {
                                    $this->info("Send Email Validation Successful...");
                                    $this->sendEmail('Validation Successful for file ' . $file['path'] . ' .', $this->distroList['emailed_file'][$this->mode]);
                                }
                            }
                        } else {
                            $this->info("Could not find the file that was copied to Answernet server");

                            $this->sendEmail(
                                'Could not find the file that was copied to Answernet server!! Import not processed' . $this->startDate->format('m-d-Y') . '.',
                                $this->distroList['emailed_file'][$this->mode]
                            );
                        }
                        break;
                    }
                } // end for validation loop
                return;
            } else {  // rate import between 2:00am and 4:00am
                if ($filesystem->has('Awaiting Processing/'.  $fileNameToImport)) {
                    $filesystem = new Filesystem($adapter);
                    // Create download directory if it doesn't exist
                    if (!file_exists(public_path($folderName))) {
                        mkdir(public_path($folderName), 0777, true);
                    }
                    if (file_exists(public_path($folderName . $fileNameToImport))) {  // if previous file delete
                        unlink(public_path($folderName . $fileNameToImport));
                    }
                    if (file_exists(public_path($folderName . $fileNameToImport . '.errors.txt'))) {
                        unlink(public_path($folderName . $fileNameToImport . '.errors.txt'));
                    }
                    $this->info("Found file on FTP to process " . $fileNameToImport );
                    $contents = $filesystem->read('Awaiting Processing/'.  $fileNameToImport);
                    $rate_file = public_path($folderName . $fileNameToImport);
                    file_put_contents($rate_file, $contents);
                    $this->info("Copied file contents to Answernet");
                    if (file_exists(public_path($folderName . $fileNameToImport))) {
                        $this->info("Start Import Process");
                        $errorReturn = $this->rateImport($rate_file,$brand_id);
                        if (empty($errorReturn)) {
                            $this->info("Successful Import Process");
                            if ($filesystem->has('processed/'. $fileNameToImport)) {  // delete previous processed with same date
                                $filesystem->delete('processed/'. $fileNameToImport);  // delete off of FTP if it exists already
                            }
                            $filesystem->copy('Awaiting Processing/' . $fileNameToImport,'processed/'. $fileNameToImport);  // copy to processed folder
                            if ($filesystem->has('processed/'. $fileNameToImport)) {  // check to see if copy worked and in processing folder
                                $filesystem->delete('Awaiting Processing/' . $fileNameToImport);  // delete rate file off Awaiting Processing
                            }
                            if (!$this->option('noemail')) {
                                $attachments = [public_path($folderName . $fileNameToImport)];   // send successful email
                                $this->info("Send Email Success...");
                                $this->sendEmail('Successfully processed file ' . $fileNameToImport . ' .', $this->distroList['emailed_file'][$this->mode], $attachments);
                            }

                        } else {
                            if ($filesystem->has('errors/'. $fileNameToImport)) {  // if another error file with same name delete
                                $filesystem->delete('errors/'. $fileNameToImport);  // delete off of FTP if it exists already
                            }
                            $filesystem->copy('Awaiting Processing/' . $fileNameToImport,'errors/'. $fileNameToImport);  // copy to errors folder
                            if ($filesystem->has('errors/'. $fileNameToImport)) {   // check to see if copy worked
                                $filesystem->delete('Awaiting Processing/' .$fileNameToImport);  // delete rate file off Awaiting Processing
                            }
                            if (!$this->option('noemail')) {
                                $attachments = [public_path($folderName . $fileNameToImport)];   // send unsuccessful email
                                $this->info("Send Email not successful...");
                                $this->sendEmail('Error processing Validated file error result: ' . $errorReturn . ' .', $this->distroList['emailed_file'][$this->mode], $attachments);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::info('Exception running Rate Import ', [$e]);
    //        $errorList[] = ['lineNumber' => null, 'Error during Rate Import ' . $e->getMessage()];
    //            print_r($errorList);
            if (!$this->option('noemail')) {
                $this->info("Send Exception Email not successful...");
                $this->sendEmail('Exception Error!!' . 
                    ' LineNo: ' . $e->getLine() .
                    ' Code: ' . $e->getCode() . 
                    ' Message: ' . $e->getMessage()  .
                    ' ' . $this->startDate->format('m-d-Y') . 
                    '.', $this->distroList['ftp_error'][$this->mode]);
            }
        return;
        }
    }

    public function rateImport($rate_file,$brand_id)
    {
    
        $util_not_found = [];
        $vendor_not_found = [];
        $vendor_list = [];
 
        $lineNumber = 0;
        $handle = fopen($rate_file, 'r');
        $headers = fgetcsv($handle);
        $headers_ = [];
        foreach ($headers as $key => $value) {
            $headers_[trim($value)] = $key;
        }

        $headers = $headers_;
        $expected_fields = count($headers);
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

                return 13;
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

            if (count($data) != $expected_fields) {
                $this->info('Expected ' . $expected_fields
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
            $market = (!empty($data[$headers['Market']]))
                ? $data[$headers['Market']]
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
            )->where(
                'market',
                $market
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

                // NOTE: For legal purposes, we should avoid altering ANY values of records.  What is advised is that if
                // a product is changed, we soft delete the old product to retain the historal values for legal purposes
                // then create new product.

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

                return 13;
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
            $rate->save();

            foreach ($vendors as $vendor) {  // delete all vendor restricted rates for this rate
                $vr = VendorRate::where(
                    'vendors_id',
                    $vendor->id
                )->where(
                    'rate_id',
                    $rate->id
                )->first();
                if ($vr) {
                    $vr->delete();
                } 
            }

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

    private function termTypeToId($term_type, $default = 3)
    {
        switch (strtolower($term_type)) {
            case 'day':
            case 'days':
                return 1;

            case 'week':
            case 'weeks':
                return 2;

            case 'month':
            case 'months':
                return 3;

            case 'year':
            case 'years':
                return 4;

            default:
                return $default;
        }
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
                $name . '%'
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

    public function validateRateCsv(string $rate_file,string $brand_id)
    {
 //       set_time_limit(60 * 5);
 //       ini_set('memory_limit', '256M');
        ini_set('auto_detect_line_endings', true);
        $out = Cache::remember('rate-file-import-' . $rate_file, 5, function () use ($rate_file,$brand_id) {
            $out = [];
            $out['lines'] = [];
            $out['products'] = [];
            $out['error'] = [];
            $out['extra'] = [];
            $out['existing-products'] = [];
            $util_not_found = [];
            $vendor_not_found = [];
            $vendor_list = [];
            $expected_headers = [
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
            ];
            $expected_fields = count($expected_headers);
            $lineNumber = 0;
            if (function_exists('mb_detect_encoding')) {
                $encoding_check_file = file_get_contents($rate_file);
                $isUTF8 = mb_detect_encoding($encoding_check_file, 'UTF-8', true);
                if ($isUTF8 === false) {
                    $output_str = shell_exec('file --mime-encoding ' . $rate_file);
                    $out['extra'][] = $output_str;
                    $output_str_a = explode(':', $output_str);
                    if (count($output_str_a) > 1) {
                        if (trim($output_str_a[1]) == 'unknown-8bit') {
                            $iconv_out = shell_exec('iconv -f mac -t UTF-8 ' . $rate_file . ' -o ' . $rate_file . 'c');
                            $out['extra'][] = $iconv_out;
                            $encoding_check_file = file_get_contents($rate_file . 'c');
                            $isUTF8 = mb_detect_encoding($encoding_check_file, 'UTF-8', true);
                            if ($isUTF8) {
                                unlink($rate_file);
                                $rate_file = $rate_file . 'c';
                            }
                        }
                    }
                    if ($isUTF8 === false) {
                        $out['error'][] = 'Error: files must be UTF-8 encoded';

                        return $out;
                    }
                }
            }
            $handle = fopen($rate_file, 'r');
            $headers = fgetcsv($handle);
            if ($headers !== false) {
                if (count($headers) !== $expected_fields) {
                    // field mismatch
                    $out['error'][] = 'Expected ' . $expected_fields . ' fields but found ' . count($headers);

                    return $out;
                }
            } else {
                // invalid csv file
                $out['error'][] = 'The CSV file is invalid.';

                return $out;
            }
            $headers_ = [];
            foreach ($headers as $key => $value) {
                $headers_[trim($value)] = $key;
            }

            $headers = $headers_;

            $headerCheckFailed = false;
            foreach ($expected_headers as $eheader) {
                if (!isset($headers[$eheader])) {
                    $headerCheckFailed = true;
                    $out['error'][] = 'CSV File Missing "' . $eheader . '" column header';
                }
            }

            if ($headerCheckFailed) {
                return $out;
            }

            // print_r($headers);
            // exit();

            $pcodeCheck = [];

            while (($data = fgetcsv($handle)) !== false) {
                $lineNumber = ++$lineNumber;

                $out['lines'][$lineNumber] = [];
                $out['lines'][$lineNumber][] = implode(',', $data);
                if (
                    !empty($data[$headers['Product/Plan Name']])
                    && trim($data[$headers['Product/Plan Name']]) === 'Product/Plan Name'
                ) {
                    // skip the file header
                    continue;
                }

                $utility_name = @trim($data[$headers['Utility']]);
                $program_code = @trim($data[$headers['Program Code']]);
                if (!isset($pcodeCheck[$utility_name . '|' . $program_code])) {
                    $pcodeCheck[$utility_name . '|' . $program_code] = [];
                }
                $pcodeCheck[$utility_name . '|' . $program_code][] = $data[$headers['Product/Plan Name']];

                $commodity = @trim($data[$headers['Fuel Type']]);
                $vendors_allowed = trim($data[$headers['Vendors Allowed']]);

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
                        $out['lines'][$lineNumber][] = 'Line ' . $lineNumber . '] Fuel Type must be one of electric or gas; (' . ($commodity) . ') given.';
                        $out['error'][] = 'Line ' . $lineNumber . '] Fuel Type must be one of electric or gas; (' . ($commodity) . ') given.';
                        continue 2;
                }

                $uLookup = $this->lookupUtility($utility_name, $fuel, $brand_id);
                if ($uLookup == null) {
                    $com = ($fuel === 1) ? 'Electric' : 'Gas';
                    $out['lines'][$lineNumber][] = 'Line ' . $lineNumber . '] Unable to locate Utility: ' . $utility_name . ' for fuel type: ' . $com;
                    $out['error'][] = 'Line ' . $lineNumber . '] Unable to locate Utility: ' . $utility_name . ' for fuel type: ' . $com;
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
                    $out['missing_utilities'] = array_unique($util_not_found);
                    continue;
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

                    if (count($vendor_not_found) > 0) {
                        $out['missing_vendors'] = array_unique($vendor_not_found);
                        continue;
                    }
                }

                if (count($data) != $expected_fields) {
                    $out['error'][] = 'Line ' . $lineNumber . '] Expected ' . $expected_fields . ' fields.  ' . count($data) . ' found.';
                    continue;
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
                        $out['error'][] = 'Line ' . $lineNumber . '] Fuel Type must be one of electric or gas; ('
                            . strtolower($data[$headers['Fuel Type']]) . ') given.';

                        return $out;
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
                        $out['error'][] = 'Line ' . $lineNumber . ('] Unknown rate type: ' . $data[$headers['Rate Type']]);

                        continue 2;
                }

                $term_type_id = $this->termTypeToId($data[$headers['Term Type']]);

                $intro_term_type_id = null;
                if (isset($data[$headers['Intro Term Type']])) {
                    $intro_term_type_id = $this->termTypeToId($data[$headers['Intro Term Type']]);
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

                $product = null;
                foreach ($out['products'] as $tp) {
                    if ($tp->name == $data[$headers['Product/Plan Name']]) {
                        $product = $tp;
                        break;
                    }
                }

                if ($product == null) {
                    $daily_fee = (!empty($data[$headers['Daily Fee']]))
                        ? $data[$headers['Daily Fee']]
                        : null;
                    $green_percentage = (!empty($data[$headers['Product Green Percentage']]))
                        ? $data[$headers['Product Green Percentage']]
                        : null;
                    $term = (!empty($data[$headers['Term']]))
                        ? $data[$headers['Term']]
                        : null;

                    try {
                        $product = new \StdClass();
                        $product->brand_id = $brand_id;
                        $product->name = $data[$headers['Product/Plan Name']];
                        $product->channel = $data[$headers['Channel']];
                        $product->market = $data[$headers['Market']];
                        $product->home_type = $data[$headers['Home Type']];
                        $product->rate_type_id = $rate_type_id;
                        $product->green_percentage = $green_percentage;
                        $product->daily_fee = $daily_fee;
                        $product->term = $term;
                        $product->term_type_id = $term_type_id;
                        $product->service_fee = $data[$headers['Service Fee']];
                        $product->transaction_fee = $data[$headers['Transaction Fee']];
                        $product->transaction_fee_currency_id = ($data[$headers['Transaction Fee']] > 0)
                            ? 1 : null;
                        $product->intro_term = $data[$headers['Intro Term']];
                        $product->intro_term_type_id = $intro_term_type_id;
                        $product->date_from = (isset($data[$headers['Start Date']])
                            && strlen(trim($data[$headers['Start Date']])) > 0)
                            ? Carbon::parse($data[$headers['Start Date']])
                            : Carbon::now();
                        $product->date_to = (isset($data[$headers['End Date']])
                            && strlen(trim($data[$headers['End Date']])) > 0)
                            ? Carbon::parse($data[$headers['End Date']])
                            : null;
                        $product->existing = Product::where(
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
                        )->orderBy(
                            'created_at',
                            'desc'
                        )->withTrashed()->with(
                            [
                                'rates',
                            ]
                        )->first();
                        $out['products'][] = $product;
                    } catch (Exception $e) {
                        $out['error'][] = 'Line ' . $lineNumber . '] ' . $e->getMessage();
                    }
                }

                $uLookup = $this->lookupUtility($utility_name, $fuel, $brand_id);
                if ($uLookup == null) {
                    $out['error'][] = 'Line ' . $lineNumber . ']Unable to locate Utility: ' . $utility_name . ' for fuel type: ' . $fuel;

                    continue;
                }

                $date_from = !empty($data[$headers['Start Date']]) ? Carbon::parse($data[$headers['Start Date']]) : null;
                $date_to = !empty($data[$headers['End Date']]) ? Carbon::parse($data[$headers['End Date']]) : null;

                if ($date_from !== null && !$date_from->isValid()) {
                    $out['error'][] = 'Line ' . $lineNumber . ' The specified Start Date for Program Code ' . $data[$headers['Program Code']] . ' is not valid.';
                    continue;
                }
                if ($date_to !== null && !$date_to->isValid()) {
                    $out['error'][] = 'Line ' . $lineNumber . ' The specified End Date for Program Code ' . $data[$headers['Program Code']] . ' is not valid.';
                    continue;
                }

                if (
                    $date_from !== null
                    && $date_to !== null
                    && $date_from->isValid()
                    && $date_to->isValid()
                    && !$date_from->isBefore($date_to)
                ) {
                    $out['error'][] = 'Line ' . $lineNumber . ' Specified Start Date for Program Code ' . $data[$headers['Program Code']] . ' is not before the end date.';
                    continue;
                }

                if ($product !== null) {
                    $rate = new \StdClass();

                    $rate_uom = Cache::remember(
                        'rate_uom_' . $rate_uom_id,
                        60,
                        function () use ($rate_uom_id) {
                            return RateUom::find($rate_uom_id);
                        }
                    );

                    $rate->hidden = 0;
                    $rate->program_code = $data[$headers['Program Code']];
                    $rate->utility_id = $uLookup['id'];
                    $rate->utility_name = $utility_name;
                    $rate->rate_currency_id = $rate_currency_id;
                    $rate->rate_uom_id = $rate_uom_id;
                    $rate->rate_uom = $rate_uom;
                    $rate->cancellation_fee = $data[$headers['Cancellation Fee']];
                    $rate->admin_fee = $data[$headers['Admin Fee']];
                    $rate->external_rate_id = $data[$headers['External ID']];
                    $rate->rate_promo_code = $data[$headers['Promo Code']];
                    $rate->rate_source_code = $data[$headers['Source Code']];
                    $rate->rate_renewal_plan = $data[$headers['Renewal Plan']];
                    $rate->rate_channel_source = $data[$headers['Channel Source']];
                    $rate->rate_amount = trim($data[$headers['Rate Amount']]);
                    $rate->rate_monthly_fee = $data[$headers['Monthly Fee']];
                    $rate->date_from = Carbon::now('America/Chicago');
                    $rate->date_to = null;
                    $rate->intro_rate_amount = $data[$headers['Intro Rate']];
                    $rate->dual_only = $data[$headers['Dual Fuel Only']];
                    $rate->custom_data_1 = $data[$headers['Custom Data 1']];
                    $rate->custom_data_2 = $data[$headers['Custom Data 2']];
                    $rate->custom_data_3 = $data[$headers['Custom Data 3']];
                    $rate->custom_data_4 = $data[$headers['Custom Data 4']];
                    $rate->custom_data_5 = $data[$headers['Custom Data 5']];

                    $rate->existing = Rate::select(
                        'rates.*'
                    )->join(
                        'products',
                        'products.id',
                        'rates.product_id'
                    )->where(
                        'products.brand_id',
                        $brand_id
                    )->where(
                        'program_code',
                        $rate->program_code
                    )->where(
                        'utility_id',
                        $rate->utility_id
                    )->with(
                        [
                            'rate_uom',
                        ]
                    )->first();

                    $product->rates[] = $rate;
                }
            }

            $out['existing-products'] = Product::where('brand_id', $brand_id)->with(['rates'])->orderBy('name')->get();

            foreach ($pcodeCheck as $utilPcode => $products) {
                if (count($products) > 1) {
                    $parts = explode('|', $utilPcode);
                    $out['error'][] = 'Program code (' . $parts[1] . ') for utility "' . $parts[0] . '" is duplicated in products: ' . implode(',', $products);
                }
            }

            return $out;
        });

        return $out;
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
    protected function sendEmail(string $message, array $distro, array $files = array())
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
     * Writes an Excel file from a data array. Data array should
     * use named keys as the keys are used for the header row.
     */
    protected function writeXlsFile($data, $fileName) {

        try {
            $headers = array_keys($data[0]);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet = $spreadsheet->getActiveSheet()->setTitle('Sheet1');
            $sheet->fromArray($headers, null, 'B1');
            $sheet->fromArray($data, null, 'B2');
            $recRow = 1;
            foreach ($data as $r) {   // fromArray above makes assumptions on numeric fields rewrite cell 
                $recRow = $recRow+1; 
                $sheet->setCellValueExplicit('D'.strval($recRow),$r['confirmation_code'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.strval($recRow),$r['confirmation_code'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('J'.strval($recRow),$r['account_number1'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AE'.strval($recRow),$r['btn'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $writer->save($fileName);
        } catch (\Exception $e) {
            // TODO: Handle
        }

        // TODO: Return a result
    }

}
