<?php

namespace App\Console\Commands\Charter;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

/*

Example:

php artisan charter_backfill:run --debug --dryrun --startDate=2023-03-24 --endDate=2023-06-30 --sleep=60 --max=100

--debug points the API to localhost
--dryrun or --dry-run will not call to senddatatoclinet (Charter)

When not using a --debug (which sets API server to localhost) a .csv file with your computers local date will be generated
in the /tmp/charter_backfill/logs/ folder.  These files are NOT overwritten, instead, file names are incrementally appended
so 20230712-1.csv for first time process is called, followed by 20230712-2.csv for the second time a job is run, etc.

This command is intended to handle Charter IVRs that were not forwarded over to their systems to verifiy calls, due to hardware being taken offline
and unable to send data to their systems.  At this time, we have a new Proxy to relay the payloads to charter (due to TLS versions) which is set
in the Answernet API to initiate the call to charters systems thru the proxy.

This script is intended to take a large volume of MainIds that need to be back filled and send them periodically to charters system via the proxy
so we can update the records.

Endpoint: https://apiv2.tpvhub.com/api/charter/senddatatoclient
Method: POST
Payload: { MainId : (int) some MainId value }
*/

class charter_backfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'charter_backfill:run {--dryrun} {--dry-run} {--debug} {--sleep=} {--max=} {--startDate=} {--endDate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $seconds_to_sleep_for;
    private $max_per_interval;
    private $get_MainIds_endpoint;
    private $api_endpoint;
    private $client; // Guzzle Client
    private $run = true;
    private $counter = 1;
    private $total = 0;
    private $dryrun = false;
    private $startDate; // YYYY-MM-dd (Sql formatted date)
    private $endDate;   // YYYY-MM-dd (Sql formatted date)
    private $fileHandle;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Override any possible limits.  Setting to 0 allows command to basically run forever.  Once called, this script will likely execute for more than 24 hours.
        set_time_limit(0);

        // How long to wait between each post to the API
        $this->seconds_to_sleep_for = 60;

        // This value can be overriden to localhost with --debug argument
        $this->get_MainIds_endpoint = 'https://apiv2.tpvhub.com/api/charter/getrecordsforsendingdatatoclient';
        //$this->get_MainIds_endpoint = 'http://localhost:6500/api/charter/getrecordsforsendingdatatoclient';

        // Useful for debugging actually sending data without actually sending data to client.  If its on Prod Server, then it will be sent to client.
        $this->api_endpoint = 'https://apiv2.tpvhub.com/api/charter/senddatatoclient';
        //$this->api_endpoint = 'http://localhost:6500/api/charter/senddatatoclient';

        // Create a Guzzle Client
        $this->client = new Client();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Check for any command line arguments
        $this->setOptions();

        $this->openLogFile();

        while ($this->run){
            $this->run = $this->loadAndProcessIds();
        }

        echo "\n\nCharter Backfill complete";
    }

    private function openLogFile(){
        // If folders dont exist, create them
        if (!file_exists('./tmp/charter_backfill')) { mkdir('./tmp/charter_backfill', 0755); }
        if (!file_exists('./tmp/charter_backfill/logs')) { mkdir('./tmp/charter_backfill/logs', 0755); }        

        $date = date('Ymd');
        $i = 0;

        do {
            $i++;
            $filename = "./tmp/charter_backfill/logs/{$date}-{$i}.csv";
        }
        while (file_exists($filename));
        $this->fileHandle = fopen($filename, 'x');

        fputcsv($this->fileHandle, ['Counter','MainId','Date','Response']);
    }

    private function setOptions(){
        // Guard clauses to stop execution on
        if (!$this->option('startDate')){ throw new \Exception('Error: startDate is required. Example --startDate=YYYY-MM-DD'); }
        if (!$this->option('endDate'))  { throw new \Exception('Error: endDate is required.  Example --endDate=YYYY-MM-DD'); }
        if (!$this->option('sleep'))    { throw new \Exception('Error: sleep is required. INT: number of seconds to pause between max_per_interval.  Example: --sleep=60'); }
        if (!$this->option('max'))      { throw new \Exception('Error: max is required.  INT:number of records to process before sleep.  Example: --max=100)'); }

        // If doing a Dry Run
        if ($this->option('dryrun') || $this->option('dry-run')){ $this->dryrun = true; }

        if ($this->option('debug')){
            $this->get_MainIds_endpoint = 'http://localhost:6500/api/charter/getrecordsforsendingdatatoclient';
            $this->api_endpoint = 'http://localhost:6500/api/charter/senddatatoclient';
        }

        $this->startDate = $this->option('startDate');
        $this->endDate   = $this->option('endDate');
        $this->seconds_to_sleep_for = (int)$this->option('sleep');
        $this->max_per_interval = (int)$this->option('max');
    }    

    private function loadAndProcessIds(){
        try {
            // Build the endpoint with params
            $endpoint = $this->get_MainIds_endpoint . "?startDate={$this->startDate}&endDate={$this->endDate}";

            // Guzzle Post to get MainId's as array in response->body
            $response = $this->client->get($endpoint);

            // Casting with (string) calls __toString which gives us the value of the body back without the other object data
            $body = (string)$response->getBody();

            // If invalid JSON, $json_response will be false
            $json_response = json_decode($body);

            // Guard Clauses to prevent further execution if there is a problem with the data coming back.  Errors are already handled.
            if (!$json_response || (is_array($json_response) && count($json_response) == 0)){
                $this->run = false;
                return;
            }

            $this->total = count($json_response);

            // Now that all possible errors are handled, iterate the array
            foreach ($json_response as $value){
                
                echo "\nWoring on $value";
                // If not a Dryrun, run the command against the API
                if (!$this->dryrun) {
                    $this->sendDataToApi($value);
                }

                // Prevent Division by Zero herror and use remainder if the current count matches the interval, then throtte by sleep for x seconds
                if ($this->max_per_interval > 0 && $this->counter % $this->max_per_interval == 0){
                    $percent = number_format(round(($this->counter / $this->total * 100) * 100) / 100, 2);
                    echo "\nsleeping for $this->seconds_to_sleep_for seconds, $this->counter of $this->total, $percent% Complete\n";
                    sleep($this->seconds_to_sleep_for);
                }

                $this->counter++;
            }

            // Set flag that the job is complete
            $this->run = false;
            echo "\nCharter Backfill of " . count($json_response) . " is complete\n";

            // Return that the job completed successfully
            return true;
        }
        catch(\Exception $e){
            $this->run = false;
            echo "Fatal Error: {$e->getMessage()}";
            return;
        }
    }

    // Value is typically the MainId
    private function sendDataToApi($value){
        $request_body = [
            'MainId' => $value,
            'nodelay' => true   // This option prevents a 7 second delay which is used during live calls for values to be updated
        ];

        $request = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],            
            'body' => json_encode($request_body),
            'http_errors' => true
        ];        

        // Guzzle Post to get MainId's as array in response->body
        $response = $this->client->post($this->api_endpoint, $request);

        // Casting with (string) calls __toString which gives us the value of the body back without the other object data
        $body = (string)$response->getBody();

        // If invalid JSON, $json_response will be false
        $json_response = json_decode($body);

        // Guard Clauses to prevent further execution if there is an error
        if (!$json_response){ throw new \Exception("Fatal Error: unable to json_decode response from API\nBody:\n" . $body); }
        if (!property_exists($json_response, 'Status')){ throw new \Exception("Fatal Error: \$json_response missing 'Status' property:\n" . print_r($json_response, true)); }

        echo "\n- API Server responded: \"$json_response->Status\" on $value";

        $csv_data = [
            $this->counter,
            $value, // MainId
            date('m/d/Y h:i:sA T', time()),
            $json_response->Status
        ];

        fputcsv($this->fileHandle, $csv_data);
    }
}
