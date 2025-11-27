<?php

namespace App\Console\Commands\Spark;

use Ramsey\Uuid\Uuid;
use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\UtilitySupportedFuel;
use App\Models\ProviderIntegration;
use App\Models\CustomerList;
use App\Models\BrandUtility;
use App\Models\Brand;

use App\Traits\ExportableTrait;
use App\Traits\DeliverableTrait;
use Exception;

class SparkCustomerListLoad extends Command
{
    use ExportableTrait;
    use DeliverableTrait;
    
    // This file is used to load a Customer List for Active Customers from Client's FTP / Remote server and process Active Customers to provide Alerts to our Agents that
    // an account is already enrolled.  We have a featured called "Enable Active Customer Checks By Vendor" where some Vendors are Exempt from data loaded in this job.
    // This is a nightly job which is scheduled to be triggered on our TPVHUB server as a CRONJOB.
    //
    // Spark brand_id production  : 7845a318-09ff-42fa-8072-9b0146b174a5
    // Spark brand_id staging     : c72feb62-44e7-4c46-9cda-a04bd0c58275
    // Spark brand_id old staging : 6ed156aa-a95d-4df6-905d-df6c56956463
    //
    // EXAMPLES:
    //
    // === Old Staging ===
    // php artisan spark:activecustomer:list:load --brand=6ed156aa-a95d-4df6-905d-df6c56956463 --dryrun --no-email
    //
    // === New Staging (using option to specify a local file) ===
    // php artisan spark:activecustomer:list:load --brand=c72feb62-44e7-4c46-9cda-a04bd0c58275 --dryrun --err-email=verica.nackova@answernet.com --success-email=verica.nackova@answernet.com
    // php artisan spark:activecustomer:list:load --brand=c72feb62-44e7-4c46-9cda-a04bd0c58275 --dryrun --file='Active_List.csv' --no-email
    //
    // === Production ===
    // php artisan spark:activecustomer:list:load --brand=7845a318-09ff-42fa-8072-9b0146b174a5 --dryrun --no-email
    //
    // NOTE: --success-email and --err-email may use MULTIPLE ADDRESSES: --err-email=joe@joe.com --err-email=bob@bob.com

    //
    protected $signature = 'spark:activecustomer:list:load 
        {--dryrun          : Use to prevent saving changes to database            }
        {--dry-run         : Use to prevent saving changes to database            }
        {--debug           : Use to display additional information                }
        {--brand=          : REQUIRED, BrandID is a GUID                          }
        {--type=           : Numeric 1,2,3,4, default is 2 Active Customer        }
        {--no-restore      : Soft Deletes any entries not in file                 }
        {--no-email        : Prevent sending emails                               }
        {--force-email     : No emails are sent if --file used, this sends email  }
        {--file=           : Specify local file to import, overrides FTP download }
        {--max-chunks=     : Number of rows to write to db, higher goes faster    }
        {--err-email=*     : overrides default error email recpients if used      }
        {--success-email=* : overrides success error email recpients if used      }';

    // customer_list DB values
    private const CUSTOMER_LIST_TYPES = [
        'Blacklist' => 1,
        'Active Customer' => 2,
        'Approved Customers' => 3,
        'Do Not Call' => 4,
    ];

    private const BRAND_IDS = [
        'spark_energy' => ['production' => '7845a318-09ff-42fa-8072-9b0146b174a5', 'staging' => 'c72feb62-44e7-4c46-9cda-a04bd0c58275','old_staging' => '6ed156aa-a95d-4df6-905d-df6c56956463']
    ];

    // Data from service_types table in Focus, update with new service types as needed
    private const SERVICE_TYPES = [
        'Five9' => 1,
        'Amazon Connect' => 2,
        'Twilio' => 3,
        'TPV Development' => 4,
        'TXU/Dynegy Rates API' => 6,
        'TXU SOAP API' => 7,
        'Clearview SFTP' => 12,
        'Inspire REST API' => 13,
        'Inspire REST API' => 14,
        'Entel FTP Site' => 15,
        'Genie Retail Vendor API' => 16,
        'Twilio IVR Script' => 17,
        'Indra Active/DNC API' => 18,
        'Genie Customer Eligibility Check API' => 19,
        'Genie Customer Eligibility Check API' => 20,
        'SouthStar API' => 24,
        'Transparent BPO API' => 26,
        'Gexa Vendor API' => 27,
        'XOOM CustomerPortalWS API' => 28,
        'Direct Energy AB Tablet API' => 29,
        'GM Surveys FTP' => 30,
        'NRG Surveys FTP' => 31,
        'Direct Energy FTP' => 32,
        'Contract Generator API' => 33,
        'Indra TpvHub SFTP' => 34,
        'DXC Test FTP Site' => 35,
        'Genie FTP' => 36,
        'SouthStar TPV SFTP Prod' => 37,
        'Santanna TPV SFTP Prod' => 38,
        'Vista API' => 39,
        'Perch API Key' => 41,
        'Symmetry RPM API' => 42,
        'Abest FTP' => 43,
        'Family Energy SFTP' => 44,
        'Constellation Digital Hub API' => 45,
        'Constellation Partner Service API' => 46,
        'Clean Choice TPVStatus API' => 47,
        'Spark SFTP' => 48,
        'GCE Renewal Letter FTP' => 49,
        'Spark SFTP Active List' => 50,
        'Generic API' => 99
    ];

    protected $description = 'Loads a Customer List from FTP or remote file, which are defined in provider_integrations to the customer_list table.';
    protected $dryrun = null;

    private $brand_id = null;
    private $brand    = null;
    private $config   = null;
    private $pi_notes = null;
    private $filename = null;
    private $contents = null;
    private $now      = null;
    private $bar      = null;
    
    private $missingUtilities           = [];
    private $missingUtilityLabels       = [];
    private $duplicateUtilityLabels     = [];
    private $invalidLines               = [];
    private $brandUtilitySupportedFuels = [];
    private $customerLists              = [];
    private $lines                      = [];
    private $fileData                   = [];
    private $recordsToCreate            = [];
    private $recordsToRestore           = [];
    private $errorDistroList            = [];
    private $successDistroList          = [];
    
    // Default values, use corresponding arguments to override values
    private $doRestore = true;                                     // override with --no-restore
    private $max_chunks = 5000;                                    // override with --max-chunks=n
    private $type = self::CUSTOMER_LIST_TYPES['Active Customer'];  // override with --type=1, default here is set to 2

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
        $this->info('spark:activecustomer:list:load starting');
        Log::info('spark:activecustomer:list:load starting', [$_SERVER['argv']]);

        if ($this->option('dryrun') || $this->option('dry-run')) {
            $this->warn('spark:activecustomer:list:load DRYRUN ENABLED, ALL DATA WRITTEN WILL BE DISCARDED.  All data will be processed.  Emails may be generated...');
        }

        // Transaction - Either ALL values will be saved, or NONE of them save if an Exception is thrown.  Use --dryrun to email results without making changes
        DB::beginTransaction();        

        try {
            $this->setCommandOptions();
            $this->loadProviderIntegrations();
            $this->setEmailOptions();
            $this->ftpDownload();
            $this->loadBrandUtilitySupportedFuels();
            $this->deleteCustomerLists();
            $this->loadCustomerLists();
            $this->loadDataFromFile();
            $this->processData();
            $this->cleanupInvalidLines();
            $this->saveNewRecordsToDB();
            $this->restoreDeletedRecords();
            $this->emailRecipients();
        }
        catch(\Exception $e) {

            // Transaction rollback Database values
            DB::rollBack();

            return $this->displayAndLogException($e);
        }

        if ($this->dryrun) {
            // Transaction rollback Database values
            DB::rollBack();

            // Return to prevent commiting changes
            Log::info('spark:activecustomer:list:load complete, DRYRUN, all changes DISCARDED, job complete', [$_SERVER['argv']]);
            return $this->warn('spark:activecustomer:list:load complete, DRYRUN, all changes DISCARDED, job complete');
        }
        // Transaction Save Changes in Database
        DB::commit();

        $this->info('spark:activecustomer:list:load complete, DATA SAVED');
        Log::info('spark:activecustomer:list:load complete, DATA SAVED', [$_SERVER['argv']]);
    }

    /** setCommandOptions
     * Sets class properties for executing the command including command line arguments from signature.
     *
     * @return void
     */
    private function setCommandOptions()
    {
        $this->info("spark:activecustomer:list:load setCommandOptions starting");

        $this->now = now('America/Chicago');

        if ($this->option('dryrun') || $this->option('dry-run')) {
            $this->dryrun = true;
        }

        if ($this->option('no-restore')) {
            $this->doRestore = false;
        }

        $this->brand_id = $this->option('brand');

        if (empty($this->brand_id)) {
            throw new Exception('setCommandOptions ERROR: No Brand Specified, please use the --brand option.');
        }

        $this->brand = Brand::find($this->brand_id);

        if (empty($this->brand)) {
            throw new Exception('setCommandOptions ERROR: Unable to find brand with brand_id ' . $this->brand_id);
        }

        if ($this->option('type')) {
            if (!in_array($this->option('type'), self::CUSTOMER_LIST_TYPES)) {
                throw new Exception("setCommandOptions ERROR: --type=" . $this->option('type') . " DOES NOT EXIST.\nSelect valid value from the following:\n" . print_r(self::CUSTOMER_LIST_TYPES, true));
            }

            $this->type = $this->option('type');
        }

        if ($this->option('max-chunks')) {
            if (!is_numeric($this->option('max-chunks'))) {
                throw new Exception("setCommandOptions --max-chunks must be a number: '" . $this->option('max-chunks') . "'");
            }

            if ($this->option('max-chunks') < 0) {
                throw new Exception("setCommandOptions --max-chunks must be greater than 0: '" . $this->option('max-chunks') . "'");
            }

            if ($this->option('max-chunks') > 10000) {
                throw new Exception("setCommandOptions --max-chunks must be 10000 or less: '" . $this->option('max-chunks') . "'");
            }

            $this->max_chunks = $this->option('max-chunks');
        }        

        $this->info("spark:activecustomer:list:load setCommandOptions complete");
    }

    /** loadProviderIntegrations
     * Loads the Provider Integrations needed to connect to client's FTP or remote server by Brand ID
     *
     * @return void
     */
    private function loadProviderIntegrations()
    {
        /*
        Example Data for provider_integrations.notes in Stringified JSON

        {
            "active_customer": true,
            "active_customer_file": "Active_List.csv",
            "root": "/Marketing/AnswerNetTPV/From Spark/Active Customer List",
            "transfer_method": "sftp",
            "error_email" : ["accountmanagers@answernet.com","kkruszyna@sparkenergy.com","krodriguez@sparkenergy.com","ana.garcia@sparkenergy.com", "dpardo@sparkenergy.com", "jtrevino@sparkenergy.com", "BITeam@sparkenergy.com"],
            "success_email" : ["accountmanagers@answernet.com","kkruszyna@sparkenergy.com","krodriguez@sparkenergy.com","ana.garcia@sparkenergy.com", "dpardo@sparkenergy.com", "jtrevino@sparkenergy.com", "BITeam@sparkenergy.com"]
        }

        */
        $this->info("spark:activecustomer:list:load loadProviderIntegrations starting");

        $pi = ProviderIntegration::where('brand_id', $this->brand->id)
            ->where('service_type_id', self::SERVICE_TYPES['Spark SFTP Active List']) // Spark SFTP Active List value is 50
            ->first();

        if (!$pi) {
            throw new Exception('loadProviderIntegrations Unable to find Provider Integration for the brand ' . $this->brand_id);
        }

        if (!($pi->notes)) {
            throw new Exception('loadProviderIntegrations Unable to find notes in provider integration table');
        }
        
        $pi_notes = json_decode($pi->notes, true);
        $errorMsg = json_last_error_msg();
        if($errorMsg != 'No error') {
            throw new Exception('loadProviderIntegrations Unable to parse JSON notes: ' . $errorMsg . "\nNotes:\n$pi->notes");
        }

        if(!$pi_notes['root']) {
            throw new Exception('loadProviderIntegrations Missing root from $pi->notes');
        }
        if(!$pi_notes['active_customer_file']) {
            throw new Exception('loadProviderIntegrations Missing active customer file from $pi->notes');
        }

        $this->pi_notes = $pi->notes = $pi_notes;
        
        if ($this->option('debug') && $this->option('verbose')) {
            $this->info(print_r($pi->toArray(), true));
        }

        $this->config = [
            'host' => $pi->hostname,
            'username' => $pi->username,
            'password' => $pi->password,
            'root' => $pi->notes['root'],
            'port' => 22
        ];

        if ($this->option('debug') && $this->option('verbose')) {
            $this->info('spark:activecustomer:list:load $config: ' . print_r($this->config, true));
        }

        $this->info("spark:activecustomer:list:load loadProviderIntegrations complete");
    }

    /** setEmailOptions
     * Loads email distribution lists to memory or overrides if command line arguments used
     *
     * @return void
     */
    private function setEmailOptions()
    {
        $this->info("spark:activecustomer:list:load setEmailOptions starting");

        $this->setErrorDistroList();
        $this->setSuccessDistroList();

        $this->info("spark:activecustomer:list:load setEmailOptions complete");
    }

    /** setErrorDistroList - Helper function for setEmailOptions
     * - Loads Error Email Distribution List from provider_integrations table notes column error_emails property of JSON data
     */
    private function setErrorDistroList()
    {
        if ($this->option('err-email')) {
            return $this->errorDistroList = $this->option('err-email');
        }

        if (!isset($this->pi_notes['error_emails'])) {
            return $this->warn("spark:activecustomer:list:load setErrorDistroList ProviderIntegrations->notes missing property for 'error_email' (array of emails)");
        }

        if (!is_array($this->pi_notes['error_emails'])) {
            return $this->warn("spark:activecustomer:list:load setErrorDistroList ProviderIntegrations->notes expects 'error_emails' to be an Array");
        }

        foreach ($this->pi_notes['error_emails'] as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("spark:activecustomer:list:load setErrorDistroList email '$email' is not a valid email, skipping...");
                continue;
            }

            $this->errorDistroList[] = $email;
        }
    }

    /** setSuccessDistroList - Helper function for setEmailOptions
     * - Loads Error Email Distribution List from provider_integrations table notes column error_emails property of JSON data
     */
    private function setSuccessDistroList()
    {
        if ($this->option('success-email')) {
            return $this->successDistroList = $this->option('success-email');
        }

        if (!isset($this->pi_notes['success_emails'])) {
            return $this->warn("spark:activecustomer:list:load setSuccessDistroList ProviderIntegrations->notes missing property for 'success_emails' (array of emails)");
        }

        if (!is_array($this->pi_notes['success_emails'])) {
            return $this->warn("spark:activecustomer:list:load setSuccessDistroList ProviderIntegrations->notes expects 'success_emails' to be an Array");
        }

        foreach ($this->pi_notes['success_emails'] as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("spark:activecustomer:list:load setSuccessDistroList email '$email' is not a valid email, skipping...");
                continue;
            }

            $this->successDistroList[] = $email;
        }
    }

    /** ftpDownload
     * Downloads file from Client Remote Server using data loaded from Provider Integrations, or from Local File for immediate deployment to Prod DB
     *
     * @return void
     */
    public function ftpDownload()
    {
        $this->info('spark:activecustomer:list:load ftpDownload starting');

        try {
            if ($this->option('file')) {
                $this->warn('spark:activecustomer:list:load ftpDownload USING LOCAL FILE: ' . $this->option('file'));

                if (!file_exists($this->option('file'))) {
                    throw new Exception("ftpDownload Specified File: --file='" . $this->option('file') . "' FILE DOES NOT EXIST");
                }

                // Allow using a LOCAL FILE instead of FTP for manual data entry, IE client sends us a file we need to import now
                $this->filename = $this->option('file');
                $this->contents = file_get_contents($this->filename);
            }
            else {
                $adapter = new SftpAdapter($this->config);

                $filesystem = new Filesystem($adapter);

                $files = $filesystem->listContents($this->config['root']);
                if ($this->option('debug')) {
                    $this->info('spark:activecustomer:list:load Remote Files:');
                    $this->info('spark:activecustomer:list:load ' . print_r($files, true));
                }

                $this->filename = $this->pi_notes['active_customer_file'];
                $this->contents = $filesystem->read($this->filename);
            }

            if (!$this->contents) {
                throw new Exception("ftpDownload File '$this->filename' has no contents");
            }
        } catch (\Exception $e) {
            $errorMsg = 'ftpDownload ERROR: ' . $e->getMessage();

            if ($this->dryrun) {
                $errorMsg .= "\n\nDRYRUN - No changes to DB";
            }

            // Do not send email to Email Distro List if we specify a local file as its used by developers for manual import of data
            if (!$this->option('file')) {
                // Email to Error Distribution Lists can notify client that there is an issue with us connecting to their FTP server
                $this->emailErrorLog($errorMsg);
            }

            throw new Exception($errorMsg);
        }

        $this->info('spark:activecustomer:list:load ftpDownload complete');
    }

    /** loadBrandUtilitySupportedFuels()
     * - This will load ALL the utiltiies to a Named Array for faster lookups.  We have to load each utility supported fuel anyway, and a single query is faster
     *   than loading each utility one by one
     * - This is BEST used with array_key_exists($this->array[$key]) vs in_array as in_array iterates over the VALUES in the Array which causes slowness
     *   where as accessing by the Array Key has direct access and is VERY fast.
     * 
     * @return void
    */
    private function loadBrandUtilitySupportedFuels() {
        $this->info("spark:activecustomer:list:load loadBrandUtilySupportedFuels starting");

        $query = UtilitySupportedFuel::select(
                'utility_supported_fuels.*', 
                'brand_utilities.utility_label AS utility_label',
                'utilities.name AS utility_name',
                'states.state_abbrev AS state',
                DB::raw('case (utility_supported_fuels.utility_fuel_type_id) when "1" then "electric" else "gas" end AS commodity')
            )
            ->join('brand_utilities', function ($join_query) {
                $join_query->on('brand_utilities.utility_id', '=', 'utility_supported_fuels.utility_id')
                     ->where('brand_utilities.brand_id', '=', $this->brand_id);
            })
            ->join('utilities','utilities.id', 'utility_supported_fuels.utility_id')
            ->join('states', 'utilities.state_id', 'states.id')
            ->where('brand_utilities.brand_id', $this->brand_id)
            ->whereNull('brand_utilities.deleted_at')
            ->whereNull('utilities.deleted_at');

        $results = $query->get();

        foreach($results as $result) {
            // Key example: 'PECO_GAS' or 'PGE_ELECTRIC'
            $key = strtolower($result->utility_label) . '_' . strtolower($result->commodity);
            $this->checkDuplicateUtilityLabels($key, $result);
            $this->brandUtilitySupportedFuels[$key] = $result;
        }

        ksort($this->brandUtilitySupportedFuels);

        $this->info("spark:activecustomer:list:load loadBrandUtilitySupportedFuels complete");
    }

    /** checkDuplicateUtilityLabels
     * - Reports whether or not a Utility Supported Fuel already exists in the brandUtilitySupportedFuels Array
     * 
     * - $key :  string comprised of Utility Label and Commodity, IE: PECO_GAS
     * - $usf :  instance of Utility Supported Fuel result
     * 
     * @return void
     */
    private function checkDuplicateUtilityLabels(string $key, UtilitySupportedFuel $usf) {
        if (array_key_exists($key, $this->brandUtilitySupportedFuels)) {
            $existing_usf = $this->brandUtilitySupportedFuels[$key];
            $this->warn("spark:activecustomer:list:load loadBrandUtilySupportedFuels DUPLICATE UTILITY LABEL KEY: '$key', existing USF->id:'$existing_usf->id', new USF->id: '$usf->id'");
            $this->duplicateUtilityLabels[$key] = ['key' => strtoupper($key), 'id1' => $existing_usf->id, 'id2' => $usf->id];
        }
    }

    /** deleteCustomerLists
     * - DELETES current Client List when --no-restore command line option is used
     * 
     * @return void
     */    
    private function deleteCustomerLists()
    {
        if (!$this->doRestore) {
            $this->info("spark:activecustomer:list:load --no-restore used, deleteCustomerLists soft deleting current customer_list starting");

            // Raw SQL Query, this is way faster than loading through models
            $affectedCount = DB::table('customer_lists')
                ->where('brand_id', $this->brand_id)
                ->where('customer_list_type_id', $this->type)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $this->now
                ]);
    
            $this->info("spark:activecustomer:list:load --no-restore used, deleteCustomerLists customer list SOFT DELETED $affectedCount rows");
        }
        else {
            $this->info("spark:activecustomer:list:load deleteCustomerLists NOTHING SOFT DELETED because --no-restore option not used");
        }
    }

    /** loadCustomerLists
     * - Loads all data from customer_lists table for given type
     * - Loading all data is faster than doing a query row by row
     * 
     * @return void
     */    
    private function loadCustomerLists()
    {
        $this->info('spark:activecustomer:list:load loadCustomerLists starting, loading current CustomerLists to memory');

        // Raw SQL Query, this is way faster than loading through models, needs to run AFTER we do deletes so we can see what to RESTORE
        $customerLists = DB::table('customer_lists')
            ->where('brand_id', $this->brand_id)
            ->where('customer_list_type_id', $this->type)
            ->get();

        foreach ($customerLists as $customerList) {
            // FUTURE THINKING - The $key here could be built dynamically to meet other needs depending on conditions specified in Provider Integrations
            $key = $customerList->utility_supported_fuel_id . '_' . $customerList->account_number1;
            $this->customerLists[$key] = $customerList;
        }

        $this->info('spark:activecustomer:list:load CustomerLists loading current CustomerLists to memory complete');
    }

    /** loadDataFromFile
     * - Converts file contents into fileData array from CSV
     *
     * @return void
     */
    private function loadDataFromFile()
    {
        $this->info("spark:activecustomer:list:load loadDataFromFile starting on file: $this->filename");

        // Expects Carriage Return Newline: \r\n
        $this->lines = explode(PHP_EOL, $this->contents);

        $this->fileData  = [];

        $this->bar = $this->output->createProgressBar(count($this->lines) - 1);
        $this->bar->start();

        foreach ($this->lines as $line) {
            $this->fileData[] = str_getcsv($line);
            $this->bar->advance();
        }

        $this->bar->finish();
        $this->bar = null;
        $this->line('');

        $this->info("spark:activecustomer:list:load loadDataFromFile: '$this->filename' complete with " . count($this->fileData) . " total lines");
    }

    /** processData
     * - Iterates each row of imported CSV file to generate two lists, recordsToCreate and recordsToRestore
     *
     * @return void
     */
    private function processData()
    {
        $this->info("spark:activecustomer:list:load processData starting");

        $this->bar = $this->output->createProgressBar(count($this->fileData));
        $this->bar->start();

        // Iterate all data, start at 1 to skip header row
        for ($i = 1, $max = count($this->fileData); $i < $max; $i++) {
            if ($this->isValidRowOrLogError($this->fileData[$i], $i)) {
                $this->processRow($this->fileData[$i], $i);
            }
            
            $this->bar->advance();
        }
        
        $this->bar->finish();
        $this->bar = null;
        $this->line('');

        $this->info("spark:activecustomer:list:load processData complete");
    }

    /** isValidRowOrLogError - Helper function for processData()
     * - This is one of several places we can check for potential errors and inform users how to fix the data.
     * - Returns either true or false.  In the event a row is not valid for processing, data is retained to be sent to error email recipients
     *
     * $row   - CSV Row as Array, should have at least 3 options in correct order of Account Number, Commidity, and Utility Label
     * $count - CSV Line number, used for Error Reporting
     * 
     * @return void
     */
    private function isValidRowOrLogError($row, $count) : bool
    {
        // We expect a minimum of 3 rows of data.  If we do not have 3 rows, push to invalidLines array.  End of file lines cleaned later.
        if (count($row) < 3) {
            if (count($row) > 0) { $this->invalidLines[] = [ 'line' => $count + 1, 'data' => trim($this->lines[$count]) ]; }
            return false;
        }

        return true;
    }

    /** processRow - Helper function for processData()
     * - locates Utility from brandUtilitiySupportedFuels loaded to memory by Utility Label and Commodity
     *
     * $row   - CSV Row as Array, should have at least 3 options in correct order of Account Number, Commidity, and Utility Label
     * $count - CSV Line number, used for Error Reporting
     * 
     * @return void
     */
    private function processRow($row, $count)
    {
        $acctNumber   = ltrim(trim($row[0]), 'A');
        $rawCommodity = trim($row[2]);
        $utilityLabel = trim($row[1]);

        // NOTE: if getUtilitySupportedFuelOrLogError can not find a record, it appends to missingUtilities array
        $usf = $this->getUtilitySupportedFuelOrLogError($utilityLabel, $rawCommodity, $count);

        if (!$usf) {
            return;
        }

        // We may get null values back from getCustomerList, this is normal if we are creating a new record
        $customerList = $this->getCustomerList($usf->id, $acctNumber);

        // If $customerList exists, always add it to recordsToRestore, else create a New Record.  To have a value here, it must be in both Import File and DB
        if ($customerList) {
            $this->recordsToRestore[] = $customerList->id;
        }
        else {
            $this->recordsToCreate[] = [
                'id' => Uuid::uuid4(),
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'customer_list_type_id' => $this->type,
                'brand_id' => $this->brand_id,
                'utility_supported_fuel_id' => $usf->id,
                'account_number1' => $acctNumber,
                'filename' => $this->filename,
                'processed' => 1
            ];
        }
    }

    /** cleanupInvalidLines
     * - Removes invalid lines with no data used at end of file
     * - Invalid Lines are considered to be lines with insufficient data.  If it occurs in the middle of the file, it will be listed.  Empty lines at end of file are removed here.
     *
     * @return void
     */
    private function cleanupInvalidLines()
    {
        $this->info("spark:activecustomer:list:load cleanupInvalidLines starting");

        $lines = [];
        $lastData = false;

        // Count the number of Empty Lines ONLY from the END of file
        $count = 0;

        // Iterate the array in reverse order, skips adding to new array until valid data found
        for ($i = count($this->invalidLines) - 1; $i > -1; $i--) {
            if (!$lastData && trim($this->invalidLines[$i]['data'])) {
                $lastData = true;
            }

            // Continue if looking at data at end of file, contine to not add to cleaned up array (may be more than one line)
            if (!$lastData) {
                $count++;
                continue;
            } 

            $lines[] = $this->invalidLines[$i];
        }

        // Reverse the order of the array since $lines array was processed in reverse order
        $this->invalidLines = array_reverse($lines);

        $msg = ($count > 0 ? "removed $count Empty Lines at END OF FILE from invalidLines" : "0 invalidLines removed");
        $this->info("spark:activecustomer:list:load cleanupInvalidLines complete, $msg");
    }    

    /** getCustomerList - Helper function for processRow
     * - Loads Customer List row from Customer Lists in memory by Array Key of Utility ID and Account Number 1 for fast lookups
     *
     * @return mixed
     */
    private function getCustomerList(string $utility_supported_fuel_id, string $account_number1)
    {
        $key = $utility_supported_fuel_id . '_' . $account_number1;
        return array_key_exists($key, $this->customerLists) ? $this->customerLists[$key] : null;
    }

    /** getUtilitySupportedFuelOrLogError - Helper function for processRow
     * - Loads Utility from Brand Utilites in memory by Array Key of Utility Label and Commodity for fast lookups
     * 
     * $ulabel    - Utility Label
     * $commodity - either 'Gas' or 'Electric', case insensitive
     * $line      - Line number of CSV row
     * 
     * @return mixed
     */
    private function getUtilitySupportedFuelOrLogError(string $utilityLabel, string $rawCommodity, int $line)
    {
        $rawCommodityLower = strtolower($rawCommodity);
        if (in_array($rawCommodityLower, ["gas", "electric"])) {
            $key = strtolower($utilityLabel) . '_' . $rawCommodityLower;
            // array_key_exists is very fast in comparison to in_array because in_array looks at each value of the data in the array
            $val = array_key_exists($key, $this->brandUtilitySupportedFuels) ? $this->brandUtilitySupportedFuels[$key] : null;

            if ($val) {
                return $val;
            }
        }

        // +1 to $line handles Arrays starting at 0 (which is our Header row), but Line Numbers when a human is reading start at 1
        $this->missingUtilities[] = [$line + 1, $utilityLabel, $rawCommodity];

        if (!array_key_exists($utilityLabel, $this->missingUtilityLabels)) {
            $this->missingUtilityLabels[$utilityLabel] = ['label' => $utilityLabel, 'electric' => 0, 'gas' => 0, 'total' => 0];
        }

        $this->missingUtilityLabels[$utilityLabel][$rawCommodityLower]++;
        $this->missingUtilityLabels[$utilityLabel]['total']++;

        // We need to return a null here to continue processing so we can see that a Utility was not found
        return null;
    }

    /** saveNewRecordsToDB
     * - Iterates recordsToCreate and inserts into DB in Chunks.  Use --max-chunks to change default value
     *
     * @return void
     */
    private function saveNewRecordsToDB()
    {
        $this->info("spark:activecustomer:list:load saveNewRecordsToDB starting");

        if (empty($this->recordsToCreate)) {
            return $this->warn('spark:activecustomer:list:load No new records to create');
        }

        $this->info('spark:activecustomer:list:load Creating ' . count($this->recordsToCreate) . " new records in Chunks of " . $this->max_chunks);

        $this->bar = $this->output->createProgressBar(ceil(count($this->recordsToCreate) / $this->max_chunks));
        $this->bar->start();

        $chunks = collect($this->recordsToCreate)->chunk($this->max_chunks);
        foreach ($chunks as $chunk) {
            DB::table('customer_lists')->insert($chunk->toArray());
            $this->bar->advance();
        }

        $this->bar->finish();
        $this->bar = null;
        $this->line('');

        $this->info("spark:activecustomer:list:load saveNewRecordsToDB complete");
    }

    /** restoreDeletedRecords
     * - Iterates recordsToRestore and inserts into DB in Chunks.  Use --max-chunks to change default value
     * - Customer List Rows that match the Utility ID and Commodity and are in the CSV file are always set to be restored
     *
     * @return void
     */
    private function restoreDeletedRecords()
    {
        $this->info("spark:activecustomer:list:load restoreDeletedRecords starting");

        if (empty($this->recordsToRestore)) {
            return $this->warn('spark:activecustomer:list:load No new records to restore');
        }

        $this->info('spark:activecustomer:list:load Restoring ' . count($this->recordsToRestore) . " records in Chunks of " . $this->max_chunks);

        $this->bar = $this->output->createProgressBar(ceil(count($this->recordsToRestore) / $this->max_chunks));
        $this->bar->start();

        $result_count = 0;

        $chunks = collect($this->recordsToRestore)->chunk($this->max_chunks);
        foreach ($chunks as $chunk) {
            $result_count += DB::table('customer_lists')
                ->where('brand_id', $this->brand_id)
                ->where('customer_list_type_id', $this->type)
                ->whereNotNull('deleted_at')
                ->whereIn('id', $chunk->toArray())
                ->update([
                    'updated_at' => $this->now,
                    'deleted_at' => null,
                    'processed' => 1,
                ]);

            $this->bar->advance();
        }

        $this->bar->finish();
        $this->bar = null;
        $this->line('');

        // The $result_count here is only the number of affected rows that are restored
        $this->info("spark:activecustomer:list:load restoreDeletedRecords complete, restored " . count($this->recordsToRestore) . " records, total of $result_count rows affected.");
    }

    /** emailRecipients
     * - Handles sending either Success or Error email depending on whether or not there are any Missing Utilities
     *
     * @return void
     */
    private function emailRecipients()
    {
        $this->info("spark:activecustomer:list:load emailRecipients starting");

        // If there are no Errors found
        if (empty($this->missingUtilities) && empty($this->invalidLines) && empty($this->duplicateUtilityLabels)) {
            $this->emailSuccessLog();
            return $this->info("spark:activecustomer:list:load emailRecipients success complete");
        }

        if (!empty($this->missingUtilities)) {
            $msg = 'spark:activecustomer:list:load emailRecipients: Some (' . count($this->missingUtilities) . ') entries from rows could not find a Utility Supported Fuel';
            if (!$this->option('verbose')) { $msg .= ' (use --verbose to EMAIL each row of missing USF, WARNING: Possibly a LOT of data)'; }
            else { $msg .= ' (--verbose option enabled, EMAIL will be sent including each row of missing USF)'; }
            $this->warn($msg);

            // Sorts the Utility Lables alphabetically by KEY, makes it a bit easier to read
            ksort($this->missingUtilityLabels);

            if ($this->option('debug')) {
                $count = 1;
                $this->warn("spark:activecustomer:list:load == Missing Utility Labels with Counts ==");
                foreach ($this->missingUtilityLabels as $mul) {
                    $this->warn("\t$count:\t" . $mul['label'] . "\t" . $mul['total']);
                    $count++;
                }
                $this->warn("spark:activecustomer:list:load == End of Missing Utility Labels ==");
            }
        }

        if (!empty($this->invalidLines)) {
            $msg = 'spark:activecustomer:list:load emailRecipients: Invalid Data Lines in File: (' . count($this->invalidLines) . ') were found';
            if (!$this->option('verbose')) { $msg .= ' (use --verbose to see each Invalid Line, WARNING: Possibly a LOT of data)'; }
            $this->warn($msg);
            if ($this->option('verbose')) {
                foreach ($this->invalidLines as $invalidLine) {
                    $this->line($invalidLine['line'] . "\t" . $invalidLine['data']);
                }
            }
        }

        if (!empty($this->duplicateUtilityLabels)) {
            $this->warn('spark:activecustomer:list:load emailRecipients: Duplicate Utility Labels Found:');

            // Sorts the Duplicate Utility Lables alphabetically by KEY, makes it a bit easier to read
            ksort($this->duplicateUtilityLabels);

            if ($this->option('debug')) {
                $count = 1;
                $this->warn("spark:activecustomer:list:load == Duplicate Utility Labels with Counts ==");
                foreach ($this->duplicateUtilityLabels as $dul) {
                    $this->warn("\t$count:\t" . $dul['key'] . "\t" . $dul['id1'] . "\t" . $dul['id2']);
                    $count++;
                }
                $this->warn("spark:activecustomer:list:load == End of Duplicate Utility Labels ==");
            }
        }        
        
        $this->emailErrorLog();

        $this->info("spark:activecustomer:list:load emailRecipients with errors complete");
    }

    /** emailSuccessLog - Helper function for emailRecipients
     * - Handles Success Emails.  Can be skipped with --no-email command line argument
     *
     * @return void
     */
    private function emailSuccessLog()
    {
        if ($this->option('no-email')) {
            return $this->warn("spark:activecustomer:list:load emailSuccessLog --no-email option used NO EMAIL SENT");;
        }

        // Safeguard against accidentally emailing client when running on a Developer Environment.  Must use --force-email or specify a Success Email with --success-email
        if (($this->option('file') || config('app.env') != 'production') && !$this->option('success-email') && !$this->option('force-email')) {
            return $this->warn("spark:activecustomer:list:load emailSuccessLog --file was specified or not Production ENV, NO EMAIL SENT, override with either --success-email= or --force-email");
        }        

        $email_config = [
            'from' => 'no-reply@tpvhub.com',
            'to' => $this->successDistroList,
            'subject' => 'Spark Import Active Customers List Success',
            'body' => ""
        ];
        
        $email_config['body'] .= "\n\nSpark Import Active Customers List Success at " . date('m/d/Y h:i:s a', time() - (3600 * 4));

        $this->sendGenericEmail($email_config);
        $this->info('spark:activecustomer:list:load emailSuccessLog: emails sent to Distro List:' . print_r($this->successDistroList, true));
    }

    /** emailErrorLog - Helper function for emailRecipients
     * - Handles Error Emails.  Generates support files to allow client to address the issues.  Can be skipped with --no-email command line argument
     *
     * @return void
     */
    private function emailErrorLog($errorMsg = null)
    {
       if ($this->option('no-email')) {
            return $this->warn("spark:activecustomer:list:load emailErrorLog --no-email option used NO EMAIL SENT");
        }

        // Safeguard against accidentally emailing client when running on a Developer Environment.  Must use --force-email or specify an Error Email with --err-email
        if (($this->option('file') || config('app.env') != 'production') && !$this->option('err-email') && !$this->option('force-email')) {
            return $this->warn("spark:activecustomer:list:load emailErrorLog --file was specified or not Production ENV, NO EMAIL SENT, override with either --err-email= or --force-email");
        }        

        // This argument is present when there is an error connecting to client's remote server to advise them issue on their end
        if($errorMsg != null) {
            $email_config = [
                'from' => 'no-reply@tpvhub.com',
                'to' => $this->errorDistroList,
                'subject' => 'Spark Import Active Customers List Errors',
                'body' => $errorMsg
            ];

            $this->sendGenericEmail($email_config);
            $this->info('Spark Active Customer List Errors Email sent to Distro List');
        }

        // If there were Errors then send an Email with Failure attachment results
        if (count($this->missingUtilities) > 0 || count($this->invalidLines) > 0 || count($this->duplicateUtilityLabels) > 0) {
            $attachment_files = [];

            $this->attachMissingUtilityLabelsFiles($attachment_files);
            $this->attachInvalidLinesFile($attachment_files);
            $this->attachDuplicateUtilityLabels($attachment_files);

            $email_config = [
                'from' => 'no-reply@tpvhub.com',
                'to' => $this->errorDistroList,
                'subject' => 'Spark Import Active Customers List Errors',
                'body' => "See attached file for error details",
                'attachments' => $attachment_files
            ];

            $email_config['body'] .= "\n\nSome Active Customers failed to import into Focus.  The rest were imported successfully. See attached.";
            if ($this->dryrun){
                $email_config['body'] .= "\n\nDRYRUN - No data changed in Database";
            }
            $email_config['body'] .= "\n\nAdditional files included may be useful for comparing Utility Labels in TPV to the Error List file.";
            $email_config['body'] .= "\n\nProcess Complete: " . date('m/d/Y h:i:s a', time() - (3600 * 4));

            $this->sendGenericEmail($email_config);
            $this->info('spark:activecustomer:list:load emailErrorLog: ' . print_r($this->errorDistroList, true));
        }
    }

    /** attachMissingUtilityLabelsFiles - Helper function for emailErrorLog
     * - This will push files generated, if any, to the POINTER of the $attachment_files array if a Utility Supported Fuel can not be found by their Utility Label
     *
     * @return mixed
     */

    private function attachMissingUtilityLabelsFiles(&$attachment_files)
    {
        if (count($this->missingUtilities) == 0) {
            return $this->info('spark:activecustomer:list:load attachMissingUtilityLabelsFiles - No Missing Utilities to attach');
        }

        // This file tends to be very large and does not provide meaningful info to client.  May be useful to devs so use this with --verbose option
        if ($this->option('verbose')) {
            // This lists each line of their data file where there is a Missing Utility Supported Fuel that could not be found by the Utility Label they provided
            $error_file = public_path('\tmp\SparkActiveCustomerListError.csv');
            $error_header = ['Line', 'Missing Utility Label', 'Commodity'];
            // Args: filename, data (array), headers
            $this->writeCsvFile($error_file, $this->missingUtilities, $error_header);
            $attachment_files[] = $error_file;
        }

        // This provides a bit simpler list, only showing the Utility Labels and a breakdown of a total count of how many times the missing Utility Label was referenced
        $error_utility_label_file = public_path('\tmp\SparkActiveCustomerListUtilityLabelCountError.csv');
        $error_header_utility_label = ['Utility Label', 'Electric Count ', 'Gas Count', 'Total'];
        $this->writeCsvFile($error_utility_label_file, $this->missingUtilityLabels, $error_header_utility_label);
        $attachment_files[] = $error_utility_label_file;

        // This provides the client with a list of their Utilities and our Utility Labels to allow them the ability to correct the issue
        $brand_utility_file = public_path('\tmp\SparkActiveCustomerListBrandUtilities.csv');
        // Destructure the returned array and assign them to variable names
        [$brand_utility_headers, $brand_utilities] = $this->getBrandUtilitySupportedFuelsForFile();
        $this->writeCsvFile($brand_utility_file, $brand_utilities, $brand_utility_headers);
        $attachment_files[] = $brand_utility_file;

        $this->info('spark:activecustomer:list:load attachMissingUtilityLabelsFiles - Attaching Files: ' . print_r($attachment_files, true));
    }

    /** getBrandUtilitySupportedFuelsForFile - Helper function for attachMissingUtilities
     * - brandUtilitysupportedFuels also contains data from brandUtilities and Utilities tables
     * - Returns two arrays of Headers and Values for client to read and know what Utility and Utility Labels currently are in Focus
     *
     * @return mixed
     */
    private function getBrandUtilitySupportedFuelsForFile()
    {
        $brand_utility_headers = ['Utility Name', 'Utility Label', 'State', 'Commodity'];
        $brand_utilities = [];
        foreach ($this->brandUtilitySupportedFuels as $busf) {
            $brand_utilities[] = [$busf->utility_name, $busf->utility_label, $busf->state, $busf->commodity];
        }

        // Use Destructuring on the array returned here to assign directly to local variables, example:  [$headers, $utilities] = $this->getBrandUtilitiesForFile();
        return [$brand_utility_headers, $brand_utilities];
    }

    /** attachInvalidLinesFile - Helper function for emailErrorLog
     * - This will push files generated, if any, to the POINTER of the $attachment_files array in the event the Raw Data file has unreadable data
     * - End of File empty lines are excluded, empty lines are NOT excluded if the line is between two sets of valid data
     * 
     * @return mixed
     */

    private function attachInvalidLinesFile(&$attachment_files)
    {
        if (count($this->invalidLines) == 0) {
            return $this->info('spark:activecustomer:list:load attachInvalidLinesFile - No Invalid Lines');
        }

        $error_invalid_lines_file = public_path('\tmp\SparkActiveCustomerListInvalidLines.csv');
        $this->writeCsvFile($error_invalid_lines_file, $this->invalidLines, ['Line Number', 'Raw Data']);
        $attachment_files[] = $error_invalid_lines_file;

        return $this->info('spark:activecustomer:list:load attachInvalidLinesFile - Attached File: SparkActiveCustomerListInvalidLines.csv');
    }

    /** attachDuplicateUtilityLabels - Helper function for emailErrorLog
     * - This will push files generated, if any, to the POINTER of the $attachment_files array in the event we have Duplicate Utility Labels
     * - End of File empty lines are excluded, empty lines are NOT excluded if the line is between two sets of valid data
     * 
     * @return mixed
     */

    private function attachDuplicateUtilityLabels(&$attachment_files)
    {
        if (count($this->duplicateUtilityLabels) == 0) {
            return $this->info('spark:activecustomer:list:load duplicateUtilityLabels - No Duplicate Utility Labels');
        }

        $error_duplicate_utility_labels_file = public_path('\tmp\SparkActiveCustomerListDuplicateUtilityLabels.csv');
        $this->writeCsvFile($error_duplicate_utility_labels_file, $this->duplicateUtilityLabels, ['Duplicate Utility Supported Fuel Label and Commodity','USF ID', 'Conflicting ID']);
        $attachment_files[] = $error_duplicate_utility_labels_file;

        return $this->info('spark:activecustomer:list:load duplicateUtilityLabels - Attached File: SparkActiveCustomerListDuplicateUtilityLabels.csv');
    }   
     
    /** displayAndLogException - Helper function for handle()
     * - Logs to Laravel.log and displays output to user
     * 
     * @return void
     */
    private function displayAndLogException(\Exception $e)
    {
        $errorMsg = $e->getMessage();

        if (stripos($errorMsg, 'SQLSTATE[HY000]: General error: 1390 Prepared statement contains too many placeholders') !== false) {
            // This may happen if --max-chunks is set too high.  Truncate the error message to only display the important bit as the full error message is VERY large
            $errorMsg = 'SQLSTATE[HY000]: General error: 1390 Prepared statement contains too many placeholders';

            // Explain to user how to correct issue without having to read source code
            if ($this->option('max-chunks')) { $errorMsg .= ", try reducing --max-chunks='{$this->option('max-chunks')}'"; }
        }

        // if any progress bars are displayed, go to next line, otherwise errors are formtted a little odd
        if ($this->bar){ $this->line(''); }

        $this->error("spark:activecustomer:list:load ALL CHANGES DISCARDED due to EXCEPTION at Line {$e->getLine()}: " . $errorMsg);
        Log::info("spark:activecustomer:list:load ALL CHANGES DISCARDED due to EXCEPTION at Line {$e->getLine()}: ", [$errorMsg]);
    }

}
