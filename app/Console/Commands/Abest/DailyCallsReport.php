<?php

namespace App\Console\Commands\Abest;

use App\Helpers\FtpHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;

use App\Models\StatsProduct;
use App\Traits\ExportableTrait;
use App\Traits\DeliverableTrait;

class DailyCallsReport extends Command
{
    use DeliverableTrait;
    use ExportableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'abest:reports:daily-calls:email {--mode=} {--env=} {--no-email} {--show-sql} {--start-date=} {--end-date=} {--vendor=} {--email-to=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily calls report for Abest Power & Gas LLC';

    /**
     * Report start date.
     * 
     * @var mixed
     */
    protected $startDate = null;

    /**
     * Report end date.
     * 
     * @var mixed
     */
    protected $endDate = null;

    /**
     * Application mode. 'live' or 'test'. Use to branch logic if something has to be ignored or done differently when running in test mode.
     * 
     * @var string
     */
    protected $mode = '';

    /**
     * Application env. Used to switch between environment specific data, such as which brand ID is used in queries.
     */
    protected $env = 'prod';

    /**
     * Report distribution list
     * 
     * @var array
     */
    protected $distroList = [];

    /**
     * Brand.
     * 
     * @var string
     */
    protected $brand = 'Abest Power & Gas LLC';

    /**
     * Vendor name. Populated if vendor option is used.
     * 
     * @var string
     */
    protected $vendorName = '';

    /**
     * FTP Settings
     *
     * @var array
     */
    protected $ftpSettings = null;

    /**
     * Brand ID.
     * 
     * @var string
     */
    protected $brandId = [
        'prod' => 'd52f25c9-6583-43e0-8f5b-4d865a66dab2',
        'stage' => 'd52f25c9-6583-43e0-8f5b-4d865a66dab2'
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
        $this->setMode();
        $this->setEnv();
        $this->setDateRange();
        $this->setDistroList();

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date:   ' . $this->endDate);

        $this->info('Brand:      (' . $this->brand . ') ' . $this->brandId[$this->env] . "\n");

        // Get FTP settings
        $this->info('Getting FTP settings...');
        $this->ftpSettings = $this->getFtpSettings();

        if(!$this->ftpSettings) {
            $this->error('Unable to retrieve FTP settings. Exiting...');
            exit -1;
        }

        // Build file names, using that start date for the file/reporting dates
        $fileName = sprintf('%s Daily Calls %s-%s.csv', $this->brand, $this->startDate->format('Y_m_d'), $this->endDate->format('Y_m_d'));

        // Data layout/File header for report
        $csvHeader = [
            'SUB_ACCOUNT_NO',
            'CDR_ANI',
            'DATE_TIME',
            'DURATION',
            'REP_ID',
            'BTN',
            'COMPLETE',
            'PASSED_REVIEW',
            'CANCELED_REASON',
            'COMMENTS',
            'internal Rate Plan ID',
            '# of Accounts',
            'First Name',
            'Middle Name',
            'Last Name',
            'Buiness Name',
            'Language Spoken',
            'Billing Address',
            'Billing Address Line 2',
            'Billing City',
            'Billing State',
            'Billing Zip Code',
            'Contact Method',
            'Email',
            'Phone',
            'Mobile Phone',
            'Electronic Signature',
            'Broker',
            'Utility Id',
            'Property Type',
            'Commodity',
            'Utility Acct Number',
            'Name Key',
            'Service Address',
            'Service Address Line 2',
            'Service Zip Code',
            'County',
            'Tax Rate',
            'TPV Num',
            'Scheduled Push Date',
            'Rate Class',
            'Custom Rate',
            'Contract Term',
            'Contract End Date',
            'Rate Class 2',
            'Custom Rate 2',
            'Contract Term 2',
            'Contract End Date 2',
            'Contract Mil',
            'Early Termination Fee',
            'Auto Renewal',
            'Reward Code'
        ];

        $csv = array(); // Houses formatted report data

        $this->info('Retrieving data...');

        $data = $this->getData();

        $this->info(count($data) . ' Record(s) found.');

        if($this->option('vendor') && count($data) > 0) {
            $this->vendorName = $data[0]->vendor_name;
        }

        // Format and populate data for report file.
        $this->info("Formatting data for Daily Calls Report...");

        foreach ($data as $r) {

            // Parse ivr_review comments, if any
            $comments = '';
            if(strtolower($r->interaction_type) == 'ivr_review') {
                try {
                    $comments = json_decode($r->interaction_notes);
                    $comments = $comments->reviewerNotes;
                } catch(\Exception $e) {
                    ; // Do nothing, and record a blank comment as a result.
                }
            }

            // Determine if IVR is complete. Complete is Y when IVR is completed and reviewed
            $complete = 'N';
            if($r->interaction_type == 'ivr_review') {
                $complete = 'Y';
            }

            // Determine if IVR passed review. Use 'I' for default value, and
            // if $complete is Y, replace with Y or N depending on TPV result
            $passedReview = 'I';
            if($complete == 'Y') {
                $passedReview = ($r->result == 'Sale' ? 'Y' : 'N');
            }

            $row = [
                'SUB_ACCOUNT_NO'  => $r->grp_id,
                'CDR_ANI'         => substr($r->ani, 2),
                'DATE_TIME'       => $r->interaction_created_at->format('Ymd H:i:s'),
                'DURATION'        => round($r->interaction_time * 60),
                'REP_ID'          => $r->sales_agent_rep_id,
                'BTN'             => substr($r->btn, 2),
                'COMPLETE'        => $complete,
                'PASSED_REVIEW'   => $passedReview,
                'CANCELED_REASON' => ($r->interaction_type == 'ivr_review'? $r->disposition_reason : ''),
                'COMMENTS'        => $comments,
                'internal Rate Plan ID' => $r->custom_data_1,
                '# of Accounts'   => '',
                'First Name'      => $r->bill_first_name,
                'Middle Name'     => $r->bill_middle_name,
                'Last Name'       => $r->bill_last_name,
                'Buiness Name'    => $r->company_name,
                'Language Spoken' => strtolower($r->language),
                'Billing Address'        => $r->billing_address1,
                'Billing Address Line 2' => $r->billing_address2,
                'Billing City'           => $r->billing_city,
                'Billing State'          => $r->billing_state,
                'Billing Zip Code'       => $r->billing_zip,
                'Contact Method' => '',
                'Email'          => '',
                'Phone'          => substr($r->btn, 2),
                'Mobile Phone'         => '',
                'Electronic Signature' => '',
                'Broker'               => '',
                'Utility Id'           => $r->product_utility_name,
                'Property Type'        => strtoupper($r->market),
                'Commodity'            => $r->commodity,
                'Utility Acct Number'  => $r->account_number1,
                'Name Key'             => $r->name_key,
                'Service Address'        => $r->service_address1,
                'Service Address Line 2' => $r->service_address2,
                'Service Zip Code'       => $r->service_zip,
                'County'                 => $r->service_county,
                'Tax Rate' => '',
                'TPV Num'  => $r->confirmation_code,
                'Scheduled Push Date' => '',
                'Rate Class'        => '',
                'Custom Rate'       => '',
                'Contract Term'     => '',
                'Contract End Date' => '',
                'Rate Class 2'      => $r->rate_program_code,
                'Custom Rate 2'     => '',
                'Contract Term 2'   => '',
                'Contract End Date 2' => '',
                'Contract Mil'      => '',
                'Early Termination Fee' => '',
                'Auto Renewal'      => '',
                'Reward Code'       => ''
            ];

            array_push($csv, $row);
        }

        // Write CSV file
        $this->info('Writing CSV file...');

        try {
            $this->writeCsvFile(public_path('tmp/' . $fileName), $csv, $csvHeader);
        } catch(\Exception $e) {
            $this->error('Write File Exception: ' . $e->getMessage());
            exit -1;
        }

        //ftp report
        if (file_exists(public_path('tmp/' . $fileName))) {
            try {
                $this->info('Uploading file...');
                $ftpResult = $this->ftpWithCurl($fileName, public_path('tmp/' . $fileName));
                if (!$ftpResult) {
                    $this->error('FTP upload failed!');
                }
            } catch (\Exception $e) {
                $this->error('Email exception:');
                $this->error($e->getMessage());
            }
        }

        // Email the files
        if (!$this->option('no-email')) {

            $message = 'Attached is the Daily Calls Report for ' . $this->startDate->format('m-d-Y');
            if ($this->startDate->format('Ymd') != $this->endDate->format('Ymd')) {
                $message .= ' through ' . $this->endDate->format('m-d-Y');
            }
            $message .= '.';

            try {
                $this->info('Sending e-mail notification with file attachment...');

                $this->sendGenericEmail([
                    'to' => $this->distroList,
                    'subject' => $this->getEmailSubject(),
                    'body' => $message,
                    'attachments' => [public_path('tmp/' . $fileName)]
                ]);
            } catch (\Exception $e) {
                $this->error('Email exception:');
                $this->error($e->getMessage());
            }

            unlink(public_path('tmp/' . $fileName));
        }
    }

    /**
     * Retrieve TPV data from database
     */
    private function getData() 
    {
        $data = StatsProduct::select(
            'vendors.grp_id',
            'stats_product.ani',
            'stats_product.interaction_created_at',
            'stats_product.confirmation_code',
            'stats_product.interaction_time',
            'stats_product.sales_agent_rep_id',
            'stats_product.btn',
            'stats_product.disposition_reason',
            'rates.custom_data_1',
            'stats_product.bill_first_name',
            'stats_product.bill_middle_name',
            'stats_product.bill_last_name',
            'stats_product.company_name',
            'stats_product.language',
            'stats_product.billing_address1',
            'stats_product.billing_address2',
            'stats_product.billing_city',
            'stats_product.billing_state',
            'stats_product.billing_zip',
            'stats_product.email_address',
            'stats_product.product_utility_name',
            'stats_product.market',
            'stats_product.commodity',
            'stats_product.account_number1',
            'stats_product.name_key',
            'stats_product.service_address1',
            'stats_product.service_address2',
            'stats_product.service_city',
            'stats_product.service_state',
            'stats_product.service_zip',
            'stats_product.service_county',
            'stats_product.confirmation_code',
            'stats_product.rate_program_code',
            'stats_product.interaction_type',
            'stats_product.result',
            'interactions.notes AS interaction_notes',
            'stats_product.vendor_name'
        )
        ->leftJoin('vendors', function($join) { // In stats product, venodr_grp_id is numeric. Until that's fixed, pull the grp_id value directly from vendors table.
            $join->on('stats_product.brand_id', 'vendors.brand_id');
            $join->on('stats_product.vendor_id', 'vendors.vendor_id');
        })
        ->leftJoin('interactions', 'stats_product.interaction_id', '=', 'interactions.id')
        ->leftJoin('rates', 'stats_product.rate_id', 'rates.id')
        ->whereDate(
            'stats_product.interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.interaction_created_at',
            '<=',
            $this->endDate
        )->where(
            'stats_product.brand_id',
            $this->brandId[$this->env]
        )->where(
            function($query) {

                $query->where(

                    function($query) { // Completed and reviewed IVRs

                        $query->where(
                            'interaction_type',
                            'ivr_review'
                        )->whereIn(
                            'result',
                            ['Sale', 'No Sale']
                        );
                    }
                )->orWhere(

                    function($query) { // In-progress/Incomplete IVRs

                        $query->where(
                            'interaction_type',
                            'ivr_script'
                        )->where(
                            'result',
                            'No Sale'
                        )->where(
                            'disposition_reason',
                            'Incomplete IVR'
                        );
                    }

                )->orWhere(

                    function($query) { // Completed IVRs waiting on to be reviewed

                        $query->where(
                            'interaction_type',
                            'ivr_script'
                        )->where(
                            'result',
                            'No Sale'
                        )->where(
                            'disposition_reason',
                            'Pending Review'
                        );
                    }

                );
            }
        )->orderBy(
            'interaction_created_at'
        );

        if($this->option('vendor')) {
            $data = $data->where(
                'vendor_grp_id',
                $this->option('vendor')
            );
        }

        // Display SQL query.        
        if ($this->option('show-sql')) {
            $queryStr = str_replace(array('?'), array('\'%s\''), $data->toSql());
            $queryStr = vsprintf($queryStr, $data->getBindings());

            $this->info("");
            $this->info('QUERY:');
            $this->info($queryStr);
            $this->info("");
        }
        
        $data = $data->get();

        return $data;
    }

    /**
     * Creates and returns an email subject line string
     */
    private function getEmailSubject() 
    {
        return $this-> brand . ' - Daily Calls Report' . (!empty($this->vendorName) ? ' (' . $this->vendorName . ')' : '')
        . (config('app.env') != 'production' ? ' (' . config('app.env') . ') ' : '') 
        . ' ' . Carbon::now("America/Chicago")->format('Y-m-d');
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

    /**
     * Set the market
     */
    private function setDistroList() 
    {
        $this->distroList = ['engineering@tpv.com']; // Default, in case distro list was not provided.
        
        if ($this->option('email-to')) {
            $this->distroList = $this->option('email-to');
        } 
    }

    /**
     * Retrieve FTP settings from provider_integrations table
     */
    private function getFtpSettings(): ?array {

        return FtpHelper::getSettings(
            $this->brandId[$this->env],
            43,
            1,
            (config('app.env') === 'production' ? 1 : 2)
        );
    }

    /**
     * @param string $name
     * @param string $path
     * @return bool
     */
    private function ftpWithCurl(string $name, string $path): bool
    {
        $authDetails = $this->ftpSettings['username'].':'.$this->ftpSettings['password'];
        $url = 'ftp://'.$this->ftpSettings['host'].'/TPV/'.$name;
        $ch = curl_init();
        $fp = fopen($path, 'r');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $authDetails);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($path));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_exec ($ch);
        $error_no = curl_errno($ch);
        curl_close ($ch);
        if ($error_no == 0) {
            return true;
        } else {
           return false;
        }
    }
}
