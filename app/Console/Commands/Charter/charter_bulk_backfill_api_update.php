<?php

namespace App\Console\Commands\Charter;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use stdClass;

/*
Richard Gagnon request to hit the API with senddatatoclient for MainIds in Charter/Charter_UpdatedSales.csv
php artisan charter_bulk_backfill_api_update:run --dryrun
php artisan charter_bulk_backfill_api_update:run --file="C:\Users\dmcqueen\Desktop\Charter\Charter_UpdatedSales.csv" --sleep=3600 --dryrun
php artisan charter_bulk_backfill_api_update:run --file="C:\Users\dmcqueen\Desktop\Charter\Charter_UpdatedSales.csv" --sleep=3 --skip-lines=1551 --sleep-per=500 --dry-run
php artisan charter_bulk_backfill_api_update:run --file="https://www.webucate.me/tpv/Charter_UpdatedSales.csv" --sleep=3 --skip-lines=1551 --sleep-per=500 --dry-run
php artisan charter_bulk_backfill_api_update:run --file="http://tpm.local/Charter/Backfill/5_25_2023.csv" --sleep=3 --skip-lines=1551 --sleep-per=500 --dry-run
*/

class charter_bulk_backfill_api_update extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'charter_bulk_backfill_api_update:run {--dryrun} {--dry-run} {--skip-lines=} {--skip-headers} {--sleep=} {--file=} {--sleep-per=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends mutlipe Charter MainId to https://apiv2.tpvhub.com/api/charter/senddatatoclient to post data to Charter\'s API Server';

    // Argument Options    
    private $csv_file;                      // MUST SPECIFY, MUST BE CSV, Set with --file=<path_to_file.csv>
    private $dryrun = false;                // Set with --dry-run or --dryrun
    private $max_records_per_batch = 1000;  // Override with --sleep-per=500
    private $seconds_to_sleep_for = 3600;   // Override with --sleep=3600 in Seconds, 60 seconds to sleep for 1 minute
    private $skip_headers = false;          // Set with --skip-headers if CSV does not have a Header Line
    private $skip_lines = 0;                // Set with --skip-lines if file was only partly completed

    // Internal Class Variables
    private $client;                // Guzzle Client
    private $headers;               // CSV Header Row Data - [0 => 'MainId', 1 => 'SomeColumn'] etc
    private $header_mainid_index;   // If using skip-headers, this expects MainId to be the only value before newline, so this value will be set to 0 as Column Index
    private $line_counter = 0;      // Which Line of the CSV file is being processed
    private $exec_counter = 0;      // Used with --skip-lines so sleep commands are not executed before restarting process
    private $run = true;            // Allows sending data to API, when this is false, no further lines of CSV will be processed
    private $fail_lines = [];       // Array of lines on which there was a failure;
    
    // Typically https://apiv2.tpvhub.com/api/charter/senddatatoclient     
    private $api_endpoint = 'https://apiv2.tpvhub.com/api/charter/senddatatoclient';

    // Constructor - Used to create new instances of required classes and other stuff needed before further execution
    public function __construct()
    {
        // REQUIRED prior to customizing other script specific stuff
        parent::__construct();

        // Override any possible limits.  Setting to 0 allows command to basically run forever.  Once called, this script will likely execute for more than 24 hours.
        set_time_limit(0);

        // Create a Guzzle Client
        $this->client = new Client();
    }

    // handle() - Execute the console command.
    public function handle()
    {
        try {
            // Check for any command line arguments
            $this->setOptions();

            // Loop while script is allowed to be executed
            while ($this->run){
                $this->run = $this->loadAndProcessIds();
            }

            echo "\nFailed Line Numbers:\n";
            // Print Line Numbers where a Failure occurred
            print_r($this->fail_lines);

            // Completion Message
            echo "\n\nCharter Bulk Backfill complete";
        }
        catch(\Exception $e) {
            echo "FATAL ERROR: " . $e->getMessage() . "\n";
        }
    }

    private function setOptions(){
        if (!$this->option('file')){
            // Terminate execution
            $this->run = false;
            // Message for the Error
            $error_msg = "\nFATAL ERROR: Command Terminated - No File Specified in call to charter_bulk_backfill_api_update!\n" .
                 "- Specifiy a file with php artisan charter_bulk_backfill_api_update:run --file=my_file.csv\n\n";
            // Throw the error
            throw new \Exception($error_msg);
        }

        if ($this->option('dryrun') || $this->option('dry-run')){
            $this->dryrun = true;
        }

        if ($this->option('skip-headers')){
            $this->skip_headers = true;
        }

        if ($this->option('skip-lines') && is_numeric($this->option('skip-lines'))){
            $this->skip_lines = (int)$this->option('skip-lines');
        }

        if ($this->option('sleep') && is_numeric($this->option('sleep'))){
            $this->seconds_to_sleep_for = (int)$this->option('sleep');
        }

        if ($this->option('sleep-per') && is_numeric($this->option('sleep-per'))){
            // Number of records to process each batch (prevents overloading Charter systems with too many records at once)
            $this->max_records_per_batch = (int)$this->option('sleep-per');
        }

        $this->csv_file = $this->option('file');
        echo "Endpoint: $this->api_endpoint\n";
    }

    private function loadAndProcessIds(){
        $handle = fopen($this->csv_file, "r");

        if ($handle === false){
            throw new \Exception("\nERROR: unable to open file: $this->csv_file\n");
        }

        while (!$this->skip_headers && !$this->headers && (($data = fgetcsv($handle)) !== FALSE)) {
            $this->set_headers($data);
        }

        $this->header_mainid_index = ($this->skip_headers ? 0 : $this->headers['mainid']);
        $this->exec_counter = $this->line_counter;

        while (($data = fgetcsv($handle)) !== FALSE) {
            $this->sendDataToApi($data);

            // Wait X minutes if the Remainder of Division is 0 (Remainder of 50 / 10 is 0 so multiples of 10 would pause every 10 cycles)
            if ($this->run && $this->exec_counter % $this->max_records_per_batch == 0){
                echo "Execution paused for " . ($this->seconds_to_sleep_for / 60) .  " minutes\n";
                sleep($this->seconds_to_sleep_for);
            }                    
        }

        fclose($handle);
    }

    private function set_headers($data){
        $this->line_counter++;
        if (!$data || !$data[0]) return;
        $this->headers = array_flip($data);
    }

    // Value is typically the MainId
    private function sendDataToApi($data){
        $this->line_counter++;

        if (!$data[0] || $this->line_counter < $this->skip_lines) return;

        $value = $data[$this->header_mainid_index];

        if (!$this->dryrun){
            $form_params = [
                'form_params' => ['MainId' => $value],
                'http_errors' => true
            ];
    
            // Guzzle Post to get MainId's as array in response->body
            $response = $this->client->post($this->api_endpoint, $form_params);
    
            // Casting with (string) calls __toString which gives us the value of the body back without the other object data
            $body = (string)$response->getBody();
    
            // If invalid JSON, $json_response will be false
            $json_response = json_decode($body);
    
            // Guard Clauses to prevent further execution if there is an error
            if (!$json_response){ throw new \Exception("Fatal Error: unable to json_decode response from API\nBody:\n" . $body); }
            if (!property_exists($json_response, 'Status')){
                echo "Got an Error" . json_encode($json_response) . "\n";
                array_push($this->fail_lines, ['line_counter' => $this->line_counter, 'MainId' => $value]);
                //throw new \Exception("Fatal Error: \$json_response missing 'Status' property:\n" . print_r($json_response, true));
            }
        }
        else {
            $json_response = new stdClass();
            $json_response->Status = 'dryrun - API Response Simulated, MainId:' . $value;
        }

        $status = property_exists($json_response, 'Status') ? $json_response->Status : 'Undefined Property \'Status\' on \$json_response';

        echo "Line $this->line_counter API Server responded: \"$status\" for $value\n";
        $this->exec_counter++;
    }
}
