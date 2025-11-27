<?php

namespace App\Console\Commands;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\TpvStaff;
use App\Models\TimeClock;
use App\Models\StatsTpvAgent;
use App\Models\Interaction;

class BuildTpvAgentStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats:agent {--date=} {--interval=} {--incremental} {--intervals-back=} {--agent=} {--dryrun} {--debug} {--interval-table}';

    // Normal Mode 1: calculates the prior day totals and updates intervals
    // php artisan stats:agent

    // Normal Mode 2: calculates the prior 2 interval totals, i.e. if ran at 14:35 it would do the 14:00-14:15 and 14:15-14:30 intervals by default
    // Add the --intervals-back=N option to change the number of intervals it will look back.
    // php artisan stats:agent --incremental

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compiles statistics for all agents (or specified agent) for the date given or yesterday if not given';

    protected $isIncremental = false;
    protected $interval = 0;
    protected $dryRunResults = [];
    protected $isdebug = false;
    protected $agentGiven = false;
    protected $tz = 'America/Chicago';
    protected $intervals = [];
    protected $intervalsBack = 2;
    protected $carry = 0.0;
    protected $lastAgent = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->build_interval_table();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('interval-table')) {
            $this->info('Current Interval: ' . $this->get_current_interval());
            $this->print_interval_table();
            return 1;
        }
        if ($this->option('debug')) {
            $this->isdebug = true;
            $this->warn('DEBUG ENABLED');
        }
        $dryRun = false;
        if ($this->option('dryrun')) {
            $dryRun = true;
            $this->warn('DRY RUN - Nothing will be written to database.');
        }
        $runDate = Carbon::now($this->tz)->yesterday();
        if ($this->option('date') !== null) {
            $runDate = Carbon::parse($this->option('date') . ' 00:00:00', $this->tz);
            if ($this->isdebug) {
                $this->warn('DATE SET BY USER');
            }
        }
        if ($this->isdebug) {
            $this->info('runDate is ' . $runDate->format('Y-m-d'));
        }
        $wantedAgent = null;
        if ($this->option('agent') !== null) {
            $this->agentGiven = true;
            $wantedAgent = $this->option('agent');
        }
        if ($this->option('interval') !== null) {
            if (is_numeric($this->option('interval'))) {
                $this->interval = intval($this->option('interval'));
                if ($this->isdebug) {
                    $this->warn('Processing Interval ' . $this->interval . ', ' . $this->intervals[$this->interval][0] . ' to ' . $this->intervals[$this->interval][1]);
                }
            } else {
                $this->error('Interval must be an integer from 1 to 96');
                return 31;
            }
        }
        if ($this->option('incremental') !== false) {
            if ($this->isdebug) {
                $this->warn('Incremental Mode');
            }
            $this->isIncremental = true;
        }
        if ($this->isIncremental && $this->option('date') !== null) {
            $this->error('Incremental mode is not available when the date is specified');
            return 34;
        }
        if ($this->isIncremental && $this->interval > 0) {
            $this->error('Incremental mode is not available when an interval is specified');
            return 35;
        }
        if (is_numeric($this->option('intervals-back'))) {
            $this->intervalsBack = intval($this->option('intervals-back'));
        }
        if ($this->isdebug && $this->isIncremental) {
            $this->warn('Looking ' . $this->intervalsBack . ' intervals back');
        }

        $agents = TpvStaff::where('status', 1);
        if (!empty($wantedAgent)) {
            $agents = $agents->where('id', $wantedAgent);
        }
        $agents = $agents->get();
        $agent_cnt = $agents->count();

        if ($agent_cnt === 0) {
            $this->warn('No Active Agents');
            return 1;
        }

        $currentInterval = $this->get_current_interval();
        if ($this->isdebug && $this->isIncremental) {
            $this->warn('Current Interval: ' . $currentInterval);
            $this->warn('Processing Intervals ' . ($currentInterval - $this->intervalsBack) . ' to ' . ($currentInterval - 1));
        }

        $bar = $this->output->createProgressBar($agent_cnt);
        if ($wantedAgent === null) {
            $this->info('Processing Active Agents');
        } else {
            $this->info('Processing Requested Agent');
        }
        $bar->start();
        foreach ($agents as $agent) {
            if ($this->isIncremental) { // incremental mode
                for ($_interval = $currentInterval - $this->intervalsBack; $_interval < $currentInterval; $_interval++) {
                    $this->process_agent($agent, $runDate, $_interval, !$dryRun);
                }
            } elseif ($this->interval > 0) { // just doing this one interval
                $this->process_agent($agent, $runDate, $this->interval, !$dryRun);
            } else {
                //if (!$dryRun) {
                for ($_interval = 23; $_interval < 96; $_interval++) { // only build stats from 05:30 to 23:45
                    $this->process_agent($agent, $runDate, $_interval, !$dryRun);
                }
                //}
                $this->process_agent($agent, $runDate, 0, !$dryRun); // 0 interval is the total for the day since our intervals start at 1
            }
            $bar->advance();
        }
        $bar->finish();

        if ($dryRun) {
            $this->line('');
            $this->table(['Agent', 'Interval', 'Calls', 'Hours', 'Billable', 'Calls P/H', 'Occupancy', 'Agent Cost', 'Rev p/h'], $this->dryRunResults);
        }
    }

    private function process_agent(TpvStaff $agent, Carbon $date, int $interval, bool $createRecord = true)
    {
        if ($this->lastAgent !== $agent->id) {
            $this->carry = 0.0;
        }
        $iRange = null;
        if ($interval > 0) {
            $iRange = $this->interval_to_range($interval, $date);
        }
        $existing = true;
        $sta = StatsTpvAgent::where('tpv_staff_id', $agent->id)->whereDate('stats_date', $date)->where('interval', $interval)->first();
        if (empty($sta)) {
            $existing = false;
            $sta = new StatsTpvAgent();
            $sta->stats_date = $date->format('Y-m-d');
            $sta->tpv_staff_id = $agent->id;
            $sta->interval = $interval;
        }

        $sta->total_calls = $this->get_total_calls_for_agent($agent, $date, $iRange);
        $sta->total_hours = $this->get_total_hours_for_agent($agent, $date, $iRange);
        $sta->billable_time = $this->get_billable_time_for_agent($agent, $date, $iRange);

        $sta->calls_per_hour = $this->get_avg_calls_per_hour($sta->total_calls, $sta->total_hours);
        $sta->productive_occupancy = $this->get_productive_occupancy($sta->billable_time, $sta->total_hours);
        $sta->avg_revenue_per_payroll_hour = $this->get_avg_revenue_per_payroll_hour($sta->billable_time, $sta->total_hours);
        $sta->agent_cost = $this->get_avg_cost_per_hour($sta->total_hours);

        if ($createRecord) { // only set to false if --dryrun is set
            if ($existing && $sta->total_calls == 0 && $sta->total_hours == 0) {
                $sta->delete();
            } else {
                if ($sta->total_calls > 0 && $sta->total_hours > 0) {
                    $sta->save();
                }
            }
        } else {
            $this->dryRunResults[] = [
                $agent->first_name . ' ' . $agent->last_name,
                $interval == 0 ? 'All Day' : $interval,
                $sta->total_calls,
                $this->debug_formatter($sta->total_hours),
                $this->debug_formatter($sta->billable_time),
                $this->debug_formatter($sta->calls_per_hour),

                $this->debug_formatter($sta->productive_occupancy) . '%',
                '$' . $this->debug_formatter($sta->agent_cost),
                '$' . $this->debug_formatter($sta->avg_revenue_per_payroll_hour),
            ];
        }
        $this->lastAgent = $agent->id;
    }

    private function get_current_interval(): int
    {
        $now = Carbon::now($this->tz)->format('H:i:s');
        foreach ($this->intervals as $interval => $range) {
            if ($now >= $range[0] && $now <= $range[1]) {
                return $interval;
            }
        }
        return 0;
    }

    private function print_interval_table()
    {
        $out = [];
        foreach ($this->intervals as $interval => $range) {
            $out[] = [$interval, $range[0], $range[1]];
        }
        $this->table(['Interval', 'Start', 'End'], $out);
    }

    private function debug_formatter(float $value)
    {
        if ($value == 0) {
            return '0';
        }
        if (intval($value) == $value) {
            return intval($value);
        }
        return sprintf('%01.2f', $value);
    }

    private function build_interval_table()
    {
        $cnt = 1;

        for ($hour = 0; $hour < 24; $hour++) {
            if ($hour < 10) {
                $realHour = '0' . $hour;
            } else {
                $realHour = $hour;
            }
            for ($fmi = 0; $fmi < 4; $fmi++) {
                switch ($fmi) {
                    default:
                    case 0:
                        $this->intervals[$cnt] = [$realHour . ':00:00', $realHour . ':14:59'];
                        break;
                    case 1:
                        $this->intervals[$cnt] = [$realHour . ':15:00', $realHour . ':29:59'];
                        break;
                    case 2:
                        $this->intervals[$cnt] = [$realHour . ':30:00', $realHour . ':44:59'];
                        break;
                    case 3:
                        $this->intervals[$cnt] = [$realHour . ':45:00', $realHour . ':59:59'];
                        break;
                }

                $cnt++;
            }
        }
    }

    private function interval_to_range(int $interval, Carbon $date): array
    {
        if (isset($this->intervals[$interval])) {
            $times = $this->intervals[$interval];
            return [
                Carbon::parse($date->format('Y-m-d') . ' ' . $times[0], $this->tz),
                Carbon::parse($date->format('Y-m-d') . ' ' . $times[1], $this->tz)
            ];
        }

        throw new \RuntimeException('Invalid interval');
    }

    private function get_calls_for_agent(TpvStaff $agent, Carbon $date, $range = null): Builder
    {
        $results = Interaction::select(
            'interactions.tpv_staff_id',
            'interactions.event_id',
            'interactions.id',
            'interactions.parent_interaction_id',
            'interactions.interaction_type_id',
            'interactions.interaction_time',
            'interactions.created_at'
        )->whereIn('interaction_type_id', [1, 2, 6])
            ->whereDate('interactions.created_at', $date)
            ->where('interactions.tpv_staff_id', $agent->id);

        if (!empty($range)) {
            $results = $results->whereBetween('interactions.created_at', $range);
        }
        return $results;
    }

    private function get_total_calls_for_agent(TpvStaff $agent, Carbon $date, $range = null): int
    {
        return $this->get_calls_for_agent($agent, $date, $range)->whereNull('interactions.parent_interaction_id')->get()->count();
    }

    private function get_total_hours_for_agent(TpvStaff $agent, Carbon $date, $range = null): float
    {
        $punches = TimeClock::where('tpv_staff_id', $agent->id)->whereDate('time_punch', $date)->orderby('time_punch', 'asc')->get();
        $punchIn = null;
        $punchOut = null;
        if (empty($range)) { // empty range means the whole day
            $outTime = 0.0;

            foreach ($punches as $punch) {
                if (empty($punchIn)) {
                    $punchIn = new Carbon($punch->time_punch, $this->tz);

                    continue;
                }
                if (!empty($punchIn) && empty($punchOut)) {

                    $punchOut = new Carbon($punch->time_punch, $this->tz);
                }
                if (!empty($punchIn) && !empty($punchOut)) {
                    $outTime += $punchIn->floatDiffInMinutes($punchOut);
                    $punchIn = null;
                    $punchOut = null;
                }
            }
            if (!empty($punchIn) || !empty($punchOut)) {
                $this->warn('Agent ' . $agent->first_name . ' ' . $agent->last_name . ' ' . $agent->id . ' is still clocked in.');
            }
            return $outTime / 60;
        }



        // now we figure out if the agent was clocked in for the interval and if so, how much of the interval (max is 0.25 for 15 minutes)
        $now = Carbon::now($this->tz);
        $workedMinutes = 0.0;

        foreach ($punches as $punch) {
            $setFirst = false;
            if (empty($punchIn)) {
                $setFirst = true;
                $punchIn = new Carbon($punch->time_punch, $this->tz);
            }
            if (count($punches) == 1) {

                $punchOut = $now;
            } else {
                if ($setFirst) {
                    continue;
                }
            }

            if (!empty($punchIn) && empty($punchOut)) {

                $punchOut = new Carbon($punch->time_punch, $this->tz);
            }
            if (!empty($punchIn) && !empty($punchOut)) {

                if ($range[0]->isAfter($punchIn) && $range[1]->isBefore($punchOut)) {

                    return 0.25;
                }
                if ($range[0]->isAfter($punchIn) && $range[1]->isBefore($punchOut)) {

                    $workedMinutes += $range[0]->floatDiffInMinutes($punchOut);
                    $punchIn = null;
                    $punchOut = null;
                    continue;
                }
                if ($punchIn->isAfter($range[0]) && $punchIn->isBefore($range[1])) {

                    $workedMinutes += $punchIn->floatDiffInMinutes($range[1]);
                    $punchIn = null;
                    $punchOut = null;
                    continue;
                }
                if ($punchIn->isAfter($range[0]) && $punchOut->isBefore($range[1])) {

                    $workedMinutes += $punchIn->floatDiffInMinutes($punchOut);
                    $punchIn = null;
                    $punchOut = null;
                    continue;
                }
                if ($punchOut->isAfter($range[0]) && $punchOut->isBefore($range[1])) {

                    $workedMinutes += $range[0]->floatDiffInMinutes($punchOut);
                    $punchIn = null;
                    $punchOut = null;
                    continue;
                }

                $punchIn = null;
                $punchOut = null;
            }
        }
        if ($workedMinutes > 0) {
            return $workedMinutes / 60;
        }


        return 0.0;
    }

    private function get_billable_time_for_agent(TpvStaff $agent, Carbon $date, $range = null): float
    {
        $carrySet = false;
        $interactions = $this->get_calls_for_agent($agent, $date, $range)->get();
        if (empty($range)) {
            $this->carry = 0.0;
            return $interactions->sum('interaction_time') / 60;
        }

        $outtime = 0.0;
        $now = Carbon::now($this->tz);
        foreach ($interactions as $interaction) {
            if ($interaction->created_at->timezone !== 'America/Chicago') {
                $created_at = Carbon::parse($interaction->created_at->format('Y-m-d H:i:s'), $this->tz);
            } else {
                $created_at = $interaction->created_at;
            }
            $itime = $interaction->interaction_time;
            if (empty($itime)) {
                if ($now->isBefore($range[1])) {
                    $iend = $now;
                } else {
                    $iend = $range[1];
                }
            } else {
                $iend = Carbon::parse($created_at->format("Y-m-d H:i:s"), $this->tz)->addMinutes($itime);
            }
            if ($iend->isAfter($range[1])) {
                $carrySet = true;
                $this->carry = $range[1]->floatDiffInMinutes($iend);
                $outtime += ($interaction->interaction_time - $this->carry);
            } else {
                $outtime += $created_at->floatDiffInMinutes($iend);
            }
        }
        if (!$carrySet && $this->carry > 0) {
            $outtime += $this->carry;
            $this->carry = 0.0;
        }
        return $outtime / 60;
    }

    private function get_avg_calls_per_hour(int $total_calls, float $total_hours): float
    {
        if ($total_hours != 0) {
            return $total_calls / $total_hours;
        }
        return 0.0;
    }

    private function get_productive_occupancy(float $billable_time, float $total_hours): float
    {
        if ($total_hours != 0) {
            return ($billable_time / $total_hours) * 100;
        }
        return 0.0;
    }

    private function get_avg_revenue_per_payroll_hour(float $billable_time, float $total_hours): float
    {
        if ($total_hours != 0) {
            $avg_bill_rate_per_minute = 0.732;
            return (
                ($billable_time * 60) // $billable_time is in hours so convert to minutes
                * $avg_bill_rate_per_minute)
                / $total_hours;
        }
        return 0.0;
    }

    private function get_avg_cost_per_hour(float $total_hours): float
    {
        if ($total_hours > 0) {
            $avg_cost_per_hour = 11.18;
            return $total_hours * $avg_cost_per_hour;
        }
        return 0.0;
    }
}
