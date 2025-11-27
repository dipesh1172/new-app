<?php

namespace App\Console\Commands;

use App\Http\Controllers\TwilioController;
use App\Models\JsonDocument;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Twilio\Rest\Client;

class TwilioLiveAgents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:live-agents {--count=8}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets live agent stats';

    private $_client;
    private $_workspace;
    private $_workspace_id;

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
        $this->_client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $this->_workspace_id = config('services.twilio.workspace');
        $this->_workspace = $this->_client->taskrouter
            ->workspaces($this->_workspace_id);

        $count = $this->option('count');
        for ($i = 0; $i < $count; $i++) {

            $this->getStats();

            if ($i != $count - 1) {
                sleep(5);
            }
        }
    }

    private function getStats()
    {
        $twilio = new TwilioController();
        $calls = $twilio->calls();

        $workers = [];
        try {
            $all_workers = $twilio->get_workers_raw();
            foreach ($all_workers as $worker) {
                if ($worker->activityName != 'Logged Out') {
                    $staff = Cache::remember(
                        'staff_' . $worker->friendlyName,
                        100,
                        function () use ($worker) {
                            $staff = DB::table(
                                'tpv_staff as a'
                            )->join(
                                DB::raw('(select tpv_staff_id, MAX(time_punch) time_punch from time_clocks group by tpv_staff_id) as b'),
                                'a.id',
                                'b.tpv_staff_id'
                            )->leftJoin(
                                'call_centers',
                                'a.call_center_id',
                                'call_centers.id'
                            )->leftJoin(
                                'tpv_staff_groups',
                                'tpv_staff_groups.id',
                                'a.tpv_staff_group_id'
                            )->where(
                                'a.username',
                                $worker->friendlyName
                            )->where(
                                'a.username',
                                '!=',
                                'ebbenfeagan'
                            )->select(
                                'a.id',
                                'a.username',
                                'a.first_name',
                                'a.last_name',
                                'call_centers.call_center',
                                'b.time_punch',
                                'tpv_staff_groups.group as skills',
                                'a.tpv_staff_group_id',
                                DB::raw("(select count(event_id) FROM interactions WHERE interactions.tpv_staff_id=a.id AND interactions.created_at >= '" . Carbon::now('America/Chicago')->startOfDay()->format('Y-m-d H:i:s') . "' AND interactions.created_at <= '" . Carbon::now('America/Chicago')->endOfDay()->format('Y-m-d H:i:s') . "') as calls")
                            );

                            $staff = $staff->first();

                            return $staff;
                        }
                    );

                    if ($staff) {
                        $hours_worked = ($this->get_seconds_worked_today($staff->id) / 60 / 60);
                        $current_call = 'N/A';
                        $client_id = "client:{$staff->username}";
                        $call_type = '';
                        for ($i = 0; $i < count($calls); $i++) {
                            if ($calls[$i]['to'] == $client_id && $calls[$i]['direction'] === 'outbound-api') {
                                $call_type = '(TPV)';
                                $current_call = $calls[$i]['from'];
                                unset($calls[$i]);
                                $calls = array_values($calls);
                                break;
                            } elseif ($calls[$i]['direction'] === 'outbound-dial') {
                                $call_type = '(Survey)';
                                $key = array_search($calls[$i]['parentCallSid'], array_column($calls, 'sid'));
                                if ($key) {
                                    if ($client_id == $calls[$key]['from']) {
                                        $current_call = $calls[$i]['from'];
                                        unset($calls[$i]);
                                        unset($calls[$key]);
                                        $calls = array_values($calls);
                                        break;
                                    }
                                }
                            }
                        }

                        if ($current_call != 'N/A') {
                            $brand = Cache::remember(
                                'brand_' . $current_call,
                                7200,
                                function () use ($current_call) {
                                    return DB::table(
                                        'dnis'
                                    )->leftJoin(
                                        'brands',
                                        'brands.id',
                                        'dnis.brand_id'
                                    )->where(
                                        'dnis',
                                        $current_call
                                    )->select(
                                        'brands.name'
                                    )->first();
                                }
                            );

                            if (count($brand) > 0) {
                                $current_call = $brand->name . ' ' . $call_type;
                            }
                        }

                        $carbon_time = Carbon::createFromTimestamp($worker->dateStatusChanged->getTimestamp());
                        $row = [
                            'tpv_id' => $staff->id,
                            'id' => $worker->friendlyName,
                            'name' => $staff->first_name . ' ' . $staff->last_name,
                            'status' => $worker->activitySid,
                            'status_name' => $worker->activityName,
                            'status_id' => $worker->sid,
                            'location' => $staff->call_center,
                            'status_changed_at' => $carbon_time->tz('America/Chicago'),
                            'tpv_staff_group_id' => $staff->tpv_staff_group_id,
                            'calls' => $staff->calls,
                            'CPH' => ($hours_worked > 0 ? ($staff->calls / $hours_worked) : 0),
                            'hours_worked' => $hours_worked,
                            'current_call' => $current_call,
                            'skill_name' => $staff->skills
                        ];
                        $workers[] = $row;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->info("Error getting worker data: " . $e);
        }

        if (count($workers) > 0) {
            $j = new JsonDocument();
            $j->document = $workers;
            $j->document_type = 'live-agent-stats';
            $j->save();
        }

        Cache::forget('live-agent-stats');
    }

    private function get_seconds_worked_today($tpv_staff_id)
    {
        $time_clocks = DB::table(
            'time_clocks'
        )->where(
            'tpv_staff_id',
            $tpv_staff_id
        )->where(
            'created_at',
            '>=',
            Carbon::now('America/Chicago')->startOfDay()
        )->where(
            'created_at',
            '<=',
            Carbon::now('America/Chicago')->endOfDay()
        )->whereIn(
            'agent_status_type_id',
            ['1', '2']
        )->select(
            'created_at',
            'time_punch',
            'agent_status_type_id'
        )->orderBy(
            'created_at'
        )->get();

        if ($time_clocks->isEmpty()) {
            return 0;
        }

        $punches = [];
        foreach ($time_clocks as $time_clock) {
            $this_punch = [
                'timestamp' => Carbon::parse($time_clock->time_punch, 'America/Chicago')->timestamp,
                'direction' => $time_clock->agent_status_type_id,
            ];
            $punches[] = $this_punch;
        }

        $logged_in = false;
        $seconds = 0;
        for ($i = 0; $i < count($punches); ++$i) {
            if ($logged_in && $punches[$i]['direction'] == 2) {
                $seconds += ($punches[$i]['timestamp'] - $punches[$i - 1]['timestamp']);
                $logged_in = false;
            } elseif ($logged_in && $i == count($punches) - 1) {
                $seconds += (Carbon::now('America/Chicago')->timestamp - $punches[$i]['timestamp']);
            } else {
                $logged_in = true;
            }
        }

        return $seconds;
    }
}
