<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Disposition;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\EventResult;
use App\Models\QaTracking;
use App\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;

class QaFinalController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $events = Event::select(
            'events.id', 
            'events.created_at', 
            'brands.name AS brand_name', 
            // 'events.event_id', 
            'users.last_name', 
            'users.first_name', 
            'channels.channel', 
            'events.event_length'
        )
        // ->where('brand_id', '=', session('current_brand')->id)
        ->leftJoin('brands', 'vendor_id', '=', 'brands.id')
        ->leftJoin('event_sources', 'event_source_id', '=', 'event_sources.id')
        ->leftJoin('event_results', 'event_results_id', '=', 'event_results.id')
        ->leftJoin('users', 'users.id', '=', 'events.sales_agent_id')
        ->leftJoin('channels', 'channel_id', '=', 'channels.id')
        ->whereRaw('events.disposition_id is null and ( events.event_results_id is null or events.event_results_id != 1 )')
        ->orderBy('created_at', 'desc')
        ->paginate(30);

        return view('qa.final.final', ['events' => $events]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $event = Event::select(
            'events.id', 
            'events.created_at', 
            'brands.name AS brand_name',
            'events.brand_id', 
            // 'events.event_id', 
            'event_sources.source', 
            'event_results.result', 
            'tpv_staff.last_name AS tpv_staff_last_name', 
            'tpv_staff.first_name AS tpv_staff_first_name', 
            'users.last_name AS sales_last_name', 
            'users.first_name AS sales_first_name',
            'users.email AS sales_email',
            'events.station_id', 
            'channels.channel', 
            'events.phone_number', 
            'events.email_address', 
            'events.event_length', 
            'events.inbound_time', 
            'events.outbound_time',
            'events.event_results_id'
        )
        ->leftJoin('brands', 'vendor_id', '=', 'brands.id')
        ->leftJoin('event_sources', 'event_source_id', '=', 'event_sources.id')
        ->leftJoin('event_results', 'event_results_id', '=', 'event_results.id')
        ->leftJoin('tpv_staff', 'tpv_staff_id', '=', 'tpv_staff.id')
        ->leftJoin('users', 'users.id', '=', 'events.sales_agent_id')
        ->leftJoin('channels', 'channel_id', '=', 'channels.id')
        ->leftJoin('dispositions', 'disposition_id', '=', 'dispositions.id')
        ->find($id);

        if ($event->phone_number) {
            $phone1 = substr($event->phone_number, 0, 3);
            $phone2 = substr($event->phone_number, 3, 3);
            $phone3 = substr($event->phone_number, -4, 4);
            $event->phone_number = "($phone1) $phone2-$phone3";    
        }

        $event_products = EventProduct::select(
            'event_types.event_type',
            'home_types.home_type',
            'event_product.bill_first_name',
            'event_product.bill_last_name',
            'event_product.bill_address1',
            'event_product.bill_city',
            'event_product.bill_state',
            'event_product.bill_zip',
            'event_product.company_name',
            'event_product.account_number',
            'rates.name AS rate_name'
        )
        ->leftJoin('event_types', 'event_product.event_type_id', '=', 'event_types.id')
        ->leftJoin('home_types', 'event_product.home_type_id', '=', 'home_types.id')
        ->leftJoin('rates', 'event_product.rate_id', '=', 'rates.id')
        ->where('event_id', '=', $id)
        ->get();

        $recording = Recording::select('recording')->where('id', $event->id)->first();

        $results = EventResult::get();

        $dispositions = Disposition::where('brand_id', '=', $event->brand_id)->get();

        $tracking = new QaTracking;
        $tracking->tpv_staff_id = Auth::user()->id;
        $tracking->event_id = $id;
        $tracking->qa_task_id = 1;
        $tracking->save();

        return view(
                'qa.final.show', [
                'event' => $event, 
                'event_products' => $event_products,
                'recording' => $recording,
                'results' => $results,
                'dispositions' => $dispositions,
                'tracking_id' => $tracking->id,
            ]
        );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = array(
            'event_results_id' => 'required'
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('qa_final.show', $id)
                ->withErrors($validator)
                ->withInput();
        } else {
            $event = Event::find($id);
                //audits tracking
                $audit = new Audit;
                $audit->user_id = Auth::user()->id;
                $audit->event = 'updated';
                $audit->auditable_id = $event->id;
                $audit->auditable_type = "App\Models\Event";
                $audit->old_values = json_encode(array('disposition_id' => $event->disposition_id, 'event_results_id' => $event->event_results_id));
                $audit->new_values = json_encode(array('disposition_id' => $request->disposition_id, 'event_results_id' => $request->event_results_id));
                $audit->url = $request->server('HTTP_REFERER');
                $audit->ip_address = $request->server('REMOTE_ADDR');
                $audit->user_agent = $request->server('HTTP_USER_AGENT');
                $audit->save();
                //disposition time tracking
                $tracking = QaTracking::find($request->tracking_id);
                $tracking->completed_at = NOW();
                $tracking->save();
            $event->event_results_id = $request->event_results_id;
            $event->disposition_id = $request->disposition_id;
            $event->save();



            session()->flash('flash_message', 'Event was successfully dispositioned!');
            return redirect()->route('qa_final.index');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
