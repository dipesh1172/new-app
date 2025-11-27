<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Carbon\Carbon;

class DXCProxyController extends Controller
{
    /**
     * Returns the TPV agent count from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getActiveTpvAgents(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getActiveTpvAgents&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null
            ];
        }

        return $result;
    }

    /**
     * Returns the by-day-of-week stats from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getDowStats(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getDowStats&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => []
            ];
        }

        return $result;
    }

    /**
     * Returns call center stats from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getCallCenterStats(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getCallCenterStats&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null
            ];
        }

        return $result;
    }

    /**
     * Returns the TPV calls count from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getTotalCalls(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getTotalCalls&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body,
                'dxc_total_calls' => ($body->result == 'Success' ? $body->data->dxc : -9999)
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null,
                'dxc_total_calls' => -9998
            ];
        }

        return $result;
    }

    /**
     * Returns the total payroll hours from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getPayrollHours(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getPayrollHours&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body,
                'payroll_hours' => ($body->result == 'Success' ? $body->data->focus : -9999),
                'dxc_payroll_hours' => ($body->result == 'Success' ? $body->data->dxc : -9999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null,
                'payroll_hours' => -9998,
                'dxc_payroll_hours' => -9998
            ];
        }

        return $result;
    }

    /**
     * Returns the average handle time from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getAvgHandleTime(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getAvgHandleTime&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body,
                'avg_handle_time' => ($body->result == 'Success' ? $body->data->focus : -9999),
                'dxc_avg_handle_time' => ($body->result == 'Success' ? $body->data->dxc : -9999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null,
                'avg_handle_time' => -9998,
                'dxc_avg_handle_time' => -9998
            ];
        }

        return $result;
    }

    /**
     * Returns the productive occupancy from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getProductiveOccupancy(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getProductiveOccupancy&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body,
                'productive_occupancy' => ($body->result == 'Success' ? $body->data->focus : -9999),
                'dxc_productive_occupancy' => ($body->result == 'Success' ? $body->data->dxc : -9999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null,
                'productive_occupancy' => -9998,
                'dxc_productive_occupancy' => -9998
            ];
        }

        return $result;
    }

    /**
     * Returns the overall revenue per hour from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getRevenuePerHour(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getRevenuePerHour&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body,
                'overall_rev_per_hour' => ($body->result == 'Success' ? $body->data->focus : -9999),
                'dxc_overall_rev_per_hour' => ($body->result == 'Success' ? $body->data->dxc : -9999)
            ];
        } catch (\Exception $e) {

            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null,
                'overall_rev_per_hour' => -9998,
                'dxc_overall_rev_per_hour' => -9998
            ];
        }

        return $result;
    }

    /**
     * Returns the avg call counts by day of week from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getAvgCallsByDow(Request $request)
    {
        $result = null;

        try {
            // Get date range from request            
            $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getAvgCallsByDow&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body,
                'dxc_call_data' => $body->data
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null,
                'dxc_call_data' => []
            ];
        }

        return $result;
    }

    /**
     * Returns the avg TPV agents by day of week from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getAvgTpvAgentsByDow(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getAvgTpvAgentsByDow&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body,
                'dxc_tpv_agents' => $body->data
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null,
                'dxc_tpv_agents' => []
            ];
        }

        return $result;
    }

    /**
     * Returns the avg TPV call counts by half hour from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getAvgCallsByHalfHour(Request $request)
    {
        $result = null;

        try {
            // Get date range from request
            $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

            // Set up Guzzle to make an API call to legacy API
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            // Make the request
            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getAvgCallsByHalfHour&startDate=' . $start_date . '&endDate=' . $end_date,
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            // Parse results
            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null
            ];
        }

        return $result;
    }

    /**
     * Returns TPV agent statistics from the legacy DXC database for a given date range.
     * 
     * @param Request $request The HTTP Request details.
     * @return object An object containing the results of the lookup.
     */
    public function getTpvAgentStats(Request $request)
    {
        $result = null;

        try {
            $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
            $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');
            $column = $request->get('sortBy');
            $direction = $request->get('direction');

            // For legacy data, make an API call.
            $client = new \GuzzleHttp\Client(
                [
                    'verify' => false,
                ]
            );

            $res = $client->request(
                'GET',
                'https://ws.dxc-inc.com/dxc-for-focus/tpv-agents-dashboard.php?method=getTpvAgentStats&startDate=' . $start_date . '&endDate=' . $end_date
                    . ($column && $direction
                        ? '&sortBy=' . $column . '&direction=' . $direction
                        : ''),
                ['auth' => ['focus_api', 'm0v3T0_F0cus4lr3ady!']]
            );

            $body = $res->getBody();
            $body = json_decode($body);

            $result = [
                'result' => $body->result,
                'message' => $body->message,
                'data' => $body,
            ];
        } catch (\Exception $e) {
            $result = [
                'result' => 'Error',
                'message' => 'File: ' . $e->getFile() . ' -- Line: ' . $e->getLine() . ' -- Message: ' . $e->getMessage(),
                'data' => null,
            ];
        }

        return $result;
    }
}
