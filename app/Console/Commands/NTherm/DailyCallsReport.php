<?php

namespace App\Console\Commands\NTherm;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use App\Models\StatsProduct;
use App\Traits\ExportableTrait;
use App\Traits\DeliverableTrait;

class DailyCallsReport extends Command
{
    use ExportableTrait;
    use DeliverableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ntherm:reports:daily-calls:email {--mode=} {--env=} {--no-email} {--show-sql} {--start-date=} {--end-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily calls report for Ntherm LLC';

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
     * Brand.
     * 
     * @var string
     */
    protected $brand = 'NTherm LLC';

    /**
     * Brand ID.
     * 
     * @var string
     */
    protected $brandId = [
        'prod' => 'b10a65f6-bcb2-4f20-963f-2d38a66acde4',
        'stage' => 'x' // At the time this job was written, brand was only set up in product. Use 'x' here to ensure no data can be pulled from staging DB.
    ];

    /**
     * Report distribution list
     * 
     * @var array
     */
    protected $distroList = [
        'live' => ['kbeattie@ntherm.com', 'abush@ntherm.com', 'RJennissen@ntherm.com', 'dxc_autoemails@tpv.com'],
        'test' => ['engineering@tpv.com']
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

        // Data layout/File header for TXT enrollment file
        $csvHeader = [
            'Date',
            'Account Number',
            'Rate Code',
            'Name',
            'Street',
            'City',
            'State',
            'Zip',
            'Email',
            'Phone Number',
            'Status',
            'Rejection Reason',
            'Comments',
            'Confirmation Number',
            'Sub Account No',
            'Rep ID',
            'R/C',
            'Contact Person',
            'Title',
            'Tax Exempt'
        ];

        $csv = array(); // Houses formatted enrollment file data

        $this->info('Retrieving data...');

        $data = $this->getData();

        $this->info(count($data) . ' Record(s) found.');
        
        $this->info("Formatting data for report file...");

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

            // Determine status. Status is:
            // 1) Y if IVR is completed, reviewed and good saled
            // 2) N if IVR is completed, reviewed and no saled
            // 3) I otherwise
            $status = 'I';
            if($r->interaction_type == 'ivr_review' && $r->result == 'Sale') {
                $status = 'Y';
            } else if ($r->interaction_type == 'ivr_review' && $r->result == 'Sale') {
                $status = 'N';
            }

            $row = [
                'Date' => $r->tpv_date,
                'Account Number' => $r->account_number,
                'Rate Code' => $r->rate_code,
                'Name' => $r->name,
                'Street' => $r->street,
                'City' => $r->city,
                'State' => $r->state,
                'Zip' => $r->zip,
                'Email' => $r->email,
                'Phone Number' => $r->phone,
                'Status' => $status,
                'Rejection Reason' => ($r->interaction_type == 'ivr_review'? $r->disposition_reason : ''),
                'Comments' => $comments,
                'Confirmation Number' => $r->confirmation_number,
                'Sub Account No' => $r->sub_account_no,
                'Rep ID' => $r->rep_id,
                'R/C' => $r->r_c,
                'Contact Person' => '',
                'Title' => '',
                'Tax Exempt' => '',
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
            'stats_product.interaction_created_at AS tpv_date',
            'stats_product.account_number1 AS account_number',
            'stats_product.rate_program_code AS rate_code',
            DB::raw('CONCAT(stats_product.bill_first_name, " ", stats_product.bill_last_name) AS name'),
            'stats_product.billing_address1 AS street',
            'stats_product.billing_city AS city',
            'stats_product.billing_state AS state',
            'stats_product.billing_zip AS zip',
            'stats_product.email_address AS email_address',
            'stats_product.btn AS phone_number',
            'stats_product.confirmation_code AS confirmation_number',
            'stats_product.vendor_grp_id AS sub_account_no',
            'stats_product.sales_agent_rep_id AS rep_id',
            DB::raw('IF(stats_product.market = "Commercial", "C", "R") AS r_c'),
            'stats_product.result',
            'stats_product.disposition_reason',
            'interactions.notes AS interaction_notes'
        )
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
        return $this-> brand . ' - Daily Calls Report' . (env('APP_ENV') != 'production' ? ' (' . env('APP_ENV') . ') ' : ' ' . Carbon::now("America/Chicago")->format('Y-m-d'));
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
