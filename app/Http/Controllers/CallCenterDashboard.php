<?php

namespace App\Http\Controllers;

set_time_limit(120);

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Interaction;
use Illuminate\Support\Facades\DB;
use App\Models\InboundCallData;
use App\Models\StatsProduct;
use function GuzzleHttp\json_decode;

class CallCenterDashboard extends Controller
{
    public $half_hours = ['7:00', '7:30', '8:00', '8:30', '9:00', '9:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00', '22:30', '23:00', '23:30', '24:00'];

    public function call_center_dashboard()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'call-c-dashboard',
                'title' => 'Report: Call Center Dashboard'
            ]
        );
    }

    public function totalCalls(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $calls = Interaction::selectRaw(
            "COUNT(interactions.id) as calls"
        )->leftJoin(
            'events',
            'events.id',
            'interactions.event_id'
        )->whereNotNull(
            'interactions.interaction_time'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )->whereNull(
            'events.survey_id'
        )->whereNull(
            'parent_interaction_id'
        )->leftJoin(
            'stats_product',
            'interactions.id',
            'stats_product.interaction_id'
        )->whereNull('stats_product.deleted_at');

        if ($start_date) {
            $calls = $calls->whereRaw(
                'DATE_FORMAT(stats_product.event_created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $calls = $calls->whereRaw(
                'DATE_FORMAT(stats_product.event_created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $calls = $calls->get()->toArray();

        $result = [
            'total_calls' => $calls[0]['calls']
        ];
        return response()->json($result);
    }

    public function totalOccupancy(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $talk_time = StatsProduct::selectRaw(
            'stats_product.interaction_time'
        )->eventRange(
            $start_date,
            $end_date
        )->where(
            'stats_product.interaction_time',
            '>',
            0
        )->leftJoin(
            'events',
            'events.id',
            'stats_product.event_id'
        )->whereIn(
            'stats_product.interaction_type',
            ['call_outbound', 'call_inbound']
        )->whereNull(
            'events.survey_id'
        )->groupBy('stats_product.confirmation_code');

        $talk_time = $talk_time->get()->reduce(function ($carry, $item) {
            return $carry + $item->interaction_time;
        });
        //Converting the talk_time from min to hours
        $talk_time = ($talk_time > 0) ? round($talk_time / 60, 2) : 0;

        $total_payroll_time = $this->totalPayrollTime($start_date, $end_date);
        //dd($total_payroll_time);
        $total_occupancy = ($talk_time > 0 && $total_payroll_time > 0) ? round($talk_time / $total_payroll_time, 2) : 0;

        $result = [
            'total_occupancy' => $total_occupancy
        ];
        return response()->json($result);
    }

    public function totalPayrollTime($s_date, $e_date)
    {
        $time_clocks = DB::table(
            'time_clocks'
        )->select(
            'created_at',
            'time_punch',
            'agent_status_type_id',
            'tpv_staff_id'
        )->whereIn(
            'agent_status_type_id',
            ['1', '2']
        );

        if ($s_date) {
            $time_clocks = $time_clocks->whereRaw(
                'DATE_FORMAT(time_clocks.created_at, "%Y-%m-%d") >= ?',
                $s_date
            );
        }

        if ($e_date) {
            $time_clocks = $time_clocks->whereRaw(
                'DATE_FORMAT(time_clocks.created_at, "%Y-%m-%d") <= ?',
                $e_date
            );
        }

        $time_clocks = $time_clocks->orderBy(
            'created_at'
        )->orderBy(
            'tpv_staff_id'
        )->get();

        if ($time_clocks->isEmpty()) {
            return 0;
        }

        $punches = [];
        foreach ($time_clocks as $time_clock) {
            $this_punch = [
                'timestamp' => Carbon::parse($time_clock->time_punch, 'America/Chicago')->timestamp,
                'direction' => $time_clock->agent_status_type_id,
                'tpv_staff_id' => $time_clock->tpv_staff_id
            ];
            $punches[] = $this_punch;
        }

        // direction = 1 is clock in = 2 is clock out
        $seconds = 0;
        $today = Carbon::today()->format("Y-m-d");
        for ($i = 0; $i < count($punches); $i++) {
            $date = Carbon::createFromTimestamp($punches[$i]['timestamp'])->format('Y-m-d');
            if (isset($punches[$i + 1])) {
                //If they have the same agent_id I can calculate the payroll time
                if ($punches[$i]['direction'] == 1 && $punches[$i + 1]['direction'] == 2 && $punches[$i]['tpv_staff_id'] == $punches[$i + 1]['tpv_staff_id']) {
                    $seconds += ($punches[$i + 1]['timestamp'] - $punches[$i]['timestamp']);
                    //No need to go to the next iteration so we can jump that one
                    $i++;
                }
                //If this is true is because that person is still online
                elseif ($punches[$i]['direction'] = 1 && $punches[$i]['tpv_staff_id'] != $punches[$i + 1]['tpv_staff_id']) {
                    //Double checking that the person is online
                    if ($date == $today) {
                        $seconds += (Carbon::now('America/Chicago')->timestamp - $punches[$i]['timestamp']);
                    }
                }
            } else {
                if ($punches[$i]['direction'] == 1) {
                    if ($date == $today) {
                        $seconds += (Carbon::now('America/Chicago')->timestamp - $punches[$i]['timestamp']);
                    }
                }
            }
        }

        $hours = ($seconds > 0) ? round($seconds / 3600, 2) : 0;
        return $hours;
    }

    public function avgSpeedToAnswer(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $icd = InboundCallData::select(
            'twilio_json'
        )->whereBetween(
            'start_date',
            [$start_date, $end_date]
        )->orderBy(
            DB::raw("time(start_date)"),
            "ASC"
        )->orderBy(
            DB::raw("date(start_date)"),
            "ASC"
        );

        $stats = $icd->get()->toArray();
        $avg_speed_to_answer = 0;
        foreach ($stats as $stat) {
            $decoded_stats = json_decode($stat['twilio_json'], true);
            if ($decoded_stats) {
                $avg_speed_to_answer += $decoded_stats['avgTaskAcceptanceTime'];
            }
        }

        $total_num_calls = $this->totalCalls($request);
        $total_num_calls = json_decode($total_num_calls->content(), true);
        $total_num_calls = $total_num_calls['total_calls'];

        $avg_speed_to_answer = ($avg_speed_to_answer !== 0 && $total_num_calls !== 0) ? round($avg_speed_to_answer / $total_num_calls, 2) : 0;
        $avg_speed_to_answer = gmdate("i:s", $avg_speed_to_answer);

        $result = [
            'avg_speed_to_answer' => $avg_speed_to_answer
        ];

        return response()->json($result);
    }

    public function talk_and_payroll_time(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $talk_time = Interaction::selectRaw(
            "SUM(interactions.interaction_time) AS talk_time,
             CONCAT(CAST(HOUR( interactions.created_at ) AS CHAR(2)), ':', (CASE WHEN MINUTE( interactions.created_at ) <30 THEN '00' ELSE '30' END)) AS halfhour"
        )->leftJoin(
            'events',
            'events.id',
            'interactions.event_id'
        )->whereNotNull(
            'interactions.interaction_time'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )->whereNull(
            'events.survey_id'
        )->whereNull(
            'parent_interaction_id'
        )->whereRaw(
            'HOUR(interactions.created_at) >= 7 AND HOUR(interactions.created_at) <= 24'
        );

        if ($start_date) {
            $talk_time = $talk_time->whereRaw(
                'DATE_FORMAT(interactions.created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $talk_time = $talk_time->whereRaw(
                'DATE_FORMAT(interactions.created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $talk_time = $talk_time->groupBy(
            'halfhour'
        )->get()->toArray();

        $time_clocks = DB::table(
            'time_clocks'
        )->select(
            'created_at',
            'time_punch',
            'agent_status_type_id',
            'tpv_staff_id'
        )->whereIn(
            'agent_status_type_id',
            ['1', '2']
        );

        if ($start_date) {
            $time_clocks = $time_clocks->whereRaw(
                'DATE_FORMAT(time_clocks.created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $time_clocks = $time_clocks->whereRaw(
                'DATE_FORMAT(time_clocks.created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $time_clocks = $time_clocks->orderBy(
            'created_at'
        )->orderBy(
            'tpv_staff_id'
        );

        $time_clocks = $time_clocks->get();

        $seconds = [];
        foreach ($this->half_hours as $hh) {
            $seconds[$hh] = 0;
        }

        if (!$time_clocks->isEmpty()) {
            $punches = [];
            foreach ($time_clocks as $time_clock) {
                $this_punch = [
                    'timestamp' => strtotime($time_clock->time_punch),
                    'direction' => $time_clock->agent_status_type_id,
                    'tpv_staff_id' => $time_clock->tpv_staff_id,
                    'created_at' => $time_clock->created_at
                ];
                $punches[] = $this_punch;
            }

            $today = Carbon::today()->format("Y-m-d");
            // direction = 1 is clock in = 2 is clock out
            for ($i = 0; $i < count($punches); $i++) {
                $date = Carbon::createFromTimestamp($punches[$i]['timestamp'])->format('Y-m-d');
                $date_with_hour = Carbon::parse($punches[$i]['created_at'], 'America/Chicago');
                $min = ($date_with_hour->minute < 30) ? '00' : '30';
                $half_hour = $date_with_hour->hour . ':' . $min;

                if (isset($punches[$i + 1])) {
                    //If they have the same agent_id I can calculate the payroll time
                    if ($punches[$i]['direction'] == 1 && $punches[$i + 1]['direction'] == 2 && $punches[$i]['tpv_staff_id'] == $punches[$i + 1]['tpv_staff_id']) {
                        $seconds[$half_hour] += ($punches[$i + 1]['timestamp'] - $punches[$i]['timestamp']);
                        //No need to go to the next iteration so we can jump that one
                        $i++;
                    }
                    //If this is true is because that person is still online
                    elseif ($punches[$i]['direction'] = 1 && $punches[$i]['tpv_staff_id'] != $punches[$i + 1]['tpv_staff_id']) {
                        //Double checking that the person is online
                        if ($date == $today) {
                            $seconds[$half_hour] += (Carbon::now('America/Chicago')->timestamp - $punches[$i]['timestamp']);
                        }
                    }
                } else {
                    if ($punches[$i]['direction'] == 1) {
                        if ($date == $today) {
                            $seconds[$half_hour] += (Carbon::now('America/Chicago')->timestamp - $punches[$i]['timestamp']);
                        }
                    }
                }
            }
        }

        $result = [];
        $tt_aux = [];
        foreach ($talk_time as $tt) {
            $tt_aux[$tt['halfhour']] = number_format($tt['talk_time'], 2, ".", "");
        }
        $talk_time =  $tt_aux;
        foreach ($this->half_hours as $hh) {
            $tt = (isset($talk_time[$hh])) ? round($talk_time[$hh] / 60, 2) : 0;
            $pt = (isset($seconds[$hh])) ? round($seconds[$hh] / 3600, 2) : 0;
            $result[$hh] = [
                'talk_time' => $tt,
                'payroll_time' => $pt
            ];
        }

        return response()->json($result);
    }

    public function call_center_dataset(Request $request)
    {
        $table_legend = [
            'Total Calls', 'AHT (sec)', 'Payroll Hours', 'Billable Time (Hours)', 'Ready Time(Hours)',
            'Not Ready (Hours)', 'Lunch/Break (Hours)', 'Occupancy', '% Ready', '% Not Ready', '% Lunch/Break', 'ASA', 'Service Level'
        ];

        $calls = $this->_calls_by_half_hour($request);
        $total_calls = array_sum($calls);

        $aht = $this->_aht_by_half_hour($request);
        $total_aht = array_sum($aht);
        $avg_aht = ($total_aht > 0 && count($aht) > 0) ? round($total_aht / count($aht)) : 0;

        $service_level = $this->_service_level_by_half_hour($request);
        $total_s_l = round(array_sum($service_level), 2);
        $avg_service_level = ($total_s_l > 0 && count($service_level) > 0) ? round($total_s_l / count($service_level)) : 0;

        $asa = $this->_asa_by_half_hour($request);
        $total_asa = round(array_sum($asa), 2);

        $payroll = $this->_payroll_time_by_half_hour($request);
        $total_payroll = round(array_sum($payroll), 2);

        $statuses = $this->_tpv_staff_status_time($request);
        $billable =  $statuses['billable'];
        $total_billable = round(array_sum($billable), 2);

        $lunch =  $statuses['lunch_break'];
        $total_lunch = round(array_sum($lunch), 2);

        $not_ready =  $statuses['not_ready'];
        $total_not_ready = round(array_sum($not_ready), 2);

        $ready =  $statuses['available'];
        $total_ready = round(array_sum($ready), 2);

        foreach ($this->half_hours as $hh) {
            $pct = ($billable[$hh] > 0 && $payroll[$hh] > 0) ? ($billable[$hh] / $payroll[$hh]) * 100 : 0;
            $pct_occupancy[$hh] = round($pct);
        }

        $pct_ready['total'] = ($total_payroll > 0 && $total_ready) ? round($total_ready / $total_payroll, 2) : 0;
        $pct_not_ready['total'] = ($total_payroll > 0 && $total_not_ready) ? round($total_not_ready / $total_payroll, 2) : 0;
        $pct_lunch['total'] = ($total_payroll > 0 && $total_lunch) ? round($total_lunch / $total_payroll, 2) : 0;
        $pct_occupancy['total'] = ($total_payroll > 0 && $total_billable) ? round($total_billable / $total_payroll, 2) : 0;

        foreach ($this->half_hours as $hh) {
            $pct_ready[$hh] = ($payroll[$hh] > 0 && $ready[$hh]) ? round($ready[$hh] / $payroll[$hh], 2) : 0;
            $pct_not_ready[$hh] = ($payroll[$hh] > 0 && $not_ready[$hh]) ? round($not_ready[$hh] / $payroll[$hh], 2) : 0;
            $pct_lunch[$hh] = ($payroll[$hh] > 0 && $lunch[$hh]) ? round($lunch[$hh] / $payroll[$hh], 2) : 0;
            $pct_occupancy[$hh] = ($payroll[$hh] > 0 && $billable[$hh]) ? round(($billable[$hh] / $payroll[$hh]) * 100) : 0;
        }

        $result = [];
        foreach ($table_legend as $key => $legend) {
            switch ($legend) {
                case 'Total Calls':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $total_calls
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $calls[$hh]
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'AHT (sec)':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $avg_aht
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $aht[$hh]
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'Payroll Hours':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $total_payroll
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $payroll[$hh]
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'Billable Time (Hours)':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $total_billable
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $billable[$hh]
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'Ready Time(Hours)':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $total_ready
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $ready[$hh]
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'Not Ready (Hours)':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $total_not_ready
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $not_ready[$hh]
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'Lunch/Break (Hours)':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $total_lunch
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $lunch[$hh]
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'Occupancy':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $pct_occupancy['total'] . '%'
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $pct_occupancy[$hh] . '%'
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case '% Ready':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $pct_ready['total'] . '%'
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $pct_ready[$hh] . '%'
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case '% Not Ready':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $pct_not_ready['total'] . '%'
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $pct_not_ready[$hh] . '%'
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case '% Lunch/Break':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $pct_lunch['total'] . '%'
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $pct_lunch[$hh] . '%'
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'ASA':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $total_asa
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $asa[$hh]
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;
                case 'Service Level':
                    $result[$key] = [
                        'legend' => $legend,
                        'total' => $avg_service_level . '%'
                    ];
                    foreach ($this->half_hours as $hh) {
                        $arr = [
                            $hh => $service_level[$hh] . '%'
                        ];
                        $result[$key] = array_merge($result[$key], $arr);
                    }
                    break;

                default:
                    break;
            }
        }

        $result[] = [
            'total_aht' => $avg_aht,
            'total_calls' => $total_calls,
            'total_service_level' => $avg_service_level,
            'total_asa' => $total_asa,
            'total_occupancy' => $pct_occupancy['total']
        ];

        return response()->json($result);
    }

    public function _calls_by_half_hour(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $calls = Interaction::selectRaw(
            "COUNT(interactions.id) as calls,
             CONCAT(CAST(HOUR( interactions.created_at ) AS CHAR(2)), ':', (CASE WHEN MINUTE( interactions.created_at ) < 30 THEN '00' ELSE '30' END)) AS halfhour"
        )->leftJoin(
            'events',
            'events.id',
            'interactions.event_id'
        )->whereNotNull(
            'interactions.interaction_time'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )->whereNull(
            'events.survey_id'
        )->whereNull(
            'interactions.parent_interaction_id'
        )->leftJoin(
            'stats_product',
            'interactions.id',
            'stats_product.interaction_id'
        )->whereNull('stats_product.deleted_at');

        if ($start_date) {
            $calls = $calls->whereRaw(
                'DATE_FORMAT(stats_product.event_created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $calls = $calls->whereRaw(
                'DATE_FORMAT(stats_product.event_created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $calls = $calls->groupBy(
            'halfhour'
        )->get()->toArray();

        $aux = [];
        foreach ($calls as $chh) {
            $aux[$chh['halfhour']] =  $chh['calls'];
        }
        //Ordering and completing the nonexisting times for the results
        $result = [];
        foreach ($this->half_hours as $hh) {
            $result[$hh] = (isset($aux[$hh])) ? $aux[$hh] : 0;
        }
        return $result;
    }

    public function _tpv_staff_status_time(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $statuses = DB::table(
            'agent_statuses'
        )->select(
            'agent_statuses.event as status',
            'agent_statuses.created_at',
            'agent_statuses.tpv_staff_id'
        )->whereNull(
            'agent_statuses.deleted_at'
        )->whereRaw(
            'HOUR(agent_statuses.created_at) >= 7 AND HOUR(agent_statuses.created_at) <= 24'
        );

        if ($start_date) {
            $statuses = $statuses->whereRaw(
                'DATE_FORMAT(agent_statuses.created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $statuses = $statuses->whereRaw(
                'DATE_FORMAT(agent_statuses.created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $statuses = $statuses->orderBy(
            'agent_statuses.created_at'
        )->orderBy(
            'agent_statuses.tpv_staff_id'
        )->get()->transform(function ($item) {
            return (array) $item;
        })->toArray();

        $result = [];
        $available = [];
        $lunch_break = [];
        $not_ready = [];
        $billable = [];
        foreach ($this->half_hours as $hh) {
            $available[$hh] = 0;
            $lunch_break[$hh] = 0;
            $not_ready[$hh] = 0;
            $billable[$hh] = 0;
        }
        $status = array(
            'Available', 'After Call Work', 'Meal', 'Not Ready', 'Logged Out', 'Reserved', 'Start No Sale Countdown',
            'TPV In Progress', 'Call answered', "Good Sale", "Close Call", "No Sale: Test Call", "No Sale: Customer Changed Their Mind",
            "No Sale: Sales Rep Did Not Leave Premises", "No Sale: Call Disconnected", "Call Disconnected", "Call Offered", "Break", "Unscheduled Break"
        );
        for ($i = 0; $i < count($statuses); $i++) {
            if (in_array($statuses[$i]['status'], $status)) {
                $flag = false;
                $av_status = new Carbon($statuses[$i]['created_at']);
                $min = ($av_status->minute >= 30) ? '30' : '00';
                $half_hour = $av_status->hour . ":" . $min;
                //If they have the same tpv_staff_id and the day is the same then I can compare those two
                if (
                    isset($statuses[$i + 1]) && $statuses[$i]['tpv_staff_id'] == $statuses[$i + 1]['tpv_staff_id']
                    &&
                    Carbon::parse($statuses[$i]['created_at'], 'America/Chicago')->format('Y-m-d') == Carbon::parse($statuses[$i + 1]['created_at'], 'America/Chicago')->format('Y-m-d')
                ) {
                    $next_status = new Carbon($statuses[$i + 1]['created_at']);
                    $flag = true;
                } elseif (Carbon::parse($statuses[$i]['created_at'], 'America/Chicago')->isToday()) {
                    //If the tpv_staff member only have one available status and its today then
                    $next_status = Carbon::now();
                    $flag = true;
                }
                if ($flag) {
                    switch ($statuses[$i]['status']) {
                        case "Available":
                            $available[$half_hour] += $next_status->diffInSeconds($av_status);
                            break;

                        case "Meal":
                        case "Unscheduled Break":
                        case "Break":
                            $lunch_break[$half_hour] += $next_status->diffInSeconds($av_status);
                            break;

                        case "Not Ready":
                        case "Logged Out":
                            $not_ready[$half_hour] += $next_status->diffInSeconds($av_status);
                            break;
                        default:
                            $billable[$half_hour] += $next_status->diffInSeconds($av_status);
                            break;
                    }
                    $flag = false;
                }
            }
        }

        $get_hours = function (array $statu) {
            foreach ($statu as &$s) {
                $s = ($s > 0) ? round($s / 3600, 2) : 0.00;
            }
            return $statu;
        };

        $available = $get_hours($available);
        $lunch_break = $get_hours($lunch_break);
        $not_ready = $get_hours($not_ready);
        $billable = $get_hours($billable);

        $result = [
            'available' => $available,
            'lunch_break' => $lunch_break,
            'not_ready' => $not_ready,
            'billable' => $billable,
        ];

        return $result;
    }

    public function _asa_by_half_hour(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $icd = InboundCallData::select(
            'twilio_json',
            'start_date'
        )->whereBetween(
            'start_date',
            [$start_date, $end_date]
        )->whereRaw(
            'HOUR(start_date) >= 7 AND HOUR(start_date) <= 24'
        );

        $stats = $icd->get()->toArray();
        $avg_speed_to_answer = [];
        foreach ($this->half_hours as $hh) {
            $avg_speed_to_answer[$hh] = 0;
        }

        foreach ($stats as $stat) {
            $decoded_stats = json_decode($stat['twilio_json'], true);
            if ($decoded_stats) {
                $date = new Carbon($stat['start_date']);
                $min = ($date->minute >= 30) ? '30' : '00';
                $half_hour = $date->hour . ":" . $min;

                $avg_speed_to_answer[$half_hour] += $decoded_stats['avgTaskAcceptanceTime'];
            }
        }

        $result = [];
        foreach ($this->half_hours as $hh) {
            $result[$hh] = ($avg_speed_to_answer[$hh] > 0) ? round($avg_speed_to_answer[$hh], 2) : 0;
        }
        return $result;
    }

    public function service_level(Request $request)
    {
        $service_level = $this->_service_level_by_half_hour($request);

        $result = [
            'service_level' => round(array_sum($service_level), 2)
        ];
        return response()->json($result);
    }

    public function _service_level_by_half_hour(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $icd = InboundCallData::select(
            'twilio_json',
            'start_date'
        )->whereBetween(
            'start_date',
            [$start_date, $end_date]
        )->whereRaw(
            'HOUR(start_date) >= 7 AND HOUR(start_date) <= 24'
        );

        $stats = $icd->get()->toArray();

        $calls = Interaction::select(
            "interactions.created_at"
        )->leftJoin(
            'events',
            'events.id',
            'interactions.event_id'
        )->leftJoin(
            'stats_product',
            'interactions.id',
            'stats_product.interaction_id'
        )->whereNotNull(
            'interactions.interaction_time'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )->whereNull(
            'events.survey_id'
        )->whereNull(
            'parent_interaction_id'
        )->whereNull(
            'stats_product.deleted_at'
        )->whereRaw(
            'HOUR(stats_product.event_created_at) >= 7 AND HOUR(stats_product.event_created_at) <= 24'
        );

        if ($start_date) {
            $calls = $calls->whereRaw(
                'DATE_FORMAT(stats_product.event_created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $calls = $calls->whereRaw(
                'DATE_FORMAT(stats_product.event_created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $calls = $calls->get()->toArray();
        //Getting all the calls by day and halfhour
        $calls_by_half_hour = [];
        $service_level = [];

        //Initializing service_level, calls_by_half_hour array
        foreach ($this->half_hours as $h) {
            $service_level[$h] = 0;
            $calls_by_half_hour[$h] = 0;
        }

        foreach ($calls as $call) {
            $date = Carbon::parse($call['created_at'], 'America/Chicago');
            $min = ($date->minute >= 30) ? '30' : '00';
            $half_hour = $date->hour . ":" . $min;

            $calls_by_half_hour[$half_hour] += 1;
        }

        //dd($calls_by_half_hour);

        foreach ($stats as $stat) {
            $decoded_stats = json_decode($stat['twilio_json'], true);
            if ($decoded_stats) {
                $date = Carbon::parse($stat['start_date'], 'America/Chicago');
                $min = ($date->minute >= 30) ? '30' : '00';
                $half_hour = $date->hour . ":" . $min;

                $service_level[$half_hour] += $decoded_stats['splitByWaitTime'];
            }
        }
        //number_format(@$ibcall['service_level'] * 100, 0)
        foreach ($service_level as $key => &$sl) {
            $sl = ($sl > 0 && $calls_by_half_hour[$key] > 0) ? number_format(($sl / $calls_by_half_hour[$key]) * 100, 0) : 0;
        }
        return $service_level;
    }

    /**
     * Returns the average_handle_time in hours
     */
    public function average_handle_time(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $talk_time = StatsProduct::selectRaw(
            'stats_product.interaction_time'
        )->eventRange(
            $start_date,
            $end_date
        )->where(
            'stats_product.interaction_time',
            '>',
            0
        )->leftJoin(
            'events',
            'events.id',
            'stats_product.event_id'
        )->whereIn(
            'stats_product.interaction_type',
            ['call_inbound', 'call_outbound']
        )->whereNull(
            'events.survey_id'
        )->groupBy('stats_product.confirmation_code');

        $talk_time = $talk_time->get()->reduce(function ($carry, $item) {
            return $carry + $item->interaction_time;
        });

        $statuses = DB::table(
            'agent_statuses'
        )->select(
            'agent_statuses.event as status',
            'agent_statuses.created_at',
            'agent_statuses.tpv_staff_id'
        )->whereNull('agent_statuses.deleted_at');

        if ($start_date) {
            $statuses = $statuses->whereRaw(
                'DATE_FORMAT(agent_statuses.created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $statuses = $statuses->whereRaw(
                'DATE_FORMAT(agent_statuses.created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $statuses = $statuses->orderBy(
            'agent_statuses.created_at'
        )->orderBy(
            'agent_statuses.tpv_staff_id'
        )->get()->transform(function ($item) {
            return (array) $item;
        })->toArray();

        $after_call_work = 0;
        //Loop through de staff array to calculate the $after_call_work for each staff member and sum it all
        for ($i = 0; $i < count($statuses); $i++) {
            if ($statuses[$i]['status'] == 'After Call Work') {
                $current_status_date = new Carbon($statuses[$i]['created_at']);
                //If they have the same tpv_staff_id and the day is the same then I can compare those two
                if (
                    isset($statuses[$i + 1]) && $statuses[$i]['tpv_staff_id'] == $statuses[$i + 1]['tpv_staff_id']
                    &&
                    $current_status_date->format('Y-m-d') == Carbon::parse($statuses[$i + 1]['created_at'], 'America/Chicago')->format('Y-m-d')
                ) {
                    $next_status_date = new Carbon($statuses[$i + 1]['created_at']);
                    $after_call_work += $next_status_date->diffInSeconds($current_status_date);
                } elseif (Carbon::parse($statuses[$i]['created_at'], 'America/Chicago')->isToday()) {
                    //If the tpv_staff member only have one available status and its today then
                    $next_status_date = Carbon::now();
                    $after_call_work += $next_status_date->diffInSeconds($current_status_date);
                }
            }
        }

        //Converting the values into hours
        $after_call_work = ($after_call_work > 0) ? round($after_call_work / 3600, 2) : 0;
        $talk_time = ($talk_time > 0) ? round($talk_time / 60, 2) : 0;
        $average_handle_time = $talk_time + $after_call_work;
        $average_handle_time = round($average_handle_time, 2);

        $result = [
            //'average_handle_time' => round($average_handle_time, 2)
            'average_handle_time' => $average_handle_time
        ];

        return $result;
    }

    public function _aht_by_half_hour(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $talk_time = StatsProduct::selectRaw(
            'stats_product.interaction_time,
            stats_product.created_at'
        )->eventRange(
            $start_date,
            $end_date
        )->where(
            'stats_product.interaction_time',
            '>',
            0
        )->leftJoin(
            'events',
            'events.id',
            'stats_product.event_id'
        )->whereIn(
            'stats_product.interaction_type',
            ['call_inbound', 'call_outbound']
        )->whereNull(
            'events.survey_id'
        )->groupBy('stats_product.confirmation_code');

        $talk_time = $talk_time->get()->toArray();

        $talk_time_in_half_hours = [];
        foreach ($talk_time as $tt) {
            $av_status = new Carbon($tt['created_at']);
            $min = ($av_status->minute >= 30) ? '30' : '00';
            $half_hour = $av_status->hour . ":" . $min;
            if (isset($talk_time_in_half_hours[$half_hour])) {
                $talk_time_in_half_hours[$half_hour] += $tt['interaction_time'];
            } else {
                $talk_time_in_half_hours[$half_hour] = $tt['interaction_time'];
            }
        }

        //dd($talk_time_in_half_hours);

        $statuses = DB::table(
            'agent_statuses'
        )->select(
            'agent_statuses.event as status',
            'agent_statuses.created_at',
            'agent_statuses.tpv_staff_id'
        )->whereNull('agent_statuses.deleted_at');

        if ($start_date) {
            $statuses = $statuses->whereRaw(
                'DATE_FORMAT(agent_statuses.created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $statuses = $statuses->whereRaw(
                'DATE_FORMAT(agent_statuses.created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $statuses = $statuses->orderBy(
            'agent_statuses.created_at'
        )->orderBy(
            'agent_statuses.tpv_staff_id'
        )->get()->transform(function ($item) {
            return (array) $item;
        })->toArray();

        $after_call_work = [];
        foreach ($this->half_hours as $hh) {
            $after_call_work[$hh] = 0;
        }
        //Loop through de staff array to calculate the $after_call_work for each staff member and sum it all
        for ($i = 0; $i < count($statuses); $i++) {
            if ($statuses[$i]['status'] == 'After Call Work') {
                $current_status_date = new Carbon($statuses[$i]['created_at']);
                $min = ($current_status_date->minute >= 30) ? '30' : '00';
                $half_hour = $current_status_date->hour . ":" . $min;
                //If they have the same tpv_staff_id and the day is the same then I can compare those two
                if (
                    isset($statuses[$i + 1]) && $statuses[$i]['tpv_staff_id'] == $statuses[$i + 1]['tpv_staff_id']
                    &&
                    $current_status_date->format('Y-m-d') == Carbon::parse($statuses[$i + 1]['created_at'], 'America/Chicago')->format('Y-m-d')
                ) {
                    $next_status_date = new Carbon($statuses[$i + 1]['created_at']);
                    $after_call_work[$half_hour] += $next_status_date->diffInSeconds($current_status_date);
                } elseif ($current_status_date->isToday()) {
                    //If the tpv_staff member only have one available status and its today then
                    $next_status_date = Carbon::now();
                    $after_call_work[$half_hour] += $next_status_date->diffInSeconds($current_status_date);
                }
            }
        }

        //dd($talk_time_in_half_hours, $after_call_work);
        foreach ($talk_time_in_half_hours as &$min) {
            $min = ($min > 0) ? ($min * 60) : 0;
        }

        $aht_by_half_hour = [];
        foreach ($this->half_hours as $hh) {
            $tt = (isset($talk_time_in_half_hours[$hh])) ? $talk_time_in_half_hours[$hh] : 0;
            $aht_by_half_hour[$hh] = round($tt + $after_call_work[$hh]);
        }

        return $aht_by_half_hour;
    }

    public function _payroll_time_by_half_hour(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today()->format("Y-m-d");
        $end_date = $request->get('endDate') ?? Carbon::today()->format("Y-m-d");

        $time_clocks = DB::table(
            'time_clocks'
        )->select(
            'created_at',
            'time_punch',
            'agent_status_type_id',
            'tpv_staff_id'
        )->whereIn(
            'agent_status_type_id',
            ['1', '2']
        )->whereRaw(
            'HOUR(created_at) >= 7 AND HOUR(created_at) <= 24'
        );

        if ($start_date) {
            $time_clocks = $time_clocks->whereRaw(
                'DATE_FORMAT(time_clocks.created_at, "%Y-%m-%d") >= ?',
                $start_date
            );
        }

        if ($end_date) {
            $time_clocks = $time_clocks->whereRaw(
                'DATE_FORMAT(time_clocks.created_at, "%Y-%m-%d") <= ?',
                $end_date
            );
        }

        $time_clocks = $time_clocks->orderBy(
            'created_at'
        )->orderBy(
            'tpv_staff_id'
        );

        $time_clocks = $time_clocks->get();

        $seconds = [];
        foreach ($this->half_hours as $hh) {
            $seconds[$hh] = 0;
        }

        if (!$time_clocks->isEmpty()) {
            $punches = [];
            foreach ($time_clocks as $time_clock) {
                $this_punch = [
                    'timestamp' => strtotime($time_clock->time_punch),
                    'direction' => $time_clock->agent_status_type_id,
                    'tpv_staff_id' => $time_clock->tpv_staff_id
                ];
                $punches[] = $this_punch;
            }

            $today = Carbon::today()->format("Y-m-d");
            // direction = 1 is clock in = 2 is clock out
            for ($i = 0; $i < count($punches); $i++) {
                $date = Carbon::createFromTimestamp($punches[$i]['timestamp'])->format('Y-m-d');
                $date_with_time = Carbon::createFromTimestamp($punches[$i]['timestamp']);
                $min = ($date_with_time->minute >= 30) ? '30' : '00';
                $half_hour = $date_with_time->hour . ":" . $min;
                if (isset($punches[$i + 1])) {
                    //If they have the same agent_id I can calculate the payroll time
                    if (
                        $punches[$i]['direction'] == 1 && $punches[$i + 1]['direction'] == 2
                        && $punches[$i]['tpv_staff_id'] == $punches[$i + 1]['tpv_staff_id']
                        && $date ==  Carbon::createFromTimestamp($punches[$i + 1]['timestamp'])->format('Y-m-d')
                    ) {
                        $seconds[$half_hour] += ($punches[$i + 1]['timestamp'] - $punches[$i]['timestamp']);
                        //No need to go to the next iteration so we can jump that one
                        $i++;
                    }
                    //If this is true is because that person is still online
                    elseif ($punches[$i]['direction'] = 1 && $punches[$i]['tpv_staff_id'] != $punches[$i + 1]['tpv_staff_id']) {
                        //Double checking that the person is online
                        if ($date == $today) {
                            $seconds[$half_hour] += (Carbon::now('America/Chicago')->timestamp - $punches[$i]['timestamp']);
                        }
                    }
                } else {
                    if ($punches[$i]['direction'] == 1) {
                        if ($date == $today) {
                            $seconds[$half_hour] += (Carbon::now('America/Chicago')->timestamp - $punches[$i]['timestamp']);
                        }
                    }
                }
            }

            foreach ($seconds as &$s) {
                $s = ($s > 0) ? round($s / 3600, 2) : 0;
            }
        }
        $hours = $seconds;

        return $hours;
    }
}
