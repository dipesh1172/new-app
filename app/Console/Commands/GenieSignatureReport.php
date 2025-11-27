<?php

namespace App\Console\Commands;

//use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\ProviderIntegration;
use App\Models\StatsProduct;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class GenieSignatureReport extends Command
{

     public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GenieSignatureReport {--mode=} {--start-date=} {--end-date=} {--noemail} {--email-override=*}'; //{--noftp}

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Genie Signature Report ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates Signature Report';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $IDT_brandId = '77c6df91-8384-45a5-8a17-3d6c67ed78bf'; //  IDT Energy
    protected $RES_brandId = '0e80edba-dd3f-4761-9b67-3d4a15914adb'; // Residents Energy
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [  // Left FTP logic in case client changes mind
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', 'curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com','accountmanagers@answernet.com','compliance@genieretail.com','curt.cadwell@answernet.com','curt@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com','curt.cadwell@answernet.com','curt@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ]
    ];

    /**
     * FTP Settings
     *
     * @var array
     */

     protected $ftpSettings = [
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 21,
        'root' => '/',
        'passive' => true,
        'ssl' => true,
        'timeout' => 30,
        'directoryPerm' => 0755,
    ];

    /**
     * Report start date
     *
     * @var mixed
     */
    protected $startDate = null;

    /**
     * Report end date
     *
     * @var mixed
     */
    protected $endDate = null;

    /**
     * Report mode: 'live' or 'test'.
     *
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

    /**
     * Create a new command instance.
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
        // Use --email-override=email1@tpv.com --email-override=email2@tpv.com (allows multiple override emails)
        if ($this->option('email-override')) {
            $this->distroList = [
                'ftp_success' => [
                    'live' => $this->option('email-override'),
                    'test' => $this->option('email-override')
                ],
                'ftp_error' => [
                    'live' => $this->option('email-override'),
                    'test' => $this->option('email-override')
                ],
                'emailed_file' => [
                    'live' => $this->option('email-override'),
                    'test' => $this->option('email-override')
                ]
            ];
        }

        $this->startDate = Carbon::yesterday('America/Chicago');  // run after 12 midnight 
        $this->endDate = Carbon::tomorrow('America/Chicago')->add(-1, 'second');

        // Check mode. Leave in 'live' mode if not provided or an invalid value was provided.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            }
        }

        // Check for and validate custom report dates, but only if both start and end dates are provided
        if ($this->option('start-date') && $this->option('end-date')) {
            // TODO: We're trusting the dates the user is passing. Add validation for:
            // 1) valid dates were provided
            // 2) start date <= end date
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));
            $this->info('Using custom dates...');
        }
        // if (!$this->option('noftp')) {

        //     // Get FTP details
        //     $pi = ProviderIntegration::where(
        //         'brand_id',
        //         $this->brandId
        //     )->where(
        //         'provider_integration_type_id',
        //         1
        //     )->where(
        //         'service_type_id',
        //         60
        //     )->first();

        //     if (empty($pi)) {
        //         $this->error("No credentials were found.");
        //         return -1;
        //     }

        //     $this->ftpSettings['host'] = $pi->hostname;
        //     $this->ftpSettings['username'] = $pi->username;
        //     $this->ftpSettings['password'] = $pi->password;
        
        //     $adapter = new ftp($this->ftpSettings);
        //     $filesystem = new Filesystem($adapter);
        // }
        // Build file name
        if ($this->option('start-date') && $this->option('end-date')) {
            $filename = ($this->mode == 'test' ? 'TEST_' : '')
                . $this->startDate->year  . '_'
                . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '_' 
                . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) . '_thru_' 
                . $this->endDate->year  . '_'
                . str_pad($this->endDate->month, 2, '0', STR_PAD_LEFT) . '_' 
                . str_pad($this->endDate->day, 2, '0', STR_PAD_LEFT) . '_' 
                . 'genie_signature_report.xlsx';
        } else {
            $filename = ($this->mode == 'test' ? 'TEST_' : '')
                . $this->startDate->year  . '_'
                . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) . '_' 
                . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) . '_' 
                . 'genie_signature_report.xlsx';
        }
        $this->info("Retrieving TPV data...");
        $data = StatsProduct::distinct()->select(
            DB::raw('stats_product.interaction_created_at AS date'),
            DB::raw('stats_product.brand_name AS brand'),
            'stats_product.confirmation_code',
            DB::raw('stats_product.vendor_label AS vendor_name'), 
            DB::raw('stats_product.sales_agent_rep_id AS agent_id'),
            DB::raw('stats_product.sales_agent_name AS agent_name'),
            DB::raw('stats_product.btn AS phone'),
            DB::raw('stats_product.account_number1 AS Identifier'),
            DB::raw('CONCAT(stats_product.service_address1, ", ", stats_product.service_city,", ",stats_product.service_state, " ",stats_product.service_zip) AS service_address'),
            DB::raw('CONCAT(stats_product.bill_first_name," ",stats_product.bill_last_name) AS customer_name'), 
            DB::raw('CONCAT(stats_product.auth_first_name," ", stats_product.auth_last_name) AS authorized_person'),
            'signatures.signature',
            DB::raw('"" AS sms_signature'),
            DB::raw('stats_product.result AS result'),
            DB::raw('stats_product.commodity AS commodity'),
            DB::raw('utilities.name AS utility')
            )->leftJoin(
                'utilities',
                'stats_product.utility_id',
                'utilities.id'
            )->leftJoin(
                'eztpvs',
                'stats_product.eztpv_id',
                'eztpvs.id'
            )->leftJoin(
                'signatures',
                'eztpvs.id',
                'signatures.ref_id'
            )->leftJoin(
                'signature_types',
                'signature_types.id',
                'signatures.signature_type_id'
            )->whereIn(
                'stats_product.brand_id',
                [
                    $this->IDT_brandId,
                    $this->RES_brandId
                ]
            )->where('signature_types.type',
                'customer'
            )->where('stats_product.interaction_created_at',
                '>=',
                $this->startDate
            )->where('stats_product.interaction_created_at',
                '<=',
                $this->endDate
            )->where('stats_product.result',
                '=',
                'sale'
            )->orderBy(
                'stats_product.interaction_created_at',
                'asc'
            );
            
            $data = $data->get();
        // If no records, email client and exit early
        if(count($data) === 0) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for Genie Signature Report' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList['emailed_file'][$this->mode]);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'tmp/genie/'
        . 'signaturereport/'
        . $this->startDate->year 
        . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
        . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
        . strval(time()) . '/'; // folder name

        // Create download directory if it doesn't exist
        if (!file_exists(public_path($folderName))) {
            mkdir(public_path($folderName), 0777, true);
        }

        $csvData = [];
        foreach($data as $r) {
            $row = [
                'date' => $r->date,
                'brand' => $r->brand,
                'confirmation_code' => $r->confirmation_code,
                'vendor_name' => $r->vendor_name,
                'agent_id' => $r->agent_id,
                'agent_name' => $r->agent_name,
                'phone' => $r->phone,
                'identifier' => $r->Identifier,
                'service_address' => $r->service_address,
                'customer_name' => $r->customer_name,
                'authorized_person' => $r->authorized_person,
                'signature' => $r->signature,
                'sms_signature' => $r->sms_signature,
                'result' => $r->result,
                'commodity' => $r->commodity,
                'utility_name' => $r->utility
            ];
            $excelData[] = $row;
        }

        // Write the CSV file.
        // $this->info('Writing CSV file...');
        // $filecsv = fopen(public_path($folderName . $filename), 'w');

        // $fileHeader = [];
        //  foreach (array_keys($excelData[0]) as $key) {
        //      $fileHeader[] = $key;
        //  }
        // fputs($filecsv, implode(',', $fileHeader) . "\r\n");

        // // Data
        //  foreach ($excelData as $row) {
        //      fputs($filecsv,'"' .  implode('","', $row) . '"' . "\r\n");
        //  }
        //  fclose($filecsv);

        // Create the XLS file
        $this->info('Writing data to Xls file...');
        $this->writeXlsFile($excelData, public_path($folderName . $filename),$folderName);

        //        Upload the file to FTP server
        // $ftpFileName = $filename;
        // if (!$this->option('noftp')) {
        //     $this->info('Uploading file...');
        //     $this->info($ftpFileName);
        //     $ftpResult = 'FTP at ' . Carbon::now() . '. Status: ';
        //     try {
        //         $stream = fopen(public_path($folderName . $ftpFileName), 'r+');
        //         $filesystem->writeStream(
        //             $ftpFileName,
        //             $stream
        //         );

        //         if (is_resource($stream)) {
        //             fclose($stream);
        //         }
        //         $ftpResult .= 'Success!';
        //     } catch (\Exception $e) {
        //         $ftpResult .= 'Error! The reason reported is: ' . $e;
        //         $this->info($ftpResult);
        //     }
        
        //     $this->info($ftpResult);

        //     if (isset($ftpResult)) {
        //         if (strpos(strtolower($ftpResult),'success') > 0) {
        //             $this->info('Upload succeeded.');

        //         $this->sendEmail('File ' . $ftpFileName . ' has been successfully uploaded.', $this->distroList['ftp_success'][$this->mode]);
        // } else {
        //     $this->info('Upload failed.');
        //     $this->sendEmail(
        //         'Error uploading file ' . $ftpFileName . ' to FTP server ' . $this->ftpSettings['host'] .
        //             "\n\n FTP Result: " . $ftpResult,
        //         $this->distroList['ftp_error'][$this->mode]
        //     );

        //     return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
        // }
        //     }
        // }


        // Regardless of FTP result, also email the file as an attachment
        if (!$this->option('noemail')) {
            $attachments = [public_path($folderName . $filename)];   // only send enrollment file 
            if ($this->option('start-date') && $this->option('end-date')) {
                $this->info("Emailing file...");
                $this->sendEmail('Attached is the file for Genie Signature report file ' . 
                    $this->startDate->format('m-d-Y') . ' thru ' . 
                    $this->endDate->format('m-d-Y') . '.', 
                    $this->distroList['emailed_file'][$this->mode], $attachments);
            } else {
                $this->info("Emailing file...");
                $this->sendEmail('Attached is the file for Genie Signature report file ' . 
                $this->startDate->format('m-d-Y') . '.', 
                $this->distroList['emailed_file'][$this->mode], $attachments);
            }
        }
        // Delete tmp files and folders
        $this->removeDir(public_path($folderName));

    }

    protected function removeDir($dirname) {
        if (is_dir($dirname)) {
            $dir = new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS);
            foreach (new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST) as $object) {
                if ($object->isFile()) {
                    unlink($object);
                } elseif($object->isDir()) {
                    rmdir($object);
                } else {
                    throw new \Exception('Unknown object type: '. $object->getFileName());
                }
            }
            rmdir($dirname); 
        } else {
            throw new \Exception('This is not a directory');
        }
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
    protected function sendEmail(string $message, array $distro, array $files = array())
    {
        $uploadStatus = [];
        $email_address = $distro;

        // Build email subject
        if ('production' != config('app.env')) {
            $subject = $this->jobName . ' (' . config('app.env') . ') '
                . Carbon::now();
        } else {
            $subject = $this->jobName . ' ' . Carbon::now();
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

                $this->info("Success sending email to " . $email_address[$i]);
            } catch (\Exception $e) {
                $status .= 'Error! The reason reported is: ' . $e;
                $uploadStatus[] = $status;
                $this->error("Error sending email to " . $email_address[$i] . ": " . $e->getMessage());
                info("GenieSignatureReport.php function: sendEmail Error sending email to " . $email_address[$i] . ": " . $status);
            }

            $status .= 'Success!';
            $uploadStatus[] = $status;
        }

        return $uploadStatus;
    }

    /**
     * Writes an Excel file from a data array. Data array should
     * use named keys as the keys are used for the header row.
     */
    protected function writeXlsFile($data, $fileName,$folderName) {

        try {
            $headers = array_keys($data[0]);
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($headers, null, 'A1');
            $recRow = 1;
            $sheet->getStyle('A1:M1')->getFont()->setBold(true);
            $sheet->getColumnDimension('A')->setWidth(20);
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(30);
            $sheet->getColumnDimension('D')->setWidth(30);
            $sheet->getColumnDimension('E')->setWidth(30);
            $sheet->getColumnDimension('F')->setWidth(30);
            $sheet->getColumnDimension('G')->setWidth(20);
            $sheet->getColumnDimension('H')->setWidth(30);
            $sheet->getColumnDimension('I')->setWidth(30);
            $sheet->getColumnDimension('J')->setWidth(25);
            $sheet->getColumnDimension('K')->setWidth(25);
            $sheet->getColumnDimension('L')->setWidth(150);
            $sheet->getColumnDimension('M')->setWidth(10);
            foreach ($data as $recSignature) {   // fromArray above makes assumptions on numeric fields rewrite cell 
                $recRow = $recRow+1; 
                $sheet->getRowDimension($recRow)->setRowHeight(50);
                $sheet->setCellValueExplicit('A'.strval($recRow),$recSignature['date'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B'.strval($recRow),$recSignature['brand'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C'.strval($recRow),$recSignature['confirmation_code'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D'.strval($recRow),$recSignature['vendor_name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.strval($recRow),$recSignature['agent_id'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('F'.strval($recRow),$recSignature['agent_name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('G'.strval($recRow),$recSignature['phone'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H'.strval($recRow),$recSignature['identifier'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('I'.strval($recRow),$recSignature['service_address'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('J'.strval($recRow),$recSignature['customer_name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('K'.strval($recRow),$recSignature['authorized_person'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('M'.strval($recRow),$recSignature['sms_signature'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('N'.strval($recRow),$recSignature['result'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('O'.strval($recRow),$recSignature['commodity'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('P'.strval($recRow),$recSignature['utility_name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $imageSignature = $recSignature['signature'];
                $imageSignature = str_replace('data:image/png;base64,', '', $imageSignature);
                $imageSignature = str_replace(' ', '+', $imageSignature);
                $imageName = $recSignature['confirmation_code'] . '_' . str_random(10).'.'.'png';
                $imageArrayToDelete[] = $imageName;
                $filecsv = fopen(public_path($folderName . $imageName), 'w');
                fputs($filecsv, base64_decode($imageSignature));
                fclose($filecsv);
                $drawing = new Drawing();
                //$drawing->setName('Logo');
                //$drawing->setDescription('This is my logo');
                $drawing->setPath(public_path($folderName . $imageName));
                $drawing->setCoordinates('L'.strval($recRow));
                $drawing->setResizeProportional(true);
                $drawing->setHeight(50);
                //$drawing->setWidth(200);
                $drawing->setWorksheet($spreadsheet->getActiveSheet());
            }
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($fileName);
            foreach ($imageArrayToDelete as $imageToDelete) {  // can't delete images until saved
                unlink(public_path('tmp/' . $imageToDelete));
            }
        } catch (\Exception $e) {
            // TODO: Handle
        }

        // TODO: Return a result
    }
    
}
