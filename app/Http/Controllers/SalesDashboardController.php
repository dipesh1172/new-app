<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Traits\SearchFormTrait;
use App\Models\StatsProduct;
use App\Models\Interaction;
use App\Models\Brand;

class SalesDashboardController extends Controller
{
    use SearchFormTrait;

    public function index()
    {
        return view(
            'dashboard',
            [
                'brands' => $this->get_brands(),
                'languages' => $this->get_languages(),
                'commodities' => $this->get_commodities(),
                'states' => $this->get_states(),
            ]
        );
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

    public function pending_report()
    {
        $start = request()->input('startDate');
        $end = request()->input('endDate');
        $pendings = StatsProduct::where('disposition_reason', 'Pending')
            ->where('source', 'EZTPV')
            ->eventRange($start, $end)
            ->orderBy('created_at', 'ASC')
            ->get()
            ->map(function ($item) use ($start, $end) {
                $others = StatsProduct::select('*')
                    ->where('bill_first_name', $item->bill_first_name)
                    ->where('bill_last_name', $item->bill_last_name)
                    ->where('rate_program_code', $item->rate_program_code)
                    ->where('btn', $item->btn)
                    ->where('commodity', $item->commodity)
                    ->where('service_state', $item->service_state)
                    ->eventRange($start, $end)
                    ->whereNotIn('id', [$item->id])
                    ->orderBy('created_at', 'ASC')
                    ->get();

                if ($others->count() == 0) {
                    return null;
                }

                return [$item->confirmation_code => ['original' => $item, 'new' => $others]];
            })->filter()->values();

        return response($pendings, 200);
    }

    public function sales_no_sales(Request $request)
    {
        $params = $this->reportParams($request);
        $data = $this->_sales_no_sales(...$params);

        return response()->json($data);
    }

    private function _sales_no_sales(
        $brand,
        $start_date,
        $end_date,
        $channel,
        $market,
        $language,
        $commodity,
        $state
    ) {
        $data = StatsProduct::selectRaw(
           "SUM(result = 'Sale') AS sales,
           SUM(result = 'No Sale' AND disposition_reason NOT IN ('Pending', 'Abandoned')) AS nosales"
        )->where(
            'stats_product_type_id',
            1
        )->eventRange(
            $start_date,
            $end_date
        );

        $data = $this->usual_filters($data, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        $data = $data->get();

        if (isset($data[0])) {
            $data = $data[0];
            $data->total = $data['sales'] + $data['nosales'];
            $data->sale_percentage = (isset($data['sales'])
                && $data['sales'] > 0
                && $data->total > 0) ? number_format(
                ($data['sales'] / $data->total) * 100,
                2
            ) : 0;
            $data->no_sale_percentage = (isset($data['nosales'])
                && $data['nosales'] > 0
                && $data->total > 0) ? number_format(
                ($data['nosales'] / $data->total) * 100,
                2
            ) : 0;
        } else {
            $data = null;
        }

        return $data;
    }

    public function sales_no_sales_dataset(Request $request)
    {
        $params = $this->reportParams($request);
        $data = $this->_sales_no_sales_dataset(...$params);

        return response()->json($data);
    }

    private function _sales_no_sales_dataset(
        $brand,
        $start_date,
        $end_date,
        $channel,
        $market,
        $language,
        $commodity,
        $state
    ) {
        $builder = StatsProduct::where(
            'stats_product_type_id',
            1
        )->eventRange(
            $start_date,
            $end_date
        );

        $builder = $this->usual_filters($builder, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        $the_dates =  $start_date != $end_date 
        ? DB::raw('DATE_FORMAT(event_created_at, "%Y-%m-%d") AS the_dates')
        : DB::raw('HOUR(event_created_at) AS the_dates');
        //  DB::raw('HOUR(event_created_at) AS the_dates');
        // if ($start_date != $end_date) {
        //     $the_dates = DB::raw('DATE_FORMAT(event_created_at, "%Y-%m-%d") AS the_dates');
        // }
        $itime = (clone $builder)->select(
            $the_dates,
            DB::raw('interaction_time')
        )->groupBy(['stats_product.confirmation_code']);

        $itime_aggregate = DB::table(
            DB::raw("({$itime->fullSQL()}) as sub")
        )->select(
            DB::raw('SUM(sub.interaction_time) as itime'),
            'sub.the_dates'
        )->groupBy('sub.the_dates')->get()->pluck('itime', 'the_dates');

        $sales = [];
        $nosales = [];
        $itimes = [];
        if ($start_date == $end_date) {
            //Cant use DB::raw('SUM(interaction_time) as itime'), stats_product stores confirmation_code twice in some cases,
            //ergo repeated interaction_time for single call
            $data = (clone $builder)->selectRaw(
                "HOUR(event_created_at) AS the_dates,
                SUM(result = 'Sale') AS sales,
                SUM(result = 'No Sale' AND disposition_reason NOT IN ('Pending', 'Abandoned')) AS nosales"
                //,DB::raw('SUM(interaction_time) as itime')
            );

            $data = $data->groupBy(
                DB::raw('HOUR(event_created_at)')
            )->get()->toArray();
            $labels = config('custom.labels');
            $newarray = [];
            for ($i = 0; $i < count($data); ++$i) {
                $newarray[$data[$i]['the_dates']] = $data[$i];
                $newarray[$data[$i]['the_dates']]['itime'] = (isset($itime_aggregate[$data[$i]['the_dates']]))
                    ? round($itime_aggregate[$data[$i]['the_dates']], 2)
                    : 0;
            }
            for ($i = 7; $i < 24; ++$i) {
                if (!isset($newarray[$i])) {
                    $newarray[$i] = ['the_dates' => $i, 'sales' => 0, 'nosales' => 0, 'itime' => 0];
                }
            }

            asort($newarray);

            foreach ($newarray as $na) {
                $sales[] = $na['sales'];
                $nosales[] = $na['nosales'];
                $itimes[] = $na['itime'];
            }
        } else {
            $data = (clone $builder)->select([
                DB::raw('DATE_FORMAT(event_created_at, "%Y-%m-%d") AS the_dates'),
                DB::raw('SUM(result = "Sale") AS sales'),
                DB::raw('SUM(result = "No Sale" AND disposition_reason NOT IN ("Pending", "Abandoned")) AS nosales')
            ])->groupBy(
                'the_dates'
            )->get()->mapWithKeys(function ($day) {
                return [$day->the_dates => $day->getAttributes()];
            });

            $labels = [];
            $period = \Carbon\CarbonPeriod::create($start_date, $end_date);
            foreach ($period as $date) {
                $labels[] = $date->format('Y-m-d');
            }
            $newarray = [];

            foreach ($labels as $label) {
                if (isset($data[$label])) {
                    $newarray[$label] = $data[$label];
                    $newarray[$label]['itime'] = (isset($itime_aggregate[$label])) ? round($itime_aggregate[$label], 2) : 0;
                } else {
                    $newarray[$label] = [
                        'the_dates' => $label,
                        'sales' => 0,
                        'nosales' => 0,
                        'itime' => 0
                    ];
                }
            }
            asort($newarray);

            foreach ($newarray as $na) {
                $sales[] = $na['sales'];
                $nosales[] = $na['nosales'];
                $itimes[] = $na['itime'];
            }
        }

        return [
            'labels' => $labels,
            'nosales' => $nosales,
            'sales' => $sales,
            'itime' => $itimes,
        ];
    }

    public function top_sale_agents(Request $request)
    {
        $params = $this->reportParams($request);
        $data = $this->_top_sale_agents(...$params);

        return response()->json($data);
    }

    private function _top_sale_agents(
        $brand,
        $start_date,
        $end_date,
        $channel,
        $market,
        $language,
        $commodity,
        $state
    ) {
        $data = StatsProduct::selectRaw(
            "sales_agent_name AS sales_agent,
            vendor_name AS vendor,
           SUM(result = 'Sale') AS sales,
           SUM(result = 'No Sale' AND disposition_reason NOT IN ('Pending', 'Abandoned')) AS nosales"
        )->where(
            'stats_product_type_id',
            1
        )->eventRange(
            $start_date,
            $end_date
        );

        $data = $this->usual_filters($data, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        $data = $data->groupBy(
            'sales_agent',
            'vendor'
        )->orderBy(
            'sales',
            'desc'
        )->limit(10)->get();

        return $data;
    }

    public function top_sold_products(Request $request)
    {
        $params = $this->reportParams($request);
        $data = $this->_top_sold_products(...$params);

        return response()->json($data);
    }

    private function _top_sold_products(
        $brand,
        $start_date,
        $end_date,
        $channel,
        $market,
        $language,
        $commodity,
        $state
    ) {
        $data = StatsProduct::selectRaw(
            "product_name AS name,
            SUM(result = 'Sale' THEN 1 ELSE 0 END) AS sales_num"
        )->where(
            'stats_product_type_id',
            1
        )->eventRange(
            $start_date,
            $end_date
        );

        $data = $this->usual_filters($data, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        $data = $data->groupBy(
            'name'
        )->orderBy(
            'sales_num',
            'desc'
        )->limit(10)->get();

        return $data;
    }

    public function no_sale_dispositions(Request $request)
    {
        $params = $this->reportParams($request);
        $data = $this->_no_sale_dispositions(...$params);

        return response()->json($data);
    }

    private function _no_sale_dispositions(
        $brand,
        $start_date,
        $end_date,
        $channel,
        $market,
        $language,
        $commodity,
        $state
    ) {
        $data = StatsProduct::selectRaw(
            "disposition_reason AS reason,
            COUNT(*) AS no_sales_num"
        )->where(
            'stats_product_type_id',
            1
        )->whereNotIn(
            'disposition_reason',
            ['Abandoned', 'Pending']
        )->eventRange(
            $start_date,
            $end_date
        );

        $data = $this->usual_filters($data, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        return $data->groupBy(
            'reason'
        )->orderBy(
            'no_sales_num',
            'desc'
        )->get();
    }

    public function sales_by_vendor(Request $request)
    {
        $params = $this->reportParams($request);
        $data = $this->_sales_by_vendor(...$params);

        return response()->json($data);
    }

    private function _sales_by_vendor(
        $brand,
        $start_date,
        $end_date,
        $channel,
        $market,
        $language,
        $commodity,
        $state
    ) {
        $data = StatsProduct::selectRaw(
            "vendor_name AS name,
           SUM(result = 'Sale') AS sales_num,
           SUM(result = 'No Sale' AND disposition_reason NOT IN ('Pending', 'Abandoned')) AS nosales_num"
        )->where(
            'stats_product_type_id',
            1
        )->eventRange(
            $start_date,
            $end_date
        );
        $data = $this->usual_filters($data, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        $data = $data->groupBy(
            'name'
        )->orderBy(
            'sales_num',
            'desc'
        )->limit(10)->get()->filter(function ($d) {
            return $d->sales_num > 0;
        });

        return $data;
    }

    public function sales_by_day_of_week(Request $request)
    {
        $params = $this->reportParams($request);
        $data = $this->_sales_by_day_of_week(...$params);

        return response()->json($data);
    }

    private function _sales_by_day_of_week(
        $brand,
        $start_date,
        $end_date,
        $channel,
        $market,
        $language,
        $commodity,
        $state
    ) {
        $data = StatsProduct::selectRaw(
           "DAYOFWEEK(event_created_at) AS day_of_week,
           DAYNAME(event_created_at) AS day_name,
           SUM(result = 'Sale') AS sales,
           SUM(result = 'No Sale' AND disposition_reason NOT IN ('Pending', 'Abandoned')) AS nosales"
        )->where(
            'stats_product_type_id',
            1
        )->eventRange(
            $start_date,
            $end_date
        );
        //Reseting brand to avoid an error in usual_filters()
        $brand = null;
        $data = $this->usual_filters($data, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        $data = $data->groupBy(DB::raw('DAYOFWEEK(stats_product.event_created_at)'))
            ->orderBy(DB::raw('DAYOFWEEK(stats_product.event_created_at)'))->get()->toArray();

        $newarray = [];
        $days =  config('custom.days');
        for ($i = 0; $i < count($days); ++$i) {
            if (!isset($newarray[$i + 1])) {
                $newarray[$i + 1] = [
                    'day_of_week' => $i + 1,
                    'day_name' => $days[$i],
                    'sales' => 0,
                    'nosales' => 0,
                ];
            }
        }

        foreach ($data as $value) {
            $newarray[$value['day_of_week']] = $value;
        }
        $labels = [];
        $sales = [];
        $nosales = [];

        asort($newarray);
        for ($i = 1; $i < 8; ++$i) {
            $labels[] = $newarray[$i]['day_name'];
            $sales[] = (float) $newarray[$i]['sales'];
            $nosales[] = (float) $newarray[$i]['nosales'];
        }

        return [
            'labels' => $labels,
            'sales' => $sales,
            'nosales' => $nosales,
        ];
    }

    public function good_sales_by_zip(Request $request)
    {
        $params = $this->reportParams($request);
        $data = $this->_good_sales_by_zip(...$params);

        return response()->json($data);
    }

    private function _good_sales_by_zip(
        $brand,
        $start_date,
        $end_date,
        $channel,
        $market,
        $language,
        $commodity,
        $state
    ) {
        $data = StatsProduct::selectRaw(
            "COUNT(stats_product.billing_zip) AS sales,
            stats_product.service_zip,
            zips.lat,
            zips.lon"
        )->where(
            'stats_product_type_id',
            1
        )->join(
            'zips',
            'zips.zip',
            'stats_product.service_zip'
        )->where(
            'stats_product.result',
            'Sale'
        )->eventRange(
            $start_date,
            $end_date
        );

        $data = $this->usual_filters($data, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        return $data->groupBy('stats_product.service_zip')->get();
    }

   public function sales_by_source(Request $request)
    {
        $brand = $request->get('brand');
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $channel = $request->get('channel');
        $market = $request->get('market');
        $language = $request->get('language');
        $commodity = $request->get('commodity');
        $state = $request->get('state');
    
        //common filters use to avoid code repetition
        $applyCommonFilters = function ($query) use ($brand, $channel, $market, $language, $commodity, $state) {
            return $this->usual_filters($query, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));
        };
    
        // Create base query to use repetadly
        $baseQuery = function () use ($start_date, $end_date, $applyCommonFilters) {
            $query = StatsProduct::select('source', DB::raw('count(*) as cnt'))
                ->eventRange($start_date, $end_date);
            return $applyCommonFilters($query);
        };
    
        $query = clone $baseQuery();
        $query->where('stats_product.result', 'Sale')
                   ->groupBy('stats_product.source');
    
        $query2 = clone $baseQuery();
        $query2->where('stats_product.result', 'No Sale')
                   ->whereNotIn('stats_product.disposition_reason', ['Pending', 'Abandoned'])
                   ->groupBy('stats_product.source');
    
        $query3 = clone $baseQuery();
        $query3->where('stats_product.result', 'No Sale')
                     ->whereIn('stats_product.disposition_reason', ['Pending', 'Abandoned'])
                     ->groupBy('stats_product.source');
    
        $baseInteractionQuery = function () use ($start_date, $end_date, $applyCommonFilters) {
            $query = StatsProduct::select('interaction_type', DB::raw('count(*) as cnt'))
                ->eventRange($start_date, $end_date);
            return $applyCommonFilters($query);
        };
    
        $query4 = clone $baseInteractionQuery();
        $query4->where('stats_product.result', 'No Sale')
                               ->groupBy('stats_product.interaction_type');
    
        $query5 = clone $baseInteractionQuery();
        $query5->where('stats_product.result', 'Sale')
                             ->groupBy('stats_product.interaction_type');
    
        return response()->json([
            'sales' => $query->get(),
            'other' => $query2->get(),
            'pending' => $query3->get(),
            'sales_ch' => $query5->get(),
            'nsales_ch' => $query4->get(),
        ]);
    }

    private function reportParams(Request $request)
    {
        $brand = $request->get('brand');
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $channel = $request->get('channel');
        $market = $request->get('market');
        $language = $request->get('language');
        $commodity = $request->get('commodity');
        $state = $request->get('state');

        return [
            $brand,
            $start_date,
            $end_date,
            $channel,
            $market,
            $language,
            $commodity,
            $state,
        ];
    }

    public function top_brands_sales(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $channel = $request->get('channel');
        $market = $request->get('market');
        $vendor = $request->get('vendor');
        $language = $request->get('language');
        $commodity = $request->get('commodity');
        $state = $request->get('state');

        $data = StatsProduct::selectRaw(
           "SUM(result = 'Sale') AS sales,
           SUM(result = 'No Sale' AND disposition_reason NOT IN ('Pending', 'Abandoned')) AS no_sales,
            stats_product.brand_name"
        )->eventRange(
            $start_date,
            $end_date
        );

        $brand = null;
        $data = $this->usual_filters($data, null, compact('brand', 'channel', 'market', 'language', 'commodity', 'state'));

        $data = $data->orderBy('sales', 'DESC')->groupBy('stats_product.brand_name')->get();
        $data = $data->filter(function ($item) {
            return $item->sales > 0;
        });
        return $data;
    }

    public function top_states_sales(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $data = StatsProduct::selectRaw(
           "COUNT(result) AS sales,
            states.name"
        )->eventRange(
            $start_date,
            $end_date
        )->where(
            'stats_product.result',
            'Sale'
        )->leftJoin(
            'states',
            'stats_product.service_state',
            'states.state_abbrev'
        )->whereNotNull('states.name');

        if ($request->brand) {
            $data = $data->whereIn('stats_product.brand_id', $this->listToArray($request->brand));
        }

        if ($request->channel) {
            $data = $data->whereIn('stats_product.channel_id', $this->listToArray($request->channel));
        }

        if ($request->market) {
            $data = $data->whereIn('stats_product.market_id', $this->listToArray($request->market));
        }

        if ($request->language) {
            $data = $data->whereIn('stats_product.language_id', $this->listToArray($request->language));
        }

        if ($request->commodity) {
            $data = $data->whereIn('stats_product.commodity_id', $this->listToArray($request->commodity));
        }

        if ($request->state) {
            $data = $data->whereIn(
                'states.id',
                $this->listToArray($request->state)
            );
        }

        $data = $data->where(
            'stats_product.brand_id',
            '!=',
            $this->get_brand_id('Green Mountain Energy Company')
        );

        $data = $data->orderBy('sales', 'DESC')->groupBy('states.name')->get();

        return response()->json($data);
    }

    public function sales_agent_dashboard()
    {
        return view(
            'dashboard.sales_agent_dashboard',
            [
                'brands' => $this->get_brands(),
                'languages' => $this->get_languages(),
                'commodities' => $this->get_commodities(),
                'states' => $this->get_states(),
            ]
        );
    }

    public function get_active_agents(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $stats_product = StatsProduct::selectRaw(
            'COUNT(stats_product.sales_agent_id) as active_agents'
        )->eventRange(
            $start_date,
            $end_date
        )->whereNotNull('sales_agent_id');

        $stats_product = $this->usual_filters($stats_product, $request);

        $stats_product = $stats_product->groupBy('sales_agent_id')->get();

        return [
            'active_agents' => $stats_product->count(),
        ];
    }

    public function avg_sales_per_day(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $stats_product = StatsProduct::selectRaw(
            "DATE_FORMAT(stats_product.event_created_at,'%d-%m-%Y') as formated_date,
                     COUNT(stats_product.result) as sales"
        )->eventRange(
            $start_date,
            $end_date
        )->where(
            'stats_product.result',
            'Sale'
        );

        $stats_product = $this->usual_filters($stats_product, $request);

        $stats_product = $stats_product->groupBy('formated_date')->get()->toArray();

        $avg = 0;
        if (count($stats_product) > 0) {
            $sales = array_column($stats_product, 'sales');
            $total_s = array_sum($sales);
            $avg = $total_s / count($stats_product);
            $avg = round($avg, 2);
        }
        $result = [
            'avg_sales_per_day' => $avg,
        ];

        return $result;
    }

    public function avg_calls_per_day(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $calls = Interaction::selectRaw(
           "DATE_FORMAT(stats_product.event_created_at,'%d-%m-%Y') as formated_date,
           COUNT(DISTINCT interactions.id) as calls"
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
            'events.survey_id'
        )->whereNull(
            'interactions.parent_interaction_id'
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

        $calls = $calls->groupBy('formated_date')->get();

        $avg = 0;
        if ($calls) {
            $total_days = $calls->count();
            $total_calls = $calls->sum('calls');
            if ($total_calls > 0 && $total_days > 0) {
                $avg = round(($total_calls / $total_days), 2);
            }
        }

        return [
            'avg_calls_per_day' => $avg,
        ];
    }

    public function avg_agents_active_per_day(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

        $stats_product = StatsProduct::selectRaw(
            "COUNT(DISTINCT stats_product.sales_agent_id) as active_agents,
             DATE_FORMAT(stats_product.event_created_at,'%d-%m-%Y') as formated_date"
        )->eventRange(
            $start_date,
            $end_date
        )->whereNotNull('sales_agent_id');

        $stats_product = $this->usual_filters($stats_product, $request);

        $stats_product = $stats_product->groupBy('formated_date')->get();

        $avg = 0;
        $a_sum = $stats_product->sum('active_agents');
        if ($a_sum > 0) {
            $sp_count = $stats_product->count();
            $avg = round(
                ($sp_count > 0) ? $a_sum / $sp_count : 0
            );
        }

        return [
            'avg_agents_active_per_day' => $avg
        ];
    }

    public function avg_daily_sales_per_agent(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

        $stats_product = StatsProduct::select(
            DB::raw('COUNT(stats_product.result) as sales'),
            'stats_product.sales_agent_id',
            DB::raw("DATE_FORMAT(stats_product.event_created_at,'%d-%m-%Y') as formated_date")
        )->eventRange(
            $start_date,
            $end_date
        )->where(
            'stats_product.result',
            'Sale'
        )->whereNotNull('stats_product.sales_agent_id');

        $stats_product = $this->usual_filters($stats_product, $request);

        $stats_product = $stats_product->groupBy('formated_date');

        //Working with the subquery
        $all_avg = DB::table(DB::raw("({$stats_product->fullSQL()}) as avg"))->selectRaw(
            'AVG(sales) as avg'
        )->groupBy('sales_agent_id');
        $all_avg = $all_avg->get();
        $avg = 0;
        if ($all_avg) {
            $total_avg = $all_avg->sum('avg');
            $amount = $all_avg->count();
            $avg = ($amount > 0 && $total_avg > 0) ? round($total_avg / $amount, 2) : 0;
        }

        return [
            'avg_daily_sales_per_agent' => $avg,
        ];
    }

    public function avg_calls_by_half_hour(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

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
            $c->avg = ($c->calls != 0 && $c->number_of_days) ? round($c->calls / $c->number_of_days, 2) : 0;
            return [$c->halfhour => [
                'halfhour' => $c->halfhour,
                'avg' => $c->avg,
            ]];
        });

        $half_hours = config('custom.half_hours');
        $result = [];
        foreach ($half_hours as $hh) {
            if (!$calls->has($hh)) {
                $result[] = [
                    'halfhour' => $hh,
                    'avg' => 0,
                ];
            } else {
                $result[] = $calls->get($hh);
            }
        }

        return $result;
    }

    public function avg_calls_per_week(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

        // $a_week_before = (new Carbon($end_date))->subWeek();
        // //If start_date is in a range of a week before the end_date then show last 7 days data
        // if ((new Carbon($start_date))->between($a_week_before, (new Carbon($end_date)))) {
        //     $start_date = $a_week_before->format('Y-m-d');
        // }

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

        $days = config('custom.days');
        $calls = $calls->groupBy('dayofweek')->get()->mapWithKeys(function ($c) use ($days) {
            $c->avg = ($c->calls > 0 && $c->number_of_days) ? round($c->calls / $c->number_of_days, 2) : 0;
            $day = $days[($c->dayofweek - 1)];
            return [$day => [
                'dayofweek' => $day,
                'avg' => $c->avg
            ]];
        });

        $result = [];
        foreach ($days as $day) {
            if (!$calls->has($day)) {
                $result[] = [
                    'dayofweek' => $day,
                    'avg' => 0,
                ];
            } else {
                $result[] = $calls->get($day);
            }
        }

        return $result;
    }

    public function avg_active_agents_per_week(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');

        // $a_week_before = (new Carbon($end_date))->subWeek();
        // //If start_date is in a range of a week before the end_date then show last 7 days data
        // if ((new Carbon($start_date))->between($a_week_before, (new Carbon($end_date)))) {
        //     $start_date = $a_week_before->format('Y-m-d');
        // }

        $stats_product = StatsProduct::selectRaw(
            'DAYOFWEEK(stats_product.event_created_at) as dayofweek,
             COUNT(DISTINCT stats_product.sales_agent_id) as active_agents,
             COUNT(DISTINCT DAY(stats_product.event_created_at)) as number_of_days'
        )->eventRange(
            $start_date,
            $end_date
        );

        $stats_product = $this->usual_filters($stats_product, $request);

        $days =config('custom.days');

        $stats_product = $stats_product->groupBy('dayofweek')->get()->mapWithKeys(function ($sp) use ($days) {
            $sp->avg_agents = ($sp->active_agents > 0 && $sp->number_of_days) ? round($sp->active_agents / $sp->number_of_days) : 0;
            $day = $days[($sp->dayofweek - 1)];
            return [$day => [
                'dayofweek' => $day,
                'avg_agents' => $sp->avg_agents
            ]];
        });

        $result = [];
        foreach ($days as $day) {
            if (!$stats_product->has($day)) {
                $result[] = [
                    'dayofweek' => $day,
                    'avg_agents' => 0,
                ];
            } else {
                $result[] = $stats_product->get($day);
            }
        }

        return $result;
    }

    public function s_a_d_table_dataset(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::yesterday()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today()->format('Y-m-d');
        $column = $request->get('column');
        $direction = $request->get('direction');

        $stats_product = StatsProduct::selectRaw(
            "stats_product.brand_id,
             stats_product.brand_name,
             clients.name AS client_name,
             COUNT(DISTINCT stats_product.sales_agent_id) AS active_agents,
             SUM(stats_product.result = 'Sale') / COUNT(DISTINCT DAY(stats_product.event_created_at)) AS avg_sales_per_day,
             COUNT(DISTINCT stats_product.sales_agent_id) / COUNT(DISTINCT DAY(stats_product.event_created_at)) AS avg_agents_per_day,
             SUM(stats_product.result = 'Sale') / COUNT(DISTINCT stats_product.sales_agent_id) AS avg_sales_per_agent"
        )->leftJoin(
            'brands',
            'stats_product.brand_id','=',
            'brands.id'
        )->leftJoin(
            'clients',
            'brands.client_id','=',
            'clients.id'
        )->eventRange(
            $start_date,
            $end_date
        );

        //Removing brand param from request
        $request->request->remove('brand');
        $request->query->remove('brand');

        $stats_product = $this->usual_filters($stats_product, $request);

        $stats_product = $stats_product->groupBy('brand_name')->get();

        //Calculating the calls statistics
        $calls = Interaction::selectRaw(
            'stats_product.brand_id,
             COUNT(DISTINCT interactions.id) / COUNT(DISTINCT DAY(stats_product.event_created_at)) AS avg_calls_per_day,
             COUNT(DISTINCT interactions.id) AS total_calls'
        )->leftJoin(
            'events',
            'events.id','=',
            'interactions.event_id'
        )->leftJoin(
            'stats_product',
            'interactions.event_id','=',
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
        )->whereNull(
            'stats_product.deleted_at'
        )->whereIn(
            'stats_product.brand_id',
            $stats_product->pluck('brand_id')
        );

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

        $calls = $calls->groupBy('stats_product.brand_id')->get()->mapWithKeys(function ($call) {
            return [$call->brand_id => [
                'avg_calls_per_day' => $call->avg_calls_per_day,
                'total_calls' => $call->total_calls
            ]];
        });

        foreach ($stats_product as &$sp) {
            //Changing values of avg into 2 pos decimal
            $sp->avg_sales_per_day = round($sp->avg_sales_per_day, 2);
            $sp->avg_agents_per_day = round($sp->avg_agents_per_day, 2);
            $sp->avg_sales_per_agent = round($sp->avg_sales_per_agent, 2);
            $sp->avg_calls_per_day = 0;
            $sp->avg_calls_per_agent = 0;
            if ($calls->has($sp->brand_id)) {
                $c = $calls->get($sp->brand_id);
                $sp->avg_calls_per_day = ($c['avg_calls_per_day'] > 0)
                    ? round($c['avg_calls_per_day'], 2)
                    : 0;
                $sp->avg_calls_per_agent = ($c['total_calls'] > 0 && $sp->active_agents > 0)
                    ? round($c['total_calls'] / $sp->active_agents, 2)
                    : 0;
            }
        }

        if ($column && $direction) {
            if ($direction == 'desc') {
                $stats_product = $stats_product->sortByDesc($column)->values()->all();
            } else {
                $stats_product = $stats_product->sortBy($column)->values()->all();
            }
        }

        return $stats_product;
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

        /*
        // why are we excluding green mountain from the results here?
        $model = $model->where(
            'stats_product.brand_id',
            '!=',
            $this->get_brand_id('Green Mountain Energy Company')
        );*/

        return $model;
    }

    public function sales_amount_by_channel(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $data = StatsProduct::select(
            DB::raw('SUM(result = "Sale") AS sales'),
            'stats_product.brand_name',
            'stats_product.channel'
        )->where(
            'stats_product_type_id',
            1
        )->eventRange(
            $start_date,
            $end_date
        );

        $data = $this->usual_filters($data, $request);

        $data = $data->groupBy(
            'channel',
            'stats_product.brand_name'
        )->get();

        $result = [
            'DTD' => 0,
            'TM' => 0,
            'Retail' => 0,
            'Care' => 0,
        ];

        foreach ($data as $d) {
            if (isset($result[$d->channel])) {
                $result[$d->channel] += $d->sales;
            }
        }

        return $result;
    }

    public function calls_amount_and_aht_by_channel(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        //If no left Join with events then the numbers doesnt match with other reports
        $data = Interaction::select(
            'interactions.event_id',
            'interactions.parent_interaction_id',
            'interactions.interaction_type_id',
            'interactions.interaction_time',
            'stats_product.channel'
        )->leftJoin(
            'stats_product',
            'stats_product.event_id','=',
            'interactions.event_id'
        )->leftJoin(
            'events',
            'events.id','=',
            'interactions.event_id'
        )->whereDate(
            'stats_product.event_created_at',
            '>=',
            $start_date
        )->whereDate(
            'stats_product.event_created_at',
            '<=',
            $end_date
        )->whereNotNull(
            'interactions.interaction_time'
        )->whereNotNull(
            'stats_product.brand_name'
        )->whereNotNull(
            'stats_product.channel'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2, 6]
        )->whereNull(
            'events.survey_id'
        );

        $data = $this->usual_filters($data, $request);

        $data = $data->groupBy(
            'interactions.id'
        )->get()->groupBy('event_id')->transform(function ($call) {
            //Two cases digital or not
            $digital = false;
            $obj = app()->make('stdClass');
            $obj->calls = 0;
            $obj->total_time = 0;
            foreach ($call as $c) {
                //Valid here because when interaction_type_id = 6 interaction_time === 0.00  
                $obj->total_time += $c->interaction_time;
                if ($c->interaction_type_id == 6) {
                    $digital = true;
                    break;
                }
            }
            if ($digital) {
                foreach ($call as $c) {
                    if (in_array($c->interaction_type_id, [1, 2])) {
                        $obj->calls++;
                    }
                }
            } else {
                foreach ($call as $c) {
                    if (in_array($c->interaction_type_id, [1, 2]) && is_null($c->parent_interaction_id)) {
                        $obj->calls++;
                    }
                }
            }

            $obj->channel = $call[0]->channel;
            return $obj;
        })->groupBy('channel')->transform(function ($calls) {
            $calls_amount = 0;
            $total_time = 0;
            foreach ($calls as $c) {
                $calls_amount += $c->calls;
                $total_time += $c->total_time;
            }
            $aht = ($total_time > 0 &&  $calls_amount > 0) ? round($total_time / $calls_amount, 2) : 0;
            return compact('calls_amount', 'aht');
        });

        $result = collect([
            'DTD' => ['calls_amount' => 0, 'aht' => 0],
            'TM' => ['calls_amount' => 0, 'aht' => 0],
            'Retail' => ['calls_amount' => 0, 'aht' => 0],
            'Care' => ['calls_amount' => 0, 'aht' => 0],
        ]);

        return $result->merge($data);
    }
}
