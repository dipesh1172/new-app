<?php

namespace App\Console\Commands;

use Twilio\Rest\Client;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\InboundCallData;
use App\Models\BrandTaskQueue;

class inbound_call_data_task extends Command
{
    protected $twilio_client = null;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'InboundCallData:update {--forever}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'A task to get the latest information from Twilio and cache it in the database.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->twilio_client = (new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        ))->taskrouter->v1->workspaces(config('services.twilio.workspace'));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option("forever")) {
            $start_date = Carbon::parse('2019-01-01 07:00:00', 'America/Chicago');
        } else {
            if (Carbon::now('America/Chicago')->minute >= 30) {
                $start_date = Carbon::now('America/Chicago')->minute(30)->second(0);
            } else {
                $start_date = Carbon::now('America/Chicago')->minute(0)->second(0);
            }
        }

        $brand_task_queues = BrandTaskQueue::select(
            'brand_task_queues.task_queue_sid',
            'brand_task_queues.brand_id'
        )->leftJoin(
            'brands',
            'brand_task_queues.brand_id',
            'brands.id'
        )->whereNotNull(
            'brand_task_queues.brand_id'
        )->whereNull(
            'brands.deleted_at'
        )->orderBy('task_queue')->get();

        // $this->info(print_r($brand_task_queues->toArray(), true));

        while ($start_date->lt(Carbon::now('America/Chicago'))) {
            $end_date = $start_date->copy()->addMinutes(30);

            $cumulative = [
                'avgTaskAcceptanceTime' => 0,
                'avgWaitDurationUntilCanceled' => 0,
                'splitByWaitTime' => 0,
                'splitByWaitTimeOver' => 0,
                'tasksCanceled' => 0
            ];

            foreach ($brand_task_queues as $brand_task_queue) {
                $brand_id = $brand_task_queue->task_queue_sid;
                $stats = $this->get_twilio_stats($start_date, $end_date, $brand_id);
                $cumulative = $this->add_stats($cumulative, $stats);
                $this->save_twilio_stats($start_date, $brand_id, $stats);
            }

            $cumulative = $this->normalize_stats($cumulative, count($brand_task_queues));
            $this->save_twilio_stats($start_date, null, $cumulative);

            $start_date = $start_date->addMinutes(30);
        }
    }

    private function add_stats($a, $b)
    {
        $out = $a;
        $out['avgTaskAcceptanceTime'] = $a['avgTaskAcceptanceTime'] + $b['avgTaskAcceptanceTime'];
        $out['avgWaitDurationUntilCanceled']
            = $a['avgWaitDurationUntilCanceled'] + $b['avgWaitDurationUntilCanceled'];
        $out['splitByWaitTime'] = $a['splitByWaitTime'] + $b['splitByWaitTime'];
        $out['splitByWaitTimeOver'] = $a['splitByWaitTimeOver'] + $b['splitByWaitTimeOver'];
        $out['tasksCanceled'] = $a['tasksCanceled'] + $b['tasksCanceled'];

        return $out;
    }

    private function normalize_stats($stats, $numItems)
    {
        $out_stats = $stats;
        $out_stats['avgTaskAcceptanceTime'] = $stats['avgTaskAcceptanceTime'] / $numItems;
        $out_stats['avgWaitDurationUntilCanceled'] = $stats['avgWaitDurationUntilCanceled'] / $numItems;

        return $out_stats;
    }

    private function get_twilio_stats($start, $end, $task_queue)
    {
        $time_filter = [
            'startDate' => $start->tz('America/Chicago')->toIso8601String(),
            'endDate' => $end->tz('America/Chicago')->toIso8601String(),
            'splitByWaitTime' => '20'
        ];

        $stats_array = [];

        if ($task_queue) {
            try {
                $stats = $this->twilio_client
                ->taskQueues($task_queue)
                ->cumulativeStatistics()
                ->fetch($time_filter);

                $stats_array = [
                    'avgTaskAcceptanceTime' =>$stats->avgTaskAcceptanceTime,
                    'avgWaitDurationUntilCanceled' => $stats->waitDurationUntilCanceled['avg'],
                    'splitByWaitTime' => $stats->splitByWaitTime['20']['below']['reservations_accepted'],
                    'splitByWaitTimeOver' => $stats->splitByWaitTime['20']['above']['reservations_accepted'],
                    'tasksCanceled' => $stats->tasksCanceled,
                ];
            } catch (\Twilio\Exceptions\RestException $e) {
            } catch (\Exception $e) {
                info("Real Exception: " . $e);
            }
        }

        return $stats_array;
    }

    private function save_twilio_stats($start_date, $brand_id, $stats)
    {
        $time_start = microtime(true);

        InboundCallData::disableAuditing();

        $stats_json = json_encode($stats);
        $icd = InboundCallData::where(
            'start_date',
            $start_date->format('Y-m-d H:i:s')
        )->where(
            'brand_id',
            $brand_id
        )->first();
        if (!$icd) {
            $icd = new InboundCallData();
            $icd->start_date = $start_date;
            $icd->brand_id = $brand_id;
            $icd->twilio_json = $stats_json;
            $icd->save();

            $this->info('save twilio stats add: ' . (microtime(true) - $time_start) / 60);
        } else {
            $icd->twilio_json = $stats_json;
            $icd->save();

            $this->info('save twilio stats update: ' . (microtime(true) - $time_start) / 60);
        }

        InboundCallData::enableAuditing();
    }
}
