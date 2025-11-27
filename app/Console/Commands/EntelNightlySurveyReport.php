<?php

namespace App\Console\Commands;

use App\Models\Brand;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;

class EntelNightlySurveyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entel:nightly_report {--debug} {--start=} {--nightly} {--noEmail}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Entel Nightly Survey Report';

    /**
     * The list of email addresses being sent to.
     *
     * @var array
     */
    protected $email_address_dev = ['lauren@tpv.com', 'report@tpv.com'];
    protected $email_address_prod = ['pwooley@entelmarketing.com', 'report@tpv.com'];

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
        $brand = Brand::where(
            'name',
            'Entel Marketing'
        )->first();
        if (!$brand) {
            $this->info('Unable to find the brand id for Entel.');
            exit();
        }

        $start_date = $this->option('start')
            ? Carbon::parse($this->option('start'), 'America/Chicago')
            : Carbon::now('America/Chicago')->startOfDay();

        if ($this->option('nightly')) {
            // this report runs at 3 am Pacific time so if we used the current day it would be wrong.
            $start_date = Carbon::now('America/Chicago')->subDay()->startOfDay();
        }

        $data = [];
        $events = DB::table('events')
            ->join('surveys', 'surveys.id', 'events.survey_id')
            ->leftJoin('interactions', 'events.id', 'interactions.event_id')
            ->leftJoin('dispositions', 'dispositions.id', 'interactions.disposition_id')
            ->where('interactions.created_at', '>=', $start_date)
            ->where('events.brand_id', $brand->id)
            ->select(
                'events.id as event_id',
                'events.created_at as event_created_at',
                'interactions.id as interaction_id',
                'interactions.created_at as interaction_created_at',
                'events.confirmation_code as confirmation_code',
                'interactions.interaction_time as product_time',
                'events.language_id as language_id',
                'interactions.event_result_id as result_id',
                'dispositions.reason as disposition_reason',
                'surveys.custom_data as custom_data'
            )->get();

        if ($this->option('debug')) {
            info(print_r($events->toArray(), true));
        }

        foreach ($events as $event) {
            $custom_data = json_decode($event->custom_data, true);
            $data[] = [
                'event_id' => $event->event_id,
                'event_created_at' => $event->event_created_at,
                'interaction_id' => $event->interaction_id,
                'interaction_created_at' => $event->interaction_created_at,
                'confirmation_code' => $event->confirmation_code,
                'product_time' => $event->product_time,
                'language' => $event->language_id == '1' ? 'English' : 'Spanish',
                'result' => $event->result_id ? 'Successful' : 'Unsuccessful',
                'disposition_reason' => $event->disposition_reason,
                'company_name' => $this->safe_get_value($custom_data, 'company_name'),
                'client_name' => $this->safe_get_value($custom_data, 'client_name'),
                'user_first_name' => $this->safe_get_value($custom_data, 'user_first_name'),
                'user_last_name' => $this->safe_get_value($custom_data, 'user_last_name'),
                'custom_start_date' => $this->safe_get_value($custom_data, 'custom_start_date'),
                'product_amount' => $this->safe_get_value($custom_data, 'product_amount'),
                'product_term' => $this->safe_get_value($custom_data, 'product_term'),
                'product_term_type' => $this->safe_get_value($custom_data, 'product_term_type'),
                'product_description' => $this->safe_get_value($custom_data, 'product_description'),
                'client_service_phone' => $this->safe_get_value($custom_data, 'client_service_phone'),
            ];
        }

        $filename = public_path('/tmp/entel_nightly_survey_report_' . $start_date->format('Y-m-d') . '.csv');
        $file = fopen($filename, 'w');
        $headers = [
            'event_id', 'event_created_at', 'interaction_id', 'interaction_created_at', 'confirmation_code',
            'product_time', 'language', 'result', 'disposition_reason', 'CompanyName', 'ClientName', 'UserFirstName',
            'UserLastName', 'CustomStartDate', 'ProductAmount', 'ProductTerm', 'ProductTermType', 'ProductDescription',
            'ClientServicePhone',
        ];

        fputcsv($file, $headers);
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        $env = config('app.env');
        if (!$this->option('noEmail')) {
            try {
                Mail::raw(
                    'Here is the nightly survey report for ' . $start_date->toDateString(),
                    function ($message) use ($filename, $env, $start_date) {
                        $message->subject('Entel Nightly Survey Report - ' . $start_date->toDateString());
                        $message->from('no-reply@tpvhub.com');

                        if ($env === 'production') {
                            $message->to($this->email_address_prod);
                        } else {
                            $message->to($this->email_address_dev);
                        }

                        $message->attach($filename);
                    }
                );
            } catch (\Exception $e) {
                $this->error('Error! The reason reported is: ' . $e);
            }
        }
    }

    private function safe_get_value($a, $f, $d = '')
    {
        if (array_key_exists($f, $a)) {
            return $a[$f];
        } else {
            return $d;
        }
    }
}
