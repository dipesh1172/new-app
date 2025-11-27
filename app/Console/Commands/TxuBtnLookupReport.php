<?php

namespace App\Console\Commands;

use Twilio\Rest\Client as TwilioClient;
use League\Flysystem\Filesystem;
use League\Flysystem\Config;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\PhoneNumber;
use App\Models\Brand;
use App\Models\InvoiceAdditional;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TxuBtnLookupReport extends Command
{
     /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TXU:BtnLookupReport {--mode=}'; 
    /**
     * The console command description.
     *
     * @var string
     */

    /**
     * Report mode: 'live' or 'test'.
     * 
     * @var string
     */
    protected $mode = 'live'; // live mode by default.
   
    /**
     * description of report
     * 
     * @var string
     */

    protected $description = 'Generate BTN Report';
 
    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'live' => ['DXC_AutoEmails@dxc-inc.com','dxcit@tpv.com','engineering@tpv.com','david.hayward@txu.com','kelly.welander@txu.com','omar.rodriguez@vistracorp.com','robert.forster@vistracorp.com','josue.flores@vistracorp.com','Rosa.Cerda@vistracorp.com','Kelsey.Bowman@vistracorp.com','curt.cadwell@answernet.com'],
        'test' => ['curt@tpv.com','curt.cadwell@answernet.com','dxcit@tpv.com', 'engineering@tpv.com']
     ];
    
     /**
     * FTP Settings
     *
     * @var array
     */
    protected $ftpSettings = [
        'live' => [
            'host' => 'ftp.box.com',
            'username' => 'dxcit@tpv.com',
            'password' => 'n%YD$h5Ou1iC@2fLy4sv',
            'passive' => true,
            'root' => '/TXU btnlookup',
            'ssl' => true,
            'timeout' => 10
        ],
        'test' => [
            'host' => 'ftp.dxc-inc.com',
            'username' => 'dxctest',
            'password' => 'xchangeWithUs!',
            'passive' => true,
            'root' => '/',
            'ssl' => true,
            'timeout' => 10
        ]
    ];
 
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
        try {

            $adapter = new Ftp($this->ftpSettings[$this->mode]);
            $filesystem = new Filesystem($adapter);
            $files = $filesystem->listContents('/');
            $this->info('Processing '.count($files).' filesystem entries');
            foreach ($files as $file) {
                if ($file['type'] === 'file' and $file['extension'] === 'csv' and substr_count(strtolower($file['filename']),'voip_check_report_') == 1)  {
                   $this->info("Reading file ".$file['filename']);
                   $contents = $filesystem->read($file['path']);
                    $lines = explode(PHP_EOL, $contents);
                    $csv = [];
                    foreach ($lines as $line) {
                        if (strlen(trim($line)) > 0) {
                            $csv[] = str_getcsv($line,',');
                        }
                    }
                    $header = null;
                    $this->info('Processed '.(count($csv) - 1).' records.'); 
                    $recKnt = 0;
                    $data = [];
                    foreach ($csv as $row) {
                        if ($header === null) {
                            $header = $row;
                            $header = array_map( 'strtoupper',str_ireplace(" ","_",$header));
                            continue;
                        }
                         $data[$recKnt] = array_combine($header, $row);
                         $data[$recKnt]['PHONE_LOOKUP_ATTEMPTS'] = '';
//                         $data[$recKnt]['phone_lookup_result'] = '';
                         $data[$recKnt]['PHONE_LOOKUP_CARRIER'] = '';
                         $data[$recKnt]['PHONE_LOOKUP_COUNTRY'] = '';
                         $data[$recKnt]['PHONE_LOOKUP_LINE_TYPE'] = '';
                         $data[$recKnt]['PHONE_LOOKUP_CUST_TYPE'] = '';
                         $data[$recKnt]['PHONE_LOOKUP_CONTACT_NAME'] = '';
                         $data[$recKnt]['FILE_NAME'] = '';

            
                        $phone_number = '+1'. $data[$recKnt]['PHONE_NUMBER']; 
                        if ($phone_number && strlen($phone_number) == 12 ) {  // skip record if not correct
                            // $to = (0 !== strpos($phone_number, '+1'))
                            //     ? '+1' . preg_replace('/\D/', '', $phone_number)
                            //     : $phone_number;
                            $to = $phone_number;
                            // twilio lookup 
                            try {
                                $TwilioLookup = new TwilioClient(
                                    config('services.twilio.account'),
                                    config('services.twilio.auth_token')
                                    );
                    
                                // $TwilioLookup = $TwilioLookup->lookups->v1->phoneNumbers($to)->fetch(
                                //     array('countryCode' => 'US')
                                // );
                                $this->info("BTN lookup of ".$to);
                                $TwilioLookup = $TwilioLookup->lookups->v1->phoneNumbers($to)->fetch(
                                    array('type' => 'carrier',
                                        'type' => 'caller-name',
                                        'addons' => 'whitepages_pro_phone_intel')
                                );
                                $data[$recKnt]['PHONE_LOOKUP_ATTEMPTS'] = '';
 //                               $data[$recKnt]['phone_lookup_result'] = '';
                                $data[$recKnt]['PHONE_LOOKUP_CARRIER'] = 
                                    str_replace('-','_',strtoupper($TwilioLookup->addOns['results']['whitepages_pro_phone_intel']['result']['carrier']));
                                $data[$recKnt]['PHONE_LOOKUP_COUNTRY'] =  
                                    str_replace('-','_',strtoupper($TwilioLookup->addOns['results']['whitepages_pro_phone_intel']['result']['country_code']));
                                $data[$recKnt]['PHONE_LOOKUP_LINE_TYPE'] = 
                                    str_replace('-','_',strtoupper($TwilioLookup->addOns['results']['whitepages_pro_phone_intel']['result']['line_type']));
                                $data[$recKnt]['PHONE_LOOKUP_CONTACT_NAME'] = 
                                    str_replace('-','_',strtoupper(($TwilioLookup->callerName['caller_name'] == null  ? '' : $TwilioLookup->callerName['caller_name'])));
                                $data[$recKnt]['PHONE_LOOKUP_CUST_TYPE'] = 
                                    str_replace('-','_',strtoupper(($TwilioLookup->callerName['caller_type'] == null ? '' : $TwilioLookup->callerName['caller_type'])));
                                $data[$recKnt]['FILE_NAME'] = str_replace('-','_',strtoupper($file['basename']));
                
                            } catch (RestException $e) {
                                Log::debug(
                                    'Twilio hit a RestException ('
                                        . $e . ') Attempting to skip it and send anyway.'
                                );
                            } catch (TwilioException $e) {
                                Log::error(
                                    'Could not send SMS notification.' .
                                        ' error: ' . $e
                                );
                
                                return;
                            }
                           $recKnt++;
                        } else {
                            $data[$recKnt]['PHONE_LOOKUP_CARRIER'] = 'Incorrect phone number provided';
                            $recKnt++;
                        }
                    }
                    if (empty($data)) {  // no data report to customer
                        $data[$recKnt] = array_combine(array("CREATION_DATE","PHONE_NUMBER"),array("Creation Date","Phone Number"));
                        $recKnt++;
                        $data[$recKnt] = array_combine(array("CREATION_DATE","PHONE_NUMBER"),array("Data not provided","Data not provided"));
                        $recKnt++;
                    }
                    $this->info("Finished of BTN Lookup ".$file['filename']);
                    // Create the XLS file
                    $startDate = Carbon::today('America/Chicago');
                    $folderName = 'tmp/txu_energy/'
                    . 'btnlookups/'
                    . $startDate->year 
                    . str_pad($startDate->month, 2, '0', STR_PAD_LEFT) 
                    . str_pad($startDate->day, 2, '0', STR_PAD_LEFT)
                    . strval(time()) . '/'; // folder name
                    // Create download directory if it doesn't exist
                    if (!file_exists(public_path($folderName))) {
                        mkdir(public_path($folderName), 0777, true);
                    }
                    $this->info('Writing data to Xls file...');
                    $this->writeXlsFile($data, public_path($folderName . $file['filename'] . '.xls'));
                    if (!file_exists(public_path($folderName) .  $file['filename'] . '.xls')) {
                        $this->error('Unable to locate file ' . public_path($folderName) . $file['filename'] . '.xls');
                        return -1;
                    }
                    $subject = 'TXU - Res - DTD - BTN Lookup '. Carbon::now('America/Chicago')->format("Ymd-His");
                    $email_address = $this->distroList[$this->mode];
                    $fileToSend = $file['filename'] . '.xls';
                    $fileToAttach = public_path($folderName) . $fileToSend;
                    $message = 'Attached is the btn lookup file for ' . $fileToSend;
                    $data = [
                    'subject' => '',
                    'content' => $message
                    ];
                    $this->info("Sending Email");
                    for ($i = 0; $i < count($email_address); ++$i) {
                        $status = 'Email Sent to ' . $email_address[$i]
                            . ' at ' . Carbon::now();
                
                        Mail::send(
                            'emails.generic',
                            $data,
                            function ($message) use ($subject, $email_address, $i,$fileToAttach) {
                                $message->subject($subject);
                                $message->from('no-reply@tpvhub.com');
                                $message->to($email_address[$i]);
                                $message->attach($fileToAttach);
                                }
                            );
                        $this->info($status);
                    }
                    $this->info("Finished sending Email");
                   // unlink(public_path($folderName) . basename($file['basename'],'.csv').'.csv');  // Delete file off of server
                    unlink(public_path($folderName) . basename($file['basename'],'.csv').'.xls');  // Delete file off of server
                     $responseDelete = $filesystem->delete($file['basename']);   // remove file from BOX
                    if ($responseDelete) {
                        $this->info("Successfully deleted file ". $file['basename']);
                    } else {
                        $this->info("Unable to delete file ". $file['basename']);
                    }
                    $newRecord = new invoiceadditional();
                    $newRecord->rate = 0.12;  // lookup cost per Paul on DXC
                    $newRecord->owner = 'Automated Job';
                    $newRecord->ticket = 'N/A';
                    $newRecord->category = '23';
                    $newRecord->duration = (count($csv) - 1);
                    $newRecord->date_of_work = Carbon::now('America/Chicago')->format('Y-m-d');
                    $newRecord->brand_id = '200979d8-e0f5-41fb-8aed-e58a91292ca0';
                    $newRecord->description = 'BTN Lookup report for file ' . $file['basename'];
                    $newRecord->save();

                }
            }  // end for loop
        } catch (\Exception $e) {
            Log::info('Exception running TXU BTN LOOKUP ', [$e]);
            $errorList[] = ['lineNumber' => null, 'Error during TXU BTN LOOKUP ' . $e->getMessage()];
            print_r($errorList);
            return;
        }
    }
        /**
     * Writes an Excel file from a data array. Data array should
     * use named keys as the keys are used for the header row.
     */
    protected function writeXlsFile($data, $fileName) {

        try {
            $headers = array_keys($data[0]);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet()->setTitle('Sheet1');
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($data, null, 'A2');
            $recRow = 1;
            foreach ($data as $r) {   // fromArray above makes assumptions on numeric fields rewrite cell 
                $recRow = $recRow+1; 
                $sheet->setCellValueExplicit('B'.strval($recRow),$r['PHONE_NUMBER'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $writer->save($fileName);
        } catch (\Exception $e) {
            // TODO: Handle
        }

        // TODO: Return a result
    }
    
}