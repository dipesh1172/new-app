<?php

namespace App\Console\Commands\CleanChoiceEnergy;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use App\Models\StatsProduct;

class DailyCalls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanchoice:reports:daily-calls {--no-email} {--show-sql} {--start-date=} {--end-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily calls for CleanChoice Energy Shared Services, LLC';

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
     * Brand.
     * 
     * @var string
     */
    protected $brand = 'CleanChoice Energy';

    /**
     * Brand ID.
     * 
     * @var string
     */
    protected $brandId = '37cb9600-76e7-45cd-b24b-d0e2ccff8032';

    /**
     * Report distribution list
     * 
     * @var array
     */
    protected $distroList = [
        'TMsales@ethicalelectric.com', 'brian.cushin@cleanchoiceenergy.com', 'christina.kruse@cleanchoiceenergy.com', 
        'christopher.mudd@cleanchoiceenergy.com', 'jr.kenna@cleanchoiceenergy.com', 'chris.tobias@cleanchoiceenergy.com'
    ];

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

        $this->info('Start Date: ' . $this->startDate);
        $this->info('End Date:   ' . $this->endDate);
                
        $this->info('Brand:      (' . $this->brand . ') ' . $this->brandId . "\n");

        // Build file names, using that start date for the file/reporting dates
        $fileName = sprintf('%s Daily Calls %s-%s.csv', $this->brand, $this->startDate->format('Y_m_d'), $this->endDate->format('Y_m_d'));

        // Data layout/File header for TXT enrollment file
        $csvHeader = [
            'CUSTOMER_FIRST_NAME', 
            'CUSTOMER_LAST_NAME', 
            'CONTACT_FIRST_NAME', 
            'CONTACT_LAST_NAME', 
            'UTILITY', 
            'ACCOUNT_NUMBER', 
            'PLAN_NAME', 
            'PLAN_CODE', 
            'RATE_TYPE', 
            'RATE',
            'STATE',
            'SUB_ACCOUNT_NO',
            'CDR_ANI',
            'DATE_TIME',
            'DURATION',
            'REP_ID',
            'BTN',
            'VERIFIED',
            'CANCELLED_REASON',
            'CANCELLED_TEXT',
            'COMMENTS'
        ];

        $csv = array(); // Houses formatted enrollment file data

        $data = StatsProduct::select(
            'stats_product.bill_first_name AS CUSTOMER_FIRST_NAME',
            'stats_product.bill_last_name AS CUSTOMER_LAST_NAME',
            'stats_product.auth_first_name AS CONTACT_FIRST_NAME',
            'stats_product.auth_last_name AS CONTACT_LAST_NAME',
            'stats_product.product_utility_name AS UTILITY',
            'stats_product.account_number1 AS ACCOUNT_NUMBER',
            'stats_product.product_name AS PLAN_NAME',
            'stats_product.rate_program_code AS PLAN_CODE',
            DB::raw('CONCAT(stats_product.product_term, " ", stats_product.product_term_type, " ", stats_product.product_rate_type) AS RATE_TYPE'),
            'rates.rate_amount AS RATE',
            'stats_product.billing_state AS STATE',
            'stats_product.vendor_grp_id AS SUB_ACCOUNT_NO',
            'stats_product.ani AS CDR_ANI',
            'stats_product.interaction_created_at AS DATE_TIME',
            'stats_product.interaction_time AS DURATION',
            'stats_product.sales_agent_rep_id AS REP_ID',
            'stats_product.btn AS BTN',
            DB::raw('IF(stats_product.result = "Sale", "Y", "N") AS VERIFIED'),
            DB::raw('IF(stats_product.result = "Sale",  "", stats_product.disposition_label) AS CANCELLED_REASON'),
            DB::raw('IF(stats_product.result = "Sale",  "", stats_product.disposition_reason) AS CANCELLED_TEXT'),
            'interactions.notes AS COMMENTS',
            'stats_product.interaction_type'
        )
        ->leftJoin('rates', 'rates.id', '=', 'stats_product.rate_id')
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
            $this->brandId
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

        $this->info('Retrieving data...');
        $data = $data->get();

        $this->info(count($data) . ' Record(s) found.');

        // If no records found, quit program after sending an email blast
        if (count($data) == 0) {

            $message = 'There were no records to send for ' . $this->startDate->format('m-d-Y');
            if ($this->startDate->format('Ymd') != $this->endDate->format('Ymd')) {
                $message .= ' through ' . $this->endDate->format('m-d-Y');
            }
            $message .= '.';

            $this->sendEmail($message, $this->distroList);

            return 0;
        }

        // Format and populate data for enrollment file.
        $this->info("Formatting data for enrollment file...");

        foreach ($data as $r) {

            // Parse ivr_review comments, if any
            $comments = '';
            if(strtolower($r->interaction_type) == 'ivr_review') {
                try {
                    $comments = json_decode($r->COMMENTS);
                    $comments = $comments->reviewerNotes;
                } catch(\Exception $e) {
                    ; // Do nothing, and record a blank comment as a result.
                }
            }

            $row = [
                'CUSTOMER_FIRST_NAME' => $r->CUSTOMER_FIRST_NAME, 
                'CUSTOMER_LAST_NAME' => $r->CUSTOMER_LAST_NAME, 
                'CONTACT_FIRST_NAME' => $r->CONTACT_FIRST_NAME, 
                'CONTACT_LAST_NAME' => $r->CONTACT_LAST_NAME, 
                'UTILITY' => $r->UTILITY, 
                'ACCOUNT_NUMBER' => $r->ACCOUNT_NUMBER, 
                'PLAN_NAME' => $r->PLAN_NAME, 
                'PLAN_CODE' => $r->PLAN_CODE, 
                'RATE_TYPE' => $r->RATE_TYPE, 
                'RATE' => $r->RATE,
                'STATE' => $r->STATE,
                'SUB_ACCOUNT_NO' => $r->SUB_ACCOUNT_NO,
                'CDR_ANI' => $r->CDR_ANI,
                'DATE_TIME' => $r->DATE_TIME,
                'DURATION' => ($r->DURATION ? round($r->DURATION * 60) : 0),
                'REP_ID' => $r->REP_ID,
                'BTN' => $r->BTN,
                'VERIFIED' => $r->VERIFIED,
                'CANCELLED_REASON' => $r->CANCELLED_REASON,
                'CANCELLED_TEXT' => $r->CANCELLED_TEXT,
                'COMMENTS' => $comments
            ];

            array_push($csv, $row);
        }

        // Write CSV file
        $this->info("Writing CSV file...");
        $file = fopen(public_path('tmp/' . $fileName), 'w');

        // Header Row
        fputcsv($file, $csvHeader);

        // Data
        foreach ($csv as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        // Email the files
        if (!$this->option('no-email')) {
            $this->info('Emailing files...');

            $message = 'Attached is the enrollment file for ' . $this->startDate->format('m-d-Y');
            if ($this->startDate->format('Ymd') != $this->endDate->format('Ymd')) {
                $message .= ' through ' . $this->endDate->format('m-d-Y');
            }
            $message .= '.';

            $this->sendEmail($message, $this->distroList, [public_path('tmp/' . $fileName)]);

            unlink(public_path('tmp/' . $fileName));
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
    public function sendEmail(string $message, array $distro, array $files = array())
    {
        $uploadStatus = [];
        $email_address = $distro;

        $subjectBrand = $this->brand;

        // Build email subject
        if ('production' != config('app.env')) {
            $subject = $subjectBrand . ' - Daily Calls File Generation (' . config('app.env') . ') '
                . Carbon::now('America/Chicago');
        } else {
            $subject = $subjectBrand . ' - Daily Calls File Generation '
                . Carbon::now('America/Chicago');
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
            }

            $status .= 'Success!';
            $uploadStatus[] = $status;
        }

        return $uploadStatus;
    }
}
