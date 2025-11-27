<?php

namespace App\Console\Commands;

use Twilio\Rest\Client;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\JsonDocument;

class TwilioCallcenterDashboard extends Command
{
    private $_client;
    private $_workspace_id;
    private $_workspace;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:call:dashboard {--clean} {--debug} {--count=8}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches stats used for the callcenter dashboard';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        // info('in TwilioCallcenterDashboard script...');
        // info(print_r($_SERVER, true));

        $this->_client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $this->_workspace_id = config('services.twilio.workspace');
        $this->_workspace = $this->_client->taskrouter
            ->workspaces($this->_workspace_id);

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('clean')) {
            JsonDocument::where(
                'created_at',
                '<=',
                Carbon::now()->subMinutes(30)
            )->where(
                'document_type',
                'stats-job'
            )->forceDelete();
        } else {
            $count = $this->option('count');
            for ($i = 0; $i < $count; $i++) {
                if ($this->option('debug')) {
                    Log::debug("Running getStats #" . ($i + 1));
                }

                $this->getStats();

                if ($i != $count - 1) {
                    sleep(5);
                }
            }
        }
    }

    public function getStats()
    {
        $timing = [];
        $start = microtime(true);
        $out = $this->getWorkspaceStats();
        $timing['getWorkspaceStats'] = microtime(true) - $start;
        $out['queues'] = collect($this->getTaskQueues())->map(
            function ($item) {
                return [
                    $item['FriendlyName'] => $this->getQueueStats($item['Sid'])
                ];
            }
        )->toArray();
        $timing['getTaskQueueStats'] = microtime(true) - $timing['getWorkspaceStats'];

        $svcLevels = Cache::remember(
            'service-levels',
            60,
            function () {
                return $this->get_service_levels();
            }
        );

        foreach ($svcLevels as $queue => $stats) {
            for ($i = 0, $len = count($out['queues']); $i < $len; $i++) {
                $q = array_keys($out['queues'][$i])[0];
                if ($q === $queue) {
                    $out['queues'][$i][$queue]['svcLevel'] = $stats;
                }
            }
        }
        $timing['processServiceLevels'] = microtime(true) - $timing['getTaskQueueStats'];
        $timing['total'] = microtime(true) - $start;


        $out['AsOf'] = Carbon::now()->toIso8601String();
        $out['ProcessingTime'] = $timing;
        $out['isNew'] = true;

        $newJ = new JsonDocument();
        $newJ->document_type = 'stats-job';
        $newJ->document = $out;
        $newJ->save();
        Cache::forget('stats-job');
    }

    private function getWorkspaceStats()
    {
        $time_start = microtime(true);
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $out = [];
        $stats = $this->_workspace->statistics()->fetch(['startDate' => $today]);
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
        $out['*ActivityStatistics'] = collect($rstats[snake_case('activityStatistics')])->map(
            function ($item) {
                return [$item['friendly_name'] => $item['workers']];
            }
        );

        $time_end = microtime(true);

        if ($this->option('debug')) {
            Log::debug('Took getWorkspaceStats ' . ($time_end - $time_start) . ' sec(s)');
        }

        return $out;
    }

    private function getQueueStats($queueSid)
    {
        $time_start = microtime(true);

        $today = Carbon::today('America/Chicago')->toIso8601String();
        $stats = $this->_workspace
            ->taskQueues($queueSid)->statistics()
            ->fetch(['startDate' => $today]);
        $rstats = $stats->realtime;
        $cstats = $stats->cumulative;
        $out = [];

        $out['*LongestTaskWaitingAge'] = $rstats[snake_case('longestTaskWaitingAge')];
        $out['*TotalTasks'] = $rstats[snake_case('totalTasks')];
        $out['!Tasks'] = count($this->_workspace->tasks->read(['taskQueueSid' => $queueSid, 'assignmentStatus' => 'pending']));
        $out['*TotalEligibleWorkers'] = $rstats[snake_case('totalEligibleWorkers')];
        $out['*TotalAvailableWorkers'] = $rstats[snake_case('totalAvailableWorkers')];
        $out['WaitDurationUntilAccepted'] = $cstats[snake_case('waitDurationUntilAccepted')];
        $out['*ActivityStatistics'] = collect($rstats[snake_case('activityStatistics')])->map(
            function ($item) {
                return [$item['friendly_name'] => $item['workers']];
            }
        );

        $time_end = microtime(true);

        if ($this->option('debug')) {
            Log::debug('Took ' . $queueSid . ' getQueueStats ' . ($time_end - $time_start) . ' sec(s)');
        }

        return $out;
    }

    private function getTaskQueues()
    {
        $time_start = microtime(true);

        $gtq = Cache::remember(
            'twilio-task-queues',
            900,
            function () {
                $taskQueues = $this->_workspace->taskQueues->read();
                $out = [];
                foreach ($taskQueues as $queue) {
                    $out[] = [
                        'FriendlyName' => $queue->friendlyName,
                        'Sid' => $queue->sid
                    ];
                }
                return $out;
            }
        );

        $time_end = microtime(true);

        if ($this->option('debug')) {
            Log::debug('Took getTaskQueues ' . ($time_end - $time_start) . ' sec(s)');
        }

        return $gtq;
    }

    private function get_service_levels()
    {
        $time_start = microtime(true);

        $lastIntervalStart = null;
        $lastIntervalEnd = null;

        $rightNow = Carbon::now();
        if ($rightNow->minute >= 30) {
            $lastIntervalStart = $rightNow->copy();
            $lastIntervalStart->minute = 0;
            $lastIntervalStart->second = 0;
        } else {
            $lastIntervalStart = $rightNow->copy();
            $lastIntervalStart->minute = 30;
            $lastIntervalStart->second = 0;
            $lastIntervalStart->addHours(-1);
        }

        $lastIntervalEnd = $lastIntervalStart->copy()
            ->addMinutes(30)->addSeconds(-1);

        $t = $this->_workspace
            ->events
            ->read(
                [
                    'EventType' => 'reservation.created',
                    'StartDate' => $lastIntervalStart->format(\DateTime::ISO8601),
                    'EndDate' => $lastIntervalEnd->format(\DateTime::ISO8601),
                ]
            );

        $t2 = $this->_workspace
            ->events
            ->read(
                [
                    'EventType' => 'reservation.accepted',
                    'StartDate' => $lastIntervalStart->format(\DateTime::ISO8601),
                    'EndDate' => $lastIntervalEnd->format(\DateTime::ISO8601),
                ]
            );


        $out = [];
        $total = count($t) + count($t2);
        $t = array_merge($t, $t2);
        foreach ($t as $event) {
            try {
                $x = $this->_workspace->events($event->sid)->fetch()->toArray();
                if (str_contains($x['eventType'], ['reservation.created', 'reservation.accepted'])) {
                    switch ($x['eventType']) {
                        case 'reservation.created':
                            $queue = $x['eventData']['task_queue_name'];
                            if (!isset($out[$queue])) {
                                $out[$queue] = [];
                            }
                            if (!isset($out[$queue][$x['eventData']['reservation_sid']])) {
                                $out[$queue][$x['eventData']['reservation_sid']] = [
                                    'start' => (Carbon::instance($x['eventDate']))->format(\DateTime::ISO8601)
                                ];
                            } else {
                                $out[$queue][$x['eventData']['reservation_sid']]['start']
                                    = (Carbon::instance($x['eventDate']))->format(\DateTime::ISO8601);
                            }

                            break;

                        case 'reservation.accepted':
                            $queue = $x['eventData']['task_queue_name'];
                            if (!isset($out[$queue])) {
                                $out[$queue] = [];
                            }
                            if (!isset($out[$queue][$x['eventData']['reservation_sid']])) {
                                $out[$queue][$x['eventData']['reservation_sid']] = [
                                    'end' => (Carbon::instance($x['eventDate']))->format(\DateTime::ISO8601)
                                ];
                            } else {
                                $out[$queue][$x['eventData']['reservation_sid']]['end']
                                    = (Carbon::instance($x['eventDate']))->format(\DateTime::ISO8601);
                            }
                            break;
                    }
                }
            } catch (\Twilio\Exceptions\RestException $e) {
                info($e->getCode() . ' : ' . $e->getMessage());
            }
        }

        $out2 = [];
        foreach ($out as $queue => $tasks) {
            $temp = [];
            foreach ($tasks as $reservation => $info) {
                if (isset($info['start']) && isset($info['end'])) {
                    $start = Carbon::parse($info['start']);
                    $end = Carbon::parse($info['end']);
                    $temp[] = $end->diffInSeconds($start);
                }
            }

            $out2[$queue] = [];
            if (count($temp) > 0) {
                for ($i = 10; $i < 65; $i += 5) {
                    $out2[$queue][$i . '']
                        = round(
                            floatval(
                                $this->count_values_less_than($temp, $i) / count($temp)
                            ) * 100,
                            2
                        );
                }
            }
        }

        $time_end = microtime(true);

        if ($this->option('debug')) {
            Log::debug('Took get_service_levels ' . ($time_end - $time_start) . ' sec(s)');
        }

        return $out2;
    }

    private function count_values_less_than(array $array, int $value): int
    {
        $ret = 0;
        $len = count($array);
        for ($i = 0; $i < $len; $i++) {
            if ($array[$i] < $value) {
                $ret++;
            }
        }
        return $ret;
    }
}
