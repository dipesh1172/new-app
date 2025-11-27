<?php

namespace App\Http\Controllers;

set_time_limit(60);

use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use App\Traits\SearchFormTrait;
use App\Traits\CSVResponseTrait;
use App\Models\WebEnrollSubmission;
use App\Models\TextMessage;
use App\Models\StatsTpvAgent;
use App\Models\StatsProduct;
use App\Models\State;
use App\Models\ScriptAnswer;
use App\Models\Language;
use App\Models\Interaction;
use App\Models\Invoice;
use App\Models\EztpvDocument;
use App\Models\Eztpv;
use App\Models\EventType;
use App\Models\EventFlag;
use App\Models\Event;
use App\Models\DxcCall;
use App\Models\DxcBrand;
use App\Models\DigitalSubmission;
use App\Models\DailyStat;
use App\Models\DailyQuestionSubmission;
use App\Models\DXCLegacy;
use App\Models\BrandEztpvContract;
use App\Models\Brand;
use App\Models\ApiSubmission;
class ReportController extends Controller
{
    use CSVResponseTrait;
    use SearchFormTrait;

    /**
     * @var GuzzleClient
     */


    public function index()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'report-list',
                'title' => 'Reports',
            ]
        );
    }

    public function pending_replace_report()
{
    $array = Cache::remember('pending_replace_report', 3600, function () {
        $data = [];

        StatsProduct::where('disposition_reason', 'Pending')
            ->where('stats_product_type_id', 1)
            ->chunk(100, function ($stats_products) use (&$data) {
                $btns = $stats_products->pluck('btn')->toArray();
                $replaced_data = StatsProduct::whereIn('btn', $btns)
                    ->where('disposition_reason', '!=', 'Pending')
                    ->orderBy('event_created_at', 'desc')
                    ->get()
                    ->groupBy('btn');

                foreach ($stats_products as $sp) {
                    $btn = $sp->btn;
                    $replaced = $replaced_data[$btn] ?? null;

                    if ($replaced) {
                        $data[$btn . ' - ' . $sp->brand_name] = $replaced->toArray();
                    }
                }
            });

        return $data;
    });

    return view('reports.pending_replace_report', ['data' => $array]);
}



    public function report_no_sales_by_agent(Request $request)
    {
        if (!$request->ajax() && !$request->csv) {
            return view('generic-vue')->with(
                [
                    'componentName' => 'no-sales-by-agent',
                    'title' => 'Report: No Sales',
                    'parameters' => [
                        'brands' => json_encode($this->brands_select_options()),
                    ],
                ]
            );
        }

        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $tpv_agent_name = $request->tpv_agent_name_search;
        $column = $request->column ?? 'total_pct_no_sales';
        $direction = $request->direction ?? 'asc';

        /**
         * No ->whereNull('interactions.parent_interaction_id')
         * is needed since we have to count the digital ones too.
         */
        $agents = Interaction::select(
            'interactions.tpv_staff_id',
            'tpv_staff.first_name',
            'tpv_staff.last_name',
            'events.brand_id',
            'brands.name as brand_name',
            'events.channel_id',
            'channels.channel',
            'interactions.event_result_id',
            'interactions.parent_interaction_id',
            'interactions.id',
            'interactions.interaction_type_id',
            'interactions.event_id'
        )
            ->leftJoin('tpv_staff', 'interactions.tpv_staff_id', 'tpv_staff.id')
            ->leftJoin('events', 'interactions.event_id', 'events.id')
            ->leftJoin('brands', 'events.brand_id', 'brands.id')
            ->leftJoin('channels', 'events.channel_id', 'channels.id')
            ->where(function ($query) {
                $query->whereIn('interactions.event_result_id', [1, 2])
                    ->orWhereNull('interactions.event_result_id');
            })
            ->whereNull(
                'events.survey_id'
            )->whereNotNull(
                'interactions.interaction_time'
            )->whereIn(
                'interactions.interaction_type_id',
                [1, 2, 6]
            );

        if ($request->brand) {
            $agents = $agents
                ->whereIn('events.brand_id', $request->brand);
        }
        if ($tpv_agent_name) {
            $agents = $agents->where(function ($query) use ($tpv_agent_name) {
                $query->where('tpv_staff.first_name', 'LIKE', "%$tpv_agent_name%")
                    ->orWhere('tpv_staff.last_name', 'LIKE', "%$tpv_agent_name%")
                    ->orWhere('tpv_staff.username', 'LIKE', "%$tpv_agent_name%");
            });
        }

        $agents = $agents->whereNotNull('tpv_staff.last_name')
            ->whereNotNull('tpv_staff.first_name')
            ->whereDate('interactions.created_at', '>=', $start_date)
            ->whereDate('interactions.created_at', '<=', $end_date)
            ->orderBy('interactions.created_at')->get()->groupBy('tpv_staff_id')->transform(function ($interactions) {
                //counting the brand and channels per brand
                $brands = [];
                $agent = [
                    'name' => $interactions[0]->last_name . ', ' . $interactions[0]->first_name,
                    'id' => $interactions[0]->tpv_staff_id,
                ];
                foreach ($interactions as $i) {
                    if (isset($i->brand_id) && !array_key_exists($i->brand_id, $brands)) {
                        $brands[$i->brand_id] = [
                            'name' => $i->brand_name,
                            'id' => $i->brand_id,
                            'channels' => [],
                        ];
                    }

                    if (isset($i->channel_id) && !array_key_exists($i->channel_id, $brands[$i->brand_id]['channels'])) {
                        //Init channel on brand with sales and no sales values
                        $brands[$i->brand_id]['channels'][$i->channel_id] = [
                            'name' => $i->channel,
                            'id' => $i->channel_id,
                            'sales' => 0,
                            'no_sales' => 0,
                        ];
                    }
                }

                $agent['brands'] = $brands;
                $agent['total_sales'] = 0;
                $agent['total_no_sales'] = 0;

                //Counting the sales and sales per channel
                $interactions->groupBy('event_id')->each(function ($calls) use (&$agent) {
                    //Two cases digital or no
                    $digital = false;
                    foreach ($calls as $c) {
                        if ($c->interaction_type_id == 6) {
                            $digital = true;
                            break;
                        }
                    }
                    //repeated block
                    $check_event_result = function ($c) use (&$agent) {
                        //Increasing Sales No Sales for agent
                        if ($c->event_result_id == 1) {
                            ++$agent['total_sales'];
                            //Increasing Sales No Sales per Brand->Channel for agent
                            if (isset($c->brand_id) && isset($c->channel_id)) {
                                ++$agent['brands'][$c->brand_id]['channels'][$c->channel_id]['sales'];
                            }
                        }
                        if ($c->event_result_id == 2) {
                            ++$agent['total_no_sales'];
                            //Increasing Sales No Sales per Brand->Channel for agent
                            if (isset($c->brand_id) && isset($c->channel_id)) {
                                ++$agent['brands'][$c->brand_id]['channels'][$c->channel_id]['no_sales'];
                            }
                        }
                    };

                    if ($digital) {
                        foreach ($calls as $c) {
                            if (in_array($c->interaction_type_id, [1, 2])) {
                                $check_event_result($c);
                            }
                        }
                    } else {
                        foreach ($calls as $c) {
                            if (in_array($c->interaction_type_id, [1, 2]) && (is_null($c->parent_interaction_id))) {
                                //If no $c->event_result_id then I need to check for last interaction $c->event_result_id and grab that one
                                $parent_id = $c->id;
                                $obj = $c;
                                if (is_null($c->event_result_id)) {
                                    foreach ($calls as $call) {
                                        if ($call->parent_interaction_id == $parent_id) {
                                            $parent_id = $c->id;
                                            $obj = $call;
                                        }
                                    }
                                }
                                $check_event_result($obj);
                            }
                        }
                    }
                });
                $agent['total_pct_no_sales'] = ($agent['total_no_sales'] > 0)
                    ? round((($agent['total_no_sales'] / ($agent['total_no_sales'] + $agent['total_sales'])) * 100), 2)
                    : 0;

                return $agent;
            });

        //Finally ready to get pct_no_sales and total values
        $gt_sales = 0;
        $gt_no_sales = 0;
        $agents = $agents->values()->toArray();
        foreach ($agents as &$a) {
            foreach ($a['brands'] as &$brands) {
                foreach ($brands['channels'] as &$channel) {
                    $channel['pct_no_sales'] = ($channel['no_sales'] > 0)
                        ? round((($channel['no_sales'] / ($channel['sales'] + $channel['no_sales'])) * 100), 2)
                        : 0;
                }
            }
            $gt_sales += $a['total_sales'];
            $gt_no_sales += $a['total_no_sales'];
        }

        //Filtering agents with sales or no sales
        $agents = array_filter($agents, function ($agent) {
            return $agent['total_sales'] > 0 || $agent['total_no_sales'] > 0;
        });

        $gt_pct_no_sales = ($gt_no_sales > 0)
            ? round((($gt_sales / ($gt_sales + $gt_no_sales)) * 100), 2)
            : 0;

        //Sorting
        $sort_type = ('desc' == $direction) ? SORT_DESC : SORT_ASC;
        array_multisort(array_column($agents, $column), $sort_type, $agents);

        if ($request->csv) {
            $csv = [];
            if ($agents) {
                foreach ($agents as $agent) {
                    $name = $agent['name'];
                    $csv[] = [$name, $agent['total_sales'], $agent['total_no_sales'], number_format($agent['total_pct_no_sales'], 2)];

                    foreach ($agent['brands'] as $brand) {
                        $csv[] = [$brand['name'], '', '', ''];

                        foreach ($brand['channels'] as $channel) {
                            $csv[] = [$channel['name'], $channel['sales'], $channel['no_sales'], number_format($channel['pct_no_sales'], 2)];
                        }
                    }
                    $csv[] = ['', '', '', ''];
                }
                $csv[] = ['Grand Totals', $gt_sales, $gt_no_sales, number_format($gt_pct_no_sales, 2)];
            }

            return $this->csv_response(
                $csv,
                'Report_No_Sales_By_Agent (' . $start_date . ' - ' . $end_date . ')',
                [
                    'Sales Agent',
                    'Good Sales',
                    'No Sales',
                    '% of No Sales',
                ]
            );
        }

        return [
            'agents' => $agents,
            'gt_sales' => $gt_sales,
            'gt_no_sales' => $gt_no_sales,
            'gt_pct_no_sales' => $gt_pct_no_sales,
        ];
    }

    protected function getLegacyPortalDropDownOptions()
    {
        $API_URL = 'https://apiv2.tpvhub.com/api/recordingsportal/GetDropdownValues';

        try {
            $httpClient = new HttpClient(['verify' => false]);

            // Post the request
            $response = $httpClient->get($API_URL);

            // Check response and build result
            return json_decode($response->getBody()->getContents());
        }
        catch (\Exception $e) {

            $msg = "Error loading the initial values for ReportController->Legacy_Portal. getLegacyPortalDropDownOptions. $API_URL " . $e->getMessage();
            
            info($msg);
            SendTeamMessage('monitoring', "[ReportController->Legacy_Portal] " . $msg);
         
            return '';
        }
    }
    
    public function legacy_portal(Request $request)
    {
        try {
            if (!$request->ajax() && !$request->csv) {

                // Get the drop down option values
                $DropDownOptions = $this->getLegacyPortalDropDownOptions();
                $Options = [];

                if ($DropDownOptions === '') {
                    $emptyArray = [];
                    $emptyArray[]["id"] = 0;
                    $emptyArray[]["name"] = "Error loading values from AN API";

                    $Options['DXC'] =  $emptyArray;
                    $Options['TPV'] =  $emptyArray;
                    $Options['TRUSTED'] =  $emptyArray;

                } else {
                    $Options['DXC'] =  $DropDownOptions->DXC;
                    $Options['TPV'] =  $DropDownOptions->TPV;
                    $Options['TRUSTED'] =  $DropDownOptions->TRUSTED;
                }
                // End get the drop down option values

                return view('generic-vue')->with(
                    [
                        'componentName' => 'legacy-portal',
                        'title' => 'Legacy Portal',
                        'parameters' => [
                            'brands' => json_encode( $Options['DXC']),
                            'vendors' => json_encode($Options['TPV']),
                            'languages' => json_encode($Options['TRUSTED']),
                        ],
                    ]
                );
            }

            // legacy_portal_data
            info("Loading the legacy portal data ReportController->legacy_portal. ");
            $start_date = $request->startDate ;
            $end_date = $request->endDate;
            $confirmation_number_search = $request->confirmation_number_search;

            $DXCsource = $request->brand;
            $TPVsource = $request->vendor;
            $TRUSTEDsource = $request->language;

            $AccountNumber = $request->account_search;
            $BTN = $request->btn_search;
            $FirstName = $request->firstname_search;
            $LastName = $request->lastname_search;
            $PostalCode = $request->postalcode_search;
            $recordings = [];
            
            if ((strlen($start_date) > 7 and strlen($end_date) > 7)
                || strlen($confirmation_number_search) > 2
                || strlen($BTN) > 7
                || strlen($AccountNumber) > 3
                || strlen($FirstName) > 3
                || strlen($LastName) > 3
                || strlen($PostalCode) > 3
                && (
                    strlen($DXCsource) > 3
                    || strlen($TPVsource) > 3
                    || strlen($TRUSTEDsource) > 3
                )
            ) {

                $filter = [
                    'ConfirmationNumber' => $confirmation_number_search,
                    'StartTime' => strlen($start_date) > 7 ? $start_date . ' 00:00:00' : '',
                    'EndTime' => strlen($end_date) > 7 ? $end_date . ' 23:59:59' : '',
                    'AccountNumber' => $AccountNumber,
                    'BTN' => $BTN,
                    'FirstName' => $FirstName,
                    'LastName' => $LastName,
                    'PostalCode' => $PostalCode,
                    "Platform" => array(
                        "DXC" => $DXCsource,
                        "TRUSTED" => $TRUSTEDsource,
                        "TPV" => $TPVsource,
                    ),
                ];

                $httpClient = new HttpClient(['verify' => false]);

                $ApiURL = (config('app.env') == 'production' ? 'https://apiv2.tpvhub.com' : 'http://apiv2.staging.tpvhub.com:6500' );

                $response2 = $httpClient->post(
                    "$ApiURL/api/recordingsportal/search", ['json' => $filter]
                );

                $result2 = json_decode($response2->getBody()->getContents());

                $i = 0;

                $dataBases = array("TRUSTED", "TPV", "DXC");

                foreach ($dataBases as $dataBase) {

                    if (isset($result2->$dataBase)) {

                        foreach ($result2->$dataBase as $rowdata) {

                            $recordings[$i]['Platform']           = $dataBase;
                            $recordings[$i]['Filename']           = (isset($rowdata->Filename)) ? $rowdata->Filename : '';
                            $recordings[$i]['ConfirmationNumber'] = (isset($rowdata->ConfirmationCode)) ? $rowdata->ConfirmationCode : '';
                            $recordings[$i]['Fullname']           = ((isset($rowdata->first_name)) ? $rowdata->first_name : '') . ' ' . ((isset($rowdata->last_name)) ? $rowdata->last_name : '');
                            $recordings[$i]['BTN']           = (isset($rowdata->btn)) ? $rowdata->btn : '';
                            $recordings[$i]['Date']          = (isset($rowdata->dt_date)) ? $rowdata->dt_date : '';
                            $recordings[$i]['AccountNumber'] = (isset($rowdata->account_number)) ? $rowdata->account_number : '';
                            $recordings[$i]['Company']       = (isset($rowdata->Company)) ? $rowdata->Company : ''; 

                            $recordings[$i]['ServiceAddress'] =
                                ((isset($rowdata->service_address1)) ? $rowdata->service_address1 : '')
                                . ' '
                                . ((isset($rowdata->service_address2)) ? $rowdata->service_address2 : '')
                                . ' '
                                . ((isset($rowdata->service_city)) ? $rowdata->service_city : '')
                                . ' '
                                . ((isset($rowdata->service_state)) ? $rowdata->service_state : '')
                                . ' '
                                . ((isset($rowdata->service_zip)) ? $rowdata->service_zip : '');

                            $recordings[$i]['BillingAddress'] =
                                ((isset($rowdata->billing_address1)) ? $rowdata->billing_address1 : '')
                                . ' '
                                . ((isset($rowdata->billing_address2)) ? $rowdata->billing_address2 : '')
                                . ' '
                                . ((isset($rowdata->billing_city)) ? $rowdata->billing_city : '')
                                . ' '
                                . ((isset($rowdata->billing_state)) ? $rowdata->billing_state : '')
                                . ' '
                                . ((isset($rowdata->billing_zip)) ? $rowdata->billing_zip : '');

                            $recordings[$i]['Source'] = (isset($rowdata->Source)) ? $rowdata->Source : '';
                            $recordings[$i]['Email'] = (isset($rowdata->email)) ? $rowdata->email : '';
                            $recordings[$i]['StepId'] = (isset($rowdata->StepId)) ? $rowdata->StepId : '';

                            $i++;
                        }
                    }
                }
            }

            return [ $recordings ];

        } catch (\Exception $e) {

            info("Error loading the ReportController->legacy_portal. " . $e->getMessage());
            SendTeamMessage('monitoring', "Error loading the  ReportController->Legacy_Portal" . $e->getMessage());
        }
    }

    public function report_daily_call_count_by_channel(Request $request)
    {
        if (!$request->ajax() && !$request->csv) {
            return view('generic-vue')->with(
                [
                    'componentName' => 'daily-call-count-by-channel',
                    'title' => 'Report: Daily Call Count by Channel',
                    'parameters' => [
                        'brands' => $this->brands_select_options(),
                    ],
                ]
            );
        }

        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column;
        $direction = $request->direction;
        $brand = $request->brand;

        $calls = Interaction::select(
            'brands.name as company',
            'states.state_abbrev as state',
            'vendor.name as vendor',
            'channels.channel as channel',
            'event_product.event_type_id',
            'interactions.event_id',
            'interactions.interaction_type_id',
            'interactions.parent_interaction_id'
        )
            ->leftJoin('events', 'interactions.event_id', 'events.id')
            ->leftJoin('event_product', 'event_product.event_id', 'events.id')
            ->leftJoin('brands', 'events.brand_id', 'brands.id')
            ->leftJoin('states', 'brands.state', 'states.id')
            ->leftJoin('brands as vendor', 'events.vendor_id', 'vendor.id')
            ->leftJoin('channels', 'events.channel_id', 'channels.id')
            ->whereDate('interactions.created_at', '>=', $start_date)
            ->whereDate('interactions.created_at', '<=', $end_date)
            ->whereNotNull(
                'interactions.interaction_time'
            )->whereIn(
                'interactions.interaction_type_id',
                [1, 2, 6]
            )->whereNull(
                'events.survey_id'
            )->whereNull('events.deleted_at');

        if ($brand) {
            $calls = $calls->whereIn('events.brand_id', $brand);
        }

        $calls = $calls->orderBy(
            'interactions.created_at'
        )->groupBy(
            'interactions.id'
        );

        $calls = $calls->get()->groupBy(
            'event_id'
        )->transform(function ($icalls) {
            //Two cases digital or not
            $digital = false;
            //Here im returning all props from the first obj since they are all the same
            $tcalls = [];
            foreach ($icalls as $c) {
                if ($c->interaction_type_id == 6) {
                    $digital = true;
                    break;
                }
            }
            if ($digital) {
                foreach ($icalls as $c) {
                    if (in_array($c->interaction_type_id, [1, 2])) {
                        $tcalls[] = $c;
                    }
                }
            } else {
                foreach ($icalls as $c) {
                    if (in_array($c->interaction_type_id, [1, 2]) && is_null($c->parent_interaction_id)) {
                        $tcalls[] = $c;
                    }
                }
            }

            return $tcalls;
        })->flatten();

        $result = [];
        foreach ($calls as $c) {
            $key = base64_encode($c->company . $c->state . $c->vendor . $c->channel);
            $product = '';
            switch ($c->event_type_id) {
                case 1:
                    $product = 'electric';
                    break;
                case 2:
                    $product = 'gas';
                    break;
                default:
                    $product = 'unknown';
                    break;
            }
            if (array_key_exists($key, $result)) {
                ++$result[$key]['total'];
                ++$result[$key][$product];
            } else {
                $result[$key] = [
                    'company' => $c->company,
                    'state' => $c->state,
                    'vendor' => $c->vendor,
                    'channel' => $c->channel,
                    'total' => 1,
                    'electric' => 0,
                    'gas' => 0,
                    'unknown' => 0,
                ];
                $result[$key][$product] = 1;
            }
        }

        $result = array_values($result);

        if ($column && $direction) {
            $sort_type = ('desc' == $direction) ? SORT_DESC : SORT_ASC;
            array_multisort(array_column($result, $column), $sort_type, $result);
        }

        if ($request->csv) {
            return $this->csv_response(
                $result,
                'Report_Daily_Call_Count_By_Channel - ( ' . $start_date . ' - ' . $end_date . ' )'
            );
        }

        return collect($result)->paginate(25);
    }

    public function report_call_followups(Request $request)
    {
        if (!$request->csv && !$request->ajax()) {
            return view('generic-vue')->with(
                [
                    'componentName' => 'call-followups',
                    'title' => 'Report: Call Followups',
                ]
            );
        }

        $start_date = ($request->startDate) ? $request->startDate . ' 00:00:00' : date('Y-m-d 00:00:00');
        $end_date = ($request->endDate) ? $request->endDate . ' 23:59:59' : date('Y-m-d 23:59:59');
        $column = $request->column ?? 'event_flags.created_at';
        $direction = $request->direction ?? 'asc';

        $reports = EventFlag::select(
            'event_flags.created_at AS date_completed',
            'events.confirmation_code',
            'interactions.created_at AS date_created',
            'event_flags.flag_reason_id AS flag_type',
            'brands.name AS brand_name',
            'tpv_staff.first_name',
            'tpv_staff.last_name',
            'vendor_name',
            'call_centers.call_center',
            // 'event_flags.notes AS notes',
            'btn',
            'event_flag_reasons.description'
        )->join(
            'events',
            'event_flags.event_id',
            'events.id'
        )->join(
            'interactions',
            'interactions.id',
            'event_flags.interaction_id'
        )->leftJoin(
            'tpv_staff',
            'tpv_staff.id',
            'event_flags.flagged_by_id'
        )->join(
            'brands',
            'brands.id',
            'events.brand_id'
        )->leftJoin(
            'event_flag_reasons',
            'event_flag_reasons.id',
            'event_flags.flag_reason_id'
        )->leftJoin(
            'stats_product',
            'stats_product.interaction_id',
            'interactions.id'
        )->leftjoin(
            'call_centers',
            'call_centers.id',
            'tpv_staff.call_center_id'
        )->whereNull(
            'call_review_type_id'
        )->whereNull(
            'reviewed_by'
        )->where(
            'event_flags.flag_reason_id',
            '00000000000000000000000000000000'
        );

        if ($request->startDate && $request->endDate) {
            $reports = $reports->whereBetween(
                'event_flags.created_at',
                [$start_date, $end_date]
            );
        }

        if ($column == 'name') {
            $reports = $reports->orderBy(
                'tpv_staff.first_name',
                $direction
            )->orderBy(
                'tpv_staff.last_name',
                $direction
            );
        } else {
            $reports = $reports->orderBy(
                $column,
                $direction
            );
        }

        if (!$request->csv) {
            $paginator = $reports->simplePaginate(20);
            $nav = ['next' => $paginator->toArray()['next_page_url'], 'last' => $paginator->toArray()['prev_page_url']];
            $reports = $paginator->all();
        } else {
            $reports = $reports->get();
        }

        if ($request->csv) {
            $reports->map(function ($r) {
                if (
                    isset($r->flag_type)
                    && $r->flag_type == '00000000000000000000000000000000'
                ) {
                    $r->flag_type = 'Final Disposition';
                }

                return $r;
            });

            return $this->csv_response(
                array_values(
                    $reports->toArray()
                ),
                'Report_Call_Follow_Ups (' . $start_date . ' - ' . $end_date . ')'
            );
        }

        return [
            'reports' => $reports,
            'page_nav' => $nav,
        ];
    }

    public function report_daily_billing_figures(Request $request)
    {
        if ($request->date_from) {
            $data = explode('/', $request->date_from);
            $date_from = $data[2] . '/' . $data[0] . '/' . $data[1] . ' 00:00:00';
        } else {
            $date_from = date('Y/m/d 00:00:00');
        }

        if ($request->date_to) {
            $data = explode('/', $request->date_to);
            $date_to = $data[2] . '/' . $data[0] . '/' . $data[1] . ' 23:59:59';
        } else {
            $date_to = date('Y/m/d 23:59:59');
        }

        $calls = Interaction::select(
            'brands.name as company',
            'states.state_abbrev as state',
            'markets.market as market',
            'channels.channel as channel',
            'languages.language as language',
            DB::raw('sec_to_time(sum(time_to_sec(interaction_time))) as calltime'),
            DB::raw('count(*) as calltotal')
        )
            ->leftJoin('events', 'interactions.event_id', 'events.id')
            ->leftJoin('brands', 'events.brand_id', 'brands.id')
            ->leftJoin('states', 'brands.state', 'states.id')
            ->leftJoin('brand_states', 'events.brand_id', 'brand_states.brand_id')
            ->leftJoin('brand_state_markets', 'brand_states.id', 'brand_state_markets.brand_state_id')
            ->leftJoin('markets', 'brand_state_markets.market_id', 'markets.id')
            ->leftJoin('channels', 'events.channel_id', 'channels.id')
            ->leftJoin('brand_state_languages', 'brand_states.id', 'brand_state_languages.brand_state_id')
            ->leftJoin('languages', 'brand_state_languages.language_id', 'languages.id')
            ->where('interactions.created_at', '>=', $date_from)
            ->where('interactions.created_at', '<=', $date_to);

        if ($request->brand_id) {
            $calls = $calls->where('events.brand_id', $request->brand_id);
        }

        $calls = $calls->groupBy('company', 'states.state_abbrev', 'states.id', 'markets.market', 'channel', 'language')->get();

        $data_from = explode(' ', $date_from);
        $data_from2 = explode('/', $data_from[0]);
        $date_from = $data_from2[1] . '/' . $data_from2[2] . '/' . $data_from2[0];

        $data_to = explode(' ', $date_to);
        $data_to2 = explode('/', $data_to[0]);
        $date_to = $data_to2[1] . '/' . $data_to2[2] . '/' . $data_to2[0];

        return view(
            'reports.report_daily_billing_figures',
            [
                'calls' => $calls,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'brands' => $this->brands_select_options(),
                'brand_id' => $request->brand_id,
            ]
        );
    }

    public function on_the_clock_report(Request $request)
    {
        $date = $request->startDate ? Carbon::parse($request->startDate . ' 00:00:00', 'America/Chicago') : (Carbon::now('America/Chicago')->yesterday());

        return view('reports.genericReport')->with([
            'title' => 'On The Clock',
            'mainUrl' => route('reports.on_the_clock'),
            'url' => route('reports.list_on_the_clock'),
            'vendors' => '[]',
            'languages' => '[]',
            'commodities' => '[]',
            'startDate' => $date->toDateString(),
            'endDate' => $date->toDateString(),
            'hiddenColumns' => ['id', 'tpv_staff_id'],
            'viewLink' => '/tpv_staff/[tpv_staff_id]/time?date=[startDate]',
        ]);
    }

    public function list_on_the_clock(Request $request)
    {
        $date = $request->startDate ? Carbon::parse($request->startDate . ' 00:00:00', 'America/Chicago') : (Carbon::now('America/Chicago')->yesterday());

        $results = DB::select(
            'select id, tpv_staff_id, tpv_name, username, last_time_punch 
                from (
                    select tc.id, tc.tpv_staff_id, concat(ts.first_name, " ", ts.last_name) as tpv_name, ts.username, max(tc.time_punch) as last_time_punch, count(*) as row_cnt 
                    from time_clocks tc 
                    left join tpv_staff ts on ts.id = tc.tpv_staff_id
                    where date(tc.time_punch) = ? 
                    group by tc.tpv_staff_id 
                ) as t 
                    where mod(row_cnt, 2) = 1',
            [
                $date->format('Y-m-d')
            ]
        );

        return response()->json(['data' => $results, 'date' => $date->format('Y-m-d'), 'last_page' => 1, 'total' => count($results)]);
    }

    public function report_cph()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'cph',
                'title' => 'Report: CPH',
                'parameters' => [
                    'brands' => json_encode($this->brands_select_options()),
                ],
            ]
        );
    }

    public function list_cph(Request $request)
    {
        $start_date = ($request->startDate) ? $request->startDate . ' 00:00:00' : date('Y-m-d 00:00:00');
        $end_date = ($request->endDate) ? $request->endDate . ' 23:59:59' : date('Y-m-d 23:59:59');
        $column = $request->column ?? 'name';
        $direction = $request->direction ?? 'asc';

        //Refactoring one single query to improve performance
        $agents = Interaction::select(
            'tpv_staff.last_name',
            'tpv_staff.first_name',
            'tpv_staff.middle_name',
            'interactions.tpv_staff_id',
            'interactions.event_id',
            'interactions.id',
            'interactions.parent_interaction_id',
            'interactions.interaction_type_id',
            'interactions.interaction_time'
        )->leftJoin(
            'tpv_staff',
            'interactions.tpv_staff_id',
            'tpv_staff.id'
        )->whereIn('interaction_type_id', [1, 2, 6]);

        if ($request->brand) {
            $agents = $agents->leftJoin('events', 'interactions.event_id', 'events.id')
                ->leftJoin('brands', 'events.brand_id', 'brands.id')
                ->whereIn('events.brand_id', $request->brand);
        }

        $agents = $agents->whereBetween('interactions.created_at', [$start_date, $end_date])
            ->whereNotNull('interactions.tpv_staff_id')
            ->orderBy(
                'interactions.created_at'
            )->groupBy(
                'interactions.id'
            )->get()->groupBy('event_id')->transform(function ($calls) {
                //Two cases digital or not
                $digital = false;
                $tcalls = [];
                foreach ($calls as $c) {
                    if ($c->interaction_type_id == 6) {
                        $digital = true;
                        break;
                    }
                }
                if ($digital) {
                    foreach ($calls as $c) {
                        if (in_array($c->interaction_type_id, [1, 2])) {
                            $tcalls[] = $c;
                        }
                    }
                } else {
                    foreach ($calls as $c) {
                        if (in_array($c->interaction_type_id, [1, 2]) && is_null($c->parent_interaction_id)) {
                            $parent_id = $c->id;
                            foreach ($calls as $call) {
                                if ($call->parent_interaction_id == $parent_id) {
                                    $parent_id = $call->id;
                                    $c->interaction_time += $call->interaction_time;
                                }
                            }
                            $tcalls[] = $c;
                        }
                    }
                }

                return $tcalls;
            })->flatten()->groupBy('tpv_staff_id')->transform(function ($calls) {
                //Init values
                $staff = [
                    'name' => $calls[0]->last_name . ', ' . $calls[0]->first_name . ' ' . $calls[0]->middle_name,
                    'calls_ib' => 0,
                    'calls_ob' => 0,
                    'calls_total' => 0,
                    'time_total' => 0,
                ];
                foreach ($calls as $staff_call) {
                    $staff['time_total'] += $staff_call->interaction_time;
                    ++$staff['calls_total'];
                    $key = $staff_call->interaction_type_id == 1 ? 'calls_ib' : 'calls_ob';
                    ++$staff[$key];
                }
                $staff['cph'] = ($staff['time_total'] > 0 && $staff['calls_total'] > 0)
                    ? round($staff['time_total'] / $staff['calls_total'], 2)
                    : 0;
                $staff['time_total'] = round($staff['time_total'], 2);

                return $staff;
            })->values();

        $agents = ('desc' == $direction) ? $agents->sortByDesc($column)->values() : $agents->sortBy($column)->values();

        if ($request->csv) {
            return $this->csv_response(
                array_values(
                    $agents->all()
                ),
                'AHT (' . $start_date . ' - ' . $end_date . ')'
            );
        }

        return $agents->paginate(25);
    }

    /**
     * Inbound Call Volume Report.
     */
    public function report_inbound_call_volume()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'inbound-call-volume',
                'title' => 'Report: Inbound Call Volume',
                'parameters' => [
                    'brands' => $this->brands_select_options(),
                    'languages' => json_encode($this->get_languages()->filter(function ($l) {
                        return $l->id != 3;
                    })->values()->all()),
                ],
            ]
        );
    }

    public function list_report_inbound_call_volume(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $brand = $request->get('brand');
        $language = $request->get('language');

        if ($language) {
            foreach ($language as &$l) {
                switch ($l) {
                    case 1:
                        $l = 'English';
                        break;
                    case 2:
                        $l = 'Spanish';
                        break;
                    case 4:
                        $l = 'Chinese';
                        break;
                }
            }
        }

        $date_from = ($request->startDate)
            ? Carbon::createFromFormat('Y-m-d', $request->startDate, 'America/Chicago')->startOfDay()
            : Carbon::now('America/Chicago')->startOfDay();

        $date_to = ($request->endDate)
            ? Carbon::createFromFormat('Y-m-d', $request->endDate, 'America/Chicago')->endOfDay()
            : Carbon::now('America/Chicago')->endOfDay();

        //Setting ASA, service_level,... values
        //task got cancel = event_type
        $half_hours = ['07:00', '07:30', '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00', '22:30', '23:00', '23:30', '24:00'];
        $vars = get_defined_vars();
        [$twilio_stats, $dxc, $focus] = array_map(function ($func) use ($vars) {
            return $this->$func($vars);
        }, ['twilio_stats_for_inboundcv', 'dxc_calls_for_inboundcv', 'focus_calls_for_inboundcv']);
        //Setting calls container for dxc and focus calls
        $calls = [];
        foreach ($half_hours as $hh) {
            $app = app();
            $calls[$hh] = $app->make('stdClass');
            //Adding dxc_calls to calls container
            $calls[$hh]->call_time = (isset($dxc[$hh]->call_time)) ? $dxc[$hh]->call_time : 0;
            $calls[$hh]->calls = (isset($dxc[$hh]->calls)) ? $dxc[$hh]->calls : 0;
        }

        foreach ($focus as $f) {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $f->created_at);
            $h = $date->format('H');
            $m = ($date->minute >= 30) ? '30' : '00';
            $key = $h . ':' . $m;
            //Adding focus_calls to calls container
            $calls[$key]->call_time += $f->interaction_time;
            ++$calls[$key]->calls;
        }

        $ibcalls = [];
        $totals = [
            'calls' => 0,
            'call_time' => 0,
            'avg_call_time' => 0,
            'asa' => 0,
            'calls_abandoned' => 0,
            'avg_abandon_time' => 0,
            'service_level' => 0.0,
        ];
        $current_time = '';
        if ($date_to->isToday() || $date_from->isToday()) {
            $now = Carbon::now('America/Chicago');
            $current_time = $now->hour;
            if ($now->minute >= 30) {
                $current_time = $current_time . ':30';
            } else {
                $current_time = $current_time . ':00';
            }
        }

        //Setting final results
        foreach ($half_hours as $hh) {
            $ibcalls[$hh]['time_slice'] = $hh;
            $ibcalls[$hh]['calls'] = $calls[$hh]->calls;
            $ibcalls[$hh]['call_time'] = $calls[$hh]->call_time;
            $ibcalls[$hh]['avg_abandon_time'] = $twilio_stats[$hh]->avg_abandon_time;
            $ibcalls[$hh]['asa'] = $twilio_stats[$hh]->asa;
            $ibcalls[$hh]['calls_abandoned'] = $twilio_stats[$hh]->calls_abandoned;
            $ibcalls[$hh]['service_level'] = $twilio_stats[$hh]->service_level;
            $ibcalls[$hh]['avg_call_time'] = ($calls[$hh]->call_time > 0 && $calls[$hh]->calls) ? $calls[$hh]->call_time / $calls[$hh]->calls : '0.00';

            //Setting totals
            // $i_period = Carbon::now('America/Chicago');
            // $i_period->hour = explode(':', $hh)[0];
            // $i_period->minute = explode(':', $hh)[1];
            // if (Carbon::now('America/Chicago')->gt($i_period)) {
            //     ++$periods_finished;
            //     $totals['service_level'] += $ibcalls[$hh]['service_level'];
            // }
            $totals['calls'] += $ibcalls[$hh]['calls'];
            $totals['call_time'] += $ibcalls[$hh]['call_time'];
            $totals['asa'] += $ibcalls[$hh]['asa'] * $ibcalls[$hh]['calls'];
            $totals['calls_abandoned'] += $ibcalls[$hh]['calls_abandoned'];
            $totals['avg_abandon_time'] += $ibcalls[$hh]['avg_abandon_time'];
        }

        //calculating proper $totals[service_level]
        $t_calls_answered_on_time = array_sum(array_column($twilio_stats, 'calls_answered_on_time'));
        $t_calls = array_sum(array_column($twilio_stats, 'calls'));
        $totals['service_level'] = ($t_calls_answered_on_time && $t_calls) ? $t_calls_answered_on_time / $t_calls : 0;

        if (count($ibcalls) > 0 && $column && $direction) {
            $sort_type = ('desc' == $direction) ? SORT_DESC : SORT_ASC;
            array_multisort(array_column($ibcalls, $column), $sort_type, $ibcalls);
        }

        if ($request->csv) {
            $results = [];
            foreach ($ibcalls as $key => $value) {
                $row = [];
                $row['Interval Start'] = $key;
                $row['Calls'] = $value['calls'];
                $row['Call Time'] = $value['call_time'];
                $row['Average Call Time (mins)'] = $value['avg_call_time'];
                $row['Average Speed of Answer (secs)'] = $value['asa'];
                $row['Calls Abandonded'] = $value['calls_abandoned'];
                $row['SA (secs)'] = $value['avg_abandon_time'];
                $row['Service Level'] = $value['service_level'];
                $results[] = $row;
            }

            return $this->csv_response(
                $results,
                'Inbound_Call_Volume ( ' . $date_from . ' - ' . $date_to . ' )'
            );
        }

        return [
            'current_time' => $current_time,
            'ibcalls' => $ibcalls,
            'totals' => $totals,
        ];
    }

    private function twilio_stats_for_inboundcv(array $arr)
    {
        [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'brand' => $brand,
            'language' => $language,
            'half_hours' => $half_hours
        ] = $arr;

        // This is needed as values from Twilio that we are looking up in the DB are in UTC times using "format" which does not include the Timezone which throws values off
        $date_from->setTimezone('UTC');
        $date_to->setTimezone('UTC');

        $ec = DB::table(
            'event_callback AS ec'
        )->select(
            'ec.task_age',
            'ec.event_type',
            'btq.task_queue',
            'task_attributes',
            DB::raw("CONCAT(DATE_FORMAT(CONVERT_TZ(ec.created_at, 'UTC', 'America/Chicago'), '%H'),
            ':',
            CASE
                WHEN MINUTE(ec.created_at) >= 30 THEN 30
                ELSE '00'
            END) AS time")
        )->join(
            'brand_task_queues AS btq',
            'ec.task_queue_sid',
            'btq.task_queue_sid'
        );

        if ($brand) {
            $ec = $ec->whereIn('btq.brand_id', $brand);
        }
        
        $ec->whereRaw(
            //"CONVERT_TZ(ec.created_at, 'UTC', 'America/Chicago') >= '" . $date_from->format('Y-m-d H:i:s') . "'"
            // Index Range Scan better than converting, $date_from Timezone set to 'America/Chicago' so -6 hours
            "ec.created_at >= '" . $date_from->format('Y-m-d H:i:s') . "'"
        )->whereRaw(
            //"CONVERT_TZ(ec.created_at, 'UTC', 'America/Chicago') <= '" . $date_to->format('Y-m-d H:i:s') . "'"
            // Index Range Scan better than converting, $date_to Timezone set to 'America/Chicago' so -6 hours
            "ec.created_at <= '" . $date_to->format('Y-m-d H:i:s') . "'"
        )->whereIn(
            'ec.event_type',
            ['reservation.accepted', 'task.canceled']
        )->whereRaw(
            "HOUR(CONVERT_TZ(ec.created_at, 'UTC', 'America/Chicago')) BETWEEN 7 AND 21"
        )->where(
            'btq.task_queue',
            'Not Like',
            '%outbound%'
        )->where(
            'btq.task_queue',
            'Not Like',
            '%survey%'
        )->whereNotNull('ec.task_age');

        if ($language) {
            $ec = $ec->where(function ($query) use ($language) {
                $query->where(
                    'ec.task_queue_name',
                    'Like',
                    '%' . array_shift($language) . '%'
                );
                if (count($language)) {
                    foreach ($language as $l) {
                        $query->orWhere('ec.task_queue_name', 'LIKE', "%$l%");
                    }
                }

                return $query;
            });
        }

        $ec = $ec->get();

        // Reset to default UTC Timezone as these $date_ objects are used elsewhere by Reference
        $date_from->setTimezone('America/Chicago');
        $date_to->setTimezone('America/Chicago');

        $twilio_stats = [];
        //init twilio_stats
        foreach ($half_hours as $hh) {
            $app = app();
            $twilio_stats[$hh] = $app->make('stdClass');
            $twilio_stats[$hh]->avg_abandon_time = $twilio_stats[$hh]->wait_duration_until_canceled = $twilio_stats[$hh]->calls = $twilio_stats[$hh]->calls_abandoned = $twilio_stats[$hh]->asa = $twilio_stats[$hh]->calls_answered_on_time = $twilio_stats[$hh]->service_level = 0;
        }

        foreach ($ec as $e) {
            if ($e->event_type === 'task.canceled') {
                ++$twilio_stats[$e->time]->calls_abandoned;
                $twilio_stats[$e->time]->wait_duration_until_canceled += $e->task_age;
            } else {
                ++$twilio_stats[$e->time]->calls;
                $twilio_stats[$e->time]->asa += $e->task_age;
                $twilio_stats[$e->time]->calls_answered_on_time += ($e->task_age <= 20) ? 1 : 0;
            }
        }
        //Once all calls has been counted we can get the final asa, avg_abandon_time and service_level
        return array_map(function ($e) {
            if ($e->calls > 0) {
                $e->asa = ($e->asa > 0) ? $e->asa / $e->calls : 0;
                $e->service_level = ($e->calls_answered_on_time > 0) ? $e->calls_answered_on_time / $e->calls : 0;
            }
            $e->avg_abandon_time = ($e->wait_duration_until_canceled > 0 && $e->calls_abandoned > 0) ? $e->wait_duration_until_canceled / $e->calls_abandoned : 0;

            return $e;
        }, $twilio_stats);
    }

    private function dxc_calls_for_inboundcv(array $arr)
    {
        [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'brand' => $brand,
            'language' => $language,
            'half_hours' => $half_hours
        ] = $arr;

        $dxc = DXCLegacy::selectRaw(
            "SUM(dxc_legacy.call_time) AS call_time,
            COUNT(*) AS calls,
            CONCAT(DATE_FORMAT(dxc_legacy.insert_at, '%H'),
            ':',
            CASE
                WHEN MINUTE(dxc_legacy.insert_at) >= 30 THEN 30
                ELSE '00'
            END) AS time"
        )->whereDate(
            'dxc_legacy.insert_at',
            '>=',
            $date_from
        )->whereDate(
            'dxc_legacy.insert_at',
            '<=',
            $date_to
        )->where(
            'dxc_legacy.tpv_type',
            'TPV'
        );

        if ($language) {
            $dxc = $dxc->whereIn(
                'dxc_legacy.language',
                $language
            );
        }

        if ($brand) {
            $dxc_brands = $this->is_brand_in_dxc_legacy($brand);
            if ($dxc_brands) {
                $dxc = $dxc->whereIn(
                    'dxc_legacy.brand',
                    $dxc_brands
                );
            } else {
                return [];
            }
        }

        $dxc = $dxc->groupBy('time')->withTrashed()->get()->mapWithKeys(function ($i) {
            $app = app();
            $std = $app->make('stdClass');
            $std->call_time = $i->call_time;
            $std->calls = $i->calls;

            return [$i->time => $std];
        })->toArray();

        //dd($dxc);

        $final_dxc = [];
        foreach ($half_hours as $hh) {
            $app = app();
            $final_dxc[$hh] = $app->make('stdClass');
            $final_dxc[$hh]->call_time = $final_dxc[$hh]->calls = 0;
        }

        return array_merge($final_dxc, $dxc);
    }

    private function is_brand_in_dxc_legacy($brand)
    {
        if (is_array($brand)) {
            $brand = $brand[0];
        }

        return Cache::remember(
            'is_brand_in_dxc_legacy_' . $brand,
            1800,
            function () use ($brand) {
                return DxcBrand::select(
                    'dxc_brands.dxc_brand_name'
                )->whereIn(
                    'brands_id',
                    $brand
                )->groupBy('dxc_brands.dxc_brand_name')->withTrashed()->get();
            }
        );
    }

    private function focus_calls_for_inboundcv(array $arr)
    {
        [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'brand' => $brand,
            'request' => $request
        ] = $arr;

        $focus = Interaction::select(
            'interactions.created_at',
            'interactions.interaction_time',
            'interactions.parent_interaction_id',
            'interactions.interaction_type_id',
            'interactions.id'
        )->leftJoin(
            'events',
            'events.id',
            'interactions.event_id'
        )->whereNotNull(
            'interactions.interaction_time'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2, 6]
        )->whereNull('events.survey_id')
            ->whereNull('events.deleted_at')
            ->where(
                'interactions.created_at',
                '>=',
                $date_from->format('Y-m-d H:i:s')
            )->where(
                'interactions.created_at',
                '<=',
                $date_to->format('Y-m-d H:i:s')
            );

        if ($brand) {
            $focus = $focus->where(
                'events.brand_id',
                $brand
            );
        }

        if ($request->language) {
            $focus = $focus->where(
                'events.language_id',
                $request->language
            );
        }

        return $focus->orderBy('interactions.created_at')->groupBy('interactions.id')->get()->groupBy('interactions.event_id')->transform(function ($icalls) {
            //Two cases digital or not
            $digital = false;
            //Here im returning all props from the first obj since they are all the same
            $tcalls = [];
            foreach ($icalls as $c) {
                if ($c->interaction_type_id == 6) {
                    $digital = true;
                    break;
                }
            }
            if ($digital) {
                foreach ($icalls as $c) {
                    if (in_array($c->interaction_type_id, [1, 2])) {
                        $tcalls[] = $c;
                    }
                }
            } else {
                foreach ($icalls as $c) {
                    if (in_array($c->interaction_type_id, [1, 2]) && is_null($c->parent_interaction_id)) {
                        //Now making sure that the returned call has the sum of all interaction_time from
                        //in + out and the last event_result_id
                        $parent_id = $c->id;
                        $time_call = $c->interaction_time;
                        foreach ($icalls as $second_c) {
                            if ($parent_id == $second_c->parent_interaction_id) {
                                $time_call += $second_c->interaction_time;
                                $parent_id = $second_c->id;
                            }
                        }
                        $c->interaction_time = round($time_call, 2);
                        $tcalls[] = $c;
                    }
                }
            }

            return $tcalls;
        })->flatten();
    }

    private function brands_select_options()
    {
        return Cache::remember(
            'icd_brand_task_queues',
            120,
            function () {
                return Brand::select('brands.id', 'brands.name')
                    ->join('brand_task_queues', 'brands.id', 'brand_task_queues.brand_id')
                    ->whereNotNull('brand_task_queues.brand_id')
                    ->orderBy('brands.name')
                    ->distinct('brands.id')->get();
            }
        );
    }

    private function statuses_select_options()
    {
        return Cache::remember(
            'icd_statuses',
            120,
            function () {
                return DB::table(
                    'agent_status_types'
                )->select(
                    'id',
                    DB::raw('REPLACE(`name`, "\"", "") as name')
                )->orderBy(
                    'id'
                )->whereNull(
                    'deleted_at'
                )->get()->pluck(
                    'name',
                    'id'
                )->all();
            }
        );
    }

    public function report_agent_statuses(Request $request)
    {
        if (!$request->ajax()) {
            return view('reports.report_agent_statuses');
        }

        $column = $request->column ?? 'name';
        $direction = $request->direction ?? 'asc';

        //Refactoring subquery. Added WhereDate reduced the execution time by half
        $selectSub = DB::table(
            'agent_statuses as c'
        )->select(
            'c.tpv_staff_id',
            DB::raw('MAX(c.created_at) as max_date')
        )->whereDate(
            'c.created_at',
            Carbon::now('America/Chicago')->format('Y-m-d')
        )->groupBy('c.tpv_staff_id');

        $agents = DB::table('agent_statuses as b')
            ->select(['b.created_at', 'agent.id', 'agent.first_name', 'agent.last_name', 'agent.call_center_id', 'b.event'])
            ->join('tpv_staff as agent', 'agent.id', 'b.tpv_staff_id');

        if (isset($request->location_filter)) {
            $agents = $agents->where('agent.call_center_id', $request->location_filter);
        }

        if (isset($request->status_filter)) {
            $event_name = DB::table('agent_status_types')->select('name')->where('id', $request->status_filter)->get();
            $agents = $agents->where('b.event', str_replace('"', '', $event_name[0]->name));
            //Speding up the status_filter inside the subquery
            $selectSub = $selectSub->where('c.event', str_replace('"', '', $event_name[0]->name));
        }

        if (isset($request->name_filter)) {
            $agents = $agents
                ->where('agent.first_name', 'LIKE', "%{$request->name_filter}%")
                ->orWhere('agent.last_name', 'LIKE', "%{$request->name_filter}%");
        }

        $agents = $agents->join(
            DB::raw('(' . $selectSub->fullSQL() . ') AS a'),
            function ($join) {
                $join->on('a.tpv_staff_id', 'b.tpv_staff_id');
                $join->on('a.max_date', 'b.created_at');
            }
        )
            ->groupBy('agent.id')
            ->whereRaw('date(b.created_at) = ?', [Carbon::now('America/Chicago')->format('Y-m-d')]);

        //Improving execution time with simplePaginate
        $paginator = $agents->simplePaginate(20);
        $nav = [
            'next' => $paginator->nextPageUrl(),
            'last' => $paginator->previousPageUrl(),
        ];
        $agents = $paginator->items();

        foreach ($agents as &$agent) {
            $agent->time = Carbon::parse($agent->created_at)->format('m-d-Y h:i:s A');
            $agent->elapsed = Carbon::parse($agent->created_at, 'America/Chicago')->diffForHumans('UTC', true, true, 3);
            switch ($agent->call_center_id) {
                case 1:
                    $agent->call_center_id = 'Tulsa';
                    break;
                case 2:
                    $agent->call_center_id = 'Tahlequah';
                    break;
                case 3:
                    $agent->call_center_id = 'Las Vegas';
                    break;
            }
            $agent->name = $agent->first_name . ' ' . $agent->last_name;
            $agent = (array) $agent;
        }

        if ($column && $direction) {
            $sort_type = ('desc' == $direction) ? SORT_DESC : SORT_ASC;
            array_multisort(array_column($agents, $column), $sort_type, $agents);
        }

        return [
            'agents' => $agents,
            'statuses' => $this->statuses_select_options(),
            'page_nav' => $nav,
        ];
    }

    public function report_qc_callbacks(Request $request)
    {
        if (!$request->csv && !$request->ajax()) {
            return view('generic-vue')->with(
                [
                    'componentName' => 'qc-callbacks',
                    'title' => 'Report: QC Callbacks',
                ]
            );
        }

        $column = $request->column;
        $direction = $request->direction;

        $date_from = ($request->startDate)
            ? Carbon::createFromFormat(
                'Y-m-d',
                $request->startDate,
                'America/Chicago'
            )
            : Carbon::now('America/Chicago');
        $date_from = $date_from->startOfDay()->toIso8601String();

        $date_to = ($request->endDate)
            ? Carbon::createFromFormat(
                'Y-m-d',
                $request->endDate,
                'America/Chicago'
            )
            : Carbon::now('America/Chicago');
        $date_to = $date_to->startOfDay()->toIso8601String();

        //Included interactions.date, interactions.interaction_type_id filter for interactions table reduced execution time
        //by 98%
        //Changing Eloquent for QueryB improve exceution time by 2s
        $callbacks = DB::table('stats_product')->select(
            'stats_product.tpv_agent_name',
            'stats_product.channel',
            'stats_product.event_id',
            'stats_product.confirmation_code',
            'stats_product.interaction_created_at',
            'stats_product.disposition_label',
            'stats_product.result',
            'interactions.notes'
        )->leftJoin(
            'interactions',
            'stats_product.interaction_id',
            'interactions.id'
        )->where(
            'stats_product.interaction_type',
            'call_inbound'
        )->where(
            'stats_product.stats_product_type_id',
            '3'
        )->where(
            'interactions.interaction_type_id',
            '1'
        )->whereBetween(
            'stats_product.interaction_created_at',
            [$date_from, $date_to]
        )->whereBetween(
            'interactions.created_at',
            [$date_from, $date_to]
        );

        if ($request->confirmation_code_search) {
            $callbacks = $callbacks->where(
                'stats_product.confirmation_code',
                $request->confirmation_code_search
            );
        }

        if ($request->tpv_agent_name_search) {
            $callbacks = $callbacks->where(
                'stats_product.tpv_agent_name',
                'LIKE',
                '%' . $request->tpv_agent_name_search . '%'
            );
        }

        $callbacks = $callbacks->orderBy('stats_product.interaction_created_at', 'desc');

        if (!$request->csv) {
            $callbacks = $callbacks->simplePaginate(20);
            $nav = [
                'next' => $callbacks->nextPageUrl(),
                'last' => $callbacks->previousPageUrl(),
            ];
        } else {
            $callbacks = $callbacks->get();
        }

        $calls = [];

        for ($i = 0; $i < count($callbacks) - 1; ++$i) {
            if ($callbacks[$i]->confirmation_code !== $callbacks[$i + 1]->confirmation_code) {
                $row = [
                    'tpv_agent_name' => $callbacks[$i]->tpv_agent_name,
                    'channel' => $callbacks[$i]->channel,
                    'event_id' => $callbacks[$i]->event_id,
                    'confirmation_code' => $callbacks[$i]->confirmation_code,
                    'interaction_created_at' => $callbacks[$i]->interaction_created_at,
                    'disposition_label' => $callbacks[$i]->result == 'Sale' ? 'Complete' : $callbacks[$i]->disposition_label,
                    'date_log' => Carbon::parse($callbacks[$i]->interaction_created_at)->format('m/d/Y h:i:s A'),
                    'log' => $callbacks[$i]->result == 'Sale' ? 'Complete' : $callbacks[$i]->disposition_label,
                    'comments' => $this->parse_notes($callbacks[$i]->notes),
                ];

                $calls[] = $row;

            }
        }

        if (count($callbacks) > 0) {
            $count_minus_one = count($callbacks) - 1;

            $calls[] = [
                'tpv_agent_name' => $callbacks[$count_minus_one]->tpv_agent_name,
                'channel' => $callbacks[$count_minus_one]->channel,
                'event_id' => $callbacks[$count_minus_one]->event_id,
                'confirmation_code' => $callbacks[$count_minus_one]->confirmation_code,
                'interaction_created_at' => $callbacks[$count_minus_one]->interaction_created_at,
                'disposition_label' => $callbacks[$count_minus_one]->result == 'Sale' ? 'Complete' : $callbacks[$count_minus_one]->disposition_label,
                'date_log' => Carbon::parse($callbacks[$count_minus_one]->interaction_created_at)->format('m/d/Y h:i:s A'),
                'log' => $callbacks[$count_minus_one]->result == 'Sale' ? 'Complete' : $callbacks[$count_minus_one]->disposition_label,
                'comments' => $this->parse_notes($callbacks[$count_minus_one]->notes),
            ];
        }

        if ($column && $direction) {
            $sort_type = ('desc' == $direction) ? SORT_DESC : SORT_ASC;
            array_multisort(array_column($calls, $column), $sort_type, $calls);
        }

        if ($request->csv) {
            $exported_calls = $calls;
            if ($this->delete_col($exported_calls, 'event_id')) {
                return $this->csv_response(
                    array_values(
                        $exported_calls
                    ),
                    'Report_QC_Callbacks (' . $date_from . ' - ' . $date_to . ')',
                    [
                        'TPV Agent Name',
                        'Channel',
                        'Confirmation Code',
                        'CB Date',
                        'CB Attempts Log',
                        'CB Status',
                        'CB Notes Log',
                    ]
                );
            } else {
                session('flash_message', 'There was an error when trying to export to CSV');
            }
        }

        return [
            'calls' => $calls,
            'page_nav' => $nav,
        ];
    }

    private function parse_notes($notes)
    {
        if (is_array($notes)) {
            if (array_key_exists('feedback', $notes)) {
                return $notes['feedback'];
            }

            return '';
        }

        if (strlen($notes) === 0) {
            return '';
        }
        //Since some notes are poorly shaped we need to use json_decode twice ex:
        //"{\"ani\":\"+12146592306\",\"dnis\":\"+19182058955\",\"campaign\":\"Direct Dial\",\"callSession\":\"WT463d8a63cfbe79407767b3102ff2ffff\",\"station\":\"1-004595\",\"callType\":\"survey\",\"event_id\":\"8653131b-91ba-482d-b857-fa9b6b4ee4ec\",\"language\":\"english\",\"devSession\":\"false\"}"
        //{"ani":"+266696687","dnis":"+18554234866","campaign":"Direct Dial","callSession":"WT0d00da650e4b12beda969416d33efc52","station":"1969716","state_id":"33","language":"English","devSession":"false"}
        $arr = json_decode($notes, true);
        //if its still a string after the first convertion
        if (is_string($arr)) {
            $arr = json_decode($arr, true);
        }
        //Making sure its an array
        if (is_array($arr)) {
            if (array_key_exists('feedback', $arr)) {
                return $arr['feedback'];
            }
        }

        return '';
    }

    private function delete_col(&$array, $key)
    {
        return array_walk($array, function (&$v) use ($key) {
            unset($v[$key]);
        });
    }

    public function dailyStats()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'daily-stats',
                'title' => 'Report: Daily Stats',
            ]
        );
    }

    public function ListDailyStats(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::now()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::now()->format('Y-m-d');
        $column = $request->column ?? 'name';
        $direction = $request->direction ?? 'asc';

        $ds = DailyStat::select(
            'brands.name',
            DB::raw('SUM(daily_stats.total_live_min) AS total_live_min'),
            DB::raw('SUM(daily_stats.live_channel_tm) AS live_channel_tm'),
            DB::raw('SUM(daily_stats.live_channel_dtd) AS live_channel_dtd'),
            DB::raw('SUM(daily_stats.live_channel_retail) AS live_channel_retail'),
            DB::raw('SUM(daily_stats.live_english_min) AS live_english_min'),
            DB::raw('SUM(daily_stats.live_spanish_min) AS live_spanish_min'),
            DB::raw('SUM(daily_stats.live_cc_tulsa_min) AS live_cc_tulsa_min'),
            DB::raw('SUM(daily_stats.live_cc_tahlequah_min) AS live_cc_tahlequah_min'),
            DB::raw('SUM(daily_stats.live_cc_lasvegas_min) AS live_cc_lasvegas_min'),
            DB::raw('SUM(daily_stats.total_records) AS total_records'),
            DB::raw('SUM(daily_stats.total_eztpvs) AS total_eztpvs'),
            DB::raw('SUM(daily_stats.eztpv_contract) AS eztpv_contract'),
            DB::raw('SUM(daily_stats.digital_transaction) AS digital_transaction'),
            DB::raw('SUM(daily_stats.voice_imprint) AS voice_imprint')
        )->leftJoin(
            'brands',
            'daily_stats.brand_id',
            'brands.id'
        )->whereNull(
            'brands.deleted_at'
        )->where(
            'brands.name',
            'NOT LIKE',
            'z_%'
        )->whereBetween(
            'daily_stats.stats_date',
            [
                $start_date,
                $end_date,
            ]
        )->groupBy(
            'daily_stats.brand_id'
        )->get();

        if ($column && $direction) {
            if ($direction == 'desc') {
                $ds = $ds->sortByDesc($column)->values()->all();
            } else {
                $ds = $ds->sortBy($column)->values()->all();
            }

            foreach ($ds as &$d) {
                $d = $d->getAttributes();
            }
        }
        if ($request->get('csv')) {
            return $this->csv_response(
                array_values(
                    is_array($ds) ? $ds : $ds->toArray()
                ),
                'Report_Daily_Stats (' . $start_date . ' - ' . $end_date . ')'
            );
        }

        return $ds;
    }

    public function rawBilling()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'raw-billing',
                'title' => 'Report: Raw Billing',
            ]
        );
    }

    public function ListRawBilling(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::now()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::now()->format('Y-m-d');
        $column = $request->column ?? 'name';
        $direction = $request->direction ?? 'asc';

        $ds = DailyStat::select(
            'clients.name as client_name',
            'brands.name as brand_name',
            DB::raw('DATE_FORMAT(stats_date, "%M-%Y") AS period'),
            DB::raw('DATE_FORMAT(min(stats_date), "%m-%d-%Y") as rawDate'),
            DB::raw('SUM(daily_stats.total_records) AS total_records'),
            DB::raw('SUM(daily_stats.total_live_min) AS total_live_min'),
            DB::raw('SUM(daily_stats.total_live_inbound_min) AS total_live_inbound_min'),
            DB::raw('SUM(daily_stats.total_live_outbound_min) AS total_live_outbound_min'),
            DB::raw('SUM(daily_stats.live_english_min) AS live_english_min'),
            DB::raw('SUM(daily_stats.live_spanish_min) AS live_spanish_min'),
            DB::raw('SUM(daily_stats.live_good_sale) AS live_good_sale'),
            DB::raw('SUM(daily_stats.live_no_sale) AS live_no_sale'),
            DB::raw('SUM(daily_stats.live_channel_dtd) AS live_channel_dtd'),
            DB::raw('SUM(daily_stats.live_channel_tm) AS live_channel_tm'),
            DB::raw('SUM(daily_stats.live_channel_retail) AS live_channel_retail'),
            DB::raw('SUM(daily_stats.live_cc_tulsa_min) AS live_cc_tulsa_min'),
            DB::raw('SUM(daily_stats.live_cc_tahlequah_min) AS live_cc_tahlequah_min'),
            DB::raw('SUM(daily_stats.live_cc_lasvegas_min) AS live_cc_lasvegas_min'),
            DB::raw('SUM(daily_stats.total_ivr_min) AS total_ivr_min'),
            DB::raw('SUM(daily_stats.total_ivr_inbound_min) AS total_ivr_inbound_min'),
            DB::raw('SUM(daily_stats.total_ivr_outbound_min) AS total_ivr_outbound_min'),
            DB::raw('SUM(daily_stats.dnis_tollfree) AS dnis_tollfree'),
            DB::raw('SUM(daily_stats.dnis_local) AS dnis_local'),
            DB::raw('SUM(daily_stats.total_eztpvs) AS total_eztpvs'),
            DB::raw('SUM(daily_stats.total_dtd_eztpvs) AS total_dtd_eztpvs'),
            DB::raw('SUM(daily_stats.total_retail_eztpvs) AS total_retail_eztpvs'),
            DB::raw('SUM(daily_stats.total_tm_eztpvs) AS total_tm_eztpvs'),
            DB::raw('SUM(daily_stats.eztpv_contract) AS eztpv_contract'),
            DB::raw('SUM(daily_stats.eztpv_photo) AS eztpv_photo'),
            DB::raw('SUM(daily_stats.ld_dom) AS ld_dom'),
            DB::raw('SUM(daily_stats.ld_intl) AS ld_intl'),
            DB::raw('SUM(daily_stats.hrtpv_live_min) AS hrtpv_live_min'),
            DB::raw('SUM(daily_stats.hrtpv_records) AS hrtpv_records'),
            DB::raw('SUM(daily_stats.survey_live_min) AS survey_live_min'),
            DB::raw('SUM(daily_stats.digital_transaction) AS digital_transaction'),
            DB::raw('SUM(daily_stats.voice_imprint) AS voice_imprint')
        )->leftJoin(
            'brands',
            'daily_stats.brand_id',
            'brands.id'
        )->leftJoin(
            'clients',
            'clients.id',
            'brands.client_id'
        )->whereNull(
            'brands.deleted_at'
        )->whereNull(
            'clients.deleted_at'
        )->where(
            'brands.name',
            'NOT LIKE',
            'z_%'
        )->whereBetween(
            'daily_stats.stats_date',
            [
                $start_date,
                $end_date,
            ]
        )->groupBy(
            'clients.name'
        )->groupBy(
            'brands.name'
        )->groupBy(
            DB::raw('DATE_FORMAT(stats_date, "%M-%Y") ')
        )->orderBy(
            'clients.name'
        )->orderBy(
            'brands.name'
        )->orderBy(
            DB::raw('DATE_FORMAT(min(stats_date), "%m-%d-%Y")')
        )->get();

        if ($column && $direction) {
            if ($direction == 'desc') {
                $ds = $ds->sortByDesc($column)->values()->all();
            } else {
                $ds = $ds->sortBy($column)->values()->all();
            }

            foreach ($ds as &$d) {
                $d = $d->getAttributes();
            }
        }
        if ($request->get('csv')) {
            return $this->csv_response(
                array_values(
                    is_array($ds) ? $ds : $ds->toArray()
                ),
                'Report_Raw_Billing (' . $start_date . ' - ' . $end_date . ')',['Client','Brand','Period','Raw Date','Total Records','Total Live Min','Total Live Inbound Min','Total Live Outbound Min','Live English Min','Live Spanish Min','Live Good Sale','Live No Sale','Live Channel Dtd','Live Channel Tm','Live Channel Retail','Live Cc Tulsa Min','Live Cc Tahlequah Min','Live Cc Lasvegas Min','Total Ivr Min','Total Ivr Inbound Min','Total Ivr Outbound Min','Dnis Tollfree','Dnis Local','Total Eztpvs','Total Dtd Eztpvs','Total Retail Eztpvs','Total Tm Eztpvs','Eztpv Contract','Eztpv Photo','Ld Dom','Ld Intl','Hrtpv Live Min','Hrtpv Records','Survey Live Min','Digital Transaction','Voice Imprint']
            );
        }

        return $ds;
    }

    public function slaReport()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'sla-report',
                'title' => 'Report: SLA',
                'parameters' => [
                    'brands' => json_encode($this->brands_select_options())
                ]
            ]
        );
    }

    public function callTimesByInterval()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'call-times-by-interval',
                'title' => 'Report: Call Validation',
            ]
        );
    }

    public function listCallTimesByInterval(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today('America/Chicago')->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today('America/Chicago')->format('Y-m-d');
        $column = $request->column;
        $direction = $request->direction;

        $calls = Interaction::select(
            'interactions.created_at AS interval1',
            DB::raw("'' AS interval_date"),
            DB::raw("'' AS interval_time"),
            'stats_product.confirmation_code',
            'stats_product.brand_name',
            DB::raw("(CASE WHEN stats_product.market IS NULL THEN '' ELSE (CASE WHEN stats_product.market = 'Residential' THEN 'Res' ELSE 'SC' END) END) AS market"),
            'stats_product.channel',
            'stats_product.service_state',
            'stats_product.language',
            'stats_product.vendor_name',
            'stats_product.event_id',
            'tpv_staff.username AS employee_id',
            DB::raw("CONCAT(tpv_staff.first_name, ' ', tpv_staff.last_name) AS employee_name"),
            'interactions.interaction_time',
            'interactions.event_result_id',
            'interactions.parent_interaction_id',
            'interactions.interaction_type_id',
            'interactions.id',
            DB::raw("'interactions' AS data_table"),
            DB::raw("'Focus' AS platform")
        )->leftJoin(
            'events',
            'events.id',
            'interactions.event_id'
        )->leftJoin(
            'stats_product',
            'interactions.event_id',
            'stats_product.event_id'
        )->leftJoin(
            'tpv_staff',
            'interactions.tpv_staff_id',
            'tpv_staff.id'
        )->whereNotNull(
            'interactions.interaction_time'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )->whereNull('events.survey_id')
            ->whereNull('events.deleted_at')
            ->whereNull('stats_product.deleted_at')
            ->whereDate(
                'interactions.created_at',
                '>=',
                $start_date
            )->whereDate(
                'interactions.created_at',
                '<=',
                $end_date
            );

        $calls = $calls->orderBy('interactions.created_at')->groupBy('interactions.id')->get();

        $calls = $calls->groupBy('event_id')->transform(function ($icalls) {
            //Two cases digital or not
            $digital = false;
            //Here im returning all props from the first obj since they are all the same
            $tcalls = [];
            foreach ($icalls as $c) {
                if ($c->interaction_type_id == 6) {
                    $digital = true;
                    break;
                }
            }
            if ($digital) {
                foreach ($icalls as $c) {
                    if (in_array($c->interaction_type_id, [1, 2])) {
                        $tcalls[] = $c;
                    }
                }
            } else {
                foreach ($icalls as $c) {
                    if (in_array($c->interaction_type_id, [1, 2]) && is_null($c->parent_interaction_id)) {
                        //Now making sure that the returned call has the sum of all interaction_time from
                        //in + out and the last event_result_id
                        $parent_id = $c->id;
                        $time_call = $c->interaction_time;
                        $result = $c->event_result_id;
                        foreach ($icalls as $second_c) {
                            if ($parent_id == $second_c->parent_interaction_id) {
                                $time_call += $second_c->interaction_time;
                                $result = $second_c->event_result_id;
                                $parent_id = $second_c->id;
                            }
                        }
                        $c->interaction_time = round($time_call, 2);
                        $c->event_result_id = $result;
                        $tcalls[] = $c;
                    }
                }
            }

            foreach ($tcalls as $ca) {
                $ca->makeHidden(['event_id', 'interaction_type_id', 'parent_interaction_id', 'event_result_id']);
            }

            return $tcalls;
        })->flatten();

        // Round down interval times to nearest quarter hour
        for ($i = 0; $i < count($calls); $i++) {
            $t = Carbon::parse($calls[$i]['interval1']);

            $calls[$i]['interval1'] = $t->format("Y-m-d H:") . str_pad($t->minute - ($t->minute % 15), 2, '0') . ":00";
            $calls[$i]['interval_date'] = $t->format("Y-m-d");
            $calls[$i]['interval_time'] = $t->format("H:") . str_pad($t->minute - ($t->minute % 15), 2, '0') . ":00";
        }

        if ($column && $column != 'question' && $direction) {
            if ($direction == 'desc') {
                $calls = $calls->sortByDesc($column)->values();
            } else {
                $calls = $calls->sortBy($column)->values();
            }
        }

        if ($request->get('csv')) {
            return $this->csv_response(
                array_values(
                    $calls->toArray()
                ),
                'call_validation_report'
            );
        }

        return $calls->paginate(25, $request->page);
    }

    public function call_validation()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'call-validation',
                'title' => 'Report: Call Validation',
            ]
        );
    }

    public function list_call_validation(Request $request)
    {
        $start_date = $request->get('startDate') ?? Carbon::today('America/Chicago')->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today('America/Chicago')->format('Y-m-d');
        $column = $request->column;
        $direction = $request->direction;

        $calls = Interaction::select(
            'interactions.created_at as date',
            'stats_product.confirmation_code',
            'stats_product.channel',
            'stats_product.brand_name',
            'stats_product.market',
            'stats_product.language',
            'stats_product.vendor_name',
            'stats_product.event_id',
            'stats_product.office_name',
            'stats_product.sales_agent_name',
            'stats_product.tpv_agent_name',
            'interactions.event_result_id',
            'stats_product.disposition_reason',
            'interactions.interaction_time',
            'stats_product.btn',
            'interactions.parent_interaction_id',
            'interactions.interaction_type_id',
            'interactions.id',
            'stats_product.result'
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
            [1, 2, 6]
        )->whereNull('events.survey_id')
            ->whereNull('events.deleted_at')
            ->whereNull('stats_product.deleted_at')
            ->whereDate(
                'interactions.created_at',
                '>=',
                $start_date
            )->whereDate(
                'interactions.created_at',
                '<=',
                $end_date
            );

        $calls = $calls->orderBy('interactions.created_at')->groupBy('interactions.id')->get()->groupBy('event_id')->transform(function ($icalls) {
            //Two cases digital or not
            $digital = false;
            //Here im returning all props from the first obj since they are all the same
            $tcalls = [];
            foreach ($icalls as $c) {
                if ($c->interaction_type_id == 6) {
                    $digital = true;
                    break;
                }
            }
            if ($digital) {
                foreach ($icalls as $c) {
                    if (in_array($c->interaction_type_id, [1, 2])) {
                        $tcalls[] = $c;
                    }
                }
            } else {
                foreach ($icalls as $c) {
                    if (in_array($c->interaction_type_id, [1, 2]) && is_null($c->parent_interaction_id)) {
                        //Now making sure that the returned call has the sum of all interaction_time from
                        //in + out and the last event_result_id
                        $parent_id = $c->id;
                        $time_call = $c->interaction_time;
                        $result = $c->event_result_id;
                        foreach ($icalls as $second_c) {
                            if ($parent_id == $second_c->parent_interaction_id) {
                                $time_call += $second_c->interaction_time;
                                $result = $second_c->event_result_id;
                                $parent_id = $second_c->id;
                            }
                        }
                        $c->interaction_time = round($time_call, 2);
                        $c->event_result_id = $result;
                        $tcalls[] = $c;
                    }
                }
            }

            foreach ($tcalls as $ca) {
                $ca->question = null;
                $results = ['Sale', 'No Sale', 'Closed', 'Not Applicable', 'In Progress'];
                //If for some weird case event_result_id is null then the calls result will be equal to the result on stats_product
                $ca->result = (isset($ca->event_result_id) && !is_null($ca->event_result_id))
                    ? $results[($ca->event_result_id - 1)]
                    : $ca->result;

                if ($ca->result == 'No Sale') {
                    //Getting the first question of the last ones
                    $last_q = ScriptAnswer::select(
                        'script_questions.question'
                    )->leftJoin(
                        'script_questions',
                        'script_answers.question_id',
                        'script_questions.id'
                    )->where(
                        'script_answers.event_id',
                        $ca->event_id
                    )->whereNotNull(
                        'script_questions.id'
                    )->orderBy(
                        'script_answers.created_at',
                        'desc'
                    )->first();

                    if ($last_q) {
                        $ca->question = $last_q->question['english'] ?? null;
                    }
                }
                $ca->makeHidden(['event_id', 'interaction_type_id', 'parent_interaction_id', 'event_result_id', 'id']);
            }

            return $tcalls;
        })->flatten();

        if ($column && $column != 'question' && $direction) {
            if ($direction == 'desc') {
                $calls = $calls->sortByDesc($column)->values();
            } else {
                $calls = $calls->sortBy($column)->values();
            }
        }

        if ($request->get('csv')) {
            return $this->csv_response(
                array_values(
                    $calls->toArray()
                ),
                'call_validation_report'
            );
        }

        return $calls->paginate(25, $request->page);
    }

    public function call_validation_dxc(Request $request)
    {
        if (!$request->get_json && !$request->csv) {
            return view('generic-vue')->with(
                [
                    'componentName' => 'call-validation-dxc',
                    'title' => 'Report: Call Validation DXC',
                ]
            );
        }

        $start_date = $request->get('startDate') ?? Carbon::today('America/Chicago')->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::today('America/Chicago')->format('Y-m-d');
        $column = $request->column ?? 'dateCreated';
        $direction = $request->direction ?? 'desc';

        $dxc = DxcCall::select(
            'sid',
            'duration',
            'startTime',
            'endTime',
            'direction',
            'toFormatted',
            'fromFormatted',
            'forwardedFrom',
            'callerName',
            'answeredBy'
        )->whereDate(
            'dateCreated',
            '>=',
            $start_date
        )->whereDate(
            'dateCreated',
            '<=',
            $end_date
        );

        if ($column && $direction) {
            $dxc = $dxc->orderBy($column, $direction);
        }

        if ($request->csv) {
            $dxc = $dxc->get()->map(function ($elemt) {
                //Since im casting the duation value to int to save the converted value to min
                //I need to create a new property to store it
                $elemt->duration_in_min = round($elemt->duration / 60, 2);
                unset($elemt->duration);

                return $elemt;
            })->toArray();

            return $this->csv_response(
                array_values(
                    $dxc
                ),
                'Reports_Call_Validation_DXC (' . $start_date . ' - ' . $end_date . ')'
            );
        }

        return  $dxc->paginate(25);
    }

    public function sales_by_channel(Request $request)
    {
        if (!$request->ajax() && !$request->csv) {
            return view('generic-vue')->with(
                [
                    'componentName' => 'sales-by-channel',
                    'title' => 'Report: Sales and Calls by Channel',
                    'parameters' => [
                        'brands' => $this->get_search_filters()->brands,
                        'languages' => $this->get_search_filters()->languages,
                        'commodities' => $this->get_search_filters()->commodities,
                        'states' => $this->get_search_filters()->states,
                    ],
                ]
            );
        }

        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'Total';
        $direction = $request->direction ?? 'desc';

        $data = StatsProduct::select(
            DB::raw('SUM(CASE WHEN result = "Sale" THEN 1 ELSE 0 END) AS sales'),
            'stats_product.brand_name',
            'stats_product.channel'
        )->eventRange(
            $start_date,
            $end_date
        )->where(
            'stats_product_type_id',
            1
        );

        $data = $this->usual_filters($data, $request);

        $data = $data->groupBy(
            'channel',
            'stats_product.brand_name'
        )->having(
            'sales',
            '>',
            0
        )->get();

        $brands = $data->pluck('brand_name')->unique();

        $result = [];
        //Setting initial values
        foreach ($brands as $b) {
            $result[$b] = [
                'brand' => $b,
                'DTD' => 0,
                'Retail' => 0,
                'TM' => 0,
                'Care' => 0,
                'Total' => 0,
            ];
        }

        foreach ($data as $d) {
            $result[$d->brand_name][$d->channel] = $d->sales;
            $result[$d->brand_name]['Total'] += $d->sales;
        }
        $direction = ($direction == 'desc') ? SORT_DESC : SORT_ASC;
        array_multisort(array_column($result, $column), $direction, $result);

        if ($request->csv) {
            return $this->csv_response(
                array_values($result),
                'Report_Sales_By_Channel - (' . $start_date . ' - ' . $end_date . ')',['Brand','DTD','Retail','TM','Care','Total']
            );
        }

        return array_values($result);
    }

    public function calls_by_channel(Request $request)
    {
        if (!$request->ajax() && !$request->csv) {
            return view(
                'reports.calls_by_channel',
                [
                    'brands' => $this->get_search_filters()->brands,
                    'languages' => $this->get_search_filters()->languages,
                    'commodities' => $this->get_search_filters()->commodities,
                    'states' => $this->get_search_filters()->states,
                ]
            );
        }

        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'Total';
        $direction = $request->direction ?? 'desc';

        //If no left Join with events then the numbers doesnt match with other reports
        $data = Interaction::select(
            'interactions.event_id',
            'interactions.parent_interaction_id',
            'interactions.interaction_type_id',
            'stats_product.brand_name',
            'stats_product.channel'
        )->leftJoin(
            'stats_product',
            'stats_product.event_id',
            'interactions.event_id'
        )->leftJoin(
            'events',
            'events.id',
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
            foreach ($call as $c) {
                if ($c->interaction_type_id == 6) {
                    $digital = true;
                    break;
                }
            }
            if ($digital) {
                foreach ($call as $c) {
                    if (in_array($c->interaction_type_id, [1, 2])) {
                        ++$obj->calls;
                    }
                }
            } else {
                foreach ($call as $c) {
                    if (in_array($c->interaction_type_id, [1, 2]) && is_null($c->parent_interaction_id)) {
                        ++$obj->calls;
                    }
                }
            }

            $obj->channel = $call[0]->channel;
            $obj->brand_name = $call[0]->brand_name;

            return $obj;
        })->groupBy('brand_name')->transform(function ($calls) {
            //Init final result
            $r = [
                'brand' => $calls[0]->brand_name,
                'DTD' => 0,
                'Retail' => 0,
                'TM' => 0,
                'Care' => 0,
                'Total' => 0,
            ];
            foreach ($calls as $c) {
                $r[$c->channel] += $c->calls;
                $r['Total'] += $c->calls;
            }

            return $r;
        });

        if ($direction == 'desc') {
            $data = $data->sortByDesc($column)->values();
        } else {
            $data = $data->sortBy($column)->values();
        }

        if ($request->csv) {
            return $this->csv_response(
                $data->all(),
                'Report_Calls_By_Channel - (' . $start_date . ' - ' . $end_date . ')',['Brand','DTD','Retail','TM','Care','Total']
            );
        }

        return $data;
    }

    protected function usual_filters($model, Request $request = null)
    {
        $brand = $request->get('brand');
        $commodity = $request->get('commodity');
        $channel = $request->get('channel');
        $language = $request->get('language');
        $state = $request->get('state');
        $market = $request->get('market');

        if ($brand) {
            $model = $model->whereIn('stats_product.brand_id', $brand);
        }

        if ($channel) {
            $model = $model->whereIn('stats_product.channel_id', $channel);
        }

        if ($market) {
            $model = $model->whereIn('stats_product.market_id', $market);
        }

        if ($language) {
            $model = $model->whereIn('stats_product.language_id', $language);
        }

        if ($commodity) {
            $model = $model->whereIn('stats_product.commodity_id', $commodity);
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

    public function get_brand_id(string $name)
    {
        return Cache::remember(base64_encode($name), 3600, function () use ($name) {
            return Brand::select('id')->where(
                'name',
                $name
            )->first()->id;
        });
    }

    protected function get_search_filters()
    {
        $r = app()->make('stdClass');
        $r->states = Cache::remember('report_states', 1800, function () {
            return State::select('id', 'name')->get();
        });
        $r->languages = Cache::remember('report_languages', 1800, function () {
            return Language::select('id', 'language AS name')->get();
        });
        $r->commodities = Cache::remember('report_commodities', 1800, function () {
            return EventType::select('id', 'event_type AS name')
                ->whereNull('deleted_at')
                ->whereIn('id', [1, 2])
                ->get();
        });
        $r->brands = Cache::remember('report_brands', 1800, function () {
            return Brand::select('id', 'name')
                ->whereNotNull(
                    'client_id'
                )->orderBy('name')
                ->get();
        });

        return $r;
    }

    public function stats_product_validation()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'stats-product-validation',
                'title' => 'Report: Stats Product Validation',
            ]
        );
    }

    public function list_stats_product_validation(Request $request)
    {
        $start_date = Carbon::today()->setTime(7, 0, 0)->format('Y-m-d H:i:s');
        $end_date = Carbon::now()->subMinutes(30)->format('Y-m-d H:i:s');
        $column = $request->column ?? 'stats_product.brand_name';
        $direction = $request->direction ?? 'desc';

        $sp = StatsProduct::select(
            'stats_product.created_at',
            'stats_product.brand_name',
            'stats_product.id',
            'stats_product.event_id',
            'stats_product.confirmation_code',
            'event_product.id AS ep_id'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->whereNull('event_product.deleted_at')
            ->whereBetween('stats_product.created_at', [$start_date, $end_date])
            ->orderBy($column, $direction)->get()
            ->filter(function ($st) {
                return is_null($st->ep_id);
            })->values();

        if ($request->csv) {
            return $this->csv_response(
                $sp->map(function ($sp) {
                    unset($sp->event_id);
                    unset($sp->ep_id);

                    return $sp;
                })->toArray(),
                'Report_Stats_Product_Validation - ( ' . $start_date . ' - ' . $end_date . ' )'
            );
        }

        return $sp->paginate(25);
    }

    public function sms_report()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'sms-report',
                'title' => 'Report: SMS Report',
                'parameters' => [
                    'brands' => $this->get_search_filters()->brands,
                ],
            ]
        );
    }

    public function list_sms_report(Request $request)
    {
        set_time_limit(0);

        $mode = !empty($request->input('mode')) ? $request->input('mode') : 'all';
        $start_date = Carbon::parse($request->input('startDate') ?? Carbon::now('America/Chicago'), 'America/Chicago');
        $end_date = Carbon::parse($request->input('endDate') ?? Carbon::now('America/Chicago'), 'America/Chicago');
        $perPage = is_numeric($request->input('perPage')) && !empty($request->input('perPage')) ? $request->input('perPage') : 15;
        $column = !empty($request->input('column')) ? $request->input('column') : 'text_messages.created_at';
        $dir = $request->input('direction') ?? 'ASC';
        $brands = $request->input('brand');
        $export = !empty($request->input('csv'));

        $results = TextMessage::select([
            'text_messages.created_at',
            'brands.name as brand_name',
            'phone_numbers.phone_number as sent_to',
            'dnis.dnis as sent_from',
            'text_messages.content',
        ])
            ->leftJoin('brands', 'brands.id', 'text_messages.brand_id')
            ->leftJoin('phone_numbers', 'phone_numbers.id', 'text_messages.to_phone_id')
            ->leftJoin('dnis', 'dnis.id', 'text_messages.from_dnis_id');

        $results = !empty($brands) ? $results->whereIn('text_messages.brand_id', $brands) : $results;
        $results = ($mode === 'notif') ? $results->where('text_message_type_id', 2) : $results;

        $results = $results->whereDate('text_messages.created_at', '>=', $start_date)
            ->whereDate('text_messages.created_at', '<=', $end_date)
            ->orderBy($column, $dir);

        if (!$export) {
            return response()->json($results->paginate($perPage));
        }

        $filenameBase = 'sms-report-';
        $dateString = $start_date->format('Y_m_d');
        if ($start_date->notEqualTo($end_date)) {
            $dateString = $start_date->format('Y_m_d') . '_' . $end_date->format('Y_m_d');
        }

        return $this->new_csv_response($results, $filenameBase . $dateString, ['Date','Brand','Sent To','Sent From','Content']);
    }

    public function digital_report()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'digital-report',
                'title' => 'Report: Digital Report',
                'parameters' => [
                    'brands' => $this->get_search_filters()->brands,
                ],
            ]
        );
    }

    public function list_digital(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'brands.name';
        $direction = $request->direction ?? 'asc';
        $brand = $request->get('brand');

        $digital = DigitalSubmission::select(
            'brands.name',
            'events.confirmation_code',
            'events.created_at'
        );

        if (!$request->csv) {
            $digital = $digital->addSelect('digital_submission.event_id');
        }

        $digital = $digital->leftJoin(
            'events',
            'digital_submission.event_id',
            'events.id'
        )->leftJoin(
            'brands',
            'brands.id',
            'events.brand_id'
        )->whereDate(
            'events.created_at',
            '>=',
            $start_date
        )->whereDate(
            'events.created_at',
            '<=',
            $end_date
        )->whereNull(
            'events.deleted_at'
        );

        if ($brand) {
            $digital = $digital->whereIn('events.brand_id', $brand);
        }

        $digital = $digital->orderBy($column, $direction);

        if ($request->csv) {
            $digital = $digital->get()->toArray();

            return $this->csv_response(
                array_values($digital),
                'Digital_Report ( ' . $start_date . ' - ' . $end_date . ' )'
            );
        }

        return $digital->paginate(25);
    }

    public function ivr_report()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'ivr-report',
                'title' => 'Report: IVR Report',
                'parameters' => [
                    'brands' => $this->get_search_filters()->brands,
                ],
            ]
        );
    }

    public function list_ivr(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'brands.name';
        $direction = $request->direction ?? 'asc';
        $brand = $request->get('brand');

        $ivr = Interaction::select(
            'brands.name',
            'interactions.created_at',
            'vendors.vendor_label',
            'events.confirmation_code',
            'events.id as event_id',
            'interactions.interaction_time'
        );

        $ivr = $ivr->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->leftJoin(
            'brands',
            'events.brand_id',
            'brands.id'
        )->leftJoin(
            'vendors',
            function ($join) {
                $join->on('events.brand_id', '=', 'vendors.brand_id');
                $join->on('events.vendor_id', '=', 'vendors.vendor_id');
            }
        )->whereBetween('interactions.created_at', [$start_date, $end_date])
        ->whereNull(
            'interactions.deleted_at'
        )->where(
            'interactions.interaction_type_id',
            20
        );

        if ($brand) {
            $ivr = $ivr->whereIn('events.brand_id', $brand);
        }

        $ivr = $ivr->orderBy($column, $direction);

        if ($request->csv) {
            $ivr = $ivr->get()->toArray();

            return $this->csv_response(
                array_values($ivr),
                'IVR_Report ( ' . $start_date . ' - ' . $end_date . ' )',['Brand ','Date ','Vendor','Confirmation Code','Event Id','Duration']
            );
        }

        return $ivr->paginate(25);
    }

    public function questionnaire_report()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'questionnaire-report',
                'title' => 'Report: Questionnaire Report',
                'parameters' => [
                    'brands' => $this->get_search_filters()->brands,
                ],
            ]
        );
    }

    public function list_questionnaire(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'brands.name';
        $direction = $request->direction ?? 'asc';
        $brand = $request->get('brand');

        $questionnaire = DailyQuestionSubmission::select(
            'brands.name',
            'daily_question_submissions.created_at',
            'vendors.vendor_label',
            'brand_users.tsr_id',
            'users.first_name',
            'users.last_name',
            'daily_question_submissions.result'
        );

        $questionnaire = $questionnaire->leftJoin(
            'brands',
            'brands.id',
            'daily_question_submissions.brand_id'
        )->leftJoin(
            'vendors',
            function ($join) {
                $join->on('daily_question_submissions.brand_id', '=', 'vendors.brand_id');
                $join->on('daily_question_submissions.vendor_id', '=', 'vendors.vendor_id');
            }
        )->leftJoin(
            'brand_users',
            'daily_question_submissions.brand_user_id',
            'brand_users.id'
        )->leftJoin(
            'users',
            'brand_users.user_id',
            'users.id'
        )->whereDate(
            'daily_question_submissions.created_at',
            '>=',
            $start_date
        )->whereDate(
            'daily_question_submissions.created_at',
            '<=',
            $end_date
        )->where(
            'daily_question_submissions.result',
            1
        )->whereNull(
            'daily_question_submissions.deleted_at'
        );

        if ($brand) {
            $questionnaire = $questionnaire->whereIn('daily_question_submissions.brand_id', $brand);
        }

        $questionnaire = $questionnaire->orderBy($column, $direction);

        if ($request->csv) {
            $questionnaire = $questionnaire->get()->toArray();

            return $this->csv_response(
                array_values($questionnaire),
                'Questionnaire_Report ( ' . $start_date . ' - ' . $end_date . ' )',['Brand','Date','Vendor Label','Tsr ID','First Name','Last Name','Result']
            );
        }

        return $questionnaire->paginate(25);
    }

    public function invoice_details()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'invoice-details',
                'title' => 'Report: Invoice Details'
            ]
        );
    }

    public function list_invoice_details(Request $request) 
    {
        $start_date = $request->get('startDate') ?? Carbon::now()->format('Y-m-d');
        $end_date = $request->get('endDate') ?? Carbon::now()->format('Y-m-d');
        $column = $request->column ?? 'brand_id';
        $direction = $request->direction ?? 'asc';
      
        $details = Invoice::select(
            'invoices.id',
            'invoices.brand_id',
            'brands.name AS brand_name',
            'invoices.invoice_bill_date',
            'invoices.invoice_start_date',
            'invoices.invoice_end_date',
            'invoices.invoice_due_date',
            'invoices.account_number',
            'invoices.invoice_number',
            'invoice_items.quantity',
            'invoice_items.invoice_desc_id',
            'invoice_items.rate',
            'invoice_items.note',
            'invoice_items.total'
        )->join(
            'invoice_items',
            'invoices.id',
            'invoice_items.invoice_id'
        )->join(
            'brands',
            'invoices.brand_id',
            'brands.id'
        )->whereDate(
            'invoices.invoice_start_date',
            $start_date
        )->whereDate(
            'invoices.invoice_end_date',
            $end_date
        )->get();

        if ($column && $direction) {
            if ($direction == 'desc') {
                $details = $details->sortByDesc($column)->values()->all();
            } else {
                $details = $details->sortBy($column)->values()->all();
            }

            foreach ($details as &$d) {
                $d = $d->getAttributes();
            }
        }

        if ($request->get('csv')) {
            return $this->csv_response(
                array_values(
                    is_array($details) ? $details : $details->toArray()
                ),
                'Invoice Details Report (' . $start_date . ' - ' . $end_date . ')'
            );
        }

        return $details;
    }

    public function call_validation_dxc_legacy()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'call-validation-dxc-legacy',
                'title' => 'Report: Call Validation DXC from Legacy',
            ]
        );
    }

    public function list_call_validation_dxc_legacy(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'brand';
        $direction = $request->direction ?? 'asc';

        $dxc_legacy = DB::table(
            'dxc_legacy'
        )->select(
            'brand',
            'insert_at',
            'tpv_type',
            'language',
            'call_segments',
            'confirmation_code',
            'cic_call_id_keys',
            'call_time'
        )->whereDate(
            'dxc_legacy.insert_at',
            '>=',
            $start_date
        )->whereDate(
            'dxc_legacy.insert_at',
            '<=',
            $end_date
        );

        if ($request->tpvType) {
            $dxc_legacy = $dxc_legacy->where(
                'dxc_legacy.tpv_type',
                $request->tpvType
            );
        }

        if ($request->language) {
            $dxc_legacy = $dxc_legacy->where(
                'dxc_legacy.language',
                $request->language
            );
        }

        if ($request->brand) {
            $dxc_legacy = $dxc_legacy->where(
                'dxc_legacy.brand',
                $request->brand
            );
        }

        $dxc_legacy = $dxc_legacy->orderBy($column, $direction);

        if ($request->csv) {
            //Its necessary to use map and transform result from stdClass to array
            $dxc_legacy = $dxc_legacy->get()->map(function ($call) {
                return (array) $call;
            })->toArray();

            return $this->csv_response(
                array_values($dxc_legacy),
                'Report_DXC_Calls_from_Legacy ( ' . $start_date . ' - ' . $end_date . ' )'
            );
        }

        return $dxc_legacy->paginate(25);
    }

    public function dxc_brands()
    {
        return Cache::remember('dxc_brands', 3600, function () {
            return DB::table(
                'dxc_legacy'
            )->select(
                'dxc_legacy.brand'
            )->groupBy('dxc_legacy.brand')->get();
        });
    }

    public function report_contracts()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'report-contract',
                'parameters' => [
                    'brands' => $this->get_brands(),
                    'vendors' => $this->get_vendors(),
                    'channels' => $this->get_channels(),
                    'languages' => $this->get_languages(),
                    'states' => $this->get_states(),
                ],
            ]
        );
    }

    public function list_contracts(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'name';
        $direction = $request->direction ?? 'asc';
        $upload_type_id = $request->upload_type_id ?? 3;

        $docs = EztpvDocument::select(
            'brands.name',
            'channels.channel',
            'eztpv_documents.event_id',
            'eztpv_documents.created_at',
            'states.state_abbrev',
            'events.confirmation_code',
            'languages.language',
            'b.name AS vendor_name'
        )->leftJoin(
            'events',
            'events.id',
            'eztpv_documents.event_id'
        )->leftJoin(
            'channels',
            'channels.id',
            'eztpv_documents.channel_id'
        )->leftJoin(
            'brands',
            'brands.id',
            'events.brand_id'
        )->leftJoin(
            'states',
            'states.id',
            'eztpv_documents.state_id'
        )->leftJoin(
            'brands AS b',
            'b.id',
            'events.vendor_id'
        )->leftJoin(
            'languages',
            'languages.id',
            'events.language_id'
        )->leftJoin(
            'uploads',
            'eztpv_documents.uploads_id',
            'uploads.id'
        )->whereDate(
            'events.created_at',
            '>=',
            $start_date
        )->whereDate(
            'events.created_at',
            '<=',
            $end_date
        )->where(
            'uploads.upload_type_id',
            $upload_type_id
        )->whereNull(
            'uploads.deleted_at'
        );

        if ($request->brand) {
            $docs = $docs->whereIn(
                'events.brand_id',
                $request->brand
            );
        }

        if ($request->vendor) {
            $docs = $docs->whereIn(
                'events.vendor_id',
                $request->vendor
            );
        }

        if ($request->language) {
            $docs = $docs->whereIn(
                'events.language_id',
                $request->language
            );
        }

        if ($request->channel) {
            $docs = $docs->whereIn(
                'eztpv_documents.channel_id',
                $request->channel
            );
        }

        if ($request->state) {
            $docs = $docs->whereIn(
                'eztpv_documents.state_id',
                $request->state
            );
        }

        $docs = $docs->whereNull(
            'events.deleted_at'
        )->orderBy($column, $direction);

        if ($request->csv) {
            $docs = $docs->get()->each->makeHidden(['event_id'])->toArray();

            return $this->csv_response(
                array_values($docs),
                'Report_Contracts ( ' . $start_date . ' - ' . $end_date . ' )',['Brand','Channel','Created At','State','Confirmation Code','Language','Vendor']
            );
        }

        return $docs->paginate(25);
    }

    public function eztpv_by_channel()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'eztpv-by-channel',
                'parameters' => [
                    'brands' => $this->get_brands(),
                    'vendors' => $this->get_vendors(),
                    'channels' => $this->get_channels(),
                    'languages' => $this->get_languages(),
                ],
            ]
        );
    }

    public function list_eztpv_by_channel(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'eztpvs.created_at';
        $direction = $request->direction ?? 'desc';
        $channel = $request->channel;
        $brand = $request->brand;
        $language = $request->language;
        $vendor = $request->vendor;

        $data = Eztpv::select(
            'brands.name AS brand_name',
            'b.name AS vendor_name',
            'events.id AS event_id',
            'eztpvs.created_at',
            'events.confirmation_code',
            'channels.channel',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) as sales_agent_name'),
            'brand_users.tsr_id',
            'languages.language'
        )->leftJoin(
            'brands',
            'eztpvs.brand_id',
            'brands.id'
        )->leftJoin(
            'events',
            'eztpvs.id',
            'events.eztpv_id'
        )->leftJoin(
            'brand_users',
            'events.sales_agent_id',
            'brand_users.id'
        )->leftJoin(
            'users',
            'brand_users.user_id',
            'users.id'
        )->leftJoin(
            'channels',
            'events.channel_id',
            'channels.id'
        )->leftJoin(
            'brands AS b',
            'b.id',
            'events.vendor_id'
        )->leftJoin(
            'languages',
            'languages.id',
            'events.language_id'
        );

        if ($brand) {
            $data = $data->whereIn(
                'eztpvs.brand_id',
                $brand
            );
        }

        if ($channel) {
            $data = $data->whereIn(
                'events.channel_id',
                $channel
            );
        }

        if ($language) {
            $data = $data->whereIn(
                'events.language_id',
                $language
            );
        }

        if ($vendor) {
            $data = $data->whereIn(
                'events.vendor_id',
                $vendor
            );
        }

        $data = $data->whereBetween(
            'events.created_at',
            [
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59',
            ]
        )->whereNull(
            'events.deleted_at'
        )->orderBy($column, $direction);

        if ($request->csv) {
            $data = $data->get()->each->makeHidden(['event_id'])->toArray();

            return $this->csv_response(
                array_values($data),
                'Report_EZTPV_By_Channel ( ' . $start_date . ' - ' . $end_date . ' )'
            );
        }

        return $data->paginate(25);
    }

    public function api_submissions()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'api-submissions',
                'parameters' => [
                    'brands' => $this->get_brands(),
                    'vendors' => $this->get_vendors(),
                    'channels' => $this->get_channels(),
                    'languages' => $this->get_languages(),
                ],
            ]
        );
    }

    public function list_api_submissions(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'api_submissions.created_at';
        $direction = $request->direction ?? 'desc';
        $brand = $request->brand;

        $data = ApiSubmission::select(
            'brands.name AS brand_name',
            'events.id AS event_id',
            'api_submissions.created_at',
            'events.confirmation_code'
        )->leftJoin(
            'brands',
            'api_submissions.brand_id',
            'brands.id'
        )->leftJoin(
            'events',
            'api_submissions.event_id',
            'events.id'
        );

        if ($brand) {
            $data = $data->whereIn(
                'api_submissions.brand_id',
                $brand
            );
        }

        $data = $data->whereBetween(
            'events.created_at',
            [
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59',
            ]
        )->whereNull(
            'events.deleted_at'
        )->orderBy($column, $direction);

        if ($request->csv) {
            $data = $data->get()->each->makeHidden(['event_id'])->toArray();

            return $this->csv_response(
                array_values($data),
                'Report_API_Submissions ( ' . $start_date . ' - ' . $end_date . ' )'
            );
        }

        return $data->paginate(25);
    }

    public function web_enroll_submissions()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'web-enroll-submissions',
                'parameters' => [
                    'brands' => $this->get_brands(),
                    'vendors' => $this->get_vendors(),
                    'channels' => $this->get_channels(),
                    'languages' => $this->get_languages(),
                ],
            ]
        );
    }

    public function list_web_enroll_submissions(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'web_enroll_submissions.created_at';
        $direction = $request->direction ?? 'desc';
        $channel = $request->channel;
        $brand = $request->brand;
        $language = $request->language;
        $vendor = $request->vendor;

        $data = WebEnrollSubmission::select(
            'brands.name AS brand_name',
            'events.id AS event_id',
            'web_enroll_submissions.created_at',
            'events.confirmation_code',
            'channels.channel',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) as sales_agent_name'),
            'brand_users.tsr_id',
            'languages.language'
        )->leftJoin(
            'brands',
            'web_enroll_submissions.brand_id',
            'brands.id'
        )->leftJoin(
            'events',
            'web_enroll_submissions.event_id',
            'events.id'
        )->leftJoin(
            'brand_users',
            'events.sales_agent_id',
            'brand_users.id'
        )->leftJoin(
            'users',
            'brand_users.user_id',
            'users.id'
        )->leftJoin(
            'channels',
            'events.channel_id',
            'channels.id'
        )->leftJoin(
            'languages',
            'languages.id',
            'events.language_id'
        );

        if ($brand) {
            $data = $data->whereIn(
                'web_enroll_submissions.brand_id',
                $brand
            );
        }

        if ($channel) {
            $data = $data->whereIn(
                'events.channel_id',
                $channel
            );
        }

        if ($language) {
            $data = $data->whereIn(
                'events.language_id',
                $language
            );
        }

        if ($vendor) {
            $data = $data->whereIn(
                'events.vendor_id',
                $vendor
            );
        }

        $data = $data->whereBetween(
            'events.created_at',
            [
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59',
            ]
        )->whereNull(
            'events.deleted_at'
        )->orderBy($column, $direction);

        if ($request->csv) {
            $data = $data->get()->each->makeHidden(['event_id'])->toArray();

            return $this->csv_response(
                array_values($data),
                'Report_Web_Enroll_Submissions ( ' . $start_date . ' - ' . $end_date . ' )',['Brand','Created At','Confirmation Code','Channel','Sales Agent Name','Rep ID','Language']
            );
        }

        return $data->paginate(25);
    }

    public function search_user_info_from_audits()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'search-user-info',
                'title' => 'Report: Search User Info',
            ]
        );
    }

    public function missing_recording_report()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'missing-recording-report',
                'title' => 'Report: Missing Recordings',
            ]
        );
    }

    public function list_missing_recordings()
    {
        $query = Interaction::select(
            'interactions.created_at',
            'interactions.event_id',
            'stats_product.confirmation_code',
            'stats_product.result',
            'stats_product.brand_name',
            DB::raw('count(*) as count_missing'),
            DB::raw('CONCAT("' . config('app.urls.mgmt') . '/events/",interactions.event_id) as event_page')
        )->leftJoin(
            'recordings',
            'recordings.interaction_id',
            'interactions.id'
        )->leftJoin(
            'stats_product',
            'stats_product.event_id',
            'interactions.event_id'
        )->whereNull(
            'interactions.deleted_at'
        )->whereNull(
            'stats_product.deleted_at'
        )->whereNull(
            'recordings.id'
        )->whereNotNull(
            'interactions.event_id'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )->where(
            'interactions.session_id',
            'NOT LIKE',
            'DEV%'
        )->where(
            'interactions.interaction_time',
            '>',
            0
        )->groupBy(
            'interactions.event_id'
        )->orderBy(
            'interactions.created_at',
            'DESC'
        );

        if (request()->export != null) {
            return $this->new_csv_response($query, 'missing_recording_report',['Date','Event Id','Confirmation Code','Result','Brand','Missing','Event Page']);
        }

        return $query->paginate();
    }

    public function missing_contracts()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'missing-contracts',
                'title' => 'Report: Missing Contracts',
                'parameters' => [
                    'brands' => $this->allowed_contract_brands(),
                    'states' => $this->get_states(),
                ],
            ]
        );
    }

    /**
     * Returns a Paginate object with a collection of all missing contracts with at least an hour old.
     *
     * @return object
     */
    public function list_missing_contracts(Request $request)
    {
        $brand = $request->brand;
        $state = $request->state;
        $direction = $request->direction ?? 'desc';
        $column = $request->column ?? 'stats_product.event_created_at';
        $contracts = DB::table('stats_product')->select(
            'stats_product.brand_name',
            'stats_product.service_state',
            'stats_product.event_created_at',
            'stats_product.sales_agent_id AS user_id',
            'stats_product.confirmation_code',
            'stats_product.ip_address AS ip_addr',
            'stats_product.event_id'
        );

        if ($brand) {
            $contracts = $contracts->whereIn(
                'stats_product.brand_id',
                $brand
            );
        } else {
            $contracts = $contracts->whereIn(
                'stats_product.brand_id',
                $this->allowed_contract_brands()->pluck('id')
            );
        }

        if ($state) {
            $contracts = $contracts->whereIn(
                'stats_product.service_state',
                $this->get_state_abbrev($state)
            );
        }

        // ignore "no contract"-type contracts
        $contracts = $contracts->join(
            'eztpvs',
            'stats_product.eztpv_id',
            'eztpvs.id'
        )->where(
            'eztpvs.contract_type',
            '!=',
            0
        );

        $contracts = $contracts->where(
            'stats_product.result',
            'Sale'
        )->where(
            'stats_product.eztpv_initiated',
            1
        )->where(
            'stats_product.event_created_at',
            '<',
            Carbon::now()->subHour()
        )->whereNull(
            'stats_product.deleted_at'
        )->whereNotIn('stats_product.eztpv_id', function ($query) {
            $query->select('eztpv_documents.eztpv_id')
                ->from('eztpv_documents')
                ->leftJoin('uploads', 'uploads.id', 'eztpv_documents.uploads_id')
                ->where('uploads.upload_type_id', 3)
                ->whereNull('eztpv_documents.deleted_at')
                ->whereNull('uploads.deleted_at');
        })
            ->groupBy('stats_product.event_id')
            ->orderBy(
                $column,
                $direction
            );

        $contracts = $contracts->get()->filter(function ($c) {
            switch ($c->brand_name) {
                case 'Clearview Energy':
                    if ($c->service_state !== 'MD') {
                        return;
                    }
                    break;
                case 'Great American Power, LLC':
                    if (!in_array($c->service_state, ['IL', 'MD', 'OH'])) {
                        return;
                    }
                    break;
                case 'Indra Energy':
                    if (!in_array($c->service_state, ['IL', 'MD', 'PA', 'MA'])) {
                        return;
                    }
                    break;
                default:
                    return true;
                    break;
            }

            return true;
        });

        if ($request->csv) {
            $csvData = $contracts->map(function ($contract) {
                return [
                    'Brand '         => $contract->brand_name,
                    'Date'   => $contract->event_created_at,
                    'User ID'     => $contract->user_id,
                    'Confirmation Code'  => $contract->confirmation_code,
                    'IP Address'         => $contract->ip_addr,
                ];
            })->toArray();
                return $this->csv_response(array_values($csvData), 'Report_Missing_Contracts', ['Brand ','Event Created At','User ID','Confirmation Code','IP Address']);
        }
        return $contracts->paginate(25);
    }

    public function get_state_abbrev(array $states)
    {
        return State::select('state_abbrev')->whereIn('id', $states)->get();
    }

    public function allowed_contract_brands()
    {
        return Cache::remember(
            'reports_allowed_contract_brands',
            1800,
            function () {
                return Brand::select('id', 'name')
                    ->where('name', 'Clearview Energy')
                    ->orWhere('name', 'Great American Power, LLC')
                    ->orWhere('name', 'Indra Energy')
                    ->orWhere('name', 'Kiwi Energy')
                    ->orWhere('name', 'Median Energy Corp')
                    ->orWhere('name', 'RPA Energy')
                    ->orWhere('name', 'Spring Power & Gas')
                    ->get();
            }
        );
    }

    public function multiple_sale_events()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'multiple-sale-events',
                'parameters' => [
                    'brands' => json_encode($this->get_brands()),
                ],
            ]
        );
    }

    public function list_multiple_sale_events(Request $request)
    {
        $brand = $request->brand;
        $search = $request->search;
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');

        $events = Event::select(
            'events.created_at',
            'brands.name AS brand_name',
            'events.confirmation_code',
            'events.id'
        )->leftJoin(
            'brands',
            'events.brand_id',
            'brands.id'
        );
        if ($brand) {
            $events = $events->where(
                'brand_id',
                $brand
            );
        }
        if ($search) {
            $events = $events->where('events.confirmation_code', $search);
        }
        $events = $events->whereIn('events.confirmation_code', function ($query) use ($start_date, $end_date, $brand, $search) {
            $query = $query->select('events.confirmation_code')
                ->from('events')
                ->leftJoin(
                    'interactions',
                    'interactions.event_id',
                    'events.id'
                )->whereDate(
                    'events.created_at',
                    '>=',
                    $start_date
                )->whereDate(
                    'events.created_at',
                    '<=',
                    $end_date
                )->where(
                    'interactions.event_result_id',
                    1
                )->whereNull('events.deleted_at')
                ->whereNull('interactions.deleted_at');
            if ($brand) {
                $query = $query->whereIn(
                    'brand_id',
                    $brand
                );
            }
            if ($search) {
                $query = $query->where('events.confirmation_code', $search);
            }
            $query = $query->groupBy('interactions.event_id')->havingRaw('COUNT(*) > 1');
        })->orderBy('events.created_at', 'DESC');

        if ($request->csv) {
            return $this->csv_response(
                $events->get()->each->makeHidden('id')->toArray(),
                'Report_Multiple_Sale_Events ( ' . $start_date . ' - ' . $end_date . ' )',['Date','Brand','Confirmation Code']
            );
        }

        return $events->paginate(25);
    }

    public function product_disassociated_contracts()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'product-disassociated-contracts',
                'parameters' => [
                    'brands' => json_encode($this->get_brands()),
                ],
            ]
        );
    }

    public function list_product_disassociated_contracts(Request $request)
    {
        $brand = $request->brand;
        $direction = $request->direction ?? 'asc';
        $column = $request->column ?? 'brands.name';

        $contracts = BrandEztpvContract::select(
            'brands.name AS brand_name',
            'products.name AS product_name',
            'products.deleted_at AS product_deleted_at',
            'brand_eztpv_contracts.contract_pdf AS contract_file',
            'brand_eztpv_contracts.id AS contract_id',
            'products.id AS product_id'
        )
            ->join(
                'products',
                'brand_eztpv_contracts.product_id',
                'products.id'
            )
            ->join(
                'brands',
                'brand_eztpv_contracts.brand_id',
                'brands.id'
            )
            ->whereNotNull(
                'brand_eztpv_contracts.product_id'
            )
            ->whereNotNull(
                'products.deleted_at'
            );

        if (isset($brand)) {
            $contracts = $contracts->where(
                'brand_eztpv_contracts.brand_id',
                $brand
            );
        }

        $contracts = $contracts->orderBy(
            $column,
            $direction
        )
            ->get();

        if ($request->csv) {
            return $this->csv_response($contracts->toArray(), 'Report_Product_Disassociated_Contracts',['Brand ','Product','Product Deleted At','Contract File','Contract Id','Product Id']);
        }

        return $contracts->paginate(25);
    }

    public function reprocessEztpvs($product_id)
    {
        Eztpv::join(
            'events',
            'events.eztpv_id',
            'eztpvs.id'
        )
            ->join(
                'event_product',
                'event_product.event_id',
                'events.id'
            )
            ->join(
                'rates',
                'event_product.rate_id',
                'rates.id'
            )
            ->where(
                'rates.product_id',
                $product_id
            )
            ->where(
                'eztpvs.processed',
                3
            )
            ->update([
                'processed' => 0
            ]);

        return redirect()->route('reports.product_disassociated_contracts');
    }

    public function agent_stats_daily(Request $request)
    {
        $date = $request->startDate ? Carbon::parse($request->startDate . ' 00:00:00', 'America/Chicago') : (Carbon::now('America/Chicago')->yesterday());

        return view('reports.genericReport')->with([
            'title' => 'Daily Agent Stats',
            'mainUrl' => route('reports.agent_stats_daily'),
            'url' => route('reports.list_agent_stats_daily'),
            'vendors' => '[]',
            'languages' => '[]',
            'commodities' => '[]',
            'startDate' => $date->toDateString(),
            'endDate' => $date->toDateString(),
            'hiddenColumns' => ['id', 'tpv_staff_id'],
            'showExportButton' => true,
            'searchOptions' => ['search' => true]
        ]);
    }

    public function list_agent_stats_daily(Request $request)
    {
        $direction = $request->direction ?? 'asc';
        $column = $request->column ?? 'username';
        $search = $request->search;
        $date = $request->startDate ?? Carbon::now('America/Chicago')->yesterday()->format('Y-m-d');
        $perPage = 15;
        if ($request->has('perPage') && is_numeric($request->perPage)) {
            $perPage = intval($request->perPage);
        }

        $results = StatsTpvAgent::select(
            [
                'tpv_staff.username',
                'tpv_staff.payroll_id',
                'stats_tpv_agent.tpv_staff_id',
                DB::raw('CONCAT(tpv_staff.first_name, " ", tpv_staff.last_name) as agent_name'),
                'stats_tpv_agent.total_calls',
                'stats_tpv_agent.total_hours',
                'stats_tpv_agent.billable_time',
                'stats_tpv_agent.calls_per_hour',
                'stats_tpv_agent.productive_occupancy',
                'stats_tpv_agent.avg_revenue_per_payroll_hour',
                'stats_tpv_agent.agent_cost'
            ]
        )
            ->join('tpv_staff', 'tpv_staff.id', 'stats_tpv_agent.tpv_staff_id')
            ->where('interval', 0)
            ->where('stats_date', $date);

        if (!empty($search)) {
            $results = $results->where(function ($q) use ($search) {
                $q->where('tpv_staff.username', 'like', '%' . $search . '%')
                    ->orWhere('tpv_staff.payroll_id', 'like', '%' . $search . '%')
                    ->orWhere('tpv_staff.first_name', 'like', '%' . $search . '%')
                    ->orWhere('tpv_staff.last_name', 'like', '%' . $search . '%');
            });
        }

        $results = $results->orderBy($column, $direction);

        if ($request->csv) {
            return $this->csv_response($results->get()->toArray(), 'agent-daily-stats-' . $date);
        }

        return $results->paginate($perPage);
    }

    public function finalized_sales_for_digital_customers()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'finalized-sales-for-digital-customers-report',
                'title' => 'Report: Finalized Sales for Digital Customers Report',
                'parameters' => [
                    'brands' => $this->get_search_filters()->brands,
                ],
            ]
        );
    }

    public function list_finalized_sales_for_digital_customers(Request $request)
    {
        $start_date = $request->startDate ?? Carbon::today()->format('Y-m-d');
        $end_date = $request->endDate ?? Carbon::today()->format('Y-m-d');
        $column = $request->column ?? 'brand_name';
        $direction = $request->direction ?? 'asc';
        $brand = $request->get('brand');
    
        $sales = StatsProduct::select(
            'event_id',
            'event_created_at',
            'confirmation_code',
            'result',
            'interaction_type',
            'channel',
            'disposition_label',
            'disposition_reason',
            'brand_name',
            'vendor_name',
            'office_name',
            'sales_agent_name',
            'sales_agent_rep_id',
            'service_state'
        )->join('eztpvs', 'stats_product.eztpv_id', '=', 'eztpvs.id')
        ->whereBetween('stats_product.event_created_at', ["$start_date 00:00:00", "$end_date 23:59:59"])
        ->whereNotNull('stats_product.eztpv_id')
        ->where('eztpvs.finished', 0);          
        if ($brand) {
            $sales->whereIn('stats_product.brand_id', $brand);
        };
            
        $sales->orderBy($column, $direction);

        if ($request->csv) {
            $salesData = $sales->get()->toArray();
            $filename = 'Finalized_Sales_for_Digital_Customers_Report (' . $start_date . ' - ' . $end_date . ')';
            return $this->csv_response(array_values($salesData), $filename, [
                'Event Id',
                'Event Created at',
                'Confirmation Code',
                'Result',
                'Interaction Type',
                'Channel',
                'Disposition Label',
                'Disposition Reason',
                'Brand Name',
                'Vendor Name',
                'Office Name',
                'Sales Agent Name',
                'Sales Agent Rep Id',
                'Service State'
            ]);
            
        }

        return $sales->paginate(25);
    }
    
}
