<?php

namespace App\Console\Commands\Landpower;

use App\Console\Commands\Exception;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class LandPowerSLAReport extends Command
{
    public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landpower:sla-report {--mode=} {--env=} {--start-date=} {--end-date=} {--monthly} {--distro=*} {--noemail}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Answernet LandPower Service Level Report ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates LandPower Service Level Report';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = [
        'prod' => '70d88b23-650e-4d36-813d-575e894f2412',
        'stage' => 'c74a626a-a676-4ac4-a7e4-6e270e1719c8'
    ];

    /**
     * Distribution list
     *
     * @var array
     */

    protected $distroList = [];

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
     * env mode: 'prod' or 'stage'.
     *
     * @var string
     */
    protected $env = 'prod';

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
        $this->setMode();
        $this->setEnv();
        $this->setDateRange();

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date:   ' . $this->endDate);
        $this->info('Mode:   ' . $this->mode);
        $this->info('Env:   ' . $this->env);


        $this->distroList = $this->option('distro');

        if (count($this->distroList) === 0 ){
            $errMessage="Error LandPowerSLA Report. Distro List is empty";
            $this->info($errMessage);
            SendTeamMessage('monitoring', $errMessage);
            return 0;
        }

        // Build file name
        if ($this->option('monthly')) {
            $fileName = sprintf('LandPower_Monthly_Service_Level_Report-%s.xlsx', $this->startDate->isoFormat('MMM'));
        } else {
            $fileName = sprintf('LandPower_Daily_Service_Level_Report-%s.xlsx', $this->startDate->format('Y_m_d'));
        }

        $this->info("Retrieving data...");

        $API_URL = "https://apiv2.tpvhub.com/api/reporting/sla"
            . "?startdate=".$this->startDate->toDateString()
            . "&enddate=".$this->endDate->toDateString();

        try {
            $httpClient = new HttpClient(['verify' => false]);
            $response = $httpClient->get($API_URL);
            $rawData= json_decode($response->getBody()->getContents());

        } catch (\Exception $e) {
            $msg = "Error loading the data from: " . $API_URL  . $e->getMessage();
            info($msg);
            SendTeamMessage('monitoring', "[LandPowerSLA] " . $msg);

            exit -1;
        }

        $excelData = [];
        foreach ($rawData->data->brands as $brandName => $data) {

            if (stripos($brandName, "landpower" ) !== false) {

                foreach ($data as $callDate => $ctime) {

                    foreach ($ctime as $interval => $intervalData) {
                        $row = [
                            'Date' => $intervalData->date,
                            'Interval' => $intervalData->interval,
                            'Calls Available' => $intervalData->callsAvailable,
                            'Calls Handled' => $intervalData->callsHandled,
                            'Abandoned Calls' => $intervalData->abdnCalls,
                            'Abandoned %' => $intervalData->abdnPct,
                            'Answer Time' => $intervalData->totalAnswerTime,
                            'Avg Answer Time' => $intervalData->avgAnswerTime,
                            'Longest Hold Time' => $intervalData->longestHoldTime,
                            'Service Level (%)' => $intervalData->serviceLevel,
                            'Service Level Split' => $intervalData->serviceLevelSplit,
                        ];

                        $excelData[] = $row;
                    }
                }
            }
        }

        // If no records, email client and exit early
        if (count($excelData) === 0) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for '.$fileName.'.';
            $this->sendEmail($message, $this->distroList);

            return 0;
        }
        
        $this->info(count($excelData) . ' Record(s) found.');
        
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'tmp/landpower/'
            . 'slareport/'
            . $this->startDate->year 
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
            . strval(time()) . '/'; // folder name

        // Create download directory if it doesn't exist
        if (!file_exists(public_path($folderName))) {
            mkdir(public_path($folderName), 0777, true);
        }

        // Create the XLS file
        $this->info('Writing data to Xls file...');
        $this->writeXlsFile($excelData, public_path($folderName . $fileName),$folderName);

        // Email the file as an attachment
        if (!$this->option('noemail')) {
            $attachments = [public_path($folderName . $fileName)];   // only send enrollment file
            $this->info("Emailing file...");
            $this->sendEmail('Attached is the file for Answernet Landpower TPV report ' .
                $this->startDate->format('m-d-Y') . ' thru ' .
                $this->endDate->format('m-d-Y') . '.',
                $this->distroList, $attachments
            );
        }

        // Delete tmp files and folders
        $this->removeDir(public_path($folderName));
    }

    protected function removeDir($dirname)
    {
        if (is_dir($dirname)) {
            $dir = new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS);
            foreach (new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST) as $object) {
                if ($object->isFile()) {
                    unlink($object);
                } elseif($object->isDir()) {
                    rmdir($object);
                } else {
                    throw new Exception('Unknown object type: '. $object->getFileName());
                }
            }
            rmdir($dirname); 
        } else {
            throw new Exception('This is not a directory');
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
        $subject = $this->jobName 
            . ' '
            . $this->startDate->format('m-d-Y') . ' thru '
            . $this->endDate->format('m-d-Y');

        if ($this->mode === 'test') {
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
            } catch (\Exception $e) {
                $status .= 'Error! The reason reported is: ' . $e;
                $uploadStatus[] = $status;
                SendTeamMessage('monitoring', "Error sending email for LandPowerSLA" . $e->getMessage());
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
    private function getCustomField($json, $fieldName)
    {
        try {

            if ($json !== null ){

                $customFieldsArray = json_decode($json, true);
                foreach ($customFieldsArray as $customField) {
                    if ($customField['output_name'] === $fieldName){
                        return $customField['value'];
                    }
                }
            }

            $this->info("customField: " . $fieldName . " not found. LandPowerTPV->getCustomField. " . $json);

        } catch (\Exception $e) {

            $this->info($e);
            // SendTeamMessage('monitoring', "Error looking for a custom field $fieldName in $json" . $e->getMessage());
            return "";
        }
    }

    /**
     * Writes an Excel file from a data array. Data array should
     * use named keys as the keys are used for the header row.
     */
    protected function writeXlsFile($data, $fileName, $folderName)
    {
        try {

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = array_keys($data[0]);
            $sheet->fromArray($headers, null, 'A1');
            $recRow = 1;

            $sheet->getStyle('A1:K99')->getFont()->setBold(true);

            foreach ($data as $dataRow) {
                $recRow = $recRow+1;

                $sheet->setCellValueExplicit('a'.strval($recRow),$dataRow['Date'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('b'.strval($recRow),$dataRow['Interval'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('c'.strval($recRow),$dataRow['Calls Available'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('d'.strval($recRow),$dataRow['Calls Handled'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('e'.strval($recRow),$dataRow['Abandoned Calls'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('f'.strval($recRow),$dataRow['Abandoned %'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('g'.strval($recRow),$dataRow['Answer Time'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('h'.strval($recRow),$dataRow['Avg Answer Time'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('i'.strval($recRow),$dataRow['Longest Hold Time'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('j'.strval($recRow),$dataRow['Service Level (%)'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('k'.strval($recRow),$dataRow['Service Level Split'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            }

            // Loop through columns B to M and set auto size
            foreach (range('A1','K9') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($fileName);

        } catch (\Exception $e) {

            $this->info("Error LandPowerSLA->writeXLSfile");
            $this->info($e);
            SendTeamMessage('monitoring', "Error creating $fileName" . $e->getMessage());

            exit -1;
        }
    }

    /**
     * Set date range
     */
    private function setDateRange()
    {
        // Set default report dates.
        $this->startDate = Carbon::yesterday("America/Chicago");
        $this->endDate = Carbon::yesterday("America/Chicago")->endOfDay();

        // Check for custom date range. Custom date range will only be used if both start and end dates are present.
        if ($this->option('start-date') && $this->option('end-date')) {
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));

            if ($this->startDate > $this->endDate) {
                $tmp = $this->startDate;
                $this->startDate = $this->endDate;
                $this->endDate = $tmp;
            }
            $this->info('Using custom date range.');
        }

        // Check for monthly parameter.
        if ($this->option('monthly')) {
            // Get the first day of the previous month
            $this->startDate = Carbon::now()->subMonthsNoOverflow()->startOfMonth();

            // Get the last day of the previous month
            $this->endDate = Carbon::now()->subMonthsNoOverflow()->endOfMonth()->endOfDay();

            $this->info('Using previous month.');
        }
    }

    /**
     * Set environment job is running against
     */
    private function setEnv()
    {
        $this->env = 'prod';

        if ($this->option('env')) {
            if (
                strtolower($this->option('env')) == 'prod' ||
                strtolower($this->option('env')) == 'stage'
            ) {
                $this->env = strtolower($this->option('env'));
            } else {
                $this->error('Invalid --env value: ' . $this->option('env') . '. "prod" or "stage" expected.');
                exit -1;
            }
        }
    }

    /**
     * Set the application mode
     */
    private function setMode()
    {
        $this->mode = 'live';

        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            } else {
                $this->error('Unrecognized --mode: ' . $this->option('mode'));
                return -1;
            }
        }
    }

    private function formatUSAPhoneNumber($phoneNumber)
    {
        // Remove non-digit characters
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Determine the length of the cleaned number
        $length = strlen($cleanNumber);

        // Handle different lengths of input
        if ($length > 10) {
            // International format
            $countryCode = substr($cleanNumber, 0, $length - 10);
            $formattedNumber = /*'+'. $countryCode. ' */'('. substr($cleanNumber, $length - 10, 3). ') '. substr($cleanNumber, $length - 7, 3). '-'. substr($cleanNumber, $length - 4, 4);
        } elseif ($length == 10) {
            // Standard USA format
            $formattedNumber = '('. substr($cleanNumber, 0, 3). ') '. substr($cleanNumber, 3, 3). '-'. substr($cleanNumber, 6, 4);
        } elseif ($length == 7) {
            // Shorter format
            $formattedNumber = substr($cleanNumber, 0, 3). '-'. substr($cleanNumber, 3, 4);
        } else {
            // Invalid input
            //$formattedNumber = "Invalid phone number";
            $formattedNumber = $phoneNumber; //Instead invalid, returning the same as the input
        }

        return $formattedNumber;
    }
}
