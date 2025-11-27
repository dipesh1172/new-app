<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

use League\Flysystem\Filesystem;

use App\Models\Product;
use App\Models\Rate;
use App\Models\Utility;
use App\Models\VendorRate;

class ReliantRatesImport extends Command
{
    /**
     * The name and signature of the console command.
     * 
     * --file            The name of the file to import.
     * --dry-run         Will perform all operations except for the SQL insert and deletion of downloaded and source rate files.
     * --no-email        Prevents the result email going out. Since the original file is attached to the email, this will also prevent deletion of downloaded and source rate files.
     *
     * @var string
     */
    protected $signature = 'Reliant:RatesImport {--mode=} {--env=} {--file=} {--dry-run} {--no-email}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Reliant Energy - Rates Import.';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Please Make sure that the Reliant file is in the 'public/tmp' folder \n"
        . "\n"
        . " --file:          If specified, imports the file from project root directly. With default filename based on current date. \n"
        . " --dry-run:       Prevents database modifications. Used this to test a rate imports. \n";

    /**
     * Brand identifier
     *
     * @var string
     */
    protected $brandId = [];

    /**
     * List of utilities for brand.
     */
    protected $utilityList = null;

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [ // TODO: Replace with live distros
        'live' => ['DXC_AutoEmails@dxc-inc.com','JSchroeder@reliant.com','KGibson@reliant.com','Ana.Villanueva@nrgenergy.com','XVela@reliant.com',
            'bclark@reliant.com','Jelytza.Geren@nrgenergy.com','Allison.Remmert@nrgenergy.com','MMenzie1@reliant.com','ENzei@reliant.com',
            'roger.banda@greenmountain.com','michael.gonzalez@nrgenergy.com','matt.akers@nrg.com','Anthony.Alexander@nrg.com',
            'John.Hurcadi@nrg.com','JLopez7@reliant.com','JTorres01@reliant.com'],
        'test' => ['dxcit@tpv.com', 'engineering@tpv.com'],
        'error' => ['dxcit@tpv.com', 'engineering@tpv.com']
    ];

    /**
     * Report mode: 'live' or 'test'.
     *
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

    /**
     * Environment: 'staging' or 'prod'.
     *
     * @var string
     */
    protected $env = 'prod'; // prod env by default.

    /**
     * Expected header row in rate file. Used to validate file layout.
     */
    protected $fileHeaderRow = '';

    /**
     * Program start date
     */
    protected $startDate = null;

    /**
     * File download dir
     */
    protected $downloadDir = '';

    /**
     * The expected rate file header
     */
    protected $expectedHeader = "active,effect_date,pseudo_ocd,dwelling type,promo_code,usage_seg,ldc,ldc_code,prod type,plan name,program,term,cancel_fee,unit_price,avg_usage,month_chrg,mon_char_2,incentive,fix_tdsp,var_tdsp,marketer,unit_price2,unit_price3";

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
        ini_set('auto_detect_line_endings', true);

        $this->startDate = Carbon::now("America/Chicago");
        $this->downloadDir = public_path('tmp/');

        // Validate Brand
        $this->brandId['staging'] = '3be79a3b-4cdb-456a-bb08-5647f575d434';
        $this->brandId['prod'] = 'a56d5655-1de7-4aa5-9a76-bd2a2cda9e17';

        // Validate mode.
        if ($this->option('mode')) {
            $environment = strtolower($this->option('mode'));
            if (
                $environment == 'live' || $environment == 'test'
            ) {
                $this->mode = $environment;
            } else {
                $this->error('Invalid --mode value: ' . $environment);
                return -1;
            }
        }

        // Validate env.
        if ($this->option('env')) {
            if (
                strtolower($this->option('env')) == 'prod' ||
                strtolower($this->option('env')) == 'staging'
            ) {
                $this->env = strtolower($this->option('env'));
            } else {
                $this->error('Invalid --env value: ' . $this->option('env'));
                return -1;
            }
        }

        $filename = $this->option('file');

        if (!file_exists(public_path('tmp/') .$this->option('file')) and (strpos(strtolower($filename),'xls') == 0 || strpos(strtolower($filename),'csv') == 0)) {
            $this->error('File not found --file value: ' . $this->option('file'));
            return -1;
        }

  
        $this->info("Mode:     " . $this->mode);
        $this->info("Env:      " . $this->env);
        $this->info("Brand ID: " . $this->brandId[$this->env]);
        $this->info("Filename: " . $filename . "\n");
        
        if (strpos(strtolower($filename),'xls') > 0) {
            // Convert file to CSV
           $this->info("Converting file from XLS -> CSV...");
            //        $convertResult = xls2Csv($this->downloadDir . $filename, $this->downloadDir . $filenameCsv);
           $convertResult = xls2Csv($this->downloadDir . $filename, $this->downloadDir . basename($filename,'.xls') . '.csv');

           if (!$convertResult == 'success') {
               $this->error('xls2Csv: ' . $convertResult);
              return -1;
           } 
           $this->info("  Done!");
           if (!file_exists(public_path('tmp/') .  basename($filename,'.xls') . '.csv')) {
            $this->error("Unable to locate file '" . public_path('tmp/') . basename($filename,'.xls') . '.csv' . "'");
            return -1;
           }
        
        } else {
               copy($filename,$this->downloadDir . $filename);
               if (!file_exists(public_path('tmp/') .  $filename)) {
                $this->error("Unable to locate file '" . public_path('tmp/') . $filename . "'");
                return -1;
               }
         }


        $this->info("Importing CSV file content...");

        $importResult = $this->importCsvFile($this->downloadDir . (strpos(strtolower($filename),'xls') > 0 ? basename($filename,'.xls') . '.csv' : $filename));

        if ($importResult['result'] != 'success') {
            $this->info("  Error!: " . $importResult['details']);

            $message = "Error " . $filename . " file.";

            $this->sendEmail($message, $this->distroList['error']);
            return -1;
        }

        $this->info("  Success!");

        // Get utility list
        $this->info("");
        $this->info("Retrieving utility list...");

        $utilities = Utility::select(
            'utilities.name AS utility_name',
            'brand_utilities.utility_label',
            'states.state_abbrev AS state',
            'utility_supported_fuels.utility_fuel_type_id',
            'utility_supported_fuels.id',
            'utilities.ldc_code AS utility_ldc_code'
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
            $this->brandId[$this->env]
        )->whereNull(
            'utility_supported_fuels.deleted_at'
        )->whereNull(
            'utilities.deleted_at'
        )->whereNull(
            'brand_utilities.deleted_at'
        )->get();

        if ($utilities) {
            $this->utilityList = $utilities;
            $this->info("  Done!");
        } else {
            $this->error("  Error retrieving utility list.");
            return -1;
        }

        // Delete existing products and rates for this brand
        if ($this->option('dry-run')) {
            $this->info("");
            $this->info("Dry run. Skipping existing product/rate deletion.");
        } else {
            $this->info("Deleting existing Reliant products");

            $products = Product::where('brand_id', $this->brandId[$this->env])->get();

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
        }

        // Write data to DB
        $rates = $importResult['details'];

        $this->info("");
        $this->info("Inserting rates:");

        $totalRates = count($rates);
        $counter = 0;

        foreach ($rates as $r) {
            $counter++;

            $this->info("");
            $this->info("------------------------------------------------------------");
            $this->info("[" . $counter . " / " . $totalRates . "]");
            $this->info("  Product:      " . $r['plan_name']);
            $this->info("  Program Code: " . $r['pseudo_ocd'] . "\n");

            //print_r($r);

            $ldc_code = @$r['ldc'];
            $fuel_type_id = 1;               // All rates are for electricity
            $vendors_allowed = 'all';
            $rate_type_id = 1;          // Fixed
            $rate_uom_id = 2;           // Always 'kWh'
            $rate_currency_id = 1;      // Cents
            $term_type_id = 3;          // Months
            $intro_term_type_id = $term_type_id;

            $product_name = trim($r['plan_name']);

            // Utility lookup
            $this->info("  Utility lookup...");
            $utilityLookupResult = $this->utilityLookup("TX", $ldc_code, $fuel_type_id);
            // if ($utilityLookupResult == null) {
            //     // TODO: Add error logging
            //     $this->info("    Not found! Skipping rate.");
            //     continue;
            // }

            // Product lookup
            $this->info('  Product lookup...');
            $product = Product::where(
                'name',
                $product_name
            )->where(
                'brand_id',
                $this->brandId[$this->env]
            )->where(
                'term',
                $r['term']
            )->orderBy('created_at', 'desc')->withTrashed()->first();

            // Exists. Reuse.
            if ($product && !$this->option('dry-run')) { // For dry runs, we always want to run the else case to test field mappings.
                $this->info("    Found. Using existing record.");
                $product->restore();
            } else {
                $this->info("    Not found. New product will be created.");

                $product = new Product();
                $product->brand_id = $this->brandId[$this->env];
                $product->name = $product_name;
                $product->channel = 'DTD';                          // Reliant/GME TX are DTD only
                $product->market = 'Residential';                   // Reliant/GME TX are Residential only
                $product->home_type = null;
                $product->rate_type_id = $rate_type_id;
                $product->green_percentage = null;
                $product->term = $r['term'];
                $product->term_type_id = $term_type_id;
                $product->service_fee = $r['month_chrg'];
                $product->transaction_fee = null;
                $product->transaction_fee_currency_id = null;
                $product->intro_term = null;
                $product->intro_term_type_id = $intro_term_type_id;
                $product->date_from = null;
                $product->date_to = null;

                if (!$this->option('dry-run')) {
                    $this->info('    Saving product...');
                    $product->save();
                } else {
                    $this->info('    Dry run. Product details will not be saved.');
                }
            }


            // Check if rate exists.
            $this->info("  Rate lookup...");

            $rate = Rate::where(
                'product_id',
                $product->id
            )->where(
                'program_code',
                $r['pseudo_ocd']
            )->where(
                'utility_id',
                $utilityLookupResult['id']
            )->where(
                'rate_currency_id',
                $rate_currency_id
            )->where(
                'rate_uom_id',
                $rate_uom_id
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
                trim($r['unit_price'])
            )->where(
                'rate_monthly_fee',
                ''
            )->where(
                'intro_rate_amount',
                ''
            )->orderBy('created_at', 'desc')->withTrashed()->first();


            if (!$rate || $this->option('dry-run')) { // For dry runs, we always want to create a new record, to test field mapping
                $this->info("    Not found. Creating new rate: " . $r['pseudo_ocd']);
                $rate = new Rate();
            } else {
                $this->info("    Found. Updating existing rate: " . $r['pseudo_ocd']);

                $rate->restore();
            }

            $extra_fields = [];
            $extra_fields_header =['unit_price','mon_char_2','effect_date','fix_tdsp','var_tdsp','incentive'];
            $r['effect_date'] = date_format(date_create($r['effect_date']),"m-d-Y");

            foreach ($extra_fields_header as $he) {
                $extra_fields[] = [
                    'name' => $he,
                    'value' => $r[$he]
                ];
            }

            $rate->hidden = 0;
            $rate->product_id = $product->id;
            $rate->program_code = $r['pseudo_ocd'];
            $rate->utility_id = $utilityLookupResult['id'];
            $rate->rate_currency_id = $rate_currency_id;
            $rate->rate_uom_id = $rate_uom_id;
            $rate->cancellation_fee = $r['cancel_fee'];
            $rate->admin_fee = $r['month_chrg'];
            $rate->external_rate_id = $r['promo_code'];;
            $rate->rate_promo_code = null;
            $rate->rate_source_code = $r['program'];
            $rate->rate_renewal_plan = null;
            $rate->rate_channel_source = null;
            $rate->intro_rate_amount = null;
            $rate->rate_amount = $r['avg_usage'];
            $rate->rate_monthly_fee = $r['mon_char_2'];
            $rate->date_from = null;
            $rate->date_to = null;
            $rate->dual_only = 0;
            $rate->custom_data_1 = null;
            $rate->custom_data_2 = null;
            $rate->custom_data_3 = null;
            $rate->custom_data_4 = null;
            $rate->custom_data_5 = null;
            $rate->extra_fields = json_encode($extra_fields);
            $rate->scripting = null;

            $this->info("");
            if (!$this->option('dry-run')) {
                $this->info("  Saving rate...");
                $rate->save();
            } else {
                $this->info("  Dry run. Rate not saved.");
            }
        }

        // TODO: There is a post-insert rate validation code block in legacy. Add to this job if it still applies to Focus.

        $this->info("------------------------------------------------------------");
        $this->info("Wrap-up:");

        $this->info("");

        $emailtResult = null;
        if ($this->option('no-email')) {

            $this->info("No-Email flag set. Skipping result email.");
        } else {
            $this->info("Sending results email.");

            // Email the original file with the result. Since we'll be deleting the oringal file, we'll want to keep this copy.
            $emailtResult = $this->sendEmail('Rate import succeeded', $this->distroList[$this->mode], [$filename]); // TODO: Rework message
        }

        if(isset($emailtResult)){
            $emailtResultStatus = strpos($emailtResult[0], 'Success');
            if ($emailtResultStatus == true) { // only delete if email was send successfully
                // Delete the converted and original files
                unlink($this->downloadDir . (strpos(strtolower($filename),'xls') > 0 ? basename($filename,'.xls') . '.csv' : $filename));  // always a csv file

                // Delete source file
                // TODO: Implement
            }
        }
    }

    /**
     * Import a CSV file to an array.
     *
     * @return mixed
     */
    private function importCsvFile($file)
    {
        if ($this->isUTF8($file) === false) {
            return $this->newResult('error', 'Error: files must be UTF-8 encoded');
        }

        // TODO: Check file extension?

        $csv = array();

        // Get the file's header row and validate it against the expected header values.
        $handle = fopen($file, 'r');

        $csvHeader = trim(strtolower(implode(",", fgetcsv($handle))),'﻿');

        if ($csvHeader != strtolower($this->expectedHeader)) {
            return $this->newResult('error', 'Unexpected header values in file "' . $file . '".');
        }

        // Import row data
        $h = explode(",", $this->expectedHeader);
        while (($data = fgetcsv($handle)) !== false) {

            $row = [];
            for ($i = 0; $i < count($h); $i++) {
                $field = strtolower(str_replace(" ", "_", $h[$i]));
                $row[$field] = $data[$i];
            }

            array_push($csv, $row);
        }

        return $this->newResult('success', $csv); // csv content in 'message' property.
    }

    /**
     * Creates a generic result object
     * 
     * @param string $result  - The result
     * @param $details - Result message or data
     */
    public function newResult(string $result = '', $details = null)
    {
        $r = [
            'result' => $result,
            'details' => $details
        ];

        return $r;
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
            $subject = $this->jobName . ' '
                . Carbon::now();
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
     * Lookup Utility.
     */
    public function utilityLookup($salesState, $ldcCode, $fuel_type)
    {
        if ($this->utilityList) {
            $equalLdcCode = null;
            switch (strtolower($ldcCode)) {
                case "tnp":
                    $equalLdcCode = 'tnmp';
                    break;
                case "onc":
                    $equalLdcCode = 'oncor_elec';
                    break;
                case "cpl":
                    $equalLdcCode = 'aep_cent';
                    break;
                case "cnp":
                    $equalLdcCode = 'cnp';
                    break;
                case "wtu":
                    $equalLdcCode = 'aepwtu';
                    break;
                default:
                    $equalLdcCode = strtolower($ldcCode);
            }
            foreach ($this->utilityList as $util) {
                if (
                    strtolower($util->state) == strtolower($salesState) &&
                    strtolower($util->utility_ldc_code) == $equalLdcCode &&
                    strtolower($util->utility_fuel_type_id) == strtolower($fuel_type)
                ) {
                    return [
                        'id' => $util->id,
                        'state_name' => $util->state,
                        'utility_name' => $util->utility_name,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check's if a text file is encoded in UTF-8.
     *
     * @return bool
     */
    private function isUTF8($file)
    {
        $isUTF8 = true;

        if (function_exists('mb_detect_encoding')) {
            $encoding_check_file = file_get_contents($file);
            $isUTF8 = mb_detect_encoding($encoding_check_file, 'UTF-8', true);
            if ($isUTF8 === false) {
                $output_str = shell_exec('file --mime-encoding ' . $file);
                $output_str_a = explode(':', $output_str);
                if (count($output_str_a) > 1) {
                    if (trim($output_str_a[1]) == 'unknown-8bit') {
                        $iconv_out = shell_exec('iconv -f mac -t UTF-8 ' . $file . ' -o ' . $file . 'c');
                        $encoding_check_file = file_get_contents($file . 'c');
                        $isUTF8 = mb_detect_encoding($encoding_check_file, 'UTF-8', true);
                        if ($isUTF8) {
                            unlink($file);
                            $rate_file = $file . 'c';
                        }
                    }
                }
            }
        }

        return $isUTF8;
    }
}