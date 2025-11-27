<?php

namespace App\Console\Commands\Greenlight;

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
    protected $signature = 'greenlight:reports:daily-calls:email {--mode=} {--env=} {--no-email} {--show-sql} {--start-date=} {--end-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily calls report for Greenlight Energy';

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
     * Application mode. Can drive things such as what distro list is used.
     * 
     * @var string
     */
    protected $mode = '';

    /**
     * Application env. Can be used to switch between environment specific data, such as which brand ID is used in queries.
     */
    protected $env = '';

    /**
     * Report distribution list
     * 
     * @var array
     */
    protected $distroList = [
        'live' => ['johnm@gogreenlightenergy.com', 'ricky@gogreenlightenergy.com', 'abacich@gogreenlightenergy.com', 'dxc_autoemails@tpv.com'],
        'test' => ['engineering@tpv.com']
    ];

    /**
     * Brand.
     * 
     * @var string
     */
    protected $brand = 'Greenlight Energy';

    /**
     * Brand ID.
     * 
     * @var string
     */
    protected $brandId = [
        'prod' => '6fb31120-1255-4a48-96b4-0f34be99c658',
        'stage' => '6fb31120-1255-4a48-96b4-0f34be99c658'
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

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date:   ' . $this->endDate);

        $this->info('Brand:      (' . $this->brand . ') ' . $this->brandId[$this->env] . "\n");

        // Build file names, using that start date for the file/reporting dates
        $fileName = sprintf('%s Daily Calls %s-%s.csv', $this->brand, $this->startDate->format('Y_m_d'), $this->endDate->format('Y_m_d'));

        // Data layout/File header for report
        $csvHeader = [
            'SUB_ACCOUNT_NO',
            'CDR_ANI',
            'HEAP',
            'TRANSACTION_ID',
            'DATE_TIME',
            'DURATION',
            'REP_ID',
            'BTN',
            'COMPLETE',
            'PASSED_REVIEW',
            'CANCELED_REASON',
            'COMMENTS'
        ];

        $csv = array(); // Houses formatted report data

        $this->info('Retrieving data...');

        $data = $this->getData();

        $this->info(count($data) . ' Record(s) found.');

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

            // Determine if IVR is complete. Complete is Y when either
            // 1) IVR is completed and waiting for review, or
            // 2) IVR is completed and reviewed
            $complete = 'N';
            if(
                $r->interaction_type == 'ivr_review' || 
                ($r->interaction_type == 'ivr_script' && $r->result == 'No Sale' && $r->disposition_reason == 'Pending Review')
            ) {
                $complete = 'Y';
            }

            // Determine if IVR passed review. Use empty string for default value, and
            // if $complete is Y, replace with Y or N depending on TPV result
            $passedReview = '';
            if($complete == 'Y') {
                $passedReview = ($r->result == 'Sale' ? 'Y' : 'N');
            }

            // Parse custom field data
            $heap = "No";
                
            $customFields = [];

            if($r->custom_fields) {
                $customFields = json_decode($r->custom_fields);
            }

            foreach($customFields AS $customField) {

                switch(strtolower($customField->output_name)) {

                    case 'heap':
                        $heap = $customField->value;
                        break;
                }
            }

            $row = [
                'sub_account_no' => $r->sub_account_no,
                'cdr_ani' => $r->cdr_ani,
                'heap' => $heap,
                'transaction_id' => $r->transaction_id,
                'date_time' => $r->date_time,
                'duration' => round($r->duration * 60),
                'rep_id' => $r->rep_id,
                'btn' => $r->btn,
                'complete' => $complete,
                'passed_review' => $passedReview,
                'canceled_reason' => ($r->interaction_type == 'ivr_review'? $r->disposition_reason : ''),
                'comment' => $comments
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
                    'to' => $this->distroList[$this->mode],
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
            'vendors.grp_id AS sub_account_no',
            'stats_product.ani AS cdr_ani',
            'stats_product.confirmation_code AS transaction_id',
            'stats_product.interaction_created_at AS date_time',
            'stats_product.interaction_time AS duration',
            'stats_product.interaction_type',
            'stats_product.sales_agent_rep_id AS rep_id',
            'stats_product.btn',
            'stats_product.result',
            'stats_product.disposition_reason',
            'stats_product.custom_fields',
            'interactions.notes AS interaction_notes'
        )
        ->leftJoin('vendors', function($join) { // In stats product, venodr_grp_id is numeric. Until that's fixed, pull the grp_id value directly from vendors table.
            $join->on('stats_product.brand_id', 'vendors.brand_id');
            $join->on('stats_product.vendor_id', 'vendors.vendor_id');
        })
        ->leftJoin('interactions', 'stats_product.interaction_id', '=', 'interactions.id')
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
        return $this-> brand . ' - Daily Calls Report' . (config('app.env') != 'production' ? ' (' . config('app.env') . ') ' : '') . ' ' . Carbon::now("America/Chicago")->format('Y-m-d');
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
}
