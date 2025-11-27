<?php

namespace App\Console\Commands\CleanSky;

use Carbon\Carbon;
use App\Models\Brand;
use Ramsey\Uuid\Uuid;
use App\Models\CustomerList;
use Illuminate\Console\Command;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\DB;
use App\Models\ProviderIntegration;
use Illuminate\Support\Facades\Log;
use App\Models\UtilitySupportedFuel;
use Illuminate\Support\Facades\Cache;
use League\Flysystem\Sftp\SftpAdapter;
use phpDocumentor\Reflection\Types\Void_;
use App\Helpers\BulkSqlUpsert;

use App\Traits\ExportableTrait;
use App\Traits\DeliverableTrait;

// Brand Specific Load List for ActiveCustomer List, a.k.a "Existing Business Relationship" or EBR
class CleanskyLoadActiveCustomerList extends Command
{
    use ExportableTrait;
    use DeliverableTrait;

    // RUN this from terminal/command prompt: "php artisan cleansky:activecustomer:list:load --brand=01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0 --verbose --debug"
    // php artisan cleansky:activecustomer:list:load --brand=01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0 --verbose --debug --err-email=Damian.McQueen@answernet.com --success-email=Damian.McQueen@answernet.com
    // CleanSky North East Brand ID: '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0'
    // CleanSky Texas Brand ID:      '6d525d22-13f6-4381-88b9-d872e8cdf492'

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleansky:activecustomer:list:load {--debug} {--brand=} {--no-restore} {--dryrun} {--err-email=*} {--success-email=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load Active Customer/Blacklist from FTP server';

    private $utilityCache = [];
    private $doRestore = true;
    private $missingUtilities = [];

    private $brand_id;
    private $filename = 'ebr_list.csv';  // Dynamic - Replaced by constructor based on current date
    private $filesystem;
    private $customerListToDelete = [];
    private $utilityLabelKeys = [];         // Named Array / Hashmap of a manufactured key with region-state-utility-utility_label 'ne-oh-electric-aep ohio'
    private $customerListToSave = [];
    private $startTime;
    private $pdo_chunk_limit = 10000;
    private $missingUtilityLabels = [];

    private $totalUtilities = 0;
    private $totalDeleted = 0;
    private $totalInserted = 0;

    private $errorDistroList = [];
    private $successDistroList = [];

    // access with self::PROVIDER_INTEGRATION_TYPES['SFTP'] or ClassName::PROVIDER_INTEGRATION_TYPES['SFTP']
    public const PROVIDER_INTEGRATION_TYPES = [
        'SFTP' => 1,
        'API' => 2,
        'FTP' => 3
    ];

    // access with self::CUSTOMER_LIST_TYPES['Blacklist'] or ClassName::CUSTOMER_LIST_TYPES['Blacklist']
    public const CUSTOMER_LIST_TYPES = [
        'Blacklist' => 1,
        'Active Customer' => 2,
        'Approved Customers' => 3,
        'Do Not Call' => 4
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Set one consistent time for all records created to be used and available throught the instance of this class
        $this->startTime = Carbon::now();

        // Create another timestamp object, double assignment does not work because objects are stored by reference and changes to one affect the other
        $server_time = Carbon::now();
        // Server Timezone (this variable is just used for getting the filename, but at +5 hours ahead, at end of day, the date is incorrect for filename)
        $server_time->setTimezone('America/New_York');

        //$this->startTime = '2023-03-20 17:30:00'; // Use for testing if recording the brand_id is not 100% certain

        // Set the filename to variable name from the date: 'Titan_EBR_032223.csv' for the Existing Business Relationship EBR file
        $this->filename = 'Titan_EBR_' . $server_time->format('mdy') . '.csv';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->setErrorDistroList();
        $this->setSuccessDistroList();

        if ($this->option('no-restore')) {
            $this->doRestore = false;
        }

        $brand_id = $this->brand_id = $this->option('brand');

        if (empty($brand_id)) {
            $this->error('No Brand Specified, please use the --brand option.');
            return 42;
        }

        $brand = Brand::find($brand_id);

        if ($brand) {
            $pi = ProviderIntegration::where('brand_id', $brand->id)
                ->where('provider_integration_type_id', self::PROVIDER_INTEGRATION_TYPES['SFTP']) // value of 1 for SFTP
                ->first();
                
            if ($pi) {
                if (is_string($pi->notes)) {
                    $pi->notes = json_decode($pi->notes, true);
                }

                if ($this->option('debug') && $this->option('verbose')) {
                    info(print_r($pi->toArray(), true));
                }

                if ($this->option('debug')) {
                    $this->info('Using SFTP');
                }

                $config = [
                    'hostname' => $pi->hostname,
                    'username' => $pi->username,
                    'password' => $pi->password,
                    'root' => '/incoming/',
                    'port' => 22,
                    //'timeout' => 10,
                    //'directoryPerm' => 0755,
                ];

                if ($this->option('debug') && $this->option('verbose')) {
                    info(print_r($config, true));
                }

                set_time_limit(0);

                $this->ftpDownload($config, $brand->id);

            } else {
                $this->error('Could not locate credentials for the specified brand (' . $brand_id . ')');
            }
        } else {
            $this->error('The Brand (' . $brand_id . ') was not found.');
        }
    }

    /**
     * File FTP Download
     *
     * @param array  $config - configuration needed to perform FTP upload
     *
     * @return array
     */
    public function ftpDownload($config, $brand_id)
    {
        if ($this->option('verbose')) {
            $this->info('Parsing CSV file...');
            echo(__CLASS__ . " starting FTP File Transfer\n");
        }

        info('FTP at ' . Carbon::now());

        $root = (isset($config['root'])) ? trim($config['root']) : '/';
        try {
            $adapter = new SftpAdapter(
                [
                    'host' => $config['hostname'],
                    'port' => 22,
                    'username' => $config['username'],
                    'password' => $config['password'],
                    'root' => (isset($config['root'])) ? $config['root'] : '/',
                    'timeout' => 60,
                    'directoryPerm' => 0755,
                ]
            );

            $this->filesystem = new Filesystem($adapter);

            $files = $this->filesystem->listContents($root);
            if ($this->option('debug')) {
                $this->info('Remote Files:');
                $this->info(print_r($files, true));
            }

            $contents = $this->filesystem->read($this->filename);

            if (!$contents) {
                if ($this->option('debug') && $this->option('verbose')) {
                    echo "Cleansky Load Active Customer List file has no data or file does not exist.  Exiting process.";
                }
                // Prevent the next step of trying to process the file
                return;
            }

            $this->processFile($contents, $brand_id);

        } catch (\Exception $e) {
            $this->error('Error! The reason reported is: ' . $e);
        }

        return null;
    }

    // Email Lists for Failure and Success
    private function setErrorDistroList() 
    {
        // Override with php artisan cleansky:activecustomer:list:load --brand=01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0 --err-list=example@tpvhub.com --err-list=example2@tpvhub.com
        $this->errorDistroList = ['accountmanagers@answernet.com','BGutierrez@cleanskyenergy.com','MLira@cleanskyenergy.com','earmstrong@cleanskyenergy.com'];
        
        if ($this->option('err-email')) {
            $this->errorDistroList = $this->option('err-email');
        } 
    }

    private function setSuccessDistroList() 
    {
        // Override with php artisan cleansky:activecustomer:list:load --brand=01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0 --success-list=example@tpvhub.com --success-list=example2@tpvhub.com
        $this->successDistroList = ['accountmanagers@answernet.com','BGutierrez@cleanskyenergy.com','MLira@cleanskyenergy.com','earmstrong@cleanskyenergy.com'];
        
        if ($this->option('success-email')) {
            $this->successDistroList = $this->option('success-email');
        } 
    }    

    /** getNewCustomerList() - returns a CustomerList class object with populated values
     */
    private function getNewCustomerList(string $utility_supported_fuel_id, string $account_number) : CustomerList {
        // Create the instance of the object then set the values
        $newCustomerList = new CustomerList();        
        // Since we are doing a Bulk Insert we need to have a valid Guid, which needs to be a fillable property
        $newCustomerList->id = $this->NewGuid();
        $newCustomerList->brand_id = $this->brand_id;
        $newCustomerList->created_at = $this->startTime;
        $newCustomerList->filename = $this->filename;
        $newCustomerList->account_number1 = $account_number;
        $newCustomerList->processed = 1;
        $newCustomerList->customer_list_type_id = self::CUSTOMER_LIST_TYPES['Active Customer']; // Value of 2
        $newCustomerList->utility_supported_fuel_id = $utility_supported_fuel_id;

        return $newCustomerList;
    }

    /*
    DEPRECATED - Remove once confirmed client no longer needs any of this.  Left in place as a reference but not needed.

    // Return value from this should match the tpv.brand_utility_supported_fuel.ldc_code value from DB
    //
    // This is intended as a LIMITED NUMBER OF TIMES TO FIX FOR GETTING CLEANSKY OFF THE GROUND
    // THIS IS NOT INTENDED AS AN APPROPRIATE LONG TERM FIX DUE TO USE OF STATIC VALUES FOR LDC CODES
    private function fixUtilityMap(string $utility, string $utility_label, string &$commodity, string &$state) : string {
        
        // If $utility from clients CSV file utility_label is this string value, return a different string that matches Focus Utility Label
        // if ($utility_label == 'Public Service Electric and Gas'){
        //     if ($commodity == 'Electric') return 'Public Service Electric and Gas - Electric';
        //     if ($commodity == 'Gas') return 'Public Service Electric and Gas - GAS';
        // }

        // if ($utility_label == 'MetEd'){
        //     return 'Metropolitan Edison Company';
        // }

        // if ($utility_label == 'PECO Energy Gas' && $commodity == 'Electric'){
        //     $commodity = 'Gas';
        //     return $utility_label;
        // }

        // if ($utility_label == 'PECO Energy' && $commodity == 'Gas'){
        //     return 'PECO Energy Gas';
        // }        

        // if ($utility_label == 'National Grid - Mass'){
        //     return 'Nat Grid - Mass Electric';
        // }

        // if ($utility_label == 'NJ Natural Gas'){
        //     return 'New Jersey Natural Gas';
        // }

        // if (($utility_label == 'UGI Utilities' || $utility_label == 'UGI Utilities Gas') && $commodity == 'Electric'){
        //     return 'UGI Utilities Electric';
        // }

        // if ($utility_label == 'UGI Utilities Gas'){
        //     return 'UGI Utilities';
        // }

        // if ($utility_label == 'Washington Gas MD'){
        //     return 'Washington Gas Light Company';
        // }

        // if ($utility_label == 'Jersey Central Power & Light'){
        //     return 'JCP&L';
        // }

        // if ($utility_label == 'NSTAR - Boston Edison'){
        //     return 'NStar - BECO';
        // }

        // if ($utility_label == 'Columbia Gas OH'){
        //     return 'Columbia Gas Ohio';
        // }

        // if ($utility_label == 'Allegheny Power MD'){  // Prod Staging names are different
        //     return 'Allegheny Power';
        // }

        // if ($utility_label == 'PEPCO Holdings'){
        //     if ($state == 'DC') return 'Pepco DC';
        //     if ($state == 'MD') return 'Pepco MD';
        // }

        // if ($utility_label == 'Washington Gas DC'){
        //     return 'Washington Gas Light Company - DC';
        // }

        // if ($utility_label == 'Washington Gas MD'){
        //     return 'Washington Gas Light Company';
        // }

        // if ($utility_label == 'Dominion East of Ohio'){
        //     return 'Dominion East Ohio';
        // }

        // if ($utility_label == 'Baltimore Gas and Electric' && $commodity == 'Electric'){
        //     return 'Baltimore Gas and Electric - Electric';
        // }

        // if ($utility_label == 'Baltimore Gas and Electric' && $commodity == 'Gas'){
        //     return 'Baltimore Gas and Electric - Gas';
        // }

        // if ($utility_label == 'Baltimore Gas and Electric - Gas' && $commodity == 'Electric'){
        //     $commodity = 'Gas';
        // }

        // if ($utility_label == 'Baltimore Gas and Electric' && $commodity == 'Electric'){
        //     return 'Baltimore Gas and Electric - Electric';
        // }

        // if ($utility_label == 'Vectren'){
        //     return 'Vectren Energy Delivery';
        // }

        // if ($utility_label == 'Eversource WMECO'){
        //     return 'Western Mass Electric Company';
        // }

        // if ($utility_label == 'Dayton Power and Light'){
        //     return 'Dayton Power & Light';
        // }

        // if ($utility_label == 'Columbia Gas of PA'){
        //     return 'Columbia Gas Pennsylvania';
        // }

        // if ($utility_label == 'Columbia Gas OH'){
        //     return 'Columbia Gas Ohio';
        // }

        // if ($utility_label == 'CEIL'){
        //     return 'Cleveland Electric Illuminating Company';
        // }

        // if ($utility_label == 'Duke'){
        //     if ($commodity == 'Gas') $commodity = 'Electric';
        //     return 'Duke Energy Ohio';
        // }

        // if ($utility_label == 'Duke Gas'){
        //     if ($commodity == 'Electric') $commodity = 'Gas';
        //     return 'Duke Energy Ohio Gas';
        // }

        // if ($utility_label == 'AEP Columbus Southern'){
        //     return 'Columbus Southern Power (AEP)';
        // }

        // Need to confirm AEP-OHPC is AEP Ohio, or 'columbus southern power (aep)'
        // if ($utility_label == 'AEP-OHPC'){
        //     return 'AEP Ohio';
        // }
    
        // Default return unmodified LDC Code
        return $utility_label;
    }
    */

    private function getUtilitySupportedFuelIdByKey(array $headers, array $csv_row_array, int $rows_index) : ?string {
        $state = strtolower($csv_row_array[$headers['State']]);

        // Call fixUtilityMap BEFORE setting the keys for fixing because the value of $commodity CAN BE CHANGED
        /*
        fixUtilityMap DEPRECATED - This code was originally used to get this off the ground ASAP due to priority.
        if ($utility_label == 'AEP-OHPC'){
            return 'AEP Ohio';
        }

        The example prevented either CS or Client from being able to update values in their code.  

        */
        //$utility_label = strtolower($this->fixUtilityMap($csv_row_array[$headers['Utility']], $csv_row_array[$headers['Utility Label']], $csv_row_array[$headers['Commodity']], $csv_row_array[$headers['State']]));

        // For consistency, values are returned from the DB as all Lower Case.  This converts the data in the Utility Label column from CSV and makes it also lower case
        $utility_label = strtolower($csv_row_array[$headers['Utility Label']]);

        // There are TWO CleanSky Brands, one is only for Texas, the other brand is for all other North East states, labeled as 'ne'
        $key  = ($state == 'tx' ? 'tx-' : 'ne-') . "$state-";
        $key .= strtolower($csv_row_array[$headers['Commodity']]) . '-';
        $key .= $utility_label;

        if (array_key_exists($key, $this->utilityLabelKeys)) {
            // Return the Utility Supported Fuel Guid (named key, there are a few keys)
            return $this->utilityLabelKeys[$key]['usf_id'];
        }

        $msg = "Error: Focus unable to locate Utility with the following info: " . implode(" - ", $csv_row_array);
        $this->missingUtilityLabels[] = [$rows_index, $msg];
        echo "\n\n$msg\n\n";

        return null;
    }

    public function processFile($contents) {
        // Here, the file exists on the FTP server and it has been transferred, so now load data from DB.  Pointless to load data if file does not exist.
        $this->loadCustomerListToDelete();
        $this->loadCleanskyUtilityKeys();

        if ($this->option('debug') && $this->option('verbose')) {
            echo "Starting on creating new Customer List Objects to be saved on " . __CLASS__ . "\n";
        }

        $csv_contents = explode("\n", $contents);

        $headers = null;

        $rows_index = 1;

        // This doesnt factor in any empty lines, empty lines are just skipped...
        $newline_count = substr_count($contents, "\n");

        foreach ($csv_contents as $csv_raw) {
            $csv_raw = trim($csv_raw); // Trim any accidental newlines \n carriage returns \r, handles both
            if (!$csv_raw) continue; // Skip empty lines

            // THIS MAY CAUSE BUGS IF WE HAVE COMMAS IN VALUES
            $csv_row_array = explode(",", $csv_raw);

            // If we havent set the Header data, set it then skip to the next line
            if (!$headers) { 
                // same as array_flip and trim() on each values
                foreach ($csv_row_array as $k => $v) { $headers[trim($v)] = $k; }
                continue; 
            }

            $rows_index++;            

            $utility_supported_fuel_id = $this->getUtilitySupportedFuelIdByKey($headers, $csv_row_array, $rows_index);

            if (!$utility_supported_fuel_id) continue;

            $this->totalUtilities++;            

            $customer_key = $csv_row_array[$headers['LDC Number']] . '-' . $utility_supported_fuel_id;

            if (!array_key_exists($customer_key, $this->customerListToDelete)) {
                $this->customerListToSave[] = $this->getNewCustomerList($utility_supported_fuel_id, $csv_row_array[$headers['LDC Number']]);
            }
            else {
                // Here, we have found that a DB row exists so we should remove it from the list to be deleted
                unset($this->customerListToDelete[$customer_key]);
            }
        }

        $this->saveData();
        $this->emailErrorLog();
        $this->emailSuccessLog();
        $this->filesystem->delete($this->filename);

        echo "Import complete.  " . $this->filename . " has been removed from FTP server";
    }

    private function emailErrorLog() : void {
        // If there were Errors then send an Email with Failure attachment results
        if (count($this->missingUtilityLabels) > 0){
            $error_file = public_path('\tmp\CleanskyActiveCustomerListError.csv');
            $error_header = ['Line', 'Error'];
    
            $this->writeCsvFile($error_file, $this->missingUtilityLabels, $error_header);

            $email_config = [
                'from' => 'no-reply@tpvhub.com',
                'to' => $this->errorDistroList,
                'subject' => 'Cleansky Import Active Customers List Errors',
                'body' => "Total: " . $this->totalUtilities . "\nDeleted: " . $this->totalDeleted . "\nInserted: " . $this->totalInserted,
                'attachments' => array($error_file)
            ];

            $email_config['body'] .= "\n\nSome Active Customers (EBR) failed to import into Focus.  The rest were imported successfully. See attached.";
            $email_config['body'] .= "\n\nProcess Complete: " . date('m/d/Y h:i:s a', time() - (3600 * 4)) . "\n\nFile: " . $this->filename;

            $this->sendGenericEmail($email_config);
            $this->info('Cleansky Active Customer List Errors Email sent to Distro List');
        }
    }

    private function emailSuccessLog() : void {
        // If there were no errors then send an Email with Success results
        if (count($this->missingUtilityLabels) == 0) {
            $email_config = [
                'from' => 'no-reply@tpvhub.com',
                'to' => $this->successDistroList,
                'subject' => 'Cleansky Import Active Customers List Success',
                'body' => "Total: " . $this->totalUtilities . "\nDeleted: " . $this->totalDeleted . "\nInserted: " . $this->totalInserted
            ];
            
            $email_config['body'] .= "\n\nCleansky Import Active Customers List Success at " . date('m/d/Y h:i:s a', time() - (3600 * 4)) . "\n\nFile: " . $this->filename;

            $this->sendGenericEmail($email_config);
            $this->info('Cleansky Active Customer List Success Email sent to Distro List');
        }
    }

    private function saveData() : Void {
        if ($this->option('dryrun')) {
            echo "\n\n\n\nDRYRUN: No data saved " . __CLASS__ . "\n\n\n\n";
            return;
        }

        // Transaction - Either ALL values will be saved, or NONE of them save with an Error
        DB::beginTransaction();

        try {
            $this->softDeleteCustomerList();
            $this->saveCustomerLists();
        }
        catch (\Exception $e){
            // Transaction rollback Database values
            DB::rollBack();
            // Log and return the error that triggered this Exception
            Log::info("Error in Cleansky Active Customer List, Transaction Rolled back, Error saving data on line " . $e->getLine() . "\n". $e->getMessage());
            throw new \Exception($e->getMessage());
        }

        // Transaction Save Changes in Database
        DB::commit();

        if ($this->option('debug') && $this->option('verbose')) {
            echo "Cleansky Titan Load Active Customer EBR list: Transation Commited\n";
        }        
    }

    private function softDeleteCustomerList() : Void {
        if (count($this->customerListToDelete) == 0) {
            if ($this->option('debug') && $this->option('verbose')){
                echo "softDeleteCustomerList nothing to delete, skipping...\n";
            }

            // There is nothing to do here so dont bother just skip this step
            return;
        }

        $customer_list_ids_to_delete = "";

        foreach ($this->customerListToDelete as $customer_list){
            $customer_list_ids_to_delete .= "'$customer_list->id',";
            $this->totalDeleted++;
        }

        $customer_list_ids_to_delete = rtrim($customer_list_ids_to_delete, ",");

        $delete_customer_list_query = "
            UPDATE customer_lists
            SET deleted_at = '$this->startTime'
            WHERE brand_id = '$this->brand_id'
            AND customer_list_type_id = '" . self::CUSTOMER_LIST_TYPES['Active Customer'] . "'
            AND id IN ($customer_list_ids_to_delete)";

        DB::update($delete_customer_list_query);
    }

    private function saveCustomerLists() : Void {
        if (count($this->customerListToSave) == 0) {
            Log::info('total number of Customer List items is 0 so nothing to save, skipping...');
            echo "Total number of Customer List items is 0 so nothing to save, skipping...\n";
            return; 
        }

        Log::info('total number of CleanSky CustomerList to save is ' . count($this->customerListToSave) . '');
        echo 'total number of CleanSky CustomerList to save is ' . count($this->customerListToSave) . "\n";

        // For some reason, BulkSqlUpsert class will not load so I removed it

        // This is how to insert a LOT of records at once: INSERT INTO table (col_names) VALUES (value_set_1),(value_set_2),(value_set_3) up to 65k

        $counter = 0;
        $max = count($this->customerListToSave);

        // Base Query AND Query - MySQL INSERT IGNORE will just skip over any duplicate entries without erroring out
        $base_query = $query = "INSERT IGNORE INTO tpv.customer_lists (id, brand_id, created_at, filename, processed, customer_list_type_id, account_number1, utility_supported_fuel_id)\nVALUES";

        foreach ($this->customerListToSave as $cl){

            // Build a set of values: "\n('some_id','some_brand_id')," with trailing comma to be trimmed off later
            $query .=
                "\n('" . $cl->id . "'," .
                "'"    . $cl->brand_id . "'," .
                "'"    . $this->startTime . "'," .
                "'"    . $cl->filename . "'," .
                "'"    . $cl->processed . "'," .
                "'"    . $cl->customer_list_type_id . "'," .
                "'"    . $cl->account_number1 . "'," .
                "'"    . $cl->utility_supported_fuel_id . "'),";

            // Increment Counter so we know when to run the query before hitting PDO 65k limit
            $counter++;
            $this->totalInserted++;

            // PDO has a limit of 65000 variables.  Each row has multiple values that count against 65k limit.
            // When the counter is too high or counter is the last row, run the SQL query and reset the query to base
            if ($counter % 10000 == 0 || $counter == $max){
                // Commas are always appended to a set of values so they need to be trimmed off before executing query for valid SQL syntax
                $query = rtrim($query, ',');

                // Execute the query
                DB::insert($query);

                // Reset the query to clear out the appended values, even if it is on the last row
                $query = $base_query;

                if ($this->options('debug') && $this->option('verbose')) {
                    // Just return a single dot for progress indicator
                    echo ".";
                }
            }
        }

        if ($this->options('debug') && $this->option('verbose')) {
            echo "\nCleanSky CustomerList save complete, waiting for Transaction to be committed\n";
        }        

    }

    /** loadCleanskyUtilityKeys
     * - loads a Named Array / Hashmap where the key is generated from values in an ebr.csv file
     * - 'ne-oh-electric-aep ohio' : (2 letter 'ne' for Northeast or 'tx')-(2 letter state)-commodity (either electric or gas)-utility_label (with spaces)
     * - value is the Utility Supported Fuel ID from tpv.utility_supported_fuels table
     * - ['ne-oh-electric-aep ohio' => 'd1cc161f-6107-4f6c-9bc8-0252aba0f0c5']
     * - This is intended for fast lookups without hitting the database
     */
    private function loadCleanskyUtilityKeys() : Void {
        $query = "
            select 
                usf.id as usf_id,
                concat(
                    (case when bu.brand_id = '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0' then 'ne' else 'tx' end), '-',
                    LOWER(s.state_abbrev), '-', 
                    (case when usf.utility_fuel_type_id = 1 then 'electric' else 'gas' end), '-', 
                    lower(busf.ldc_code)
                ) as utility_key,
                busf.ldc_code as utility
            from brand_utilities bu
            join utility_supported_fuels usf on bu.utility_id = usf.utility_id and usf.deleted_at is null
            join utilities u on usf.utility_id = u.id and u.deleted_at is null
            JOIN brand_utility_supported_fuels busf ON busf.utility_supported_fuel_id = usf.id AND busf.brand_id = bu.brand_id
            join states s on u.state_id = s.id
            where
                (
                    (bu.brand_id = '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0' and u.state_id != 44)
                    OR (bu.brand_id = '6d525d22-13f6-4381-88b9-d872e8cdf492' and u.state_id = 44)
                )
                and bu.deleted_at is null
            order by utility_key";

            // order by busf.ldc_code, utility_fuel_type_id";

        $results = DB::select($query);

        // Array / Hashmap where Key is the Utility Key ('ne-md-gas-washington gas md' => 'd32fba13-a6a6-4612-9a8b-db5d2cbe779a') for fast lookups
        foreach($results as $value) {
            $this->utilityLabelKeys[$value->utility_key] = [
                'usf_id' => $value->usf_id,
                'utility' => strtolower($value->utility)
            ];
        }
    }    

    /** loadCustomerListToDelete()
     * - This loads the full customer_list for Active Customers
     * - When parsing the csv file, we delete row by row each one of these items
     * - By the end of the csv file, if there are any entries remaining in this array, they are soft deleted
     * 
     * - WHEN THIS SCRIPT COMPLETES, ANYTHING THAT REMAINS IN THIS ARRAY WILL SET A DELETED AT TIMESTAMP ON tpv.customer_lists
     */
    private function loadCustomerListToDelete() : Void{
        $bindings = [
            'brand_id' => $this->brand_id,
            'customer_list_type_id' => self::CUSTOMER_LIST_TYPES['Active Customer'] // Value of 2
        ];

        // Query to get customer_list objects
        $query = '
            SELECT
                *
            FROM customer_lists cl
            WHERE brand_id = :brand_id
            AND cl.customer_list_type_id = :customer_list_type_id
            AND cl.deleted_at IS NULL';

        $results = DB::select($query, $bindings);

        foreach ($results as $result) {
            // Named Array Key / Hashmap for fast lookup instead of querying database
            $key = $result->account_number1 . "-" . $result->utility_supported_fuel_id;
            $this->customerListToDelete[$key] = $result;
        }

    }

    // Returns a Valid V4 Guid / Uuid
    public static function NewGuid() {
        $p = '%04x%04x-%04x-%04x-%04x-%04x%04x%04x';
        return sprintf($p, mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }    

}
