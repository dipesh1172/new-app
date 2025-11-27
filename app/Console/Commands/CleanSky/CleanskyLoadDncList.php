<?php

namespace App\Console\Commands\CleanSky;

use Carbon\Carbon;
use App\Models\Brand;
use Ramsey\Uuid\Uuid;
use App\Models\PhoneNumber;
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

// Brand Specific Load List for Do Not Call List
class CleanskyLoadDncList extends Command
{
    use ExportableTrait;
    use DeliverableTrait;

    // RUN this from terminal/command prompt: "php artisan cleansky:dnc:list:load --brand=01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0 --verbose --debug"
    // php artisan cleansky:dnc:list:load --brand=01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0 --verbose --debug --err-email=Damian.McQueen@answernet.com --success-email=Damian.McQueen@answernet.com
    // CleanSky North East Brand ID: '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0'
    // CleanSky Texas Brand ID:      '6d525d22-13f6-4381-88b9-d872e8cdf492'

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleansky:dnc:list:load {--debug} {--brand=} {--no-restore} {--dryrun} {--err-email=*} {--success-email=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load Active Customer/Blacklist from FTP server';

    private $utilityCache = [];
    private $doRestore = true;
    private $missingUtilities = [];

    private $filesystem;
    private $brand_id;
    private $filename = 'dnc_list.csv'; // Dynamic - Replaced by constructor based on current date
    private $undeletedExistingPhoneNumbers = [];
    private $customerListPhoneNumbersToDelete = []; // key customer_list->id LEFT JOINED phone_number
    private $newPhoneNumbersToSave = [];
    private $customerListToSave = [];
    private $startTime;
    private $pdo_chunk_limit = 10000;
    // Minimum length for a valid phone number is 10.  I chose to allow longer for country codes.
    private $phone_length = 10;

    private $totalNumbers = 0;
    private $totalDeleted = 0;
    private $totalInserted = 0;    

    private $errorNumberList = [];
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

        // Set the filename to variable name from the date: '20230322_IDNC_Donkey_BTNS.csv' for 'Do Not Call' file
        $this->filename = $server_time->format('Ymd')  . '_IDNC_Donkey_BTNS.csv';
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

        // 1 hour
        set_time_limit(3600);

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

                $this->ftpDownload($config, $brand->id);

            } else {
                $this->error('Could not locate credentials for the specified brand (' . $brand_id . ')');
            }
        } else {
            $this->error('The Brand (' . $brand_id . ') was not found.');
        }
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
                    echo "Cleansky Load Do Not Call List file has no data or file does not exist.  Exiting process.";
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

    private function getPhoneNumberWithPlus(string $value, int $row_count) : ?string {
        // Remove single and double quotes, and + characters, as well as newline characters
        $value = trim(trim($value, "\"+'"));

        // Handle trying to parse empty strings as just null, they are skipped
        if ($value == null){
            return null;
        }

        // If either not a number after removing + and "' characters, or less than 10 digits
        if (!is_numeric($value) || strlen($value) < $this->phone_length) {
            // Record this number and the row index as an Error
            $this->errorNumberList[] = [$row_count, $value . " is not a valid $this->phone_length+ digit Phone Number"];
            // Returning null here
            return null;
        }

        // Return +7771114444, here value should only be a number
        return "+$value";
    }

    private function getNewPhoneNumber(string $number_with_plus) : PhoneNumber {
        $phoneNumber = new PhoneNumber();
        $phoneNumber->id = $this->NewGuid();

        $phoneNumber->phone_number = $number_with_plus;
        $phoneNumber->created_at = $this->startTime;

        return $phoneNumber;
    }

    /** getNewCustomerList() - returns a CustomerList class object
     * $phone_number_id - GUID for the ID on the tpv.phone_numbers table
     */
    private function getNewCustomerList(string $phone_number_id) : CustomerList {
        // Create the instance of the object then set the values
        $newCustomerList = new CustomerList();        
        // Since we are doing a Bulk Insert we need to have a valid Guid, which needs to be a fillable property
        $newCustomerList->id = $this->NewGuid();
        $newCustomerList->brand_id = $this->brand_id;
        $newCustomerList->created_at = $this->startTime;
        $newCustomerList->phone_number_id = $phone_number_id;
        $newCustomerList->filename = $this->filename;
        $newCustomerList->processed = 1;
        $newCustomerList->customer_list_type_id = self::CUSTOMER_LIST_TYPES['Do Not Call']; // Value of 4

        return $newCustomerList;
    }

    public function processFile($contents, $brand_id) {

        // If we get here, then we have been able to successfully transfer a file from the FTP server.  Now load the data from DB which takes about a minute.

        // Load All Undeleted Phone Numbers from DB
        $this->loadUndeletedPhoneNumbers();
        // Load the Customer List IDs and Phone Numbers: [phone_number => customer_list_id]
        $this->loadcustomerListPhoneNumbersToDelete();

        if ($this->option('debug') && $this->option('verbose')) {
            echo "Starting on creating new Phone Number Objects to be saved on " . __CLASS__ . "\n";
        }

        $csv_contents = explode("\n", $contents);

        $headers = null;

        $row_count = 1;

        // This doesnt factor in any empty lines, empty lines are just skipped...
        $newline_count = substr_count($contents, "\n");

        foreach ($csv_contents as $csv_raw) {
            if (!$csv_raw) continue; // Skip empty lines

            // THIS MAY CAUSE BUGS IF WE HAVE COMMAS IN VALUES
            $csv_row_array = explode(",", $csv_raw);

            // If we havent set the Header data, set it then skip to the next line
            if (!$headers) { $headers = $csv_row_array; continue; }

            // Always increment the row counter, even if data is null, used for Email Error so client can locate the error
            $row_count++;            

            // Skip null lines
            if ($csv_row_array[0] == null){
                continue;
            }

            // Get a phone number string with a single + in it: +19991115555
            $phone_number_with_plus = $this->getPhoneNumberWithPlus($csv_row_array[0], $row_count);

            if ($phone_number_with_plus === null){
                continue;
            }

            // Update value to the count, used for Emailed Report, only contains non empty lines so value may be different than $row_count
            $this->totalNumbers++;

            // Placeholder - We need a Phone Number ID for the Customer List, its really the only value
            // we actually need so use existing if it exists, otherwise make a new one
            $phone_number_id = null;

            // If the Phone Number with a + does not exist in ALL Undeleted Phone Numbers
            if (!array_key_exists($phone_number_with_plus, $this->undeletedExistingPhoneNumbers)){
                // DOUBLE ASSIGNMENT - $newPhoneNumberObj is used as a shorthand for the Object.  Any changes to $newPhoneNumberObj will affect the object in other arrays
                $this->newPhoneNumbersToSave[] = $newPhoneNumberObj = $this->getNewPhoneNumber($phone_number_with_plus);

                // Add the New Phone to the existing array to handle Duplicates in the Import File
                $this->undeletedExistingPhoneNumbers[$phone_number_with_plus] = [$newPhoneNumberObj];

                // Use the newly created Phone Number to set the $phone_number_id
                $phone_number_id = $newPhoneNumberObj->id;
            }
            else {
                // Get the Phone Number ID from the hashmap using the phone number with plus itself as the hashmap key
                $phone_number_id = $this->undeletedExistingPhoneNumbers[$phone_number_with_plus][0]->id;
            }

            // $this->customerListPhoneNumbersToDelete is a key value pair where key is phone_number and value is the CustomerList ID
            // If the phone_number does not have a Key in the array, then we need to create a new CustomerList entry using the phone_number_id as the value
            // When we save the data, everything that remains in the $this->customerListPhoneNumbersToDelete is soft deleted
            if (!array_key_exists($phone_number_with_plus, $this->customerListPhoneNumbersToDelete)) {
                // Add the new object to the list of Customer List rows to save, $new_cl just allows for shorthand syntax
                $this->customerListToSave[] = $this->getNewCustomerList($phone_number_id);
            }
            else {
                // The array key exists so we need to delete the key value pair so we do not soft delete it in the DB
                unset($this->customerListPhoneNumbersToDelete[$phone_number_with_plus]);
            }

            $this->echoPercentComplete("Load Phone Numbers from CSV", $this->totalNumbers, $newline_count);
        }

        $this->saveData();
        $this->emailErrorLog();
        $this->emailSuccessLog();        
        $this->filesystem->delete($this->filename);

        echo "Cleansky DNC Import complete.  " . $this->filename . " has been removed from FTP server";        
    }

    private function emailErrorLog() : void {
        // If there were Errors then send an Email with Failure attachment results
        if (count($this->errorNumberList) > 0){

            $error_file = public_path('\tmp\CleanskyDNCListError.csv');
            $error_header = ['Line','Error'];
            
            $this->writeCsvFile($error_file, $this->errorNumberList, $error_header);
            
            $email_config = [
                'from' => 'no-reply@tpvhub.com',
                'to' => $this->errorDistroList,
                'subject' => 'Cleansky Import DNC List Errors',
                'body' => "Total: " . $this->totalNumbers . "\nDeleted: " . $this->totalDeleted . "\nInserted: " . $this->totalInserted,
                'attachments' => array($error_file)
            ];

            $email_config['body'] .= "\n\nSome Do Not Call numbers failed to import into Focus.  The rest were imported successfully. See attached.";
            $email_config['body'] .= "\n\nProcess Complete: " . date('m/d/Y h:i:s a', time() - (3600 * 4)) . "\n\nFile: " . $this->filename;
            
            $this->sendGenericEmail($email_config);
            $this->info('Cleansky List Errors Email sent to Distro List');
        }
    }

    private function emailSuccessLog() : void {
        // If there were no errors then send an Email with Success results
        if (count($this->errorNumberList) == 0){
            $email_config = [
                'from' => 'no-reply@tpvhub.com',
                'to' => $this->successDistroList,
                'subject' => 'Cleansky Import DNC List Success',
                'body' => "Total: " . $this->totalNumbers . "\nDeleted: " . $this->totalDeleted . "\nInserted: " . $this->totalInserted
            ];
            
            $email_config['body'] .= "\n\nCleansky Import Do Not Call (DNC) List Success at " . date('m/d/Y h:i:s a', time() - (3600 * 4)) . "\n\nFile: " . $this->filename;
            
            $this->sendGenericEmail($email_config);
            $this->info('Cleansky DNC List Success Email sent to Distro List');
        }        
    }

    private function echoPercentComplete(string $title, int $count, int $total) : Void {
        if (!$this->option('debug') || !$this->option('verbose')) return;

        if ($count != $total && $count % 100 != 0) return;

        $percent = (floor($count / $total * 10000) / 100);
        $pe = explode('.', $percent);
        if(count($pe) > 1){
            if(strlen($pe[1]) < 2){
                $percent .= "0";
            }
        }
        else {
            $percent .= ".00";
        }

        echo "Percent Complete of $title: $percent% $count of $total\n";
    }

    private function saveData() : Void {
        // Transaction - Either ALL values will be saved, or NONE of them save with an Error
        DB::beginTransaction();

        try {

            $this->softDeleteCustomerListDoNotCall();
            $this->savePhoneNumbers();
            $this->saveCustomerLists();

            if ($this->option('dryrun')) {
                throw new \Exception("\n\n\n\nDRYRUN: Transaction aborted intentionally on " . __CLASS__ . "\n\n\n\n");
            }
        }
        catch (\Exception $e){
            // Transaction rollback Database values
            DB::rollBack();
            // Log and return the error that triggered this Exception
            Log::info("Error saving data on line " . $e->getLine() . "\n". $e->getMessage());
            throw new \Exception($e->getMessage());
        }

        // Transaction Save Changes in Database
        DB::commit();

        if ($this->option('debug') && $this->option('verbose')) {
            echo "Cleansky Titan Load Do Not Call list: Transation Commited\n";
        }
    }

    private function softDeleteCustomerListDoNotCall() : Void {
        if (count($this->customerListPhoneNumbersToDelete) == 0) {
            if ($this->option('debug') && $this->option('verbose')){
                echo "softDeleteCustomerListDoNotCall nothing to delete, skipping...\n";
            }

            // There is nothing to do here so dont bother just skip this step
            return;
        }

        $customer_list_ids_to_delete = "";

        foreach ($this->customerListPhoneNumbersToDelete as $customer_list_id){
            $customer_list_ids_to_delete .= "'$customer_list_id',";
            $this->totalDeleted++;
        }

        $customer_list_ids_to_delete = rtrim($customer_list_ids_to_delete, ",");

        $delete_customer_list_query = "
            UPDATE customer_lists
            SET deleted_at = '$this->startTime'
            WHERE brand_id = '$this->brand_id'
            AND customer_list_type_id = '" . self::CUSTOMER_LIST_TYPES['Do Not Call'] . "'
            AND id IN ($customer_list_ids_to_delete)";

        DB::update($delete_customer_list_query);
    }
    
    private function savePhoneNumbers() : Void {
        if (count($this->newPhoneNumbersToSave) == 0) {
            Log::info('Total number of Phone Numbers is 0 so nothing to save, skipping...');
            echo "Total number of Phone Numbers is 0 so nothing to save, skipping...\n";
            return; 
        }

        // Update value to the count, used for Emailed Report
        $this->totalInserted = count($this->newPhoneNumbersToSave);

        Log::info('total number of Phone Numbers to save is ' . count($this->newPhoneNumbersToSave) . '');
        echo 'total number of Phone Numbers to save is ' . count($this->newPhoneNumbersToSave) . "\n";

        $fields = "id, created_at, phone_number";

        // BulkSqlUpsert(string $table, $fields, array $objects, $log = false, int $chunk_limit = 60000)
        $bulk_query = new BulkSqlUpsert('phone_numbers', $fields, $this->newPhoneNumbersToSave, false, $this->pdo_chunk_limit);
        // BulkSqlUpsert-save() Params: [OPTIONAL] ($log = false, string $log_prepend = null)
        $bulk_query->save(false, __CLASS__);

        // Free up memory
        unset($bulk_query);
    }

    private function saveCustomerLists() : Void {
        if (count($this->customerListToSave) == 0) {
            Log::info('total number of Customer List items is 0 so nothing to save, skipping...');
            echo "Total number of Customer List items is 0 so nothing to save, skipping...\n";
            return; 
        }

        Log::info('total number of CleanSky CustomerList to save is ' . count($this->customerListToSave) . '');
        echo 'total number of CleanSky CustomerList to save is ' . count($this->customerListToSave) . "\n";

        $fields = "id, brand_id, created_at, phone_number_id, filename, processed, customer_list_type_id";

        // BulkSqlUpsert(string $table, $fields, array $objects, $log = false, int $chunk_limit = 60000)
        $bulk_query = new BulkSqlUpsert('customer_lists', $fields, $this->customerListToSave, false, $this->pdo_chunk_limit);
        // BulkSqlUpsert-save() Params: [OPTIONAL] ($log = false, string $log_prepend = null)
        $bulk_query->save(false, __CLASS__);

        // Free up memory
        unset($bulk_query);        
    }

    /** $this->loadUndeletedPhoneNumbers() : Void
     * - Loads All Undeleted Phone Numbers from Database to $this->undeletedExistingPhoneNumbers Array
     * - This takes about 30 seconds to create a Hash Map from the data
     */
    private function loadUndeletedPhoneNumbers() : Void {
        echo "Starting to load All Undeleted Phone Numbers on " . __CLASS__ . "\n";

        // Raw Query required because its like 2+ million records (this takes about 30 seconds)
        $phone_results = DB::select("SELECT * FROM phone_numbers WHERE deleted_at IS NULL");

        foreach ($phone_results as $result) {
            if (!array_key_exists($result->phone_number, $this->undeletedExistingPhoneNumbers)) {
                $this->undeletedExistingPhoneNumbers[$result->phone_number] = [];                
            }
                
            // Effectively a Push.  This hashmap contains all data about the existing phone number, not just the ID
            $this->undeletedExistingPhoneNumbers[$result->phone_number][] = $result;
        }

        echo "Loading All Undeleted Phone Numbers to Hash Map COMPLETE on " . __CLASS__ . "\n";
    }

    /** loadcustomerListPhoneNumbersToDelete()
     * - This loads the customer_list of just the phone number and the customer_list id
     * - This sets an array where the array keys are the phone number, and the value is the customer_list_id
     * - Later, if the phone number is in the csv list file then we remove it from this array
     * - If a phone number in this list is not in the csv file, then we update it later to delete it
     * 
     * - WHEN THIS SCRIPT COMPLETES, ANYTHING THAT REMAINS IN THIS ARRAY WILL SET A DELETED AT TIMESTAMP ON tpv.customer_lists
     */
    private function loadcustomerListPhoneNumbersToDelete() : Void{
        echo "Starting to load Brand Customer List Undeleted Phone Numbers on " . __CLASS__ . "\n";
    
        $bindings = [
            'brand_id1' => '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0', // CleanSky North East Brand ID
            'brand_id2' => '6d525d22-13f6-4381-88b9-d872e8cdf492', // CleanSky Texas Brand ID
            'customer_list_type_id' => self::CUSTOMER_LIST_TYPES['Do Not Call'] // Value of 4
        ];

        // Query to get customer_list id and the phone number, we dont care about other data
        $query = '
            SELECT
                cl.id AS cl_id,
                pn.phone_number AS phone_number
            FROM customer_lists cl
            INNER JOIN phone_numbers pn ON cl.phone_number_id = pn.id
            WHERE brand_id IN (:brand_id1, :brand_id2)
            AND cl.customer_list_type_id = :customer_list_type_id
            AND cl.deleted_at IS NULL';

        $results = DB::select($query, $bindings);

        // Only two values Customer List ID, and Phone Number from the join
        foreach ($results as $result) {
            $this->customerListPhoneNumbersToDelete[trim($result->phone_number)] = $result->cl_id;
        }

    }

    // Returns a Valid V4 Guid / Uuid
    public static function NewGuid() {
        $p = '%04x%04x-%04x-%04x-%04x-%04x%04x%04x';
        return sprintf($p, mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }    

}
