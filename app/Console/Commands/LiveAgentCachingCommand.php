<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\TimeClock;
use App\Models\JsonDocument;
use App\Http\Controllers\TwilioController;

class LiveAgentCachingCommand extends Command
{
    protected $caching_timeout = 30000;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liveagent:dashboard {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches stats used for the liveagent dashboard';

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
        $twilio = new TwilioController();
        $calls = $twilio->calls();

        $workers = [];
        try {
            $all_workers = $twilio->get_workers_raw();
            foreach ($all_workers as $worker) {
                if ($worker->activityName != 'Logged Out') {
                    $staff = Cache::remember(
                        'staff_'.$worker->friendlyName,
                        100,
                        function () use ($worker) {
                            return DB::table(
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
                            )->select(
                                'a.id',
                                'a.username',
                                'a.first_name',
                                'a.last_name',
                                'call_centers.call_center',
                                'b.time_punch',
                                'tpv_staff_groups.group as skills',
                                'a.tpv_staff_group_id',
                                DB::raw("(select count(event_id) FROM interactions WHERE interactions.tpv_staff_id=a.id AND interactions.created_at >= '".Carbon::now('America/Chicago')->startOfDay()->format('Y-m-d H:i:s')."' AND interactions.created_at <= '".Carbon::now('America/Chicago')->endOfDay()->format('Y-m-d H:i:s')."') as calls")
                            )->first();
                        }
                    );

                    if ($this->option('debug')) {
                        info(print_r($staff, true));
                    }

                    if ($staff) {
                        $hours_worked = ($this->get_seconds_worked_today($staff->id) / 60 / 60);
                        $current_call = '';
                        $client_id = "client:{$staff->username}";
                        $call_type = '';
                        for ($i = 0; $i < count($calls); ++$i) {
                            if ($calls[$i]['to'] == $client_id && $calls[$i]['direction'] === 'outbound-api') {
                                $call_type = '(TPV)';
                                $current_call = $calls[$i]['from'];
                                //unset($calls[$i]);
                                //$calls = array_values($calls);
                                break;
                            } elseif ($calls[$i]['direction'] === 'outbound-dial') {
                                $call_type = '(Survey)';
                                $key = array_search($calls[$i]['parentCallSid'], array_column($calls, 'sid'));
                                if ($key) {
                                    if ($client_id == $calls[$key]['from']) {
                                        $current_call = $calls[$i]['from'];
                                        //unset($calls[$i]);
                                        //unset($calls[$key]);
                                        //$calls = array_values($calls);
                                        break;
                                    }
                                }
                            }
                        }

                        if ($current_call !== '') {
                            if ($current_call === '+18882000961') {
                                $current_call = 'Outgoing';
                            } else {
                                $brand = Cache::remember(
                                    'brand_'.$current_call,
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
                                    $current_call = $brand->name.' '.$call_type;
                                }
                            }
                        }

                        $carbon_time = Carbon::createFromTimestamp($worker->dateStatusChanged->getTimestamp());
                        $row = [
                            'tpv_id' => $staff->id,
                            'id' => $worker->friendlyName,
                            'name' => $staff->first_name.' '.$staff->last_name,
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
                        ];
                        $workers[] = $row;
                    }
                }
            }
        } catch (\Exception $e) {
            return;
        }

        if ($this->option('debug')) {
            info(print_r($workers, true));
        } else {
            try {
                $newJ = new JsonDocument();
                $newJ->document_type = 'live-agent-stats';
                $newJ->document = $workers;
                $newJ->save();
                Cache::forget('live-agent-stats');
            } catch (\Exception $e) {
                $this->info('Unable to write to the database.');
                info('Unable to write to the database.');
            }
        }
    }

    public function get_seconds_worked_today($tpv_staff_id)
    {
        $time_clocks = TimeClock::select(
            'created_at',
            'time_punch',
            'agent_status_type_id'
        )->where(
            'tpv_staff_id',
            $tpv_staff_id
        )->where(
            'created_at',
            '>=',
            Carbon::now('America/Chicago')->startOfDay()->format('Y-m-d H:i:s')
        )->where(
            'created_at',
            '<=',
            Carbon::now('America/Chicago')->endOfDay()->format('Y-m-d H:i:s')
        )->orderBy(
            'created_at'
        )->get();

        if ($this->option('debug')) {
            info(print_r($time_clocks->toArray(), true));
        }

        if ($time_clocks->isEmpty()) {
            info($tpv_staff_id.' time clocks is empty');

            return 0;
        }

        $punches = [];
        foreach ($time_clocks as $time_clock) {
            $punches[] = [
                'timestamp' => Carbon::parse($time_clock->time_punch, 'America/Chicago')->timestamp,
                'direction' => $time_clock->agent_status_type_id,
            ];
        }

        $logged_in = false;
        $seconds = 0;
        for ($i = 0; $i < count($punches); ++$i) {
            if ($logged_in && $punches[$i]['direction'] % 2 === 1) {
                $seconds += ($punches[$i]['timestamp'] - $punches[$i - 1]['timestamp']);
                $logged_in = false;
            } else {
                $logged_in = true;
            }
        }
        if ($logged_in) {
            $seconds += (Carbon::now('America/Chicago')->timestamp - $punches[count($punches) - 1]['timestamp']);
        }
        if ($seconds === 0) {
            info('Timepunches exist for '.$tpv_staff_id.' yet they have 0 working time');
        }

        return $seconds;
    }
}
