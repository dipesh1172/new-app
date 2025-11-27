<?php
/**
 * Author: Alex K
 * Date:  2023-06-26
 * 
 * This command creates a CSV report showing all IVR Script interactions for TPV Events that 
 * have at least one IVR Script interaction, but no IVR Review interactions.
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Ramsey\Uuid\Uuid;

use App\Traits\ExportableTrait;
use App\Traits\DeliverableTrait;

class IvrEztpvAuditReport extends Command
{
    use ExportableTrait;
    use DeliverableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ivr:eztpv-audit-report {--start-date=} {--end-date=} {--email-to=*} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a CSV report of all IVR Interactions for TPV Events that have at least one IVR Interaction but no IVR Review interactions.';

    /**
     * App pretty name, for use in emails and logging
     * 
     * @var string
     */
    protected $jobName = "EZTPV IVR Audits Report";

    /**
     * A UUID for logging. Can be used to pull all related log messages.
     * 
     * @var string
     */
    protected $logSessionid;

    /**
     * Array of brand names/ids/other info for the brands that will need to be audited
     */
    protected $brands = [];

    /**
     * Email distribution list
     */
    protected $distroList = [];

    /**
     * Date search date range
     */
    protected $startDate;
    protected $endDate;

    /**
     * Our HTTP client
     */
    protected $httpClient;

    const INTERACTION_IVR_SCRIPT = 20;
    const INTERACTION_IVR_REVIEW = 27;

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
        $this->logSessionid = Uuid::uuid4(); // Generate log session UUID. All log messages will contain this ID, making it eassy to find all related log messages.

        $this->logInfo('Job Start');

        // Set date range
        $this->setDateRange();

        // Set distro list
        $this->setDistroList();

        // Get/Validate brand IDs
        $this->setBrandInfo();

        // Set up HTTP client
        $this->httpClient = new HttpClient(['verify' => false]);

        try {
            // Generate a separate report data for each brand.
            // These will get combined into one array that will then
            // be dumped to a CSV file.
            $reportData = [];

            $totalBrands = count($this->brands);
            $ctr = 0;
            foreach($this->brands as $brand) {
                $ctr = $ctr + 1;

                $this->info('');
                $this->info('--------------------------------------------------');
                $this->info("[ {$ctr} / {$totalBrands} ]");
                $this->info("Processing for brand '{$brand['name']}'");
                $this->info('');

                $this->logInfo("Processing for brand '{$brand['name']}'");

                $data = $this->generateReport($brand);

                if($data) {
                    $reportData = array_merge($reportData, $data);
                }
            }

            // Export data to CSV
            $this->info('Exporting data to CSV');

            $csvHeader = [
                'brand_name', 'ivr_date', 'confirmation_code', 'channel', 'vendor_name', 'no_sale_reason', 
                'notes', 'uniqueid', 'outcome_of_call', 'data_restored', 'review_notes'
            ];

            $filename = 'EZTPV IVR Audits Report - ' . $this->startDate->format('Ymd') . '.csv';

            $this->logInfo("Writing file: '{$filename}'");

            $this->writeCsvFile(public_path('tmp/') . $filename, $reportData, $csvHeader);

            // Email the report
            $this->info('Emailing report...');

            $this->logInfo('Emailing report...');

            $this->sendGenericEmail([
                'to' => $this->distroList,
                'subject' => $this->jobName . ' - ' . $this->startDate->format('Y-m-d'),
                'body' => 'Attached is the ' . $this->jobName . ' for ' . $this->startDate->format('Y-m-d') . '.',
                'attachments' => [ (public_path('tmp/') . $filename) ]
            ]);

        } catch (\Exception $e) {
            $this->error($e->getMessage());

            $this->logError("EXCEPTION:", $e);
        }
    }

    /** 
     * Generate the report data for the given brand
     */
    private function generateReport($brand)
    {                
        $results = $this->getData($brand);

        // If no data, return null. Calling code will need to detect the null and handle accordingly
        if(count($results) == 0) {
            $this->info('No TPV data found.');

            return null;
        }

        $this->info('Located ' . count($results) . ' record(s)');
        $this->info('');

        // Do some initial formatting from result set.
        // Main thing here, though is to convert array of object to an array of array.
        $this->info('Formatting results...');
        $data = [];
        $confCodes = []; // We can have duplicate confirmation codes if there were multiple TPV attempts. Only send unique confirmation codes to the API.        

        foreach($results as $result) {

            // Add brand name to all result records
            $result->brand = $brand['name'];

            // Get the unique id from interaction notes, if any
            // Blank out interaction notes. We'll use this notes field for our audit notes
            if($result->notes) {

                $notes = json_decode($result->notes);

                if(isset($notes->motion) && isset($notes->motion->unique_id)) {
                    $result->uniqueid = "'" . $notes->motion->unique_id;

                    $result->notes = '';
                }
            } else {
                $result->notes = '';
            }

            // Convert object to array
            $data[] = (array) $result;

            // Build list of unique confirmation codes
            if(!in_array($result->confirmation_code, $confCodes, true)) {
                $confCodes[] = $result->confirmation_code;
            }
        }

        $this->logInfo("{$brand['name']} TPV data after formatting:", $data);

        // Next, via an API call, search for the retrieved confirmation codes in MongoDB.
        // 1 - SEARCH MONGO. IF WE FIND ANYTHING IN MONGO, REMOVE RECORD FROM REPORT *
        $mongoDataResult = $this->getMongoData($brand['mongoCollection'], $confCodes);

        if ($mongoDataResult->result == 'success') {
            $mongoData = $mongoDataResult->data;

            // Flag any records found in MongoDB
            if(count($mongoData) > 0) {
                for($i = 0; $i < count($data); $i++) {

                    foreach($mongoData as $r) {
                        if($data[$i]['confirmation_code'] == $r->ConfirmationSearch) {
                            $data[$i]['notes'] = 'Found in MongoDB. ' . $r->QARecordingRecord;
                        }
                    }
                }
            }

            // Now, determine the unique ID for the interactions
            $this->info('');
            $this->info('Locating uniqueids from Motion logs...');

            $totalRecords = count($data);
            $ctr = 0;
            for($i = 0; $i < count($data); $i++) {
                $ctr = $ctr + 1;

                $this->info('  ..................................................');
                $this->info("  [ {$ctr} / {$totalRecords} ]");
                $this->info('');
                $this->info('  Confirmation Number: ' . $data[$i]['confirmation_code']);
                $this->info('');

                if($data[$i]['uniqueid']) {
                    continue;
                }

                $this->info('  Performing lookup against Motion logs');

                $dtStart = Carbon::now("America/Chicago");
                $motionLogResult = $this->getUniqueIdByConfirmationCode($data[$i]['confirmation_code']);            
                $dtEnd = Carbon::now("America/Chicago");

                $diff = $dtEnd->diffInSeconds($dtStart);
                $this->info('  Search took ' . $diff . ' second(s)');

                if($motionLogResult->result == 'success') {
                    $logData = $motionLogResult->data;

                    $uniqueIds = [];
                    foreach($logData as $item) {
                        $uniqueIds[] = $item->uniqueid;
                    }
                    
                    $this->info("  Found following unique IDs: " . implode(", ", $uniqueIds));
                    
                    $data[$i]['uniqueid'] = "'" . implode(" | ", $uniqueIds);

                    $this->logInfo('Data record after unique ID search:', $data[$i]);

                } else {
                    $this->error("ERROR");
                    if($this->option('debug')) {
                        print_r($motionLogResult);
                    }
                    $this->logError("Error getting data from Motion:", $motionLogResult);
                }
            }
        } else {
            $this->logError("Error pulling data from MongoDB:", $mongoDataResult);
        }


        return $data;
    }

    /**
     * API call to getMotionUniqueIdsByConfirmationNumber to search logs for unique ID based on provided confirmation number
     * 
     * @param $confirmationCode - The confirmation number to search the logs for
     */
    private function getUniqueIdByConfirmationCode($confirmationCode)
    {
        // Build payload
        $body = [
            "ConfirmationNumber" => $confirmationCode
        ];

        $options = [
            'verify' => false,
            'headers' => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getMotionUniqueIdsByConfirmationNumber REQUEST', $options);

        try {
            // Post the request
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getMongoMotionData',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getMotionUniqueIdsByConfirmationNumber RESPONSE', $options);
            return $this->newResult((isset($res->error) ? 'error' : 'success'), '', $res);

        } catch (ServerException $e) {
            $this->logInfo('getMotionUniqueIdsByConfirmationNumber EXCEPTION', $e->getResponse()->getBody()->getContents());
            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));            

        } catch (ClientException $e) {
            $this->logInfo('getMotionUniqueIdsByConfirmationNumber EXCEPTION', $e->getResponse()->getBody()->getContents());
            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));            

        } catch (\Exception $e) {
            $this->logInfo('getMotionUniqueIdsByConfirmationNumber EXCEPTION', $e);
            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /**
     * API call to getmongorecordsbyconfirmationcode to get data from MongoDB
     */
    private function getMongoData($collection, $confirmationCodes)
    {
        $this->info('Getting data from MongoDB...');

        // Build payload
        $body = [
            'collectionName' => $collection,
            'data' => $confirmationCodes
        ];

        $options = [
            'verify' => false,
            'headers' => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json'                
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getmongorecordsbyconfirmationcode REQUEST', $options);

        try {
            // Post the request
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getmongorecordsbyconfirmationcode',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getmongorecordsbyconfirmationcode RESPONSE', $res);

            return $res;

        } catch (ServerException $e) {
            $this->logError('getmongorecordsbyconfirmationcode EXCEPTION', $e->getResponse()->getBody()->getContents());
            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));

        } catch (ClientException $e) {
            $this->logError('getmongorecordsbyconfirmationcode EXCEPTION', $e->getResponse()->getBody()->getContents());
            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));

        } catch (\Exception $e) {
            $this->logError('getmongorecordsbyconfirmationcode EXCEPTION', $e);
            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /**
     * Convenience function for creating a simple result object
     */
    private function newResult($result = 'success', $message = '', $data = null)
    {
        return (object) [
            'result' => $result,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Retrieve interactions for TPVs that have at least on IVR Script interaction, but do NOT have an IVR Review interaction
     */
    private function getData($brand)
    {
        $this->info('Getting TPV data...');

        // Build the query
        $query = 
            "SELECT
                '' AS brand,
                i.created_at AS ivr_date,
                e.confirmation_code,
                c.channel,
                v.name AS vendor_name,
                d.reason AS no_sale_reason,
                i.notes,
                '' AS uniqueid,
                '' AS outcome_of_call,
                '' AS data_restored,
                '' AS review_notes
            FROM interactions i
            JOIN events e ON i.event_id = e.id and e.deleted_at is null
            JOIN brands v ON e.vendor_id = v.id
            JOIN vendors v2 ON e.brand_id = v2.brand_id AND e.vendor_id = v2.vendor_id
            JOIN channels c ON e.channel_id = c.id
            LEFT JOIN dispositions d ON i.disposition_id = d.id
            WHERE
                i.created_at > :start_date1
                AND i.created_at < :end_date1
                AND i.interaction_type_id = :id_ivr_script
                AND e.brand_id = :brand_id1
                AND e.confirmation_code NOT IN (
                    SELECT DISTINCT e.confirmation_code
                    FROM interactions i
                    JOIN events e ON i.event_id = e.id and e.deleted_at is null
                    JOIN brands v ON e.vendor_id = v.id
                    JOIN vendors v2 ON e.brand_id = v2.brand_id AND e.vendor_id = v2.vendor_id
                    JOIN channels c ON e.channel_id = c.id
                    WHERE
                        i.created_at > :start_date2
                        AND i.created_at < :end_date2
                        AND i.interaction_type_id = :id_ivr_review
                        AND e.brand_id = :brand_id2
                )
            ORDER BY ivr_date";

        // Build bindings
        $bindings = [
            'start_date1'   => $this->startDate->copy(),
            'end_date1'     => $this->endDate->copy(),
            'id_ivr_script' => self::INTERACTION_IVR_SCRIPT,
            'brand_id1'     => $brand['id'],
            'start_date2'   => $this->startDate->copy(),
            'end_date2'     => Carbon::tomorrow(), // This end date shoudlbe through today. This is so we can exclude the record if an IVR review exists after our date range for the data search
            'id_ivr_review' => self::INTERACTION_IVR_REVIEW,
            'brand_id2'     => $brand['id']
        ];

        // Run the requery
        $results = DB::select(DB::raw($query), $bindings);

        $this->logInfo("Focus TPV data query result for {$brand['name']}:", $results);

        return $results;
    }

    /**
     * Sets the date range for the TPV data search
     */
    private function setDateRange()
    {
        $this->startDate = Carbon::yesterday();
        $this->endDate   = Carbon::today();

        if($this->option('start-date') && $this->option('end-date')) {
            $this->info('Using custom date range.');

            $this->startDate = Carbon::parse($this->option('start-date'), "America/Chicago");
            $this->endDate   = Carbon::parse($this->option('end-date'),   "America/Chicago");
        } else {
            $this->info('Using default date range.');

            $this->startDate = Carbon::yesterday();
            $this->endDate   = Carbon::today();
        }

        $this->info('  Start Date: ' . $this->startDate->format('Y-m-d H:i:s'));
        $this->info('  End Date:   ' . $this->endDate->format('Y-m-d H:i:s'));
        $this->info('');

        $this->logInfo('Selected date range:', ['start' => $this->startDate->format('Y-m-d H:i:s'), 'end' => $this->endDate->format('Y-m-d H:i:s')] );
    }

    /**
     * Populate object array with data for brands that will be searched
     */
    private function setBrandInfo()
    {
        $this->info('Setting up brand info...');

        $this->brands = [
            [
                'name' => 'Abest',
                'id' => 'd52f25c9-6583-43e0-8f5b-4d865a66dab2',
                'mongoCollection' => 'abestTPV'
            ],[
                'name' => 'Choice Energy',
                'id' => 'c4fbae2c-3f59-4b79-8f19-53e60ef0aee1',
                'mongoCollection' => 'choiceTPV'
            ],[
                'name' => 'Clean Choice',
                'id' => '37cb9600-76e7-45cd-b24b-d0e2ccff8032',
                'mongoCollection' => 'cleanchoiceTPV'
            ],[
                'name' => 'CleanSky',
                'id' => '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0',
                'mongoCollection' => 'titanTPV'
            ],[
                'name' => 'EcoPlus',
                'id' => 'adf98e19-1f10-4de6-84ce-a27f6383307f',
                'mongoCollection' => 'ecoplusTPV'
            ],[
                'name' => 'Family Energy',
                'id' => '1de6e0fc-8951-45bd-a88b-e353b0d85dfc',
                'mongoCollection' => 'familyTPV'
            ],[
                'name' => 'Greenlight',
                'id' => '6fb31120-1255-4a48-96b4-0f34be99c658',
                'mongoCollection' => 'greenlightTPV'
            ],[
                'name' => 'Indra',
                'id' => 'eb35e952-04fc-42a9-a47d-715a328125c0',
                'mongoCollection' => 'indraTPV'
            ],[
                'name' => 'LE Energy',
                'id' => '01a68da0-b16c-43d8-899f-d9c54b296ac6',
                'mongoCollection' => 'leenergyTPV'
            ],[
                'name' => 'NGE',
                'id' => 'c2d50463-c373-4a9a-b68c-d6d96af94b5d',
                'mongoCollection' => 'ngeTPV'
            ],[
                'name' => 'Source Power',
                'id' => '730e67e2-f67d-4d71-8f31-a805463629a1',
                'mongoCollection' => 'sourceTPV'
            ],[
                'name' => 'Spark Energy',
                'id' => '7845a318-09ff-42fa-8072-9b0146b174a5',
                'mongoCollection' => 'sparkTPV'
            ]
        ];

        if($this->option('debug')) {
            print_r($this->brands);
        }
        $this->logInfo('Brands to search: ', $this->brands);
    }

    /**
     * Set the email distribution list.
     */
    private function setDistroList() 
    {
        $this->info('Setting email distrbution list...');

        $this->distroList = ['engineering@tpv.com']; // Default, in case distro list was not provided.

        if($this->option('email-to')) {
            $this->distroList = $this->option('email-to');
        } 

        if($this->option('debug')) {
            print_r($this->distroList);
        }
        $this->logInfo('Email distro list: ', $this->distroList);
    }

    /**
     * Log info message to file.
     */
    private function logInfo($message, $data = null)
    {
        if($data) {
            Log::info("<{$this->jobName} :: {$this->logSessionid}> {$message}", [$data]);
        } else {
            Log::info("<{$this->jobName} :: {$this->logSessionid}> {$message}");
        }
    }

    /**
     * Log error message to file.
     */
    private function logError($message, $data = null)
    {
        if($data) {
            Log::error("<{$this->jobName} :: {$this->logSessionid}> {$message}", [$data]);
        } else {
            Log::error("<{$this->jobName} :: {$this->logSessionid}> {$message}");
        }
    }
}
