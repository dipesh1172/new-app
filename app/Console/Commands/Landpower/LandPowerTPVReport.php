<?php

namespace App\Console\Commands\Landpower;

use App\Console\Commands\Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\StatsProduct;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class LandPowerTPVReport extends Command
{
    public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landpower:tpv-report {--mode=} {--env=} {--start-date=} {--end-date=} {--monthly} {--noemail} {--distro=*}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Answernet LandPower TPV Report ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates LandPower Report';

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
            $fileName = sprintf('Answernet_LP_Monthly_TPV_Report-%s.xlsx', $this->startDate->isoFormat('MMM'));
        } else {
            $fileName = sprintf('Answernet_LP_Daily_TPV_Report-%s.xlsx', $this->startDate->format('Y_m_d'));
        }

        $this->info("Retrieving TPV data...");
        $data = StatsProduct::select(
            DB::raw('stats_product.interaction_created_at AS TPVTimeDate'),
            'stats_product.confirmation_code as TPVConfirmation',
            DB::raw('stats_product.account_number1 AS UtilityAccountNumber'),
            DB::raw('CONCAT(stats_product.bill_first_name,trim(concat(" ",stats_product.bill_middle_name))," ",stats_product.bill_last_name) AS CustomerName'),
            DB::raw('stats_product.btn AS CustomerPhone'),
            DB::raw('TRIM(  CONCAT(stats_product.billing_address1, " ", stats_product.billing_address2) ) AS BillingAddress'),
            DB::raw('stats_product.billing_city AS BillingCity'),
            DB::raw('stats_product.billing_state AS BillingState'),
            DB::raw('stats_product.billing_zip AS BillingZip'),
            DB::raw('TRIM( CONCAT(stats_product.service_address1, " ", stats_product.service_address2)) AS ServiceAddress'),
            DB::raw('stats_product.service_city AS ServiceCity'),
            DB::raw('stats_product.service_state AS ServiceState'),
            DB::raw('stats_product.service_zip AS ServiceZip'),
            DB::raw('stats_product.language AS Language'),
            DB::raw('case (stats_product.commodity) 	when "electric" then "electric" else "gas" end AS Product'),
            DB::raw('stats_product.custom_fields AS custom_fields'),
            DB::raw('stats_product.sales_agent_name AS SalesAgent'),
            DB::raw('stats_product.result AS TPVOutcome'),
            DB::raw('stats_product.disposition_reason AS TPVFailureReason'),
            DB::raw('stats_product.tpv_agent_name AS TPVAgentName'),
            DB::raw('stats_product.tpv_agent_label AS TPVAgentId'),
            DB::raw('stats_product.interaction_time AS CallDuration'),
            DB::raw('stats_product.recording AS AudioFile')
        )->whereNotNull(
            'stats_product.interaction_time'
        )->where(
            'stats_product.interaction_time',
            '>',
            0
        )->where(
            'stats_product.brand_id',
            $this->brandId[$this->env]
        )->whereDate(
            'stats_product.interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.interaction_created_at',
            '<=',
            $this->endDate
        )->orderBy(
            'stats_product.interaction_created_at',
            'asc'
        )->get();

        // If no records, email client and exit early
        if(count($data) === 0) {
            $this->info('0 records. Sending results email...');

            $message = 'There were no records to send for LandPowerTPV Report' . $this->startDate->format('m-d-Y') . '.';
            $this->sendEmail($message, $this->distroList);

            return 0;
        }
        
        $this->info(count($data) . ' Record(s) found.');
        
        // Format and populate data CSV file
        $this->info('Formatting data...');
        $folderName = 'tmp/landpower/'
            . 'tpvreport/'
            . $this->startDate->year 
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT)
            . strval(time()) . '/'; // folder name

        // Create download directory if it doesn't exist
        if (!file_exists(public_path($folderName))) {
            mkdir(public_path($folderName), 0777, true);
        }

        $excelData = [];
        foreach($data as $r) {
            $row = [
                'TPV Time Date' => $r->TPVTimeDate,
                'TPV Confirmation' => $r->TPVConfirmation,
                'Utility Account Number' => $this->getCustomField($r->custom_fields,'account_number_'.strtolower($r->Product)), //
                'Customer Name' => $r->CustomerName,
                'Customer Phone' => $this->formatUSAPhoneNumber($r->CustomerPhone),
                'Billing Address' => $r->BillingAddress ?? $r->ServiceAddress,
                'Billing City' => $r->BillingCity ?? $r->ServiceCity,
                'Billing State' => $r->BillingState ?? $r->ServiceState,
                'Billing Zip' => $r->BillingZip ?? $r->ServiceZip,
                'Service Address' => $r->ServiceAddress ?? $r->BillingAddress,
                'Service City' => $r->ServiceCity ?? $r->BillingCity,
                'Service State' => $r->ServiceState ?? $r->BillingState,
                'Service Zip' => $r->ServiceZip ?? $r->BillingZip,
                'Language' => $r->Language,
                'Utility Name' => $this->getCustomField($r->custom_fields,'utility_name_'.strtolower($r->Product)),
                'Product (Gas/Electric)' => strtoupper(substr($r->Product,0,4)),
                'Program' => $this->getCustomField($r->custom_fields,'contract_plan_name_'.strtolower($r->Product)),
                'Rate Offer' => preg_replace('/[^0-9,.]/', "",  $this->getCustomField($r->custom_fields,'product_rate_amount_'.strtolower($r->Product))),
                'Rate Currency' =>  (strpos($this->getCustomField($r->custom_fields,'product_rate_amount_'.strtolower($r->Product)),"$") ? "dollars" : "cents"),
                'Brand' =>  $this->getCustomField($r->custom_fields,'brand_name_'.strtolower($r->Product)),
                'Sales Agent' => $r->SalesAgent,
                'TPV Outcome' => ($r->TPVOutcome === "Sale" ? "Verified" : "Non-Verified" ),
                'TPV Failure Reason' => ($r->TPVOutcome === "Sale" ? "Verified" : $r->TPVFailureReason ),
                'TPV Agent Name' => $r->TPVAgentName,
                'TPV Agent Id' => $r->TPVAgentId,
                'CallDuration' => ( intval($r->CallDuration) === 0 ? "0" : intval($r->CallDuration) ),
                'AudioFile' => $r->AudioFile,
            ];

            $excelData[] = $row;
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
        $subject = $this->jobName . ' '
                    .$this->startDate->format('m-d-Y') . ' thru '
                    .$this->endDate->format('m-d-Y');

        if ($this->mode === 'test') {
            $subject = '(TEST) ' . $subject;
        }

        $data = [
            'subject' => '',
            'content' => $message
        ];

        for ($i = 0; $i < count($email_address); ++$i) {
            $status = 'Email to ' 
                . $email_address[$i]
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
                SendTeamMessage('monitoring', "Error sending email for LandPowerTPV report" . $e->getMessage());
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
    protected function writeXlsFile($data, $fileName,$folderName)
    {
        try {

            $headers = array_keys($data[0]);
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($headers, null, 'A1');
            $recRow = 1;
            $sheet->getStyle('A1:AA1')->getFont()->setBold(true);

            foreach ($data as $dataRow) {

                $recRow = $recRow+1;

                $sheet->setCellValueExplicit('A'.strval($recRow),  \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($dataRow['TPV Time Date']),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->getStyle('A'.strval($recRow))->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_XLSX22);
                $sheet->setCellValueExplicit('B'.strval($recRow),$dataRow['TPV Confirmation'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C'.strval($recRow),$dataRow['Utility Account Number'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D'.strval($recRow),$dataRow['Customer Name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.strval($recRow),$dataRow['Customer Phone'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('F'.strval($recRow),$dataRow['Billing Address'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('G'.strval($recRow),$dataRow['Billing City'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H'.strval($recRow),$dataRow['Billing State'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('I'.strval($recRow),$dataRow['Billing Zip'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('J'.strval($recRow),$dataRow['Service Address'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('K'.strval($recRow),$dataRow['Service City'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('L'.strval($recRow),$dataRow['Service State'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('M'.strval($recRow),$dataRow['Service Zip'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('N'.strval($recRow),$dataRow['Language'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('O'.strval($recRow),$dataRow['Utility Name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('P'.strval($recRow),$dataRow['Product (Gas/Electric)'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('Q'.strval($recRow),$dataRow['Program'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('R'.strval($recRow),$dataRow['Rate Offer'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('S'.strval($recRow),$dataRow['Rate Currency'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('T'.strval($recRow),$dataRow['Brand'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('U'.strval($recRow),$dataRow['Sales Agent'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('V'.strval($recRow),$dataRow['TPV Outcome'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('W'.strval($recRow),$dataRow['TPV Failure Reason'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('X'.strval($recRow),$dataRow['TPV Agent Name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('Y'.strval($recRow),$dataRow['TPV Agent Id'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('Z'.strval($recRow),$dataRow['CallDuration'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('AA'.strval($recRow),$dataRow['AudioFile'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }

            // Loop through columns B to M and set auto size
            foreach (range('A1','Z99') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($fileName);

        } catch (\Exception $e) {

            $this->info("Error LandPowerTPV->writeXLSfile");
            $this->info($e);
            SendTeamMessage('monitoring', "Error creating $fileName" . $e->getMessage());
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
