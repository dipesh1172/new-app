<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Twilio\Rest\Client;

class TwilioRealtimeStatsUpdater extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:stats {type=queue} {ident?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Available types are "usage", "worker", "workers", "list-workers", "queue"';

    private $client;
    private $workflow;
    private $workspace;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(config('services.twilio.account'), config('services.twilio.auth_token'));
        $this->workspace = config('services.twilio.workspace');
        $this->workflow = config('services.twilio.workflow');
    }

    private function getTaskQueues()
    {
        return Cache::remember('twilio-task-queues', 900, function () {
            $taskQueues = $this->client->taskrouter->workspaces($this->workspace)->taskQueues->read();
            $out = [];
            foreach ($taskQueues as $queue) {
                $out[] = ['FriendlyName' => $queue->friendlyName, 'Sid' => $queue->sid];
            }
            return $out;
        });
    }

    private function getQueueStats($queueSid)
    {
        //$start = microtime(true);
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $stats = $this->client->taskrouter->workspaces($this->workspace)->taskQueues($queueSid)->statistics()->fetch(['startDate' => $today]);
        $rstats = $stats->realtime;
        $cstats = $stats->cumulative;
        $out = [];

        $out['*LongestTaskWaitingAge'] = $rstats[snake_case('longestTaskWaitingAge')];
        $out['*TotalTasks'] = $rstats[snake_case('totalTasks')];
        $out['*TotalEligibleWorkers'] = $rstats[snake_case('totalEligibleWorkers')];
        $out['*TotalAvailableWorkers'] = $rstats[snake_case('totalAvailableWorkers')];
        $out['WaitDurationUntilAccepted'] = $cstats[snake_case('waitDurationUntilAccepted')];
        $out['*ActivityStatistics'] = collect($rstats[snake_case('activityStatistics')])->map(function ($item) {
            return [$item['friendly_name'] => $item['workers']];
        });
        //$end = number_format(microtime(true) - $start, 3);
        //$out['ProcessingTime'] = "{$end} seconds";
        return $out;
    }

    private function getWorkspaceStats()
    {
        //$start = microtime(true);
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $out = [];
        $stats = $this->client->taskrouter->workspaces($this->workspace)->statistics()->fetch(['startDate' => $today]);
        $cstats = $stats->cumulative;
        $rstats = $stats->realtime;

        $out['AvgTaskAcceptanceTime'] = $cstats[snake_case('avgTaskAcceptanceTime')];
        $out['ReservationsAccepted'] = $cstats[snake_case('reservationsAccepted')];
        $out['ReservationsRejected'] = $cstats[snake_case('reservationsRejected')];
        $out['ReservationsTimedOut'] = $cstats[snake_case('reservationsTimedOut')];
        $out['ReservationsCanceled'] = $cstats[snake_case('reservationsCanceled')];
        $out['ReservationsRescinded'] = $cstats[snake_case('reservationsRescinded')];

        $out['TasksCreated'] = $cstats[snake_case('tasksCreated')];
        $out['TasksCanceled'] = $cstats[snake_case('tasksCanceled')];
        $out['TasksCompleted'] = $cstats[snake_case('tasksCompleted')];
        $out['TasksDeleted'] = $cstats[snake_case('tasksDeleted')];
        $out['TasksMoved'] = $cstats[snake_case('tasksMoved')];
        $out['TasksTimedOutInWorkflow'] = $cstats[snake_case('tasksTimedOutInWorkflow')];
        $out['WaitDurationUntilAccepted'] = $cstats[snake_case('waitDurationUntilAccepted')];
        $out['*ActivityStatistics'] = collect($rstats[snake_case('activityStatistics')])->map(function ($item) {
            return [$item['friendly_name'] => $item['workers']];
        });

        ////$end = number_format(microtime(true) - $start, 3);
        //$out['ProcessingTime'] = "{$end} seconds";
        return $out;
    }

    private function getAllWorkerStats()
    {
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $data = $this->client->taskrouter->workspaces(
            $this->workspace
        )->workers->statistics(['startDate' => $today])->fetch();

        $out = [];
        $out['cumulative'] = $data->cumulative;
        $out['realtime'] = $data->realtime;
        return $out;
    }

    private function listWorkers()
    {
        $data = $this->client->taskrouter->workspaces($this->workspace)->workers->read();
        $out = [];
        foreach ($data as $item) {
            $out[] = $item->sid;
        }
        return $out;
    }

    private function getWorkerStats($sid)
    {
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $data = $this->client->taskrouter->workspaces(
            $this->workspace
        )->workers($sid)->statistics(['startDate' => $today])->fetch();

        $out = [];
        $out['cumulative'] = $data->cumulative;
        //$out['realtime'] = $data->realtime;
        return $out;
    }

    private function getWorkflowStats()
    {
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $data = $this->client->taskrouter->workspaces(
            $this->workspace
        )->workflows($this->workflow)->statistics(['startDate' => $today])->fetch();

        $out = [];
        $out['cumulative'] = $data->cumulative;
        $out['realtime'] = $data->realtime;
        return $out;
    }

    private function getUsageStats()
    {
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $data = $this->client->usage->records->lastMonth->read();
        $out = [];
        $price = 0;

        foreach ($data as $item) {
            if ($item->price > 0 && $item->description != 'Total Price') {
                $price += $item->price;
                $out[$item->description] = [
                    'count' => $item->count,
                    'unit' => $item->countUnit,
                    'price' => $item->price
                ];
            }
        }

        $out['Cost'] = $price;
        return $out;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $start = microtime(true);
        $type = $this->argument('type');
        $ident = $this->argument('ident');

        switch ($type) {
            case 'usage':
                $this->line(json_encode($this->getUsageStats()));
                break;

            case 'list-workers':
                $this->line(
                    json_encode(
                        $this->listWorkers(),
                        JSON_PRETTY_PRINT
                    )
                );

                break;

            case 'workers':
                $this->line(json_encode($this->getAllWorkerStats()));
                break;

            case 'worker':
                if ($ident == null) {
                    $this->error('Please provide worker sid');
                } else {
                    $this->line(json_encode($this->getWorkerStats($ident)));
                }
                break;

            case 'workflow':
                $this->line(json_encode($this->getWorkflowStats()));
                break;

            case 'queue':
                $out = $this->getWorkspaceStats();
                //dd($out);

                $out['queues'] = collect($this->getTaskQueues())->map(function ($item) {
                    return [$item['FriendlyName'] => $this->getQueueStats($item['Sid'])];
                });
                //dd($out);
                $end = number_format(microtime(true) - $start, 3);
                $out['AsOf'] = Carbon::now()->toIso8601String();
                $out['ProcessingTime'] = $end;
                $this->line(json_encode($out));
                $this->line("Execution time: {$end}s");
                break;

            default:
                $this->error('Unknown Stat Type '.$type);
        }
    }
}
