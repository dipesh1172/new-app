<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Interaction;
use App\Models\StatsProduct;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

class DXCSurveyExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dxc:survey:export {--dateRangeStart=} {--dateRangeEnd=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a temp command to export survey data for DXC billing';

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
        if (!$this->option('dateRangeStart') || !$this->option('dateRangeEnd')) {
            echo "Syntax: php artisan dxc:survey:export --dateRangeStart=<YYYY-mm-dd> --dateRangeEnd=<YYYY-mm-dd>\n";
            exit();
        }

        $first_day = Carbon::parse($this->option('dateRangeStart'));
        $end_day = Carbon::parse($this->option('dateRangeEnd'));

        $sps = StatsProduct::select(
            'stats_product.interaction_created_at AS p_date',
            'stats_product.interaction_created_at AS dt_date',
            DB::raw('"01" AS center_id'),
            DB::raw('LOWER(stats_product.language) AS language'),
            'stats_product.channel',
            DB::raw('"TX" AS sales_state'),
            'stats_product.confirmation_code as ver_code',
            'stats_product.sales_agent_rep_id AS tsr_id',
            'stats_product.tpv_agent_label AS dxc_rep_id',
            'stats_product.product_time AS call_time',
            DB::raw('CONCAT("' . config('services.aws.cloudfront.domain') . '","/", stats_product.recording) AS activewav'),
            DB::raw('NULL AS cb_station'),
            'stats_product.result AS cb_status',
            DB::raw('NULL AS completed'),
            'stats_product.disposition_label AS disposition_label',
            'stats_product.event_id'
        )
        // ->leftJoin(
        //     'surveys',
        //     'stats_product.survey_id',
        //     'surveys.id'
        // )
        // ->leftJoin(
        //     'states',
        //     'surveys.state_id',
        //     'states.id'
        // )
        ->where(
            'stats_product.stats_product_type_id',
            3
        )->where(
            'stats_product.product_time',
            '>',
            0
        )->whereBetween(
            'stats_product.interaction_created_at',
            array(
                date("Y-m-d", strtotime($first_day)),
                date("Y-m-d", strtotime($end_day))
            )
        )->orderBy(
            'stats_product.interaction_created_at'
        )->get();
        if ($sps) {
            $filename = "gm-survey-export-" . time() . ".csv";
            $path = public_path("tmp/" . $filename);
            $file = fopen($path, "w");

            foreach ($sps as $sp) {
                if ($sp->cb_status == 'Sale') {
                    $sp->cb_status = 'CS';
                    $sp->completed = 1;
                } else {
                    $sp->cb_status = $sp->disposition_label;
                    $sp->completed = 0;

                    $event = Event::find($sp->event_id);
                    if ($event) {
                        $i = Interaction::where(
                            'event_id',
                            $event->id
                        )->count();
                        if ($i >= 3) {
                            $sp->completed = 1;
                        }
                    }
                }

                unset($sp->disposition_label);
                unset($sp->event_id);
            }

            $headers = [
                'p_date',
                'dt_date',
                'center_id',
                'language',
                'channel',
                'sales_state',
                'ver_code',
                'tsr_id',
                'dxc_rep_id',
                'call_time',
                'activewav',
                'cb_station',
                'cb_status',
                'completed'
            ];

            fputcsv($file, $headers);

            foreach ($sps->toArray() as $result) {
                fputcsv($file, $result);
            }
        }

        fclose($file);

        //print_r($sps->toArray());
    }
}
