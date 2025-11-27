<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\StatsProduct;
use App\Models\Interaction;
use App\Models\EventAlert;
use App\Mail\EventAlertTripped;

class HomeController extends Controller
{
    public function root()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }
        return redirect('/dashboard');
    }

    public static $COLORS = [
        '#d9534f', '#5bc0de', '#5cb85c', '#428bca',
        '#ffbb33', '#FF8800', '#00C851', '#007E33',
        '#0099CC', '#33b5e5'
    ];

    public static $CACHE_DURATION = 30;

    public function debug_mailable()
    {
        if (config('app.env', 'production') === 'production') {
            abort(400);
        }
        $id = request()->input('id');
        $alert = EventAlert::find($id);

        return new EventAlertTripped($alert);
    }

    public function user_get(Request $request)
    {
        return $request->user();
    }

    public function supervisor_verify(Request $request)
    {
        $username = request()->input('username');
        $password = request()->input('password');
        // $action = request()->input('action');

        if (Auth::attempt(['username' => $username, 'password' => $password], false)) {
            return response()->json(['authorized' => true]);
        }

        return response()->json(['authorized' => false]);
    }
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $start_date = $request->exists('startDate')
            ? $request->get('startDate')
            : Carbon::today()->subDays(14)->format('Y-m-d');
        $end_date = $request->exists('endDate')
            ? $request->get('endDate') : Carbon::today()->format('Y-m-d');
        $channel = $request->get('channel');

        return view(
            'dashboard.dashboard',
            [
                'sales_no_sales_chart' => $this->sales_no_sales_chart($start_date, $end_date, $channel),
                'good_sale_no_sale_chart' => $this->good_sale_no_sale_chart($start_date, $end_date, $channel),
                'top_sale_agents' => $this->top_sale_agents($start_date, $end_date, $channel),
                'top_sold_products' => $this->top_sold_products($start_date, $end_date, $channel),
                'no_sale_dispositions_chart' => $this->no_sale_dispositions_chart($start_date, $end_date, $channel),
                'sales_by_vendor' => $this->sales_by_vendor($start_date, $end_date, $channel),
                'sales_per_hour_chart' => $this->sales_per_hour_chart($start_date, $end_date, $channel),
                'eztpvs' => $this->eztpv_stats($start_date, $end_date, $channel),
                'sales_no_sales' => $this->sales_no_sales($start_date, $end_date, $channel),
            ]
        );
    }

    public function globo_search()
    {
        if (!request()->ajax()) {
            return view('search.results');
        }

        $query = request()->input('query');
        $page = request()->input('page');
        $perPage = request()->input('perPage');
        if (!is_numeric($perPage)) {
            $perPage = 15;
        }
        if (!is_numeric($page)) {
            $page = 1;
        }

        $type = null;
        if ($query == null) {
            $results = [];
        } else {
            if (preg_match('/\d{3}\.[0-9a-z]{14}\.\d{8}/', $query) === 1) {
                return redirect('/errors?ref=' . $query);
            }
            if (!is_numeric($query)) {
                $parts = explode(' ', $query);
                $type = 'StatsProduct';
                switch (count($parts)) {
                    case 2:
                        $f_name = $parts[0];
                        $l_name = $parts[1];
                        $results = StatsProduct::where('bill_first_name', 'like', '%' . $f_name . '%')
                            ->where('bill_last_name', 'like', '%' . $l_name . '%')
                            ->orWhere('company_name', 'like', '%' . $query . '%')
                            ->orWhere('email_address', 'like', '%' . $query . '%')
                            ->orWhere(function ($q) use ($f_name, $l_name) {
                                $q->where('auth_first_name', 'like', '%' . $f_name . '%')
                                    ->where('auth_last_name', 'like', '%' . $l_name . '%');
                            })->orWhere(function ($q) use ($query) {
                                $q->where('service_address1', 'like', '%' . $query . '%');
                            });
                        break;
                    case 3:
                        $f_name = $parts[0];
                        $m_name = $parts[1];
                        $l_name = $parts[2];
                        $results = StatsProduct::where('bill_first_name', 'like', '%' . $f_name . '%')
                            ->where('bill_middle_name', 'like', '%' . $m_name . '%')
                            ->where('bill_last_name', 'like', '%' . $l_name . '%')
                            ->orWhere('company_name', 'like', '%' . $query . '%')
                            ->orWhere('email_address', 'like', '%' . $query . '%')
                            ->orWhere(function ($q) use ($f_name, $m_name, $l_name) {
                                $q->where('auth_first_name', 'like', '%' . $f_name . '%')
                                    ->where('auth_middle_name', 'like', '%' . $m_name . '%')
                                    ->where('auth_last_name', 'like', '%' . $l_name . '%');
                            })->orWhere(function ($q) use ($query) {
                                $q->where('service_address1', 'like', '%' . $query . '%');
                            });
                        break;

                    default:
                        $results = StatsProduct::where('bill_first_name', 'like', '%' . $query . '%')
                            ->orWhere('bill_last_name', 'like', '%' . $query . '%')
                            ->orWhere('auth_first_name', 'like', '%' . $query . '%')
                            ->orWhere('auth_last_name', 'like', '%' . $query . '%')
                            ->orWhere('company_name', 'like', '%' . $query . '%')
                            ->orWhere('email_address', 'like', '%' . $query . '%')
                            ->orWhere('service_address1', 'like', '%' . $query . '%');
                }
            } else {
                $type = 'StatsProduct';
                if (strlen((string) $query) == 11) {
                    $results = StatsProduct::where('confirmation_code', $query)->orWhere('account_number1', 'like', '%' . $query . '%');
                } else {
                    $results = StatsProduct::where('account_number1', 'like', '%' . $query . '%')
                        ->orWhere('confirmation_code', 'like', '%' . $query . '%')
                        ->orWhere('btn', 'like', '%' . $query . '%');
                }
            }
        }

        /*if (is_object($results) && $results->count() == 1 && $type == 'StatsProduct') {
            return redirect('/events/' . $results->first()->event_id);
        }*/
        if (is_object($results)) {
            $results = $results->orderBy('created_at', 'DESC');
            $results = $results->paginate($perPage);
            //dd($results);
        }

        return [
            'results' => $type !== null ? $results->all() : $results,
            'query' => $query,
            'page' => $type !== null ? $results->currentPage() : 1,
            'perPage' => $perPage,
            'total' => $type !== null ? $results->total() : 0,
            'type' => $type,
        ];
    }

    private function eztpv_stats(
        $start_date,
        $end_date,
        $channel
    ) {
        $eztpvs = StatsProduct::select(
            DB::raw('SUM(CASE WHEN eztpv_id IS NOT NULL THEN 1 ELSE 0 END) AS eztpv'),
            DB::raw('SUM(CASE WHEN eztpv_id IS NULL THEN 1 ELSE 0 END) AS noeztpv')
        )->eventRange(
            $start_date,
            $end_date
        );

        if ($channel) {
            $eztpvs = $eztpvs->whereIn('stats_product.channel_id', $channel);
        }

        $eztpvs = $eztpvs->get();

        if (isset($eztpvs[0])) {
            $eztpvs = $eztpvs[0];
            $eztpvs->total = $eztpvs['eztpv'] + $eztpvs['noeztpv'];
            $eztpvs->eztpv_percentage = (isset($eztpvs['eztpv'])
                && $eztpvs['eztpv'] > 0 && $eztpvs->total > 0)
                ? number_format(
                    ($eztpvs['eztpv'] / $eztpvs->total) * 100,
                    2
                ) : 0;
            $eztpvs->no_eztpv_percentage = (isset($eztpvs['noeztpv'])
                && $eztpvs['noeztpv'] > 0 && $eztpvs->total > 0)
                ? number_format(
                    ($eztpvs['noeztpv'] / $eztpvs->total) * 100,
                    2
                ) : 0;
        } else {
            $eztpvs = null;
        }

        return $eztpvs;
    }

    private function sales_no_sales(
        $start_date,
        $end_date,
        $channel
    ) {
        $sales = StatsProduct::select(
            DB::raw('SUM(CASE WHEN result = "Sale" THEN 1 ELSE 0 END) AS sales'),
            DB::raw('SUM(CASE WHEN result = "No Sale" AND disposition_reason NOT IN ("Pending", "Abandoned") THEN 1 ELSE 0 END) AS nosales')
        )->eventRange(
            $start_date,
            $end_date
        );

        if ($channel) {
            $sales = $sales->whereIn('stats_product.channel_id', $channel);
        }

        $sales = $sales->get();

        if (isset($sales[0])) {
            $sales = $sales[0];
            $sales->total = $sales['sales'] + $sales['nosales'];
            $sales->sale_percentage = (isset($sales['sales'])
                && $sales['sales'] > 0 && $sales->total > 0)
                ? number_format(
                    ($sales['sales'] / $sales->total) * 100,
                    2
                ) : 0;
            $sales->no_sale_percentage = (isset($sales['nosales'])
                && $sales['nosales'] > 0 && $sales->total > 0)
                ? number_format(
                    ($sales['nosales'] / $sales->total) * 100,
                    2
                ) : 0;
        } else {
            $sales = null;
        }

        return $sales;
    }

    private function sales_no_sales_chart(
        $start_date,
        $end_date,
        $channel
    ) {
        if ($start_date == $end_date) {
            $linechart = StatsProduct::select(
                DB::raw('HOUR(stats_product.event_created_at) AS the_dates'),
                DB::raw('SUM(CASE WHEN result = "Sale" THEN 1 ELSE 0 END) AS sales'),
                DB::raw('SUM(CASE WHEN result = "No Sale" AND disposition_reason NOT IN ("Pending", "Abandoned") THEN 1 ELSE 0 END) AS nosales')
            )->eventRange(
                $start_date,
                $end_date
            );

            if ($channel) {
                $linechart = $linechart->whereIn('stats_product.channel_id', $channel);
            }

            $linechart = $linechart->groupBy(
                DB::raw('HOUR(stats_product.event_created_at)')
            )->get();

            $labels = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23];

            $data = $linechart->toArray();
            $newarray = [];
            for ($i = 0; $i < count($data); $i++) {
                $newarray[$data[$i]['the_dates']] = $data[$i];
            }

            for ($i = 7; $i < 24; $i++) {
                if (!isset($newarray[$i])) {
                    $newarray[$i] = ['the_dates' => $i, 'sales' => 0, 'nosales' => 0];
                }
            }

            asort($newarray);

            $sales = [];
            $nosales = [];

            foreach ($newarray as $na) {
                $sales[] = $na['sales'];
                $nosales[] = $na['nosales'];
            }
        } else {
            $linechart = StatsProduct::select(
                DB::raw('DATE_FORMAT(stats_product.event_created_at, "%Y-%m-%d") AS the_dates'),
                DB::raw('SUM(CASE WHEN result = "Sale" THEN 1 ELSE 0 END) AS sales'),
                DB::raw('SUM(CASE WHEN result = "No Sale" AND disposition_reason NOT IN ("Pending", "Abandoned") THEN 1 ELSE 0 END) AS nosales')
            )->eventRange(
                $start_date,
                $end_date
            );

            if ($channel) {
                $linechart = $linechart->whereIn('stats_product.channel_id', $channel);
            }

            $linechart = $linechart->groupBy(
                DB::raw('DATE_FORMAT(stats_product.event_created_at, "%Y-%m-%d")')
            )->get();

            $labels = [];
            $period = \Carbon\CarbonPeriod::create($start_date, $end_date);
            foreach ($period as $date) {
                $labels[] = $date->format('Y-m-d');
            }

            $data = $linechart->toArray();
            $newarray = [];
            for ($i = 0; $i < count($data); $i++) {
                $newarray[$data[$i]['the_dates']] = $data[$i];
            }

            foreach ($labels as $label) {
                if (!isset($newarray[$label])) {
                    $newarray[$label] = ['the_dates' => $i, 'sales' => 0, 'nosales' => 0];
                }
            }

            asort($newarray);

            $sales = [];
            $nosales = [];

            foreach ($newarray as $na) {
                $sales[] = $na['sales'];
                $nosales[] = $na['nosales'];
            }
        }

        return app()->chartjs
            ->name('salesNoSalesChart')
            ->type('bar')
            ->size(['width' => 400, 'height' => 150])
            ->labels($labels)
            ->datasets(
                [
                    [
                        "label" => "Sales",
                        "backgroundColor" => "rgb(0, 119, 200)",
                        "data" => $sales,
                    ],
                    [
                        "label" => "No Sales",
                        "backgroundColor" => "rgb(237, 139, 0)",
                        "data" => $nosales,
                    ],
                ]
            )->options(
                []
            )->optionsRaw(
                [
                    'legend' => [
                        'display' => true,
                        'labels' => [
                            'fontColor' => '#000'
                        ]
                    ],
                    'scales' => [
                        'xAxes' => [
                            [
                                'stacked' => true,
                                'gridLines' => [
                                    'display' => true
                                ]
                            ]
                        ]
                    ]
                ]
            );
    }

    private function good_sale_no_sale_chart(
        $start_date,
        $end_date,
        $channel
    ) {
        $sp = StatsProduct::select(
            DB::raw('SUM(CASE WHEN result = "Sale" THEN 1 ELSE 0 END) AS sales'),
            DB::raw('SUM(CASE WHEN result = "No Sale" AND disposition_reason NOT IN ("Pending", "Abandoned") THEN 1 ELSE 0 END) AS nosales')
        )->eventRange(
            $start_date,
            $end_date
        );

        if ($channel) {
            $sp = $sp->whereIn('stats_product.channel_id', $channel);
        }

        $sp = $sp->get();

        return app()->chartjs
            ->name('goodSaleNoSaleChart')
            ->type('pie')
            ->size(['width' => 100, 'height' => 50])
            ->labels(['No Sale', 'Sale'])
            ->datasets(
                [
                    [
                        "backgroundColor" => [
                            "rgb(237, 139, 0)",
                            "rgb(0, 119, 200)"
                        ],
                        "data" => [
                            (int) $sp[0]->nosales,
                            (int) $sp[0]->sales
                        ],
                    ],
                ]
            )->options([]);
    }

    private function top_sale_agents(
        $start_date,
        $end_date,
        $channel
    ) {
        $sp = StatsProduct::select(
            'sales_agent_name AS sales_agent',
            'brand_name AS vendor',
            DB::raw('COUNT(stats_product.id) AS sales_num')
        )->whereNotNull(
            'sales_agent_name'
        )->where(
            'result',
            'Sale'
        )->eventRange(
            $start_date,
            $end_date
        );

        if ($channel) {
            $sp =  $sp->whereIn('channel_id', $channel);
        }

        $sp = $sp->groupBy(
            'sales_agent_name',
            'brand_name'
        )->orderBy(
            'sales_num',
            'desc'
        )->limit(5)->get();

        return $sp;
    }

    private function top_sold_products(
        $start_date,
        $end_date,
        $channel
    ) {
        $sp = StatsProduct::select(
            'product_id AS id',
            'product_name AS name',
            DB::raw('COUNT(product_id) AS sales_num')
        )->where(
            'result',
            'Sale'
        )->whereNotNull(
            'product_id'
        )->eventRange(
            $start_date,
            $end_date
        );

        if ($channel) {
            $sp =  $sp->whereIn('channel_id', $channel);
        }

        $sp =  $sp->groupBy(
            'product_id',
            'product_name'
        )->orderBy(
            'sales_num',
            'desc'
        )->limit(5)->get();

        return $sp;
    }

    private function no_sale_dispositions_chart(
        $start_date,
        $end_date,
        $channel
    ) {
        $no_sales_dispositions = StatsProduct::select(
            'disposition_reason AS reason',
            DB::raw('COUNT(stats_product.id) AS no_sales_num')
        )->whereNotIn(
            'disposition_reason',
            ['Abandoned', 'Pending']
        )->where(
            'result',
            'No Sale'
        )->whereNotNull(
            'disposition_reason'
        )->eventRange(
            $start_date,
            $end_date
        );

        if ($channel) {
            $no_sales_dispositions =  $no_sales_dispositions->whereIn('channel_id', $channel);
        }

        $no_sales_dispositions =  $no_sales_dispositions->groupBy(
            'disposition_reason'
        )->orderBy(
            'no_sales_num',
            'desc'
        )->get();

        return app()->chartjs
            ->name('noSaleDispositionsChart')
            ->type('horizontalBar')
            ->size(['width' => 200, 'height' => 100])
            ->labels($no_sales_dispositions->pluck('reason')->toArray())
            ->datasets(
                [
                    [
                        "backgroundColor" => "rgb(237, 139, 0)",
                        "data" => $no_sales_dispositions->pluck('no_sales_num')
                            ->toArray(),
                    ],
                ]
            )->options([])->optionsRaw(
                [
                    'legend' => [
                        'display' => false,
                        'labels' => [
                            'fontColor' => '#000'
                        ]
                    ]
                ]
            );
    }

    private function sales_per_hour_chart(
        $start_date,
        $end_date,
        $channel
    ) {
        $calls = Interaction::select(
            DB::raw('HOUR(interactions.created_at) AS hour'),
            DB::raw('COUNT(interactions.id) AS calls_count')
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->whereNull(
            'interactions.parent_interaction_id'
        )->whereNotNull(
            'interactions.interaction_time'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )->creationRange(
            $start_date,
            $end_date
        )->channel(
            $channel
        )->groupBy('hour')->orderBy('hour')->get();


        $sales = StatsProduct::select(
            DB::raw('HOUR(stats_product.event_created_at) AS hour'),
            DB::raw('COUNT(stats_product.id) AS sales_num')
        )->eventRange(
            $start_date,
            $end_date
        )->where(
            'result',
            'Sale'
        );

        if ($channel) {
            $sales = $sales->whereIn('stats_product.channel_id', $channel);
        }

        $sales = $sales->groupBy('hour')->orderBy('hour')->get();

        return app()->chartjs
            ->name('salesPerHourChart')
            ->type('bar')
            ->size(['width' => 400, 'height' => 200])
            ->labels($calls->pluck('hour')->toArray())
            ->datasets(
                [
                    [
                        "label" => "Sales",
                        "backgroundColor" => "rgb(0, 119, 200)",
                        "data" => $sales->pluck('sales_num')->toArray(),
                    ], [
                        "label" => "Calls",
                        "backgroundColor" => "rgb(237, 139, 0)",
                        "data" => $calls->pluck('calls_count')->toArray(),
                    ]
                ]
            )->options([])->optionsRaw(
                [
                    'legend' => [
                        'display' => true,
                        'labels' => [
                            'fontColor' => '#000'
                        ]
                    ],
                    'scales' => [
                        'xAxes' => [
                            [
                                'stacked' => true,
                                'gridLines' => [
                                    'display' => true
                                ]
                            ]
                        ]
                    ]
                ]
            );
    }

    private function sales_by_vendor($start_date, $end_date, $channel)
    {
        $sp = StatsProduct::select(
            'brand_id as id',
            'brand_name as name',
            DB::raw('COUNT(stats_product.id) AS sales_num')
        )->where(
            'result',
            'Sale'
        )->eventRange(
            $start_date,
            $end_date
        );

        if ($channel) {
            $sp = $sp->whereIn('stats_product.channel_id', $channel);
        }

        $sp = $sp->groupBy(
            'brand_id',
            'brand_name'
        )->orderBy(
            'sales_num',
            'desc'
        )->get();

        return $sp;
    }
}
