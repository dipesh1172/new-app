<?php

namespace App\Http\Controllers;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Traits\CSVResponseTrait;
use App\Models\TpvStaff;
use App\Models\JsonDocument;
use App\Models\AgentStatus;

class WorkAtHomeController extends Controller
{
    use CSVResponseTrait;

    public function skills_select_options()
    {
        $skills = Cache::remember(
            'agent_skills',
            7200,
            function () {
                return DB::table(
                    'tpv_staff_groups'
                )->where(
                    'config',
                    '!=',
                    'null'
                )->orderBy(
                    'id'
                )->get()->pluck(
                    'group',
                    'id'
                )->all();
            }
        );

        asort($skills);

        return $skills;
    }

    public function statuses_select_options()
    {
        $statuses = Cache::remember(
            'agent_statuses',
            7200,
            function () {
                $client = new Client(
                    config('services.twilio.account'),
                    config('services.twilio.auth_token')
                );
                $workspace = $client->taskrouter->workspaces(config('services.twilio.workspace'));

                $statuses = [];
                $activities = $workspace->activities->read();
                foreach ($activities as $activity) {
                    $statuses[$activity->sid] = $activity->friendlyName;
                }

                return $statuses;
            }
        );

        asort($statuses);

        return $statuses;
    }

    private function search_skills_select_options()
    {
        $skills = Cache::remember(
            'search_skills',
            7200,
            function () {
                $this->_client = new Client(
                    config('services.twilio.account'),
                    config('services.twilio.auth_token')
                );
                $this->_workspace_id = config('services.twilio.workspace');
                $this->_workspace = $this->_client->taskrouter
                    ->workspaces($this->_workspace_id);

                $skills = [];
                $taskQueues = $this->_workspace->taskQueues->read();
                foreach ($taskQueues as $taskQueue) {
                    $skills[$taskQueue->friendlyName] = $taskQueue->friendlyName;
                }

                asort($skills);

                return $skills;
            }
        );

        return $skills;
    }

    public function get_seconds_worked_today($tpv_staff_id, $request)
    {
        $time_clocks = DB::table(
            'time_clocks'
        )->where(
            'tpv_staff_id',
            $tpv_staff_id
        )->where(
            'created_at',
            '>=',
            $this->getDateFrom($request->date_from)
        )->where(
            'created_at',
            '<=',
            $this->getDateTo($request->date_from)
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

    public function get_live_agents()
    {
        $j = JsonDocument::where(
            'document_type',
            'live-agent-stats'
        )->orderBy(
            'created_at',
            'desc'
        )->first();
        if ($j) {
            return response()->json($j->document);
        }

        return response()->json(null);
    }

    public function get_not_ready_agents()
    {
        $j = JsonDocument::where(
            'document_type',
            'not-ready-agent-stats'
        )->orderBy(
            'created_at',
            'desc'
        )->first();
        if ($j) {
            return response()->json($j->document);
        }

        return response()->json(null);
    }

    public function agent_activity_log($id = null, Request $request)
    {
        $statuses = [];
        if (!$id) {
            $id = $request->id;
        }

        if ($id) {
            $activity = DB::table(
                'tpv_staff'
            )->join(
                'agent_statuses',
                'tpv_staff.id',
                'agent_statuses.tpv_staff_id'
            )->join(
                'tpv_staff_roles',
                'tpv_staff.role_id',
                'tpv_staff_roles.id'
            )->where(
                'tpv_staff.id',
                $id
            )->whereRaw(
                'date(agent_statuses.created_at)=CURDATE()'
            )->whereIn(
                'agent_statuses.event',
                [
                    'Available', 'Logged in', 'Logged Out', 'Not Ready',
                    'TPV In Progress', 'After Call Work',
                    'Start No Sale Countdown', 'Meal', 'Time Punch',
                    'Unscheduled Break', 'Break', 'Meeting',
                ]
            )->orderBy(
                'created_at',
                'ASC'
            )->select(
                'tpv_staff.username',
                'tpv_staff.first_name',
                'tpv_staff.last_name',
                'agent_statuses.event',
                'agent_statuses.client_name',
                'agent_statuses.created_at',
                'tpv_staff_roles.name as role'
            )->get();

            for ($i = 0; $i < count($activity); ++$i) {
                $duration = '';
                if ($i < count($activity) - 1) {
                    $duration = Carbon::parse($activity[$i]->created_at, 'America/Chicago')->diffAsCarbonInterval($activity[$i + 1]->created_at)->format('%h:%i:%s');
                } else {
                    $duration = Carbon::parse($activity[$i]->created_at, 'America/Chicago')->diffAsCarbonInterval(Carbon::now('America/Chicago'))->format('%h:%i:%s');
                }

                $row = [
                    'tpv_agent_name' => ($activity[$i]->first_name . ' ' . $activity[$i]->last_name),
                    'tpv_agent_id' => $activity[$i]->username,
                    'tpv_agent_role' => $activity[$i]->role,
                    'status' => $activity[$i]->event,
                    'brand' => $activity[$i]->client_name,
                    'timestamp' => Carbon::parse($activity[$i]->created_at)->format('M d Y g:i:s A'),
                    'duration' => Carbon::parse($duration)->format('i:s'),
                ];

                $statuses[] = $row;
            }
            if ($request->submitbutton === 'Export CSV') {
                return $this->csv_response(
                    $statuses,
                    'Agent_Activity_Log' . ($id ? '_' . $id : ''),
                    ['Agent Name', 'Agent ID', 'Agent Role', 'Status', 'Brand', 'Timestamp', 'Duration']
                );
            } else {
                return view(
                    'reports.agent_activity_log',
                    [
                        'statuses' => collect($statuses),
                        'id' => $id,
                    ]
                );
            }
        }

        return back();
    }

    public function agent_summary_report(Request $request)
    {
        if (!$request->get_json && !$request->csv) {
            return view('generic-vue')->with(
                [
                    'componentName' => 'agent-summary',
                    'title' => 'Report: Agent Summary Report'
                ]
            );
        }

        $date_from = $request->startDate
            ? Carbon::parse($request->startDate)->startOfDay()
            : Carbon::today()->startOfDay()->toIso8601String();
        $date_to = $request->endDate
            ? Carbon::parse($request->endDate)->endOfDay()
            : Carbon::today()->endOfDay()->toIso8601String();
        $column = $request->column;
        $direction = $request->direction;
        $location_filter = $request->locationFilter;
        $agent_name_filter = $request->agentNameFilter;

        $workers = DB::table(
            'tpv_staff'
        )->where(
            'tpv_staff.status',
            '1'
        )->whereNull(
            'tpv_staff.deleted_at'
        );

        if ($location_filter) {
            $workers = $workers->where('tpv_staff.call_center_id', $location_filter);
        }

        if ($agent_name_filter) {
            $workers = $workers->where(
                'tpv_staff.first_name',
                'LIKE',
                "%{$agent_name_filter}%"
            )->orWhere(
                'tpv_staff.last_name',
                'LIKE',
                "%{$agent_name_filter}%"
            );
        }

        $workers = $workers->select(
            'tpv_staff.id',
            DB::raw("CONCAT(tpv_staff.first_name, ' ', tpv_staff.last_name) as tpv_agent_name"),
            'tpv_staff.username as tpv_id',
            'sc.status_count',
            DB::raw("(SELECT COUNT(interactions.event_id) FROM interactions WHERE interactions.tpv_staff_id=tpv_staff.id AND interactions.created_at between '{$date_from}' AND '{$date_to}') as calls")
        )->leftJoin(DB::raw("(SELECT COUNT(*) as status_count, agent_statuses.tpv_staff_id FROM agent_statuses WHERE agent_statuses.created_at between '{$date_from}' AND '{$date_to}' group by agent_statuses.tpv_staff_id) as sc"), function ($join) {
            $join->on('sc.tpv_staff_id', '=', 'tpv_staff.id');
        })->get();

        $agents = [];
        foreach ($workers as $worker) {
            if ($worker->status_count > 0) {
                $statuses = DB::table(
                    'agent_statuses'
                )->where(
                    'tpv_staff_id',
                    $worker->id
                )->where(
                    'created_at',
                    '>=',
                    $date_from
                )->where(
                    'created_at',
                    '<=',
                    $date_to
                )->whereIn(
                    'event',
                    [
                        'Available', 'Logged in', 'Logged Out', 'Not Ready',
                        'TPV In Progress', 'After Call Work',
                        'Start No Sale Countdown', 'Meal', 'Time Punch',
                        'Unscheduled Break', 'Break', 'Meeting',
                    ]
                )->select(
                    'event as status',
                    'created_at as timestamp'
                )->orderBy(
                    'created_at',
                    'ASC'
                )->get();

                $hours_worked = round(($this->get_seconds_worked_today($worker->id, $request) / 60 / 60), 2);
                if ($hours_worked > 0) {
                    $calls_per_hour = round(($worker->calls / $hours_worked), 2);
                } else {
                    $calls_per_hour = 'N/A';
                }

                $row = [
                    'tpv_agent_name' => $worker->tpv_agent_name,
                    'tpv_staff_id' => $worker->id,
                    'tpv_id' => $worker->tpv_id,
                    'logged_in' => 0.00,
                    'available' => 0.00,
                    'tpv_in_progress' => 0.00,
                    'after_call_work' => 0.00,
                    'start_no_sale_countdown' => 0.00,
                    'reserved' => 0.00,
                    'meeting' => 0.00,
                    'meal' => 0.00,
                    'break' => 0.00,
                    'unscheduled_break' => 0.00,
                    'system' => 0.00,
                    'not_ready' => 0.00,
                    'training' => 0.00,
                    'coaching' => 0.00,
                    'other' => 0.00,
                    'calls' => $worker->calls,
                    'calls_per_hour' => $calls_per_hour,
                    'total_time' => 0.00,
                    'billable_time' => 0.00,
                    'hours_worked' => $hours_worked,
                    'billable_occupancy' => '100%',
                ];

                for ($i = 0; $i < count($statuses); ++$i) {
                    if ($i < count($statuses) - 1) {
                        $duration = Carbon::parse($statuses[$i]->timestamp, 'America/Chicago')->diffInSeconds($statuses[$i + 1]->timestamp);
                    } else {
                        $duration = Carbon::parse($statuses[$i]->timestamp, 'America/Chicago')->diffInSeconds(Carbon::now('America/Chicago'));
                    }
                    $event_name = $statuses[$i]->status;
                    $row['total_time'] += $duration;
                    switch ($event_name) {
                        case 'After Call Work':
                            $row['after_call_work'] += $duration;
                            $row['billable_time'] += $duration;
                            break;

                        case 'Agent Application Started and Logged In':
                        case 'Logged in':
                        case 'Time Punch':
                            $row['logged_in'] += $duration;
                            break;

                        case 'Available':
                            $row['available'] += $duration;
                            break;

                        case 'Break':
                            $row['break'] += $duration;
                            break;

                        case 'Meal':
                            $row['meal'] += $duration;
                            break;

                        case 'Meeting':
                            $row['meeting'] += $duration;
                            break;

                        case 'Not Ready':
                        case 'Logged Out':
                            $row['not_ready'] += $duration;
                            break;

                        case 'Reserved':
                            $row['reserved'] += $duration;
                            $row['billable_time'] += $duration;
                            break;

                        case 'Start No Sale Countdown':
                            $row['start_no_sale_countdown'] += $duration;
                            $row['billable_time'] += $duration;
                            break;

                        case 'System':
                            $row['system'] += $duration;
                            break;

                        case 'TPV In Progress':
                        case 'Call answered':
                        case 'Good Sale':
                        case 'Close Call':
                        case 'No Sale: Test Call':
                        case 'No Sale: Customer Changed Their Mind':
                        case 'No Sale: Sales Rep Did Not Leave Premises':
                        case 'No Sale: Call Disconnected':
                        case 'Call Disconnected':
                        case 'Call Offered':
                            $row['tpv_in_progress'] += $duration;
                            $row['billable_time'] += $duration;
                            break;

                        case 'Unscheduled Break':
                            $row['unscheduled_break'] += $duration;
                            break;

                        default:
                            info($event_name);
                            $row['other'] += $duration;
                            break;
                    }
                }

                if ($row['total_time'] != 0) {
                    $row['billable_occupancy'] = round(($row['billable_time'] / $row['total_time']) * 100, 2) . '%';
                } else {
                    $row['billable_occupancy'] = 0;
                }

                $row['after_call_work'] = $this->formatTime($row['after_call_work']);
                $row['available'] = $this->formatTime($row['available']);
                $row['break'] = $this->formatTime($row['break']);
                $row['meal'] = $this->formatTime($row['meal']);
                $row['not_ready'] = $this->formatTime($row['not_ready']);
                $row['meeting'] = $this->formatTime($row['meeting']);
                $row['reserved'] = $this->formatTime($row['reserved']);
                $row['start_no_sale_countdown'] = $this->formatTime($row['start_no_sale_countdown']);
                $row['system'] = $this->formatTime($row['system']);
                $row['tpv_in_progress'] = $this->formatTime($row['tpv_in_progress']);
                $row['unscheduled_break'] = $this->formatTime($row['unscheduled_break']);
                $row['logged_in'] = $this->formatTime($row['logged_in']);
                $row['training'] = $this->formatTime($row['training']);
                $row['coaching'] = $this->formatTime($row['coaching']);
                $row['other'] = $this->formatTime($row['other']);
                $row['billable_time'] = $this->formatTime($row['billable_time']);
                $row['total_time'] = $this->formatTime($row['total_time']);
                $agents[] = $row;
            }
        }

        if ($column && $direction) {
            $sort_type = ('desc' == $direction) ? 3 : 4;
            array_multisort(array_column($agents, $column), $sort_type, $agents);
        }

        if ($request->get_json) {
            return response()->json([
                'agents' => collect(array_values($agents))->paginate(25, null, '/agent-summary-report'),
                'supervisors' => $this->supervisors_select_options(),
            ]);
        }

        if ($request->csv) {
            return $this->csv_response(
                array_values(
                    $agents
                ),
                'AgentSummaryReport',
                [
                    'TPV Agent Name', 'TPV ID', 'Clocked In', 'Available', 'TPV in Progress', 'ACW', 'Start No Sale Countdown', 'Reserved',
                    'Billable Time', 'Meeting', 'Meal', 'Break', 'Unscheduled Break', 'System', 'Not Ready', 'Training', 'Coaching', 'Other',
                    'Calls', 'CPH', 'Billable Occupancy'
                ]
            );
        }
    }

    public function agent_status_summary(Request $request)
    {
        ini_set('memory_limit', '512M');

        $start_date = $request->startDate
            ? Carbon::parse($request->startDate, 'America/Chicago')->startOfDay()
            : Carbon::today('America/Chicago')->startOfDay()->toIso8601String();
        $end_date = $request->endDate
            ? Carbon::parse($request->endDate, 'America/Chicago')->endOfDay()
            : Carbon::today('America/Chicago')->endOfDay()->toIso8601String();

        if ($request->input('export_csv') != null) {

            $agents = DB::select("
                        select * from(
                            select
                                tpvs.username,
                                tpvs.first_name,
                                tpvs.last_name,
                                ags.event,
                                ags.client_name,
                                ags.created_at,
                                tpvsr.name as role,
                                '' as duration,
                                IFNULL(tpvs.deleted_at, '') as deleted_at
                            from agent_statuses ags
                            inner join tpv_staff tpvs on ags.tpv_staff_id = tpvs.id
                            inner join tpv_staff_roles tpvsr on tpvs.role_id = tpvsr.id
                            where (ags.created_at >= ? and ags.created_at <= ?)
                            and ags.event in (
                                'After Call Work',
                                'Agent Disconnected Call',
                                'Auto Dispositioned',
                                'Available',
                                'Break',
                                'Call answered',
                                'Call Disconnected',
                                'Call NOT answered : Cancelled',
                                'Call NOT answered: Timed Out',
                                'Close Call',
                                'Coaching',
                                'Disposition Filter Overridden',
                                'Good Sale',
                                'Incoming Outbound-only Call',
                                'Logged In',
                                'Logged Out',
                                'Meal',
                                'Meeting',
                                'Reservation Accepted',
                                'Unscheduled Break'
                            )) as tpv_details
                            where tpv_details.deleted_at = ''
                            order by tpv_details.username asc
                    ", [$start_date, $end_date]);

            $agents = json_decode(json_encode($agents), true);

            $formatted_start_date = Carbon::parse($start_date, 'America/Chicago')->format('m_d_Y');
            $formatted_end_date = Carbon::parse($end_date, 'America/Chicago')->format('m_d_Y');
            $agents = $this->calculate_durations($agents);
            $agents = array_values($agents);
            if (!count($agents)) {
                session()->flash('flash_message', 'There are no results for the selected dates');
                return back();
            }

            return $this->csv_response(
                $agents,
                'AgentStatusDetailReport (' . $formatted_start_date . '_to_' . $formatted_end_date . ')',
                [
                    'TPV Agent Name',
                    'First Name',
                    'Last Name',
                    'Event',
                    'Brand',
                    'Timestamp',
                    'Role',
                    'Duration'
                ]
            );
        }

        return view('generic-vue')->with(
            [
                'componentName' => 'agent-status-detail',
                'title' => 'Report: Agent Status Detail'
            ]
        );
    }

    private function remove_consecutive_statuses($a)
    {
        $a = (array)$a;
        $new_statuses = [];
        for ($i = 0; $i < count($a) - 1; $i++) {
            $current = $a[$i];
            $next = $a[$i + 1];
            if ($current['username'] === $next['username']) {
                if ($current['event'] !== $next['event']) {
                    $new_statuses[] = $current;
                }
            } else {
                $new_statuses[] = $current;
            }
        }
        return $new_statuses;
    }

    private function check_for_pending_status($a)
    {
        $new_statuses = [];
        for ($i = 0; $i < count($a) - 1; $i++) {
            $current = $a[$i];
            $next = $a[$i + 1];
            if ($current['username'] === $next['username']) {
                if ($current['client_name'] !== null && $next['client_name'] !== null) {
                    if ($current['event'] === 'Break') {
                        $current['event'] = 'Sent to Break';
                    }
                    if ($current['event'] === 'Meal') {
                        $current['event'] = 'Sent to Meal';
                    }
                    if ($current['event'] === 'Meeting') {
                        $current['event'] = 'Sent to Meeting';
                    }
                    if ($current['event'] === 'Unscheduled Break') {
                        $current['event'] = 'Sent to Unscheduled Break';
                    }
                }
            } else {
                $current['event'] = str_replace("Sent to ", "", $current['event']);
            }
            $new_statuses[] = $current;
        }

        return $new_statuses;
    }

    /** 
     * Calculate the duration of each status. Starts at the first record and does a time diff
     * between the current and next record.
     */
    private function calculate_status_duration($a)
    {
        $new_statuses = [];
        $last_timestamp = null;

        for ($i = 0; $i < count($a) - 1; $i++) {
            $current = $a[$i];
            $next = $a[$i + 1];
            $prev = $i > 0 ? $a[$i - 1] : null;

            if ($current['username'] === $next['username']) {
                if (!isset($prev) || $current['username'] === $prev['username']) {

                    $current_timestamp = Carbon::parse($current['created_at'], 'America/Chicago')->timestamp;
                    $next_timestamp = Carbon::parse($next['created_at'], 'America/Chicago')->timestamp;

                    // if ($last_timestamp === null) {
                    //     $last_timestamp = $current_timestamp;
                    // }

                    // $diff = $next_timestamp - $last_timestamp;
                    $diff = $next_timestamp - $current_timestamp;
                    switch ($current['event']) {
                        case "Sent to Break":
                        case "Sent to Meal":
                        case "Sent to Meeting":
                        case "Sent to Unscheduled Break":
                            $current['duration'] = gmdate("H:i:s", 0);
                            break;

                        default:
                            $current['duration'] = gmdate("H:i:s", $diff);
                            //$last_timestamp = $current_timestamp;
                            break;
                    }
                } else {
                    $current['duration'] = gmdate("H:i:s", 0);
                }
            } else {
                $current['duration'] = gmdate("H:i:s", 0);
            }
            $new_statuses[] = $current;
        }

        // Also insert last record
        $next['duration'] = gmdate("H:i:s", 0);
        $new_statuses[] = $next;

        return $new_statuses;
    }

    private function calculate_durations($a)
    {
        $a = $this->remove_consecutive_statuses($a);
        $a = $this->check_for_pending_status($a);
        $a = $this->calculate_status_duration($a);

        return $a;
    }

    public function update_worker_skills(Request $request)
    {
        $worker_id = $request->get('worker_id');
        $tpv_staff_id = $request->get('tpv_staff_id');
        $tpv_staff_group_id = $request->get('tpv_staff_group_id');

        return $this->update_worker_skills_api($worker_id, $tpv_staff_id, $tpv_staff_group_id);
    }

    public function update_worker_skills_api($worker_id, $tpv_staff_id, $tpv_staff_group_id)
    {
        $skills = DB::table(
            'tpv_staff_groups'
        )->where(
            'id',
            $tpv_staff_group_id
        )->select(
            'config'
        )->first();

        if ($skills) {
            $this->_client = new Client(
                config('services.twilio.account'),
                config('services.twilio.auth_token')
            );
            $this->_workspace_id = config('services.twilio.workspace');
            $this->_workspace = $this->_client->taskrouter->workspaces($this->_workspace_id);

            $this->_workspace->workers($worker_id)->update(
                array(
                    'attributes' => array(
                        'skills' => $skills->config,
                    ),
                )
            );
            $staff = TpvStaff::find($tpv_staff_id);
            $staff->tpv_staff_group_id = $tpv_staff_group_id;
            $staff->save();
        }
    }

    public function getCallActiveStatus()
    {
        return "";
    }

    private function formatTime($seconds)
    {
        if ($seconds == 0) {
            return '--';
        }
        $hours = floor($seconds / 3600);
        $mins = floor($seconds / 60 % 60);
        $secs = floor($seconds % 60);

        $time_format = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

        return $time_format;
    }

    private function supervisors_select_options()
    {
        $supervisors = TpvStaff::select(
            'id',
            DB::raw("CONCAT(`first_name`, ' ', `last_name`) as name")
        )->where(
            'role_id',
            '8'
        )->orderBy(
            'id'
        )->get()->pluck(
            'name',
            'id'
        )->all();

        return $supervisors;
    }

    private function getDateTo($date_to)
    {
        if ($date_to) {
            $date_to = Carbon::createFromFormat(
                'm/d/Y',
                $date_to
            )->startOfDay('America/Chicago');
        } else {
            $date_to = Carbon::now('America/Chicago')->endOfDay();
        }

        return $date_to;
    }

    private function getDateFrom($date_from)
    {
        if ($date_from) {
            $date_from = Carbon::createFromFormat(
                'm/d/Y',
                $date_from
            )->startOfDay('America/Chicago');
        } else {
            $date_from = Carbon::now('America/Chicago')->startOfDay();
        }

        return $date_from;
    }

    public function image_click(Request $request)
    {
        return view(
            'reports.image_test',
            []
        );
    }
}
