<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Interaction;
use App\Models\StatsProduct;
use App\Traits\SearchFormTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TpvAgentDashboardController extends Controller
{
    use SearchFormTrait;

    /**
     * Launch TPV Agents Dashboard view
     * 
     * @return View The dashboard View object
     */
    public function tpv_agents_dashboard()
    {
        return view(
            'dashboard.tpv_agent_dashboard',
            [
                'brands' => $this->get_brands(),
                'languages' => $this->get_languages(),
                'commodities' => $this->get_commodities(),
                'states' => $this->get_states(),
            ]
        );
    }


    /**
     * Retrieve call center stats for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object An object containing the requested data.
     */
    public function get_call_center_stats(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $proxyResponse = null;
        $result = null;

        try {
            $res = $this->proxyApiCall('getCallCenterStats?startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $result = [
                'result' => $proxyResponse->result,
                'message' => $proxyResponse->message,
                'proxy_data' => $proxyResponse ?? null,
                'focus' => ($proxyResponse && isset($proxyResponse->data->data->focus) ? $proxyResponse->data->data->focus : []),
                'dxc' => ($proxyResponse && isset($proxyResponse->data->data->dxc) ? $proxyResponse->data->data->dxc : [])
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'focus' => ($proxyResponse->data && isset($proxyResponse->data->data->focus) ? $proxyResponse->data->data->focus : null),
                'dxc' => ($proxyResponse->data && isset($proxyResponse->data->data->dxc) ? $proxyResponse->data->data->dxc : null)
            ];
        }

        return $result;
    }

    /**
     * Retrieve stats that are broken out by day of week for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object An object containing the requested data.
     */
    public function get_dow_stats(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $proxyResponse = null;
        $result = null;

        try {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            // Calls by Day of Week
            $calls = Interaction::selectRaw(
                'DAYOFWEEK(interactions.created_at) as dayofweek,
             COUNT(DISTINCT interactions.id) as calls,
             COUNT(DISTINCT DAY(stats_product.event_created_at)) as number_of_days'
            )->leftJoin(
                'events',
                'events.id',
                'interactions.event_id'
            )->leftJoin(
                'stats_product',
                'interactions.event_id',
                'stats_product.event_id'
            )->whereNotNull(
                'interactions.interaction_time'
            )->whereIn(
                'interactions.interaction_type_id',
                [1, 2]
            )->whereNull(
                'events.survey_id'
            )->whereNull(
                'parent_interaction_id'
            )->whereNull('stats_product.deleted_at');

            if ($start_date) {
                $calls = $calls->whereDate(
                    'stats_product.event_created_at',
                    '>=',
                    $start_date
                );
            }

            if ($end_date) {
                $calls = $calls->whereDate(
                    'stats_product.event_created_at',
                    '<=',
                    $end_date
                );
            }

            $calls = $this->usual_filters($calls, $request);

            $calls = $calls->groupBy('dayofweek')->get()->mapWithKeys(function ($c) use ($days) {
                $c->avg = ($c->calls > 0 && $c->number_of_days) ? round($c->calls / $c->number_of_days, 2) : 0;
                $day = $days[($c->dayofweek - 1)];
                return [$day => [
                    'dayofweek' => $day,
                    'avg' => $c->avg
                ]];
            });

            // TPV Agents by Day of Week
            $agents = StatsProduct::selectRaw(
                'DAYOFWEEK(stats_product.event_created_at) as dayofweek,
             COUNT(DISTINCT stats_product.tpv_agent_id) as active_agents,
             COUNT(DISTINCT DAY(stats_product.event_created_at)) as number_of_days'
            )->eventRange(
                $start_date,
                $end_date
            );

            $agents = $this->usual_filters($agents, $request);

            $agents = $agents->groupBy('dayofweek')->get()->mapWithKeys(function ($c) use ($days) {
                $c->avg = ($c->active_agents > 0 && $c->number_of_days) ? round($c->active_agents / $c->number_of_days) : 0;
                $day = $days[($c->dayofweek - 1)];
                return [$day => [
                    'dayofweek' => $day,
                    'avg' => $c->avg
                ]];
            });

            // Legacy data from API
            $res = $this->proxyApiCall('getDowStats?startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $dxcCalls = collect($proxyResponse->result == 'Success' ? $proxyResponse->data->data : []);

            $dxcCalls = $dxcCalls->mapWithKeys(function ($c) use ($days) {
                $c->avg = ($c->calls != 0 && $c->number_of_days) ? round($c->calls / $c->number_of_days, 2) : 0;
                $day = $days[($c->dayofweek - 1)];
                return [$day => [
                    'dayofweek' => $day,
                    'avg' => $c->avg,
                ]];
            });

            $dxcAgents = collect($proxyResponse->result == 'Success' ? $proxyResponse->data->data : []);

            $dxcAgents = $dxcAgents->mapWithKeys(function ($c) use ($days) {
                $c->avg = ($c->tpv_agents != 0 && $c->number_of_days) ? round($c->tpv_agents / $c->number_of_days) : 0;
                $day = $days[($c->dayofweek - 1)];
                return [$day => [
                    'dayofweek' => $day,
                    'avg' => $c->avg,
                ]];
            });

            // Compile all data and break out by Day of Week
            $calls_by_dow = [
                'dxc' => array(),
                'focus' => array()
            ];

            $tpv_agents_by_dow = [
                'dxc' => array(),
                'focus' => array()
            ];

            foreach ($days as $day) {
                if (!$calls->has($day)) {
                    $calls_by_dow['focus'][] = [
                        'dayofweek' => $day,
                        'avg' => 0,
                    ];
                } else {
                    $calls_by_dow['focus'][] = $calls->get($day);
                }

                if (!$dxcCalls->has($day)) {
                    $calls_by_dow['dxc'][] = [
                        'dayofweek' => $day,
                        'avg' => 0,
                    ];
                } else {
                    $calls_by_dow['dxc'][] = $dxcCalls->get($day);
                }

                if (!$agents->has($day)) {
                    $tpv_agents_by_dow['focus'][] = [
                        'dayofweek' => $day,
                        'avg' => 0,
                    ];
                } else {
                    $tpv_agents_by_dow['focus'][] = $agents->get($day);
                }

                if (!$dxcAgents->has($day)) {
                    $tpv_agents_by_dow['dxc'][] = [
                        'dayofweek' => $day,
                        'avg' => 0,
                    ];
                } else {
                    $tpv_agents_by_dow['dxc'][] = $dxcAgents->get($day);
                }
            }

            $result = [
                'result' => $proxyResponse->result,
                'message' => $proxyResponse->message,
                'proxy_data' => $proxyResponse ?? null,
                'calls_by_dow' => $calls_by_dow ?? [],
                'tpv_agents_by_dow' => $tpv_agents_by_dow ?? []
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'calls_by_dow' => $calls_by_dow ?? [],
                'tpv_agents_by_dow' => $tpv_agents_by_dow ?? []
            ];
        }

        return $result;
    }

    /**
     * Retrieve active TPV agent count for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function get_active_tpv_agents(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $proxyResponse = null;
        $result = null;

        try {

            // For Focus, get data from DB
            $stats_product = StatsProduct::selectRaw(
                'COUNT(stats_product.tpv_agent_id) as active_agents'
            )->eventRange(
                $start_date,
                $end_date
            )->whereNotNull('tpv_agent_id');

            $stats_product = $this->usual_filters($stats_product, $request);

            $stats_product = $stats_product->groupBy('tpv_agent_id')->get();

            // For legacy, we'll need to make an API call, routed through a proxy because of a whitelist in legacy.
            $res = $this->proxyApiCall('getActiveTpvAgents?startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $result = [
                'result' => $proxyResponse->result,
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'active_agents' => $stats_product->count(),
                'dxc_active_agents' => ($proxyResponse->result == 'Success' ? $proxyResponse->data->data->dxc : -999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'active_agents' => -998,
                'dxc_active_agents' => -998
            ];
        }

        return $result;
    }

    /**
     * Retrieve total calls counts for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function total_calls(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $proxyResponse = null;
        $result = null;

        try {

            // For Focus, get data from DB
            $stats_product = StatsProduct::selectRaw(
                'COUNT(DISTINCT stats_product.confirmation_code) as tpv_calls'
            )->eventRange(
                $start_date,
                $end_date
                // )->where(
                //     'stats_product.result',
                //     '!=',
                //     'closed'
            )->whereNotIn(
                'stats_product.source',
                ['Digital', 'Web Enroll']
            );

            $stats_product = $this->usual_filters($stats_product, $request);

            $stats_product = $stats_product->get()->toArray();

            // For legacy, we'll need to make an API call, routed through a proxy because of a whitelist in legacy.
            $res = $this->proxyApiCall('getTotalCalls?startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $result = [
                'result' => $proxyResponse->result,
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'total_calls' => $stats_product[0]['tpv_calls'],
                'dxc_total_calls' => ($proxyResponse && isset($proxyResponse->dxc_total_calls) ? $proxyResponse->dxc_total_calls : -999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'total_calls' => -998,
                'dxc_total_calls' => -998
            ];
        }

        return $result;
    }

    /**
     * Retrieve total payroll hours for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function total_payroll_hours(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('m-d-Y');
        $end_date = $request->endDate ?? Carbon::today()->format('m-d-Y');

        $proxyResponse = null;
        $result = null;

        try {
            // Payroll hours for both Focus and Legacy are stored in the legacy DB.
            // We'll need to make an API call routed through a proxy because of a whitelist in legacy.
            $res = $this->proxyApiCall('getPayrollHours?&startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $result = [
                'result' => $proxyResponse->result,
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'payroll_hours' => ($proxyResponse && isset($proxyResponse->payroll_hours) ? $proxyResponse->payroll_hours : -999),
                'dxc_payroll_hours' => ($proxyResponse && isset($proxyResponse->dxc_payroll_hours) ? $proxyResponse->dxc_payroll_hours : -999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'payroll_hours' => -998,
                'dxc_payroll_hours' => -998
            ];
        }

        return $result;
    }

    /**
     * Retrieve average handle times for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function avg_handle_time(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('m-d-Y');
        $end_date = $request->endDate ?? Carbon::today()->format('m-d-Y');

        $proxyResponse = null;
        $result = null;

        try {
            // Avg handle time both Focus and Legacy are stored in the legacy DB.
            // We'll need to make an API call routed through a proxy because of a whitelist in legacy.
            $res = $this->proxyApiCall('getAvgHandleTime?&startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $result = [
                'result' => $proxyResponse->result,
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'avg_handle_time' => ($proxyResponse && isset($proxyResponse->avg_handle_time) ? $proxyResponse->avg_handle_time : -999),
                'dxc_avg_handle_time' => ($proxyResponse && isset($proxyResponse->dxc_avg_handle_time) ? $proxyResponse->dxc_avg_handle_time : -999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse,
                'avg_handle_time' => -998,
                'dxc_avg_handle_time' => -998
            ];
        }

        return $result;
    }

    /**
     * Retrieve productive occupancy stats for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function productive_occupancy(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('m-d-Y');
        $end_date = $request->endDate ?? Carbon::today()->format('m-d-Y');

        $proxyResponse = null;
        $result = null;

        try {
            // Productive Occupany for both Focus and Legacy are stored in the legacy DB.
            // We'll need to make an API call routed through a proxy because of a whitelist in legacy.
            $res = $this->proxyApiCall('getProductiveOccupancy?startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $result = [
                'result' => $proxyResponse->result,
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'productive_occupancy' => ($proxyResponse && isset($proxyResponse->productive_occupancy) ? $proxyResponse->productive_occupancy : -999),
                'dxc_productive_occupancy' => ($proxyResponse && isset($proxyResponse->dxc_productive_occupancy) ? $proxyResponse->dxc_productive_occupancy : -999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'productive_occupancy' => -998,
                'dxc_productive_occupancy' => -998
            ];
        }

        return $result;
    }

    /**
     * Retrieve overal revenue per hour stats for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function overall_rev_per_hour(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('m-d-Y');
        $end_date = $request->endDate ?? Carbon::today()->format('m-d-Y');

        $proxyResponse = null;
        $result = null;

        try {
            // Over Rev. per Hour for both Focus and Legacy are stored in the legacy DB.
            // We'll need to make an API call routed through a proxy because of a whitelist in legacy.
            $res = $this->proxyApiCall('getRevenuePerHour?&startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $result = [
                'result' => $proxyResponse->result,
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'overall_rev_per_hour' => ($proxyResponse && isset($proxyResponse->overall_rev_per_hour) ? $proxyResponse->overall_rev_per_hour : -999),
                'dxc_overall_rev_per_hour' => ($proxyResponse && isset($proxyResponse->dxc_overall_rev_per_hour) ? $proxyResponse->dxc_overall_rev_per_hour : -999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'overall_rev_per_hour' => -998,
                'dxc_overall_rev_per_hour' => -998
            ];
        }

        return $result;
    }

    /**
     * Retrieve average call counts by half-hour for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function avg_calls_by_half_hour(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

        try {
            // Get data from Focus
            $calls = Interaction::selectRaw(
                "COUNT(DISTINCT interactions.id) as calls,
             CONCAT(CAST(HOUR( interactions.created_at ) AS CHAR(2)), ':', (CASE WHEN MINUTE( interactions.created_at ) <30 THEN '00' ELSE '30' END)) AS halfhour,
             COUNT(DISTINCT DAY(stats_product.event_created_at)) as number_of_days"
            )->leftJoin(
                'events',
                'interactions.event_id',
                'events.id'
            )->leftJoin(
                'stats_product',
                'interactions.event_id',
                'stats_product.event_id'
            )->whereNotNull(
                'interactions.interaction_time'
            )->whereIn(
                'interactions.interaction_type_id',
                [1, 2]
            )->whereNull(
                'parent_interaction_id'
            )->whereNull(
                'events.survey_id'
            )->whereNull('stats_product.deleted_at');

            if ($start_date) {
                $calls = $calls->whereDate(
                    'stats_product.event_created_at',
                    '>=',
                    $start_date
                );
            }

            if ($end_date) {
                $calls = $calls->whereDate(
                    'stats_product.event_created_at',
                    '<=',
                    $end_date
                );
            }

            $calls = $this->usual_filters($calls, $request);

            $calls = $calls->groupBy('halfhour')->get()->mapWithKeys(function ($c) {
                $c->avg = ($c->calls != 0 && $c->number_of_days) ? round($c->calls / $c->number_of_days, 1) : 0;
                return [$c->halfhour => [
                    'halfhour' => $c->halfhour,
                    'avg' => $c->avg,
                ]];
            });

            // For legacy data, make an API call.
            $res = $this->proxyApiCall('getAvgCallsByHalfHour?startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);
            // $proxyResponse = (object)[
            //     'result' => 'Success',
            //     'message' => '',
            //     'data' => [
            //         'result' => "Success",
            //         'message' => '',
            //         'data' => [],
            //         'linkId' => 'test-test-test'
            //     ]
            // ];

            $dxcCalls = collect($proxyResponse->result == 'Success' ? $proxyResponse->data->data : []);

            $dxcCalls = $dxcCalls->mapWithKeys(function ($c) {
                $c->avg = ($c->calls != 0 && $c->number_of_days) ? round($c->calls / $c->number_of_days, 1) : 0;
                return [$c->halfhour => [
                    'halfhour' => $c->halfhour,
                    'avg' => $c->avg,
                ]];
            });

            // Build the half hours array. We're allowing a maximum of one leading and trailing zero.
            // Zero values will be stored in a buffer until it's determined they're not a leading or trailing set.
            $half_hours = ['7:00', '7:30', '8:00', '8:30', '9:00', '9:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00', '22:30', '23:00', '23:30', '24:00'];
            $result = [
                'focus' => array(),
                'dxc' => array(),
            ];
            $buffer = [];

            if (count($calls) == 0 && count($dxcCalls) == 0) { // No data. Return entire half-hours set
                foreach ($half_hours as $hh) {
                    $result['focus'][] = [
                        'halfhour' => $hh,
                        'avg' => 0,
                    ];

                    $result['dxc'][] = [
                        'halfhour' => $hh,
                        'avg' => 0,
                    ];
                }
            } else {

                foreach ($half_hours as $hh) {
                    if (!$calls->has($hh) && !$dxcCalls->has($hh)) {
                        $buffer[] = [
                            'halfhour' => $hh,
                            'avg' => 0,
                        ];
                    } else {
                        // write buffer 
                        if (count($buffer) > 0) {
                            if (count($result['focus']) == 0 && count($result['dxc']) == 0) { // these were leading zeros. Write the last entry
                                $result['focus'][] = end($buffer);
                                $result['dxc'][] = end($buffer);
                                $buffer = []; // reset buffer

                            } else { // not leading zeros. Write them all
                                $result['focus'] = array_merge($result['focus'], $buffer);
                                $result['dxc'] = array_merge($result['dxc'], $buffer);
                                $buffer = []; // reset buffer
                            }
                        }

                        if ($calls->has($hh)) {
                            $result['focus'][] = $calls->get($hh);
                        } else {
                            $result['focus'][] = [
                                'halfhour' => $hh,
                                'avg' => 0
                            ];
                        }

                        if ($dxcCalls->has($hh)) {
                            $result['dxc'][] = $dxcCalls->get($hh);
                        } else {
                            $result['dxc'][] = [
                                'halfhour' => $hh,
                                'avg' => 0
                            ];
                        }
                    }
                }

                // We will always end with a non-zero value. Check if the buffer has at least one element and write the first one.
                if (count($buffer) > 0) {
                    $result['focus'][] = reset($buffer);
                    $result['dxc'][] = reset($buffer);
                }

                $result = [
                    'result' => 'Success',
                    'message' => '',
                    'proxy_data' => $proxyResponse ?? null,
                    'focus' => $result['focus'],
                    'dxc' => $result['dxc']
                ];
            }
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'focus' => ($result ? $result['focus'] : []),
                'dxc' => ($result ? $result['dxc'] : [])
            ];
        }

        return $result;
    }

    /**
     * Retrieve average calls by day of week for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object An object containing the requested data.
     */
    public function avg_calls_by_dow(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

        try {
            $calls = Interaction::selectRaw(
                'DAYOFWEEK(interactions.created_at) as dayofweek,
             COUNT(DISTINCT interactions.id) as calls,
             COUNT(DISTINCT DAY(stats_product.event_created_at)) as number_of_days'
            )->leftJoin(
                'events',
                'events.id',
                'interactions.event_id'
            )->leftJoin(
                'stats_product',
                'interactions.event_id',
                'stats_product.event_id'
            )->whereNotNull(
                'interactions.interaction_time'
            )->whereIn(
                'interactions.interaction_type_id',
                [1, 2]
            )->whereNull(
                'events.survey_id'
            )->whereNull(
                'parent_interaction_id'
            )->whereNull('stats_product.deleted_at');

            if ($start_date) {
                $calls = $calls->whereDate(
                    'stats_product.event_created_at',
                    '>=',
                    $start_date
                );
            }

            if ($end_date) {
                $calls = $calls->whereDate(
                    'stats_product.event_created_at',
                    '<=',
                    $end_date
                );
            }

            $calls = $this->usual_filters($calls, $request);

            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            $calls = $calls->groupBy('dayofweek')->get()->mapWithKeys(function ($c) use ($days) {
                $c->avg = ($c->calls > 0 && $c->number_of_days) ? round($c->calls / $c->number_of_days, 2) : 0;
                $day = $days[($c->dayofweek - 1)];
                return [$day => [
                    'dayofweek' => $day,
                    'avg' => $c->avg
                ]];
            });

            // For legacy data, make an API call.
            $res = $this->proxyApiCall('getAvgCallsByDow?startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $dxcCalls = collect($proxyResponse->result == 'Success' ? $proxyResponse->dxc_call_data : []);

            $dxcCalls = $dxcCalls->mapWithKeys(function ($c) use ($days) {
                $c->avg = ($c->calls != 0 && $c->number_of_days) ? round($c->calls / $c->number_of_days, 2) : 0;
                $day = $days[($c->dayofweek - 1)];
                return [$day => [
                    'dayofweek' => $day,
                    'avg' => $c->avg,
                ]];
            });

            $result = [
                'focus' => array(),
                'dxc' => array()
            ];

            foreach ($days as $day) {
                if (!$calls->has($day)) {
                    $result['focus'][] = [
                        'dayofweek' => $day,
                        'avg' => 0,
                    ];
                } else {
                    $result['focus'][] = $calls->get($day);
                }

                if (!$dxcCalls->has($day)) {
                    $result['dxc'][] = [
                        'dayofweek' => $day,
                        'avg' => 0,
                    ];
                } else {
                    $result['dxc'][] = $dxcCalls->get($day);
                }
            }

            $result = [
                'result' => 'Success',
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'focus' => $result['focus'],
                'dxc' => $result['dxc']
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'focus' => [],
                'dxc' => []
            ];
        }

        return $result;
    }

    /**
     * Retrieve average active TPV agents by day of week for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function avg_active_tpv_agents_by_dow(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

        try {
            $agents = StatsProduct::selectRaw(
                'DAYOFWEEK(stats_product.event_created_at) as dayofweek,
             COUNT(DISTINCT stats_product.tpv_agent_id) as active_agents,
             COUNT(DISTINCT DAY(stats_product.event_created_at)) as number_of_days'
            )->eventRange(
                $start_date,
                $end_date
            );

            $agents = $this->usual_filters($agents, $request);

            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            $agents = $agents->groupBy('dayofweek')->get()->mapWithKeys(function ($c) use ($days) {
                $c->avg = ($c->active_agents > 0 && $c->number_of_days) ? round($c->active_agents / $c->number_of_days) : 0;
                $day = $days[($c->dayofweek - 1)];
                return [$day => [
                    'dayofweek' => $day,
                    'avg' => $c->avg
                ]];
            });

            // For legacy data, make an API call.
            $res = $this->proxyApiCall('getAvgTpvAgentsByDow?startDate=' . $start_date . '&endDate=' . $end_date);

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            $dxcAgents = collect($proxyResponse->result == 'Success' ? $proxyResponse->dxc_tpv_agents : []);

            $dxcAgents = $dxcAgents->mapWithKeys(function ($c) use ($days) {
                $c->avg = ($c->active_agents != 0 && $c->number_of_days) ? round($c->active_agents / $c->number_of_days) : 0;
                $day = $days[($c->dayofweek - 1)];
                return [$day => [
                    'dayofweek' => $day,
                    'avg' => $c->avg,
                ]];
            });

            $result = [
                'focus' => array(),
                'dxc' => array()
            ];

            foreach ($days as $day) {
                if (!$agents->has($day)) {
                    $result['focus'][] = [
                        'dayofweek' => $day,
                        'avg' => 0,
                    ];
                } else {
                    $result['focus'][] = $agents->get($day);
                }

                if (!$dxcAgents->has($day)) {
                    $result['dxc'][] = [
                        'dayofweek' => $day,
                        'avg' => 0,
                    ];
                } else {
                    $result['dxc'][] = $dxcAgents->get($day);
                }
            }

            $result = [
                'result' => 'Success',
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'focus' => $result['focus'],
                'dxc' => $result['dxc']
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'focus' => ($result ? $result['focus'] : []),
                'dxc' => ($result ? $result['dxc'] : [])
            ];
        }

        return $result;
    }

    /**
     * Retrieve TPV agent stats for the dashboard.
     * 
     * @param Request $request The Laravel HTTP request object.
     * 
     * @return object The object containing the requested data.
     */
    public function tpv_agent_stats(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');
        $column = $request->get('column');
        $direction = $request->get('direction');

        $proxyResponse = null;
        $result = null;

        try {
            $res = $this->proxyApiCall('getTpvAgentStats?startDate=' . $start_date . '&endDate=' . $end_date .
                ($column && $direction
                    ? '&sortBy=' . $column . '&direction=' . $direction
                    : ''));

            $proxyResponse = $res->getBody();
            $proxyResponse = json_decode($proxyResponse);

            //     return $proxyResponse->data->data;
            $result = [
                'result' => 'Success',
                'message' => '',
                'proxy_data' => $proxyResponse ?? null,
                'data' => ($proxyResponse && isset($proxyResponse->data->data) ? $proxyResponse->data->data : [])
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'proxy_data' => $proxyResponse ?? null,
                'data' => ($proxyResponse ? $proxyResponse->data->data : [])
            ];
        }

        return $result;
        // return [
        //     'result' => 'Success',
        //     'message' => '',
        //     'proxy_data' => null,
        //     'data' => []
        // ];
    }

    public function get_brand_id(string $name)
    {
        return Cache::remember(base64_encode($name), 3600, function () use ($name) {
            return Brand::select('id')->where(
                'name',
                $name
            )->first()->id;
        });
    }

    /**
     * Apply common filters to query
     * 
     * @param Model $model The Laravel model.
     * @param Request $request The Laravel HTTP request.
     * @param array $params The filters to apply.
     * 
     * @return Model An updated model, with the filters applied.
     */
    protected function usual_filters($model, Request $request = null, array $params = [])
    {
        if (count($params) > 0) {
            extract($params);
        } else {
            $brand = $request->get('brand');
            $commodity = $request->get('commodity');
            $channel = $request->get('channel');
            $language = $request->get('language');
            $state = $request->get('state');
            $market = $request->get('market');
        }


        if ($brand) {
            $model = $model->whereIn('stats_product.brand_id', $this->listToArray($brand));
        }

        if ($channel) {
            $model = $model->whereIn('stats_product.channel_id', $this->listToArray($channel));
        }

        if ($market) {
            $model = $model->whereIn('stats_product.market_id', $this->listToArray($market));
        }

        if ($language) {
            $model = $model->whereIn('stats_product.language_id', $this->listToArray($language));
        }

        if ($commodity) {
            $model = $model->whereIn('stats_product.commodity_id', $this->listToArray($commodity));
        }

        if ($state) {
            $model = $model->leftJoin(
                'states',
                'stats_product.service_state',
                'states.state_abbrev'
            )->whereIn(
                'states.id',
                $this->listToArray($state)
            );
        }

        $model = $model->where(
            'stats_product.brand_id',
            '!=',
            $this->get_brand_id('Green Mountain Energy Company')
        );

        return $model;
    }

    /**
     * Use Guzzle to proxy an API call GET through the DXCProxyController.
     * 
     * @param string $functionName The proxy function being requested.
     * 
     * @return object An object containing the result of the API call.
     */
    private function proxyApiCall(string $functionName)
    {
        $client = new \GuzzleHttp\Client(
            [
                'verify' => false,
            ]
        );

        $res = $client->request(
            'GET',
            config('services.dxc_proxy_url') . '/api/dxc/proxy/' . $functionName,
            ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
        );

        return $res;
    }


    private function listToArray($items)
    {
        if (is_array($items)) {
            return $items;
        }
        if ($items != null && strlen(trim($items)) > 0) {
            return explode(',', $items);
        }

        return [];
    }
}
