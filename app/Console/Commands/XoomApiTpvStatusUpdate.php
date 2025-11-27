<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;

use App\Models\Brand;
use App\Models\Interaction;
use App\Models\JsonDocument;
use App\Models\ProviderIntegration;

class XoomApiTpvStatusUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Xoom:Api:TpvStatusUpdate 
                            {--resend : Ignores the "enrolled" field in interactions tbale, allowing previously submitted records to be submitted again.}
                            {--confirmation-code= : Search for specific confirmation codes to submit. When looking for multiple confirmation codes, enclose arg in single quotes and separate each value with a pipe character. Using this option ignores all date ranges.}
                            {--forever : Ignore date ranges when querying for records to submit.}
                            {--hoursAgo= : How many hours to look back when querying for records to submit.}
                            {--start-date= : Starting date range when querying for records to submit. This must be paired with --end-date, and takes precedence over --hoursAgo=.}
                            {--end-date= : Ending date range when querying for records to submit. This must be paired with --start-date.}
                            {--show-sql : Use this option to output SQL query statements to console}
                            {--force-prod-api : Force the prod API to be used}
                            {--force-dev-api : Force the dev/beta API to be used}
                            {--dry-run : Perform all actions except running the API, logging request/response, and flagging the records as sent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submits TPV status to XOOM Energy\'s system via API';

    /**
     * Brand name.
     * 
     * @var string
     */
    protected $brandName = "XOOM Energy";

    /**
     * Brand record from DB
     * 
     * @var Brand
     */
    protected $brand = null;

    /**
     * Default number of hours to look back when querying for interactions
     * 
     * @var integer
     */
    protected $hoursAgo = 48;

    /**
     * API Auth info
     * 
     * @var ProviderIntegration
     */
    protected $apiAuth = null;

    /**
     * Service Type ID for auth lookup
     * 
     * @var integer
     */
    protected $serviceTypeId = 28; // 'XOOM CustomerPortalWS API'

    /**
     * Provider Integration Type ID for auth lookup
     * 
     * @var integer
     */
    protected $providerIntegrationTypeId = 2; // 'API'

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
        // Retrieve brand record.
        $this->brand = $this->getBrand($this->brandName);

        // Retrieve API auth info.
        $this->apiAuth = $this->getAuthInfo($this->brand->id);

        // Retrieve the interactions we need to process.
        $data = $this->getData();

        // Process and send interactions to API endpoint
        $counter = 0;
        $totalRecords = $data->count();

        foreach ($data as $record) {
            $counter++;

            // Show horizontal rule and current progress
            $this->info("");
            $this->info(str_pad("", 80, "-"));
            $this->info("[ " . $counter . " / " . $totalRecords . " ]");
            $this->info("  Confirmation Code: " . $record->confirmation_code);
            $this->info("  Interaction ID: " . $record->interaction_id);
            $this->info("");

            // Build payload
            $data = [
                "UN" => $this->apiAuth['username'],
                "PW" => $this->apiAuth['password'],
                "ConfirmationNumber" => $record->confirmation_code,
                "Status" => $record->result,
                "Reason" => $record->disposition_reason,
                "DateOfValidation" => $record->created_at->format("Y-m-d") . "T" . $record->created_at->format("H:i:s") . "Z"
            ];

            // If dry run, who message and loop to next record
            if ($this->option('dry-run')) {
                $this->info("Dry run. Skipping API send, logging, and flagging of record as 'sent'.\n");

                $this->info("Request:");
                print_r($data);

                continue; // Iterate loop early
            }

            // Do the API post
            $this->info("Sending data...");
            $response = SoapCall($this->apiAuth['hostname'] . '?wsdl', 'TpvStatusUpdate', $data, 'production' != config('app.env'), 1, [], ['prefix' => 'Xoom-Tpv-Status-Update']);

            // Get reference to interaction and set the enrolled status to 'success' or 'error' depending on response
            $interaction = Interaction::where(
                'id',
                $record->interaction_id
            )->first();

            if($interaction) {
                // SoapCall will log details to json_documents table, so we only need a success/error flag here to prevent this interactions from being pulled in by this program again.
                $interaction->enrolled = ($response['response']->TPVStatusUpdateResult->Success ? 'Success' : 'Error');
                $interaction->save();
            }
        }
    }


    /**
     * Retrieve the brand record from DB.
     * 
     * @param string $name - The brand name to look up.
     * 
     * @return Brand The record from the brands table.
     */
    private function getBrand($name)
    {
        $this->info("Locating brand record for '" . $name . "'");

        $brand = Brand::select(
            'id',
            'name'
        )->where(
            'name',
            $name
        )->whereNotNull(
            'client_id'
        );

        $this->showSqlQuery($brand);

        $brand = $brand->get();

        if (!$brand) {
            $this->error("  Not found! Exiting...");
            exit(-1);
        }

        if ($brand->count() > 1) {
            $this->error("  More than one result returned. Exiting...");
            exit(-1);
        }

        $brand = $brand->first(); // We want the specific record alone, not the collection it belongs to. At this point we should only have one record anyway.

        $this->info("  Found! Using:");
        $this->info("    Name: " . $brand->name);
        $this->info("    ID: " . $brand->id);
        $this->info("");

        return $brand;
    }


    /**
     * Retrieve auth info for the API
     * 
     * @param string $brandId - The brand ID to look up.
     * 
     * @return array - The auth info
     */
    private function getAuthInfo($brandId)
    {
        $this->info("Retrieving API auth info...");

        $auth = ProviderIntegration::select(
            'id',
            'brand_id',
            'service_type_id',
            'provider_integration_type_id',
            'username',
            'password',
            'hostname',
            'notes',
            'env_id'
        )->where(
            'brand_id',
            $brandId
        )->where(
            'service_type_id',
            $this->serviceTypeId
        )->where(
            'provider_integration_type_id',
            $this->providerIntegrationTypeId
        );

        $this->showSqlQuery($auth);

        $auth = $auth->get();

        if (count($auth) == 0) {
            $this->error("  Not found! Exiting...");
            exit(-1);
        }

        if ($auth->count() > 1) {
            $this->error("  More than one result returned. Exiting...");
            exit(-1);
        }

        $auth = $auth->first(); // We want the specific record alone, not the collection it belongs to. At this point we should only have one record anyway.

        $this->info("  Found! Using:");
        $this->info("    API Env: " . ($auth->env_id == 1 ? 'Prod' : 'Staging'));
        $this->info("    ID: " . $auth->id);
        $this->info("");

        return $auth;
    }


    /**
     * Retrieve records that need to be submitted to Xoom's API.
     * 
     * @return mixed - The retrieved data.
     */
    private function getData()
    {
        // Some info for the console
        $this->info("Retrieving data:");
        $this->info("Filters:");
        $this->info("  Resend:  " . ($this->option('resend') ? "true" : "Not Set"));
        $this->info("  Confirmation Code: " . ($this->option('confirmation-code') ? $this->option('confirmation-code') : "Not Set"));
        $this->info("  Forever:  " . ($this->option('forever') ? "true" : "Not Set"));
        $this->info("  Hours Ago: " . ($this->option('hoursAgo') ? $this->option('hoursAgo') : "Not Set"));
        $this->info("  Start Date: " . ($this->option('start-date') ? $this->option('start-date') : "Not Set"));
        $this->info("  End Date: " . ($this->option('end-date') ? $this->option('end-date') : "Not Set"));
        $this->info("");


        // Xoom's API expects a confirmation code and a status. 
        // In order to handle scenarios where a TPV call is initially no saled, but
        // later good saled on a subsequent attempt, we'll be posting to 
        // Xoom's API once per interaction.
        $interactions = Interaction::select(
            'interactions.id as interaction_id',
            'interactions.created_at',
            'interactions.enrolled',
            'event_results.result',
            'dispositions.reason as disposition_reason',
            'events.id as event_id',
            'events.confirmation_code'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->leftJoin(
            'event_results',
            'interactions.event_result_id',
            'event_results.id'
        )->leftJoin(
            'dispositions',
            function ($join) {
                $join->on('interactions.disposition_id', '=', 'dispositions.id');
                $join->on('events.brand_id', '=', 'dispositions.brand_id');
            }
        )->where(
            'events.brand_id',
            $this->brand->id
        )->whereNull(
            'events.deleted_at'
        )->whereNull(
            'event_results.deleted_at'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2] // call_inbound, call_outbound
        );

        if(!$this->option('resend')) {
            $interactions = $interactions->whereNull(
                'interactions.enrolled'
            );
        }

        $interactions = $interactions->whereNotNull(
            'events.external_id' // We're only interested in TPVs where customer data was pulled in from a data post.
        )->where(

            function($query) {
                $query->where(
                    'interactions.event_result_id',
                    1
                )->orWhere(function($query2) {
                    $query2->where(
                        'interactions.event_result_id',
                        2
                    )->where(
                        'dispositions.reason',
                        '!=',
                        'Pending'
                    );
                });
            }
        );

        // Additional filters.

        // Search by confirmation code?
        if ($this->option('confirmation-code')) {
            $confCodes = explode("|", $this->option('confirmation-code'));

            $this->info("  Filtering on confirmation code" . (count($confCodes) != 1 ? "s" : "") . " only...");

            $interactions = $interactions->whereIn(
                'events.confirmation_code',
                $confCodes
            );
        } else {

            // Ignore date range?
            if (!$this->option('forever')) {

                // Set date range using hours?
                if ($this->option('hoursAgo')) {
                    $this->info("  Using custom 'hoursAgo' for date range...");

                    $interactions = $interactions->where(
                        'interactions.created_at',
                        '>=',
                        Carbon::now()->subHours($this->option('hoursAgo'))
                    );
                } else {

                    // Set date range using command args?
                    if ($this->option('start-date') && $this->option('end-date')) {
                        $this->info("  Using 'start/end' date for date range...");

                        $startDate = Carbon::parse($this->option('start-date'));
                        $endDate = Carbon::parse($this->option('end-date'));

                        $interactions = $interactions->where(
                            'interactions.created_at',
                            '>=',
                            $startDate
                        )->where(
                            'interactions.created_at',
                            '<=',
                            $endDate
                        );
                    } else {
                        $this->info("  Using default 'hoursAgo' (48 hours) for date range...");

                        // Use default date range; a 48-hours lookback.
                        $interactions = $interactions->where(
                            'interactions.created_at',
                            '>=',
                            Carbon::now()->subHours($this->hoursAgo)
                        );
                    }
                }
            } else {
                $this->info("  'Forever' flag set. Ignore all date ranges...");
            }
        }

        $interactions = $interactions->orderBy(
            'events.confirmation_code',
            'ASC'
        )->orderBy(
            'interactions.created_at',
            'ASC'
        );

        // Show SQL query in console?
        $this->showSqlQuery($interactions);

        // Runt the query and return results
        $interactions = $interactions->get();

        $count = $interactions->count();

        $this->info("");
        $this->info("Query returned " . $count . " record" . ($count != 1 ? "s." : "."));

        return $interactions;
    }


    /**
     * Displays a query, with its bindings, in the console.
     * 
     * @param mixed $query - The query to display.
     */
    private function showSqlQuery($query)
    {
        if ($this->option('show-sql')) {
            $queryStr = str_replace(array('?'), array('\'%s\''), $query->toSql());
            $queryStr = vsprintf($queryStr, $query->getBindings());

            $this->info("");
            $this->info('QUERY:');
            $this->info($queryStr);
            $this->info("");
        }
    }
}
