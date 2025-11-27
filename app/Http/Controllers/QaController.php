<?php

namespace App\Http\Controllers;

use Twilio\Values;
use Twilio\Rest\Client;
use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use App\Models\Vendor;
use App\Models\OutboundCallQueue;
use App\Models\Interaction;
use App\Models\EventSource;
use App\Models\EventProduct;
use App\Models\EventNote;
use App\Models\Event;
use App\Models\BrandUser;
use App\Models\Brand;

class QaController extends Controller
{
    public static function event_reprocess(Event $event, $capture_output = false)
    {
        $output = null;
        if ($capture_output) {
            $output = new BufferedOutput();
        }
        $event->synced = 0;
        $event->save();

        // Update for live enrollment
        Interaction::where('event_id', $event->id)->get()->each(function ($i) {
            $i->enrolled = null;
            $i->save();
        });

        EventProduct::where('event_id', $event->id)->get()->each(function ($i) {
            $i->live_enroll = null;
            $i->save();
        });

        ob_start();

        try {
            Artisan::call('stats:product', [
                '--confirmationCode' => $event->confirmation_code,
                '--brand' => $event->brand_id,
            ], $output);

            // Resubmit live enroll
            $brand = Brand::find($event->brand_id);
            if ($brand) {
                switch ($brand->name) {
                    case 'Atlantic Energy':
                        Artisan::call('atlantic:live:enrollments', [
                            '--skipRecordingCheck' => true,
                            '--confirmation_code' => $event->confirmation_code,
                        ], $output);
                        break;
                        
                    case 'Clean Choice Energy':
                        Artisan::call('cleanchoice:live:enrollments', [
                            '--confirmation-code' => $event->confirmation_code,
                            '--overwrite' => true,
                        ], $output);
                        break;

                    case 'CleanSky Energy':
                    case 'Titan Energy': // old name
                        Artisan::call('titan:live:enrollments', [
                            '--confirmation_code' => $event->confirmation_code,
                        ], $output);
                        break;                    

                    case 'Iberdrola': // staging
                    case 'Iberdrola Texas': // production
                        Artisan::call('iberdrola:enrollment', [
                            '--ignore_live_enroll' => true,
                            '--confirmation_code' => $event->confirmation_code,
                        ], $output);
                        break;

                    case 'Inspire Energy':
                        Artisan::call('inspire:live:enrollments', [
                            '--confirmation_code' => $event->confirmation_code,
                        ], $output);
                        break;

                    case 'NRG':
                        Artisan::call('run:vendor:live:enrollments', [
                            '--brand' => $event->brand_id,
                            '--confirmationCode' => $event->confirmation_code
                        ], $output);

                    default:
                        $params = [
                            '--brand' => $event->brand_id,
                            '--confirmation_code' => $event->confirmation_code,
                            '--overwrite' => true,
                        ];

                        Artisan::call(
                            'live:enrollments',
                            $params,
                            $output
                        );
                }

                Artisan::call(
                    'brand:file:sync',
                    [
                        '--ignoreSynced' => true,
                        '--force' => true,
                        '--debug' => true,
                        '--confirmation_code' => $event->confirmation_code,
                    ],
                    $output
                );
            }
        } catch (\Exception $e) {
            info('Error doing event_reprocess: ' . $e->getMessage(), [$e]);
            return false;
        }
        ob_end_clean();

        if ($output !== null) {
            return $output->fetch();
        }
        return false;
    }

    public function update_event_sales_rep(Request $request, Event $event)
    {
        try {
            $sales_agent_id = $request->input('agent_id');
            $vendor_id = $request->input('vendor_id');
            $office_id = $request->input('office_id');

            $event->sales_agent_id = $sales_agent_id;
            $event->vendor_id = $vendor_id;
            $event->office_id = $office_id;

            $event->save();

            $note = new EventNote();
            $note->tpv_staff_id = Auth::id();
            $note->event_id = $event->id;
            $note->internal_only = 1;
            $note->notes = 'Added Sales Agent information to event';
            $note->save();

            self::event_reprocess($event);

            return response()->json(['errors' => false]);
        } catch (\Exception $e) {
            info('error saving sales agent', [$e]);
            return response()->json(['errors' => $e->getMessage()]);
        }
    }

    public function tsr_id_lookup(Request $request)
    {
        $brand_id = $request->input('brand');
        $label = $request->input('search');

        $ret = Cache::remember(
            'lookup-rep-by-label-' . $brand_id . '-' . $label,
            15, // 1 minute
            function () use ($brand_id, $label) {
                return BrandUser::select(
                    'brand_users.id',
                    'users.first_name',
                    'users.last_name',
                    'brand_users.employee_of_id',
                    'brand_users.works_for_id',
                    'brand_user_offices.office_id',
                    'brand_users.tsr_id',
                    'brand_users.language_id',
                    'brands.name AS employer',
                    'brand_users.channel_id',
                    'email_addresses.email_address',
                    'eztpv_config.config',
                    DB::raw('phone_numbers.phone_number as phone'),
                    DB::raw('phone_numbers.id as phone_id'),
                    'brand_user_offices.deleted_at AS brand_user_office_deleted',
                    'offices.deleted_at AS office_deleted'
                )->join(
                    'users',
                    'users.id',
                    'brand_users.user_id'
                )->leftJoin(
                    'phone_number_lookup',
                    function ($join) {
                        $join->on('users.id', 'phone_number_lookup.type_id')
                            ->where('phone_number_lookup.phone_number_type_id', 1)
                            ->whereNull('phone_number_lookup.deleted_at');
                    }
                )->leftJoin(
                    'phone_numbers',
                    'phone_number_lookup.phone_number_id',
                    'phone_numbers.id'
                )->leftJoin(
                    'email_address_lookup',
                    function ($join) {
                        $join->on('users.id', 'email_address_lookup.type_id')
                            ->where('email_address_lookup.email_address_type_id', 1)
                            ->whereNull('email_address_lookup.deleted_at');
                    }
                )->leftJoin(
                    'email_addresses',
                    'email_address_lookup.email_address_id',
                    'email_addresses.id'
                )->leftJoin(
                    'brands',
                    'brand_users.employee_of_id',
                    'brands.id'
                )->leftJoin(
                    'brand_user_offices',
                    'brand_users.id',
                    'brand_user_offices.brand_user_id'
                )->leftJoin(
                    'offices',
                    'brand_user_offices.office_id',
                    'offices.id'
                )->leftJoin(
                    'eztpv_config',
                    'eztpv_config.office_id',
                    'offices.id'
                )->where(
                    'brand_users.status',
                    1
                )->whereNull(
                    'brands.deleted_at'
                )->where(
                    'brand_users.tsr_id',
                    $label
                )->where(
                    'brand_users.works_for_id',
                    $brand_id
                )->whereNull(
                    'users.deleted_at'
                )->whereNull(
                    'brand_user_offices.deleted_at'
                )->whereNull(
                    'phone_number_lookup.deleted_at'
                )->whereNull(
                    'email_address_lookup.deleted_at'
                )->groupBy('brand_users.id')->get()->map(
                    function ($item) {
                        $v = Vendor::select(
                            'vendors.id',
                            'vendors.deleted_at'
                        )->where(
                            'brand_id',
                            $item->works_for_id
                        )->where(
                            'vendor_id',
                            $item->employee_of_id
                        )->withTrashed()->first();
                        if ($v !== null) {
                            $item->vendors_id = $v->id;
                            $item->vendor_deleted_at = $v->deleted_at;
                        }

                        $item->config = json_decode($item->config, true);

                        return $item;
                    }
                );
            }
        );

        return response()->json($ret);
    }

    public function initiateReTPV(Request $request, $event)
    {
        $retpv = EventSource::where('source', 'ReTPV')->first();
        $script = $request->input('script');
        if (!empty($script)) {
            $e = Event::find($event);
            if ($e) {
                Cache::forget('agents-event-' . $event);
                info(Auth::id() . ' ReTPV updating script on event ' . $event . ' from ' . ($e->script_id !== null ? $e->script_id : 'null') . ' to ' . $script);
                $e->script_id = $script;
                $e->save();
            }
        }
        $ocq = new OutboundCallQueue();
        $ocq->event_id = $event;
        $ocq->event_source_id = $retpv->id;
        $ocq->save();

        return response()->json(['message' => 'ok']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
    }

    public function showSearchCallStatus()
    {
        $date = request()->input('date');
        $to = CleanPhoneNumber(request()->input('to'));
        $type = request()->input('type');
        switch ($type) {
            case 'call':
            case 'sms':
                break;

            default:
                $type = 'call';
        }
        if ($date == null) {
            $date = (new CarbonImmutable($date, 'UTC'))->startOfDay()->format('Y-m-d');
        }
        if ($to !== null) {
            $client = new Client(
                config('services.twilio.account'),
                config('services.twilio.auth_token')
            );
            switch ($type) {
                case 'call':
                    $calls = $client->calls->read(['to' => $to, 'startTimeAfter' => $date]);
                    break;

                case 'sms':
                    $calls = $client->messages->read(['to' => $to, 'dateSentAfter' => $date]);
                    break;
            }
        } else {
            $calls = [];
        }

        return view('qa.call-status-search')->with(['calls' => $calls, 'date' => $date, 'phone' => $to, 'type' => $type]);
    }

    public function searchOutCallsByDxcId($dxcid, $date)
    {
        set_time_limit(0);
        $cdate = new CarbonImmutable($date, 'UTC');
        $yesterday = $cdate->subDay()->startOfDay();
        $tomorrow = $cdate->addDay()->endOfDay();
        $client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $workspace_id = config('services.twilio.workspace');
        /*$workspace = $client->taskrouter
            ->workspaces($workspace_id);*/

        $calls = $client->calls->read(['from' => 'client:' . $dxcid, 'status' => 'completed', 'startTimeAfter' => $yesterday, 'endTimeBefore' => $tomorrow]);
        $out = [];
        foreach ($calls as $call) {
            $item = $call->toArray();

            $r = [];
            $recordings = $call->recordings->read();
            foreach ($recordings as $recording) {
                $r[] = $recording->toArray();
            }
            $item['recordings'] = $r;
            if (count($r) > 0) {
                if (Interaction::where('session_call_id', $item['sid'])->count() == 0) {
                    $out[] = $item;
                }
            }
        }

        return view('qa.recording-search')->with(['calls' => $out, 'date' => $date, 'dxcid' => $dxcid, 'dir' => '', 'strict' => false, 'interaction' => request()->input('interaction')]);
    }

    public function searchInCallsByDxcId($dxcid, $date)
    {
        set_time_limit(0);
        $start = hrtime(true);
        $cdate = new CarbonImmutable($date, 'UTC');
        $strict = request()->strict !== null;
        $interaction = Interaction::find(request()->interaction);
        if (!$strict) {
            $yesterday = $cdate->subDay()->startOfDay();
            $tomorrow = $cdate->addDay()->endOfDay();
        } else {
            $cdate = (new CarbonImmutable($interaction->created_at, 'America/Chicago'))->timezone('UTC');
            $yesterday = $cdate->subMinutes(5);
            $tomorrow = $cdate->addMinutes(5);
        }

        $fromDnis = null;
        if ($strict && $interaction !== null && $interaction->notes !== null) {
            $notes = $interaction->notes;
            if (isset($notes['dnis'])) {
                $fromDnis = $notes['dnis'];
            }
        }
        //dd(['cdata' => $cdate, 'yesterday' => $yesterday]);
        $client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $workspace_id = config('services.twilio.workspace');
        /* $workspace = $client->taskrouter
            ->workspaces($workspace_id);*/

        if ($fromDnis !== null) {
            $calls = $client->calls->read([
                'to' => 'client:' . $dxcid,
                'from' => $fromDnis,
                'status' => 'completed',
                'startTimeAfter' => $yesterday,
                'endTimeBefore' => $tomorrow,
            ]);
        } else {

            $calls = $client->calls->read([
                'to' => 'client:' . $dxcid,
                'status' => 'completed',
                'startTimeAfter' => $yesterday,
                'endTimeBefore' => $tomorrow,
            ]);
        }

        $out = [];
        $sids = [];
        foreach ($calls as $call) {
            $item = $call->toArray();

            $r = [];
            $recordings = $call->recordings->read();
            foreach ($recordings as $recording) {
                $r[] = $recording->toArray();
            }
            $item['recordings'] = $r;
            if (count($r) > 0 && $item['direction'] == 'outbound-api') {

                //if (Interaction::where('session_call_id', $item['sid'])->count() == 0) {
                $sids[] = $item['sid']; // new method
                $out[] = $item;
                //}
            }
        }

        /* new method */
        $existing = Interaction::whereIn('session_call_id', $sids)->get();
        $out = array_filter($out, function ($item) use ($existing) {
            return $existing->where('session_call_id', $item['sid'])->count() == 0;
        });
        /* end new method */

        $endtime = hrtime(true);

        //dd([count($out), $endtime - $start]);

        return view('qa.recording-search')->with([
            'dir' => 'in-',
            'date' => $date,
            'dxcid' => $dxcid,
            'strict' => request()->strict !== null,
            'calls' => $out,
            'interaction' => request()->input('interaction')
        ]);
    }

    public function updateInteractionSessionCallId(Request $request)
    {
        $interaction = Interaction::where('id', $request->input('interaction'))->first();
        if ($interaction) {
            $interaction->session_call_id = $request->input('sid');
            $interaction->save();

            $event = Event::find($interaction->event_id);
            if ($event) {
                Artisan::call(
                    'fetch:recording',
                    [
                        '--confirmation_code' => $event->confirmation_code,
                    ]
                );
            }
        }

        return response('The Interaction has been updated, you may now close this window.', 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    }
}
