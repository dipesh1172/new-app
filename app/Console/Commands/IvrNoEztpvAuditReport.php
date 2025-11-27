<?php
/**
 * Author: Alex K
 * Date:  2023-07-07
 *
 * Builds an audit report for IVR TPVs that don't use Focus/EZTPV. This report covers TPVs stored in both DB12 and MongoDB.
 * Program flow:
 * - Grab all records from Motion's report_call table for the Brands/DNISes we're tracking
 * - For each result, check if a record exists with a matching uniqueid value in DB12/MongoDB
 * - If a record is not found, include it on the report
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use App\Traits\DeliverableTrait;
use App\Traits\ExportableTrait;

use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Ramsey\Uuid\Uuid;

class IvrNoEztpvAuditReport extends Command
{
    use ExportableTrait;
    use DeliverableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ivr:no-eztpv-audit-report {--start-date=} {--end-date=} {--debug} {--email-to=*} {--email-to2=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'NEED DESCRIPTION';

    /**
     * App pretty name, for use in emails and logging.
     *
     * @var string
     */
    protected $jobName = 'Non-EZTPV IVR Audits Report';

    /**
     * A UUID for logging. Can be used to pull all related log messages.
     *
     * @var string
     */
    protected $logSessionid;

    /**
     * Array of brand names/ids/other info for the brands that will need to be audited.
     */
    protected $brands = [];

    /**
     * Email distribution list.
     */
    protected $distroList = [];
    protected $distroList2 = []; // Charter/Humana distro list

    /**
     * Date search date range.
     */
    protected $startDate;
    protected $endDate;

    /**
     * Our HTTP client.
     */
    protected $httpClient;

    /**
     * The data from motion.
     */
    protected $motionData;

    /**
     * Data from DB12 query results. For each collection we query we'll store data in a separate key in this
     * variable. This will allow us to refer to this array instead of querying Mongo DB for each unique ID from the same collection.
     */
    protected $dbData;

    /**
     * QARecordings data from MongoDB
     */
    protected $QaRecordingsData;

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

        try {
            // Set date range
            $this->setDateRange();

            // Set distro list
            $this->setDistroList();

            // Get/Validate brand IDs
            $this->setBrandInfo();

            $this->logInfo('There are ' . count($this->brands) . ' DNIS record(s) to process');

            // Set up HTTP client
            $this->httpClient = new HttpClient(['verify' => false]);

            // Build summary            
            $summary = $this->buildSummary();

            // Get Data from Motion for all the configured brands
            $motionDataResult = $this->getMotionData();

            if ($motionDataResult->result == 'error') {
                $this->error('Error getting data from Motion');
                $this->logError('Error get data from Motion.', $motionDataResult);

                exit -1;
            }

            // Check record count
            if (count($motionDataResult->data) == 0) {
                $this->info('No TPV data found in Motion.');

                return null;
            }

            // Get QARecordings data from MongoDB
            $qaRecordingsResult = $this->getQaRecordingsRecordsByDateRange();

            if ($qaRecordingsResult->result == 'error') {
                $this->error('Error getting QARecordings data from MongoDB');
                $this->logError('Error getting QARecordings data from MongoDB.', $qaRecordingsResult);

                exit -1;
            }

            $this->QaRecordingsData = $qaRecordingsResult->data;

            $this->info('Located '.count($motionDataResult->data).' record(s)');
            $this->info('');

            // Attach some metadata to each record from Motion, and convert results from array of objects to array of arrays
            $motionData = [];
            foreach ($motionDataResult->data as $record) {                
                $brand = $this->getBrandByDnis($record->destination);

                $record->brand   = $brand['name'];
                $record->dbType  = $brand['db_type'];
                $record->dbTable = $brand['db_table'];
                $record->hasQaReview = $brand['qa_review'];

                $motionData[] = (array) $record;

                $summaryBrand = str_replace(' ', '_', strtolower($record->brand));
                $summaryDnis  = str_replace('+1', '', $record->destination);

                $summary[$summaryBrand][$summaryDnis]['MotionRecordCount'] ++;
            }

            $this->logInfo('Motion data after brand name and collection are are populated and array conversion:', $motionData);            

            // Process results and build report.
            // Check by unique ID if record exists in D12/MongoDB, and exclude from report if a match is found.
            $reportData  = [];
            $reportData2 = []; // Charter/Humana report, sent to different distro


            $ctr = 0;
            $totalRecords = count($motionData);

            foreach ($motionData as $record) {

                $ctr = $ctr + 1;

                $summaryBrand = str_replace(' ', '_', strtolower($record['brand']));
                $summaryDnis  = str_replace('+1', '', $record['destination']);

                $this->info('--------------------------------------------------------------------------------');
                $this->info("[ {$ctr} / {$totalRecords} ]");
                $this->info('');
                $this->info("Brand: {$record['brand']}");
                $this->info("DNIS:  {$record['destination']}");
                $this->info("DB:    {$record['dbType']}");
                $this->info("Table: {$record['dbTable']}");
                $this->info('');

                $dbLookupResult = null;

                // Check if we have downloaded the data locally.
                // If not, query SQL/Mongo
                // Previous queries are stored with key $record['dbTable]                
                $this->info('Checking for local data...');

                $queryDb = true;
                if( isset($this->dbData[$record['dbTable']]) ) {
                    $this->info('Local data exits.');

                    $queryDb = false;                    
                }

                if($queryDb) {
                    $this->info('Local data does NOT exist.');

                    if(strtolower($record['dbType']) == 'sql') { // Check in DB12
                        $this->info('Getting data from DB12...');
                        // $dbLookupResult = $this->getDB12RecordsByUniqueId($record);
                        $dbLookupResult = $this->getDB12RecordsByDateRange($record);
                        $this->info('Done!');
                    } else {    // Check in MongoDB
                        $this->info('Getting data from MongoDB...');
                        // $dbLookupResult = $this->getMongoRecordsByUniqueId($record);
                        $dbLookupResult = $this->getMongoRecordsByDateRange($record);
                        $this->info('Done!');
                    }

                    $recCount = count($dbLookupResult->data);
                    $this->info("Query returned {$recCount} record(s)");

                    // Save results for later use
                    $this->dbData[$record['dbTable']] = $dbLookupResult->data;
                }

                $this->info('');
                $this->info('Searching local data...');

                $dbLookupResult = $this->searchDbData($record, $record['dbTable']);

                if($dbLookupResult->result != 'success') {

                    $this->error($dbLookupResult->message);
                    $this->logError($dbLookupResult->message);
                    continue;
                }

                $notes = "";
                $sqlOrMongoDb      = (strtolower($record['dbType']) == 'sql' ? 'DB12' : 'MongoDB');
                $tableOrCollection = (strtolower($record['dbType']) == 'sql' ? 'Table' : 'Collection');

                if(count($dbLookupResult->data) == 0) {
                        
                    $this->info('TPV record NOT found. Adding to report');

                    $summary[$summaryBrand][$summaryDnis]['TpvDataNotFound'] ++;

                    // Try to find confirmation code in motion logs
                    $this->info('Performing Confirmation Code lookup against Motion logs');

                    $dtStart = Carbon::now("America/Chicago");
                    $motionLogResult = $this->getConfirmationCodeByUniqueId($record['uniqueid']);
                    $dtEnd = Carbon::now("America/Chicago");
    
                    $diff = $dtEnd->diffInSeconds($dtStart);
                    $this->info('Search took ' . $diff . ' second(s)');

                    if($motionLogResult->result == 'success') {
                        $logData = $motionLogResult->data;
    
                        $cfCodes = [];
                        foreach($logData as $item) {
                            $cfCodes[] = $item->ConfirmationSearch;
                        }
                        
                        $this->info('Found Confirmation Code: ' . implode(', ', $cfCodes));
                        
                        $record['confirmation_code'] = implode(' | ', $cfCodes);
    
                    } else {
                        $this->error('ERROR');
                        if($this->option('debug')) {
                            print_r($motionLogResult);
                        }
                        $this->logError("Error getting data from Motion:", $motionLogResult);
                    }                    

                    $notes = "Did not find record in {$sqlOrMongoDb}. {$tableOrCollection} checked: {$record['dbTable']}.";

                    if(empty($record['confirmation_code'])) {
                        $notes .= " Unable to find Confirmation Code in Motion logs";
                    }

                    if($this->isCharterOrHumana($record['destination'])) {
                        $reportData2[] = [
                            'brand_name' => $record['brand'], 
                            'ivr_date'   => $record['starttime'], 
                            'confirmation_code' => $record['confirmation_code'],
                            'channel'           => '',
                            'vendor_name'       => '',
                            'no_sale_reason'    => '',
                            'notes'             => $notes,
                            'uniqueid'          => "'" . $record['uniqueid'], 
                            'outcome_of_call'   => '', 
                            'data_restored'     => '',
                            'review_notes'      => ''
                        ];
                    } else {
                        $reportData[] = [
                            'brand_name' => $record['brand'], 
                            'ivr_date'   => $record['starttime'], 
                            'confirmation_code' => $record['confirmation_code'],
                            'channel'           => '',
                            'vendor_name'       => '',
                            'no_sale_reason'    => '',
                            'notes'             => $notes,
                            'uniqueid'          => "'" . $record['uniqueid'], 
                            'outcome_of_call'   => '', 
                            'data_restored'     => '',
                            'review_notes'      => ''
                        ];
                    }
                } else {                    

                    // If this is a QA Review client, check QA Recordings before dropping from report.
                    if($record['hasQaReview']) {         
                        $this->info('TPV record FOUND. This is a QA Review client.');

                        $notes = "TPV record Found. {$sqlOrMongoDb} {$tableOrCollection}: {$record['dbTable']}.";
                        
                        // Include TPV record on report if no QARecording was found.
                        if(!$this->getQaRecordingByUniqueId($record['uniqueid'])) {
                            $this->info('NOT found.');
                            
                            $notes .= '. Unable to find QARecording record.';

                            // Try to find confirmation code in motion logs
                            $this->info('Performing Confirmation Code lookup against Motion logs');

                            $dtStart = Carbon::now("America/Chicago");
                            $motionLogResult = $this->getConfirmationCodeByUniqueId($record['uniqueid']);
                            $dtEnd = Carbon::now("America/Chicago");
            
                            $diff = $dtEnd->diffInSeconds($dtStart);
                            $this->info('Search took ' . $diff . ' second(s)');

                            if($motionLogResult->result == 'success') {
                                $logData = $motionLogResult->data;
            
                                $cfCodes = [];
                                foreach($logData as $item) {
                                    $cfCodes[] = $item->ConfirmationSearch;
                                }
                                
                                $this->info('Found Confirmation Code: ' . implode(', ', $cfCodes));
                                
                                $record['confirmation_code'] = implode(' | ', $cfCodes);
            
                            } else {
                                $this->error('ERROR');
                                if($this->option('debug')) {
                                    print_r($motionLogResult);
                                }
                                $this->logError("Error getting data from Motion:", $motionLogResult);
                            } 

                            if($this->isCharterOrHumana($record['destination'])) {
                                $reportData2[] = [
                                    'brand_name' => $record['brand'], 
                                    'ivr_date'   => $record['starttime'], 
                                    'confirmation_code' => $record['confirmation_code'],
                                    'channel'           => '',
                                    'vendor_name'       => '',
                                    'no_sale_reason'    => '',
                                    'notes'             => $notes,
                                    'uniqueid'          => "'" . $record['uniqueid'], 
                                    'outcome_of_call'   => '', 
                                    'data_restored'     => '',
                                    'review_notes'      => ''
                                ];
                            } else {
                                $reportData[] = [
                                    'brand_name' => $record['brand'], 
                                    'ivr_date'   => $record['starttime'], 
                                    'confirmation_code' => $record['confirmation_code'],
                                    'channel'           => '',
                                    'vendor_name'       => '',
                                    'no_sale_reason'    => '',
                                    'notes'             => $notes,
                                    'uniqueid'          => "'" . $record['uniqueid'], 
                                    'outcome_of_call'   => '', 
                                    'data_restored'     => '',
                                    'review_notes'      => ''
                                ];
                            }
                        } else {
                            $this->info('QA Recordings record FOUND. Excluding from report');
                        }

                    } else {

                        $this->info('TPV record FOUND. Not a QA Review client. Excluding from report');
                    }

                    $summary[$summaryBrand][$summaryDnis]['TpvDataFound'] ++;
                }                
            }

            // Export data to CSV for primary report
            $this->info('');
            $this->info('Exporting data to CSV');

            $csvHeader = [
                'brand_name', 'ivr_date', 'confirmation_code', 'channel', 'vendor_name', 'no_sale_reason', 
                'notes', 'uniqueid', 'outcome_of_call', 'data_restored', 'review_notes'
            ];

            $filename = 'Non-EZTPV IVR Audits Report - ' . $this->startDate->format('Ymd') . '.csv';

            $this->writeCsvFile(public_path('tmp/') . $filename, $reportData, $csvHeader);

            // Export data to CSV for Charter/Humana report
            $this->info('');
            $this->info('Exporting Chart/Humana data to CSV');

            $filenameCharterHumana = 'Non-EZTPV IVR Audits Report - Charter-Humana - ' . $this->startDate->format('Ymd') . '.csv';

            $this->writeCsvFile(public_path('tmp/') . $filenameCharterHumana, $reportData2, $csvHeader);

            // Export summary data to CSV
            // $csvHeader2 = [
            //     'brand', 'dnis', 'count_motion', 'count_tpv_found', 'count_tpv_not_found'
            // ];

            // $filename2 = 'Non-EZTPV IVR Audits Report - ' . $this->startDate->format('Ymd') . ' - Summary.csv';

            // $csvData = [];
            // foreach($summary as $brand => $brandData) {
            //     foreach($brandData as $dnis => $dnisData) {
            //         $csvData[] = [
            //             'brand' => $brand,
            //             'dnis' => $dnis,
            //             'count_motion' => $dnisData['MotionRecordCount'],
            //             'count_tpv_found' => $dnisData['TpvDataFound'],
            //             'count_tpv_not_found' => $dnisData['TpvDataNotFound']
            //         ];
            //     }
            // }

            // $this->writeCsvFile(public_path('tmp/') . $filename2, $csvData, $csvHeader2);

            // Email the primary report
            $this->info('Emailing report...');

            $message = 'Attached is the ' . $this->jobName . ' for ' . $this->startDate->format('Y-m-d') . '.';

            $this->sendGenericEmail([
                'to' => $this->distroList,
                'subject' => $this->jobName . ' - ' . $this->startDate->format('Y-m-d'),
                'body' => $message,
                // 'attachments' => [ (public_path('tmp/') . $filename), (public_path('tmp/') . $filename2) ]
                'attachments' => [ (public_path('tmp/') . $filename) ]
            ]);

            // Email the primary report
            $this->info('Emailing Charter/Humana report...');

            $messageCharterHumana = 'Attached is the ' . $this->jobName . ' (Charter/Humana) for ' . $this->startDate->format('Y-m-d') . '.';

            $this->sendGenericEmail([
                'to' => $this->distroList2,
                'subject' => $this->jobName . ' (Charter/Humana) - ' . $this->startDate->format('Y-m-d'),
                'body' => $messageCharterHumana,
                // 'attachments' => [ (public_path('tmp/') . $filenameCharterHumana), (public_path('tmp/') . $filename2) ]
                'attachments' => [ (public_path('tmp/') . $filenameCharterHumana) ]
            ]);

        } catch (\Exception $e) {
            $this->error($e->getMessage());

            $this->logError('EXCEPTION:', $e);
        }
    }

    /**
     * Returns true if DNIS belongs to Charter or Humana
     */
    private function isCharterOrHumana($dnis)
    {
        $brand = $this->getBrandByDnis($dnis);

        if(!$brand) {
            return false;
        }

        if(in_array(strtolower($brand['name']), ['charter', 'humana'])) {
            return true;
        }

        return false;
    }

    /**
     * Searches previously queried DB results
     */
    private function searchDbData($record, $key)
    {
        $this->info('Searching previously queried data');

        if(!isset($this->dbData[$key])) {
            $msg = "Key '{$key}' does not exist in $this->dbData";
            $this->error($msg);

            return $this->newResult('error', $msg);
        }

        $dataSet = $this->dbData[$key];
        foreach($dataSet as $dbRecord) {
            if(strtolower($record['dbType']) == 'sql') {
                if($dbRecord->IVR_UniqueId == $record['uniqueid']) {
                    return $this->newResult('success', '', [$dbRecord]);
                }
            } else {
                if($dbRecord->CallDetail->UNIQUEID == $record['uniqueid']) {
                    return $this->newResult('success', '', [$dbRecord]);
                }
            }
        }

        // No records found.
        return $this->newResult('success', '', []);
    }

    /**
     * Build summary object from brand data.
     * 
     * Layout:
     * [
     *      <BRAND> = [
     *          <DNIS> = [
     *              'MotionRecordCount' = 0,
     *              'TPVDataFound' = 0,
     *              'TPVDataNotFound' = 0
     *          ]
     *      ]
     * ]
     */
    private function buildSummary()
    {
        $summary = [];

        foreach($this->brands as $brand) {
            $dnis  = str_replace('+1', '', $brand['dnis']);
            $name = str_replace(' ', '_', strtolower($brand['name']));

            if(!isset($summary[$name])) {
                $summary[$name] = [];
            }

            if(!isset($summary[$name][$dnis])) {
                $summary[$name][$dnis] = [
                    'DB' => $brand['db_type'],
                    'TableName' => $brand['db_table'],
                    'MotionRecordCount' => 0,
                    'TpvDataFound' => 0,
                    'TpvDataNotFound' => 0
                ];
            }
        }
            
        return $summary;
    }

    /**
     * API call to getMongoMotionData to search logs for unique ID based on provided confirmation number
     * 
     * @param $confirmationCode - The confirmation number to search the logs for
     */
    private function getConfirmationCodeByUniqueId($uniqueId)
    {
        // Build payload
        $body = [
            "uniqueid" => $uniqueId
        ];

        $options = [
            'verify' => false,
            'headers' => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getConfirmationCodeByUniqueId REQUEST', $options);

        try {
            // Post the request
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getMongoMotionData',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getConfirmationCodeByUniqueId RESPONSE', $options);
            return $this->newResult((isset($res->error) ? 'error' : 'success'), '', $res);

        } catch (ServerException $e) {
            $this->logInfo('getConfirmationCodeByUniqueId EXCEPTION', $e->getResponse()->getBody()->getContents());
            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));            

        } catch (ClientException $e) {
            $this->logInfo('getConfirmationCodeByUniqueId EXCEPTION', $e->getResponse()->getBody()->getContents());
            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));            

        } catch (\Exception $e) {
            $this->logInfo('getConfirmationCodeByUniqueId EXCEPTION', $e);
            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /** 
     * Checks if a QARecording record exists in $this->QaRecordingsData with a matching uniqueId
     */
    private function getQaRecordingByUniqueId($uniqueId)
    {
        $this->info('Checking QARecordings data...');

        foreach($this->QaRecordingsData as $record) {
            if($record->uniqueid == $uniqueId) {
                $this->info('Found');
                return true;
            }
        }

        $this->info('NOT found');
        return false;
    }

    /** 
     * API call to getqarecordingsrecordsbydaterange to get data from DB12
     */
    private function getQaRecordingsRecordsByDateRange() 
    {
        $this->info("Getting QARecordings data from MongoDB for {$this->startDate->format('Y-m-d H:i:s')} - {$this->endDate->format('Y-m-d H:i:s')}...");

        // Build payload
        $body = [
            'startDate' => $this->startDate->format('Y-m-d H:i:s'),
            'endDate'   => $this->endDate->format('Y-m-d H:i:s')
        ];
        
        $options = [
            'verify' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getqarecordingsrecordsbydaterange REQUEST', $options);

        try {
            // Post the request
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getqarecordingsrecordsbydaterange',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getqarecordingsrecordsbydaterange RESPONSE', $res);

            return $res;
        } catch (ServerException $e) {
            $this->logError('getqarecordingsrecordsbydaterange EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (ClientException $e) {
            $this->logError('getqarecordingsrecordsbydaterange EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (\Exception $e) {
            $this->logError('getqarecordingsrecordsbydaterange EXCEPTION', $e);

            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /** 
     * API call to getsqlrecordsbydaterange to get data from DB12
     */
    private function getDB12RecordsByDateRange($record) 
    {
        $this->info("Getting data from DB 12 for {$this->startDate->format('Y-m-d H:i:s')} - {$this->endDate->format('Y-m-d H:i:s')}...");

        // Build payload
        $body = [
            'dbTable'   => $record['dbTable'],
            'startDate' => $this->startDate->format('Y-m-d H:i:s'),
            'endDate'   => $this->endDate->format('Y-m-d H:i:s')
        ];
        
        $options = [
            'verify' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getsqlrecordsbydaterange REQUEST', $options);

        try {
            // Post the request
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getsqlrecordsbydaterange',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getsqlrecordsbydaterange RESPONSE', $res);

            return $res;
        } catch (ServerException $e) {
            $this->logError('getsqlrecordsbydaterange EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (ClientException $e) {
            $this->logError('getsqlrecordsbydaterange EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (\Exception $e) {
            $this->logError('getsqlrecordsbydaterange EXCEPTION', $e);

            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /** 
     * API call to getsqlrecordsbyuniqueid to get data from DB12
     */
    private function getDB12RecordsByUniqueId($record) 
    {
        $this->info("Getting data from DB 12 for unqiueid {$record['uniqueid']}...");

        // Build payload
        $body = [
            'dbTable'  => $record['dbTable'],
            'uniqueId' => [ $record['uniqueid'] ],
        ];
        
        $options = [
            'verify' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getsqlrecordsbyuniqueid REQUEST', $options);

        try {
            // Post the request
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getsqlrecordsbyuniqueid',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getsqlrecordsbyuniqueid RESPONSE', $res);

            return $res;
        } catch (ServerException $e) {
            $this->logError('getsqlrecordsbyuniqueid EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (ClientException $e) {
            $this->logError('getsqlrecordsbyuniqueid EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (\Exception $e) {
            $this->logError('getsqlrecordsbyuniqueid EXCEPTION', $e);

            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /**
     * API call to getmongorecordsbyuniqueid to get data from MongoDB.
     */
    private function getMongoRecordsByUniqueId($record)
    {
        $this->info('Getting data from MongoDB...');

        // Build payload
        $body = [
            'collectionName' => $record['dbTable'],
            'uniqueId' => $record['uniqueid'],
        ];

        $options = [
            'verify' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getmongorecordsbyuniqueid REQUEST', $options);

        try {
            // Post the request
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getmongorecordsbyuniqueid',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getmongorecordsbyuniqueid RESPONSE', $res);

            return $res;
        } catch (ServerException $e) {
            $this->logError('getmongorecordsbyuniqueid EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (ClientException $e) {
            $this->logError('getmongorecordsbyuniqueid EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (\Exception $e) {
            $this->logError('getmongorecordsbyuniqueid EXCEPTION', $e);

            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /**
     * API call to getmongorecordsbydaterange to get data from MongoDB.
     */
    private function getMongoRecordsByDateRange($record)
    {
        $this->info('Getting data from MongoDB...');

        // Build payload
        $body = [
            'collectionName' => $record['dbTable'],
            'startDate' => $this->startDate->format('Y-m-d H:i:s'),
            'endDate'   => $this->endDate->format('Y-m-d H:i:s')
        ];

        $options = [
            'verify' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getmongorecordsbydaterange REQUEST', $options);

        try {
            // Post the request
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getmongorecordsbydaterange',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getmongorecordsbydaterange RESPONSE', $res);

            return $res;
        } catch (ServerException $e) {
            $this->logError('getmongorecordsbydaterange EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (ClientException $e) {
            $this->logError('getmongorecordsbydaterange EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (\Exception $e) {
            $this->logError('getmongorecordsbydaterange EXCEPTION', $e);

            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /**
     * Retrieve report_call data from Motion database, via an API call to answernet API.
     */
    private function getMotionData()
    {
        $this->info('Getting data from Motion...');
        $dnisList = [];

        foreach ($this->brands as $brand) {
            $dnisList[] = $brand['dnis'];
        }

        $body = [
            'startDate' => $this->startDate->format('Y-m-d'),
            'endDate' => $this->endDate->format('Y-m-d'),
            'dnis' => $dnisList,
        ];

        $options = [
            'verify' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ];

        $this->logInfo('getmotionreportcalldataforivraudit REQUEST', $options);

        try {
            // Post the data
            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/util/getmotionreportcalldataforivraudit',
                $options
            );

            // Check response and build result
            $res = json_decode($response->getBody()->getContents());

            $this->logInfo('getmotionreportcalldataforivraudit RESPONSE', $res);

            return $res; // Results from API call alreay comes back in our preferred format: {result:, message:, data:}
        } catch (ServerException $e) {
            $this->logError('getmotionreportcalldataforivraudit EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (ClientException $e) {
            $this->logError('getmotionreportcalldataforivraudit EXCEPTION', $e->getResponse()->getBody()->getContents());

            return $this->newResult('error', $e->getMessage(), json_decode($e->getResponse()->getBody()->getContents()));
        } catch (\Exception $e) {
            $this->logError('getmotionreportcalldataforivraudit EXCEPTION', $e);

            return $this->newResult('error', $e->getMessage(), $e);
        }
    }

    /**
     * Brand by DNIS lookup against $this->brands array.
     * 
     * @param $dnis - The DNIS to search for
     */
    private function getBrandByDnis($dnis)
    {
        foreach ($this->brands as $brand) {
            if ($dnis == $brand['dnis']) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Convenience function for creating a simple result object.
     * 
     * @param $result  - Result string (ie 'success', 'error', etc...)
     * @param $message - Optional. Message string to return with result.
     * @param $data    - Optional. Data to return with result.
     */
    private function newResult($result = 'success', $message = '', $data = null)
    {
        return (object) [
            'result' => $result,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Sets the date range for the TPV data search.
     */
    private function setDateRange()
    {
        $this->startDate = Carbon::yesterday();
        $this->endDate = Carbon::today();

        if ($this->option('start-date') && $this->option('end-date')) {
            $this->info('Using custom date range.');

            $this->startDate = Carbon::parse($this->option('start-date'), 'America/Chicago');
            $this->endDate = Carbon::parse($this->option('end-date'), 'America/Chicago');
        } else {
            $this->info('Using default date range.');

            $this->startDate = Carbon::yesterday();
            $this->endDate = Carbon::today();
        }

        $this->info('  Start Date: '.$this->startDate->format('Y-m-d H:i:s'));
        $this->info('  End Date:   '.$this->endDate->format('Y-m-d H:i:s'));
        $this->info('');

        $this->logInfo('Selected date range:', ['start' => $this->startDate->format('Y-m-d H:i:s'), 'end' => $this->endDate->format('Y-m-d H:i:s')]);
    }

    /**
     * Populate object array with data for brands that will be searched.
     */
    private function setBrandInfo()
    {
        $this->info('Setting up brand info...');

        $this->brands = [
            ["name" => "4 Change", "db_table" => "4changeTPV", "db_type" => "Mongo", "dnis" => "+18555071907", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Marathon Power", "db_table" => "marathonTPV", "db_type" => "Mongo", "dnis" => "+18552230702", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Marathon Power", "db_table" => "marathonTPV", "db_type" => "Mongo", "dnis" => "+18336262201", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "ABC Energy", "db_table" => "abcenergyTPV", "db_type" => "Mongo", "dnis" => "+18449172593", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Agway Energy", "db_table" => "agwayTPV", "db_type" => "Mongo", "dnis" => "+18336862993", "dnis2" => "", "lead_type" => "Lite", "qa_review" => false],
            ["name" => "Baja Broadband LLC", "db_table" => "BajaBroadband_Main", "db_type" => "SQL", "dnis" => "+18559412458", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Bella Albertson LLC dba The RX Helper", "db_table" => "bellaAlbertsonTPV", "db_type" => "Mongo", "dnis" => "+18558262887", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Blue Casa Telephone", "db_table" => "BlueCasaTelephone_Main", "db_type" => "SQL", "dnis" => "+18557503208", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Capital", "db_table" => "capitalTPV", "db_type" => "Mongo", "dnis" => "+18446418108", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Capital", "db_table" => "capitalTPV", "db_type" => "Mongo", "dnis" => "+18333051079", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Capital", "db_table" => "capitalTPV", "db_type" => "Mongo", "dnis" => "+18447730742", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Champion", "db_table" => "championTPV", "db_type" => "Mongo", "dnis" => "+18446173599", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18449523950", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18449044520", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18776714246", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18339824216", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18333770864", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18559434060", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18335930320", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18559234419", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18445870260", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18558233044", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18334791798", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18334540393", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18334762573", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18447391273", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18335571667", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18555191022", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18664842609", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18559644831", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18337981815", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "CSS", "db_table" => "cssTPV", "db_type" => "Mongo", "dnis" => "+18334571750", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Cydcor", "db_table" => "Cydcor", "db_type" => "Mongo", "dnis" => "+18778195656", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Cydcor", "db_table" => "Cydcor", "db_type" => "Mongo", "dnis" => "+18447831863", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Cydcor", "db_table" => "Cydcor", "db_type" => "Mongo", "dnis" => "+18556335204", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Eligo", "db_table" => "eligoTPV", "db_type" => "Mongo", "dnis" => "+18337432387", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Eligo", "db_table" => "eligoTPV", "db_type" => "Mongo", "dnis" => "+18336913560", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Family Commercial", "db_table" => "familyTPV", "db_type" => "Mongo", "dnis" => "+18444275081", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Fidelity", "db_table" => "FidelityLongDistance_Main", "db_type" => "SQL", "dnis" => "+18668139337", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Fidelity", "db_table" => "FidelityLongDistance_Main", "db_type" => "SQL", "dnis" => "+18447410411", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Grande Communications", "db_table" => "GrandeCommunications_Main", "db_type" => "SQL", "dnis" => "+18338850438", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "HC Cable Opco LLC", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18447860419", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18887252832", "dnis2" => "+18138458585", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430650", "dnis2" => "+18138458511", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512934", "dnis2" => "+18138458509", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512940", "dnis2" => "+18138458589", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430651", "dnis2" => "+18138458592", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512938", "dnis2" => "+18138458595", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430652", "dnis2" => "+18138458597", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513004", "dnis2" => "+17264656148", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430728", "dnis2" => "+17264656153", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512988", "dnis2" => "+17264656184", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430729", "dnis2" => "+17264656187", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513016", "dnis2" => "+17264656094", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430730", "dnis2" => "+17264656110", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512998", "dnis2" => "+17264656162", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430731", "dnis2" => "+17264656163", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512952", "dnis2" => "+17266002007", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430732", "dnis2" => "+17266101113", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513074", "dnis2" => "+17264656039", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430733", "dnis2" => "+17264656040", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137510531", "dnis2" => "+18138458577", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430727", "dnis2" => "+18138458582", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513010", "dnis2" => "+17264656126", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430734", "dnis2" => "+17264656127", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513073", "dnis2" => "+17264656059", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430735", "dnis2" => "+17264656065", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513076", "dnis2" => "+17264656020", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430736", "dnis2" => "+17264656028", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513078", "dnis2" => "+15619314965", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430737", "dnis2" => "+17264656011", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513034", "dnis2" => "+17264656075", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430738", "dnis2" => "+17264656083", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513003", "dnis2" => "+17264656156", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430739", "dnis2" => "+17264656158", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513082", "dnis2" => "+15612863831", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430740", "dnis2" => "+15614076182", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513080", "dnis2" => "+15618780063", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430741", "dnis2" => "+15618780098", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513007", "dnis2" => "+17264656138", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430742", "dnis2" => "+17264656142", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513075", "dnis2" => "+17264656031", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430743", "dnis2" => "+17264656032", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513081", "dnis2" => "+15616390857", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430745", "dnis2" => "+15616680031", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512989", "dnis2" => "+17264656180", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430746", "dnis2" => "+17264656183", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512956", "dnis2" => "+17264656197", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430747", "dnis2" => "+17264656198", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512992", "dnis2" => "+17264656178", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430779", "dnis2" => "+17264656179", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513079", "dnis2" => "+15618780209", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430785", "dnis2" => "+15619314183", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512996", "dnis2" => "+17264656172", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430787", "dnis2" => "+17264656176", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512997", "dnis2" => "+17264656166", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430790", "dnis2" => "+17264656168", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513008", "dnis2" => "+17264656130", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430792", "dnis2" => "+17264656133", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513011", "dnis2" => "+17264656112", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430793", "dnis2" => "+17264656116", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512957", "dnis2" => "+17264656190", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430794", "dnis2" => "+17264656195", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600026", "dnis2" => "+18138458568", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817693072", "dnis2" => "+18138458571", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600113", "dnis2" => "+18138458564", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817122043", "dnis2" => "+18138458565", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600109", "dnis2" => "+18138458525", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698240", "dnis2" => "+18138458527", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600094", "dnis2" => "+18138458523", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698221", "dnis2" => "+18138458524", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600091", "dnis2" => "+18138458515", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698223", "dnis2" => "+18138458519", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600116", "dnis2" => "+18138458512", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698227", "dnis2" => "+18138458514", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600071", "dnis2" => "+18138458507", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817697711", "dnis2" => "+18138458508", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600047", "dnis2" => "+18138458499", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817695790", "dnis2" => "+18138458503", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600019", "dnis2" => "+18138458546", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698054", "dnis2" => "+18138458555", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600036", "dnis2" => "+18138458542", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698220", "dnis2" => "+18138458544", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600035", "dnis2" => "+18138458566", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698279", "dnis2" => "+18138458567", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600104", "dnis2" => "+18138458558", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698058", "dnis2" => "+18138458562", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600112", "dnis2" => "+15619314886", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817697706", "dnis2" => "+17264656016", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600085", "dnis2" => "+15619314445", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817127198", "dnis2" => "+15619314812", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600075", "dnis2" => "+15619314027", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12813944851", "dnis2" => "+15619314291", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600115", "dnis2" => "+15615760945", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817691484", "dnis2" => "+15619243058", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600083", "dnis2" => "+15615714404", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817693170", "dnis2" => "+15615714475", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600076", "dnis2" => "+18138458598", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817697971", "dnis2" => "+18138458599", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600106", "dnis2" => "+18138458537", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817122909", "dnis2" => "+18138458540", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+15615600069", "dnis2" => "+18138458531", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12817698228", "dnis2" => "+18138458532", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513056", "dnis2" => "+17264656070", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430659", "dnis2" => "+17264656071", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512949", "dnis2" => "+17266101380", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430655", "dnis2" => "+17266101511", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137512944", "dnis2" => "+17266101524", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430656", "dnis2" => "+18138458586", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+18137513018", "dnis2" => "+17264656089", "lead_type" => "", "qa_review" => false],
            ["name" => "Humana", "db_table" => "Humana_Calls", "db_type" => "SQL", "dnis" => "+12109430726", "dnis2" => "+17264656091", "lead_type" => "", "qa_review" => false],
            ["name" => "IMON Communications LLC", "db_table" => "IMONCommunications_Main", "db_type" => "SQL", "dnis" => "+18447130564", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "ImpactConnect", "db_table" => "AmericatelCorp_Main", "db_type" => "SQL", "dnis" => "+18446910997", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "KDDI America", "db_table" => "KDDIAmerica_Main", "db_type" => "SLQ", "dnis" => "+18447550406", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Lare Marketing Group LLC", "db_table" => "lareTPV", "db_type" => "Mongo", "dnis" => "+18449184541", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Legacy Health Group", "db_table" => "legacyhealthTPV", "db_type" => "Mongo", "dnis" => "+18668280012", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Lingo", "db_table" => "Lingo_Main", "db_type" => "SQL", "dnis" => "+18559829681", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Luminant Energy Comp LLC", "db_table" => "luminantTPV", "db_type" => "Mongo", "dnis" => "+18443119131", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Midco", "db_table" => "Midco_Main", "db_type" => "SQL", "dnis" => "+18445573920", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "National Health Insurance.", "db_table" => "nationalhealthinsuranceTPV", "db_type" => "Mongo", "dnis" => "+18447431114", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "National Health Plans and Benefits Agency LLC", "db_table" => "nhbpaTPV", "db_type" => "Mongo", "dnis" => "+18447411957", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "North American Power", "db_table" => "northamericanpowerTPV", "db_type" => "Mongo", "dnis" => "+18449634947", "dnis2" => "", "lead_type" => "Cliant Lead", "qa_review" => true],
            ["name" => "Ntherm", "db_table" => "nthermTPV", "db_type" => "Mongo", "dnis" => "+18445387639", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Ntherm", "db_table" => "nthermTPV", "db_type" => "Mongo", "dnis" => "+18885725217", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Ntherm", "db_table" => "nthermTPV", "db_type" => "Mongo", "dnis" => "+18885725261", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Ntherm", "db_table" => "nthermTPV", "db_type" => "Mongo", "dnis" => "+18339072783", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Optimum", "db_table" => "SuddenLink_Main", "db_type" => "SQL", "dnis" => "+18559455064", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Pilot Power", "db_table" => "pilotTPV", "db_type" => "Mongo", "dnis" => "+18667646592", "dnis2" => "", "lead_type" => "Lite", "qa_review" => false],
            ["name" => "Pioneer Telephone Cooperative", "db_table" => "PioneerTelephoneCooperative_Main", "db_type" => "SQL", "dnis" => "+18447021183", "dnis2" => "", "lead_type" => "Manual", "qa_review" => true],
            ["name" => "Ponderosa Telephone Co", "db_table" => "PonderosaTelephone_Main", "db_type" => "SQL", "dnis" => "+18447021222", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Power Net Global", "db_table" => "PowerNetGlobal_Main", "db_type" => "SQL", "dnis" => "+18447500867", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Primus", "db_table" => "Primus_Main", "db_type" => "SQL", "dnis" => "+18447980680", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_11_Cusick_Main", "db_type" => "SQL", "dnis" => "+18333740966", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_12_Main", "db_type" => "SQL", "dnis" => "+18333741468", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_2_Main", "db_type" => "SQL", "dnis" => "+18333530108", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_5_Boston_Main", "db_type" => "SQL", "dnis" => "+18333560013", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_7_Chicago_Main", "db_type" => "SQL", "dnis" => "+18333561104", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_9_DC_Main", "db_type" => "SQL", "dnis" => "+18333620224", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_Main", "db_type" => "SQL", "dnis" => "+18449594439", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_8_Queens_Main", "db_type" => "SQL", "dnis" => "+18333580078", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RCN", "db_table" => "RCN_6_UpperDarby_Main", "db_type" => "SQL", "dnis" => "+18333560059", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Reliant Energy", "db_table" => "reliantTPV", "db_type" => "Mongo", "dnis" => "+18558647548", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Selectel Wireless", "db_table" => "selectelTPV", "db_type" => "Mongo", "dnis" => "+18555591954", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Shipleys Energy", "db_table" => "shipleyTPV", "db_type" => "Mongo", "dnis" => "+18337111518", "dnis2" => "", "lead_type" => "Manual", "qa_review" => true],
            ["name" => "Sierra Telephone Company Inc.", "db_table" => "SierraTelephoneCompany_Main", "db_type" => "SQL", "dnis" => "+18447139628", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Sparklight", "db_table" => "sparklightTPV", "db_type" => "Mongo", "dnis" => "+18333502615", "dnis2" => "", "lead_type" => "Lead", "qa_review" => true],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103011161", "dnis2" => "+17264656709", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801102", "dnis2" => "+17264656707", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801277", "dnis2" => "+17264656706", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12104249308", "dnis2" => "+17264656705", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103010779", "dnis2" => "+17264656711", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801100", "dnis2" => "+17264656710", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103011833", "dnis2" => "+17264656680", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801373", "dnis2" => "+17264656678", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12104249333", "dnis2" => "+17264656677", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801278", "dnis2" => "+17264656477", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801060", "dnis2" => "+17264656682", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12104249331", "dnis2" => "+17264656681", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103011967", "dnis2" => "+17264656685", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103010617", "dnis2" => "+17264656689", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12104249309", "dnis2" => "+17264656688", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103012053", "dnis2" => "+17264656683", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103011952", "dnis2" => "+17264656694", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801354", "dnis2" => "+17264656692", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103011953", "dnis2" => "+17264656691", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801355", "dnis2" => "+17264656690", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12103011950", "dnis2" => "+17264656696", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12104249595", "dnis2" => "+17264656703", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Charter", "db_table" => "Charter_Main", "db_type" => "SQL", "dnis" => "+12105801134", "dnis2" => "+17264656702", "lead_type" => "Lead Opt", "qa_review" => false],
            ["name" => "Startec Global", "db_table" => "StartecGlobal_Main", "db_type" => "SQL", "dnis" => "+18447021927", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "SunSea", "db_table" => "sunseaTPV", "db_type" => "Mongo", "dnis" => "+18336250850", "dnis2" => "", "lead_type" => "", "qa_review" => false],
            ["name" => "Table Top Telephone", "db_table" => "TableTopTelephone_Main", "db_type" => "SQL", "dnis" => "+18447411940", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Team Corp", "db_table" => "TeamCorp_Main", "db_type" => "SQL", "dnis" => "+18447410676", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "RX Advocates", "db_table" => "rxAdvocatesTPV", "db_type" => "Mongo", "dnis" => "+18557541704", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18336933518", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18332900624", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18444276690", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18776074819", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18776079041", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18776074799", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18776078829", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18444276614", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TSE", "db_table" => "townsquareTPV", "db_type" => "Mongo", "dnis" => "+18776078794", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "TXU Energy", "db_table" => "txuTPV", "db_type" => "Mongo", "dnis" => "+18558645642", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "UES", "db_table" => "uesTPV", "db_type" => "Mongo", "dnis" => "+18444490931", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "UES", "db_table" => "uesTPV", "db_type" => "Mongo", "dnis" => "+18445011471", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "UET", "db_table" => "unitedenergytradingTPV", "db_type" => "Mongo", "dnis" => "+18888297909", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "UET", "db_table" => "unitedenergytradingTPV", "db_type" => "Mongo", "dnis" => "+18557840074", "dnis2" => "", "lead_type" => "Lite", "qa_review" => true],
            ["name" => "Wave Broadband", "db_table" => "WaveBroadband_Main", "db_type" => "SQL", "dnis" => "+18447410414", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "Wave Broadband", "db_table" => "WaveBroadband_Main", "db_type" => "SQL", "dnis" => "+18668280102", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "XChange Telecom", "db_table" => "XChangeTelecom_Main", "db_type" => "SQL", "dnis" => "+18336933629", "dnis2" => "", "lead_type" => "Manual", "qa_review" => false],
            ["name" => "YEP Energy", "db_table" => "yepTPV", "db_type" => "Mongo", "dnis" => "+18556134258", "dnis2" => "", "lead_type" => "Lite", "qa_review" => false],
            ["name" => "Zing", "db_table" => "zingTPV", "db_type" => "Mongo", "dnis" => "+18444303211", "dnis2" => "", "lead_type" => "Lite", "qa_review" => false],
            ["name" => "Zing", "db_table" => "zingTPV", "db_type" => "Mongo", "dnis" => "+18554969678", "dnis2" => "", "lead_type" => "Lite", "qa_review" => false],
            ["name" => "Zing", "db_table" => "zingTPV", "db_type" => "Mongo", "dnis" => "+18556703265", "dnis2" => "", "lead_type" => "Lite", "qa_review" => false],
            ["name" => "Zing", "db_table" => "zingTPV", "db_type" => "Mongo", "dnis" => "+18444226121", "dnis2" => "", "lead_type" => "Lite", "qa_review" => false]
        ];

        if ($this->option('debug')) {
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
        $this->distroList2 = ['engineering@tpv.com']; // Default, in case distro list was not provided.

        if ($this->option('email-to')) {
            $this->distroList = $this->option('email-to');
        }

        if ($this->option('email-to2')) {
            $this->distroList2 = $this->option('email-to2');
        }

        if ($this->option('debug')) {
            $this->info('Primary:');
            print_r($this->distroList);
            $this->info('Charter/Humana:');
            print_r($this->distroList2);
        }
        $this->logInfo('Primary Email distro list: ', $this->distroList);
        $this->logInfo('Charter/Humana Email distro list: ', $this->distroList2);
    }

    /**
     * Log info message to file.
     */
    private function logInfo($message, $data = null)
    {
        if ($data) {
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
        if ($data) {
            Log::error("<{$this->jobName} :: {$this->logSessionid}> {$message}", [$data]);
        } else {
            Log::error("<{$this->jobName} :: {$this->logSessionid}> {$message}");
        }
    }
}
