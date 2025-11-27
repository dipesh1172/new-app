<?php

namespace App\Console\Commands;

use Twilio\Rest\Client;

// use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\JsonDocument;

class TwilioCallcenterDashboard_new extends Command
{
    private $_client;
    private $_workspace_id;
    private $_workspace;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:call:dashboard:beta {--debug} {--count=8}';

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
        $count = $this->option('count');
        for ($i = 0; $i < $count; $i++) {
            info('Dashboard Stats - start');

            $this->getStats();

            info('Dashboard Stats - complete');
            if ($i != $count - 1) {
                sleep(5);
            }
        }
    }

    private function debug($val)
    {
        if ($this->option('debug')) {
            $this->info($val);
        }
    }

    public function getInQueueCalls()
    {
        return $this->_workspace->tasks->read(array('assignmentStatus' => 'pending'));
    }

    public function getAvailableWorkers()
    {
        return $this->_workspace->workers->read(["available" => true]);
    }

    public function getBusyWorkers()
    {
        return $this->_workspace->workers->read(["activityName" => "TPV In Progress"]);
    }

    public function getCalls()
    {
        return $this->_client->calls->read(array('status' => 'in-progress'));
    }

    public function getStats()
    {
        $timing = [];
        $start = microtime(true);
        $start_total = $start;
        $out = $this->getWorkspaceStats();
        $timing['getWorkspaceStats'] = microtime(true) - $start;

        $start = microtime(true);

        $in_queue_calls = $this->getInQueueCalls();
        $ready_workers = $this->getAvailableWorkers();

        // $calls = $this->getCalls();
        // $busy_workers = $this->getBusyWorkers();

        if ($this->option('debug')) {
            $this->debug("Found " . count($in_queue_calls) . " calls in queue.");
            $this->debug("Found " . count($ready_workers) . " workers are Available.");
            // $this->debug("Found " . count($calls) . " calls.");
            // $this->debug("Found " . count($busy_workers) . " workers on call.");
        }

        $in_queue_calls_list = [];
        foreach ($in_queue_calls as $in_queue_call) {
            $diff = Carbon::now()->diffInSeconds($in_queue_call->dateCreated);
            $found = false;
            foreach ($in_queue_calls_list as &$in_queue_list_call) {
                if ($in_queue_call->taskQueueFriendlyName === $in_queue_list_call['task_queue']) {
                    $found = true;
                    $in_queue_list_call['count'] += 1;
                    if ($diff > $in_queue_list_call['task_age']) {
                        $in_queue_list_call['task_age'] = $diff;
                    }
                    break;
                }
            }
            if (!$found) {
                $in_queue_calls_list[] = [
                    'platform' => $this->isDXC(strtolower($in_queue_call->taskQueueFriendlyName)) ? 'dxc' : 'focus',
                    'count' => 1,
                    'task_queue' => $in_queue_call->taskQueueFriendlyName,
                    'task_age' => $diff,
                    'task_sid' => $in_queue_call->sid
                ];
            }
        }

        if ($this->option('debug')) {
            $this->debug("Calls in queue (" . count($in_queue_calls_list) . "): ");
            foreach ($in_queue_calls_list as $in_queue_call) {
                $this->debug("   " . $in_queue_call['task_sid']);
            }
        }

        array_multisort(array_column($in_queue_calls_list, 'task_age'), SORT_DESC, $in_queue_calls_list);

        $queues = [
            'in_queue_list' => $in_queue_calls_list,
            'on_call_list' => [],
            'dxc' => [
                'English' => [
                    'in_queue' => 0,
                    'ready' => 0,
                    'on_call' => 0,
                    'hold_time' => 0,
                    'asa' => 0,
                ],
                'Spanish' => [
                    'in_queue' => 0,
                    'ready' => 0,
                    'on_call' => 0,
                    'hold_time' => 0,
                    'asa' => 0,
                ],
            ],
            'focus' => [
                'English' => [
                    'in_queue' => 0,
                    'ready' => 0,
                    'on_call' => 0,
                    'hold_time' => 0,
                    'asa' => 0,
                ],
                'Spanish' => [
                    'in_queue' => 0,
                    'ready' => 0,
                    'on_call' => 0,
                    'hold_time' => 0,
                    'asa' => 0,
                ],
            ],
        ];

        foreach ($ready_workers as $worker) {
            foreach (json_decode($worker->attributes) as $name => $attribute) {
                if ($name === "skills") {
                    $skill = strtolower(join($attribute, " "));
                    if ($this->isDXC($skill)) {
                        if ($this->isEnglishOrBilingual($skill)) {
                            $queues['dxc']['English']['ready'] += 1;
                        }
                        if ($this->isSpanishOrBilingual($skill)) {
                            $queues['dxc']['Spanish']['ready'] += 1;
                        }
                    } else {
                        if ($this->isEnglishOrBilingual($skill)) {
                            $queues['focus']['English']['ready'] += 1;
                        }
                        if ($this->isSpanishOrBilingual($skill)) {
                            $queues['focus']['Spanish']['ready'] += 1;
                        }
                    }
                }
            }
        }

        foreach ($in_queue_calls_list as $key => $call) {
            $this->debug(print_r($call, true));
            $task_queue = strtolower($call['task_queue']);
            $diff = $call['task_age'];
            if (!$this->isSurvey($task_queue) && !$this->isReTPV($task_queue)) {
                if ($this->isDXC($task_queue)) {
                    if ($this->isEnglish($task_queue)) {
                        $queues['dxc']['English']['in_queue'] += $call['count'];
                        if ($diff > $queues['dxc']['English']['hold_time']) {
                            $queues['dxc']['English']['hold_time'] = $diff;
                        }
                    }
                    if ($this->isSpanish($task_queue)) {
                        $queues['dxc']['Spanish']['in_queue'] += $call['count'];
                        if ($diff > $queues['dxc']['Spanish']['hold_time']) {
                            $queues['dxc']['Spanish']['hold_time'] = $diff;
                        }
                    }
                } else {
                    if ($this->isEnglish($task_queue)) {
                        $queues['focus']['English']['in_queue'] += $call['count'];
                        if ($diff > $queues['focus']['English']['hold_time']) {
                            $queues['focus']['English']['hold_time'] = $diff;
                        }
                    }
                    if ($this->isSpanish($task_queue)) {
                        $queues['focus']['Spanish']['in_queue'] += $call['count'];
                        if ($diff > $queues['focus']['Spanish']['hold_time']) {
                            $queues['focus']['Spanish']['hold_time'] = $diff;
                        }
                    }
                }
            }
        }
        $this->debug(print_r($queues, true));

        $start_asa = microtime(true);
        $asa = Cache::remember('asa-calculation-cmd', 30, function () {
            return JsonDocument::where('document_type', 'asa-calculation')->orderBy('created_at', 'desc')->first();
        });
        if ($asa) {
            $computed = $asa->document;
            $queues['dxc']['English']['asa'] = $computed['dxc_English'];
            $queues['dxc']['Spanish']['asa'] = $computed['dxc_Spanish'];
            $queues['focus']['English']['asa'] = $computed['focus_English'];
            $queues['focus']['Spanish']['asa'] = $computed['focus_Spanish'];
        }
        $timing['getASA'] = microtime(true) - $start_asa;

        $out['queues'] = $queues;
        $timing['getQueueStats'] = microtime(true) - $start;
        $timing['total'] = microtime(true) - $start_total;

        $out['AsOf'] = Carbon::now()->toIso8601String();
        $out['ProcessingTime'] = $timing;
        $this->debug("Processing Time: \n" . print_r($timing, true));
        $out['isNew'] = true;

        try {
            $newJ = new JsonDocument();
            $newJ->document_type = 'stats-job-2';
            $newJ->document = $out;
            $newJ->save();
        } catch (\Exception $e) {
            $this->info("Unable to write to the database.");
        }
        // Cache::forget('stats-job-2');
    }

    private function isDXC($task_queue)
    {
        return (strpos($task_queue, "z_") !== false || strpos($task_queue, "dxc") !== false);
    }

    private function isEnglishOrBilingual($task_queue)
    {
        return ($this->isEnglish($task_queue) || $this->isBilingual($task_queue));
    }

    private function isSpanishOrBilingual($task_queue)
    {
        return ($this->isSpanish($task_queue) || $this->isBilingual($task_queue));
    }

    private function isEnglish($task_queue)
    {
        return (strpos($task_queue, "english") !== false);
    }

    private function isSpanish($task_queue)
    {
        return (strpos($task_queue, "spanish") !== false);
    }

    private function isBilingual($task_queue)
    {
        return (strpos($task_queue, "bilingual") !== false);
    }

    private function isSurvey($task_queue)
    {
        return (strpos($task_queue, 'outbound call queue') !== false || strpos($task_queue, 'survey') !== false);
    }

    private function isReTPV($task_queue)
    {
        return (strpos($task_queue, "retpv") !== false);
    }

    private function getWorkspaceStats()
    {
        $time_start = microtime(true);
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $out = [];
        $stats = $this->_workspace->statistics()->fetch(['startDate' => $today]);
        $cstats = $stats->cumulative;
        $rstats = $stats->realtime;

        $out['AvgTaskAcceptanceTime'] = $cstats['avg_task_acceptance_time'];
        $out['ReservationsAccepted'] = $cstats['reservations_accepted'];
        $out['ReservationsRejected'] = $cstats['reservations_rejected'];
        $out['ReservationsTimedOut'] = $cstats['reservations_timed_out'];
        $out['ReservationsCanceled'] = $cstats['reservations_canceled'];
        $out['ReservationsRescinded'] = $cstats['reservations_rescinded'];

        $out['TasksCreated'] = $cstats['tasks_created'];
        $out['TasksCanceled'] = $cstats['tasks_canceled'];
        $out['TasksCompleted'] = $cstats['tasks_completed'];
        $out['TasksDeleted'] = $cstats['tasks_deleted'];
        $out['TasksMoved'] = $cstats['tasks_moved'];
        $out['TasksTimedOutInWorkflow'] = $cstats['tasks_timed_out_in_workflow'];
        $out['WaitDurationUntilAccepted'] = $cstats['wait_duration_until_accepted'];
        $out['*ActivityStatistics'] = $rstats['activity_statistics'];

        $time_end = microtime(true);

        if ($this->option('debug')) {
            Log::debug('Took getWorkspaceStats ' . ($time_end - $time_start) . ' sec(s)');
        }

        return $out;
    }
}
