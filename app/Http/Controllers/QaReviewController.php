<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Models\ScriptAnswer;
use App\Models\Recording;
use App\Models\Rate;
use App\Models\Interaction;
use App\Models\EventProductIdentifier;
use App\Models\EventProduct;
use App\Models\EventFlagReason;
use App\Models\EventFlag;
use App\Models\Event;
use App\Models\Disposition;
use App\Models\CallReviewTypeCategory;
use App\Models\CallReviewType;
use App\Models\Brand;
use App\Models\AddressLookup;

class QaReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('qa.review.review');
    }

    public function update_ivr_script_transcript_text(Request $request)
    {
        $answer = $request->input('answer');
        $newText = $request->input('new_text');
        $user = Auth::user();
        $userName = $user->first_name . ' ' . $user->last_name . ' (' . $user->username . ')';
        $scriptAnswer = ScriptAnswer::where('answer', $answer)->first();
        if ($scriptAnswer) {
            $adata = $scriptAnswer->additional_data;
            if ($adata == null) {
                $adata = [];
            }
            $adata['transcript_status'] = 'completed';
            $adata['transcript_text'] = $newText;
            $adata['transcript_updatedby'] = $userName;

            $scriptAnswer->additional_data = $adata;
            $scriptAnswer->save();
        }
        return back();
    }

    public function list(Request $request)
    {
        $filter = $request->get('filter');
        $sorts = $request->get('sorts');
        $column = 'created_at';
        $direction = 'ASC';
        if ($sorts) {
            $sorts = explode('|', $sorts);
            if (is_array($sorts) && count($sorts) === 2) {
                $column = $sorts[0];
                $direction = $sorts[1];
            }
        }

        $eventFlags = DB::table('event_flags')->select(
            'event_flags.created_at',
            'event_flags.updated_at',
            'event_flags.deleted_at',
            'event_flags.event_id',
            'event_flags.interaction_id',
            'stats_product.confirmation_code',
            'stats_product.tpv_agent_name as first_name',
            DB::raw('"" as last_name'),
            'event_flags.flag_reason_id',
            'stats_product.brand_name',
            'event_flag_reasons.description',
            'qa_tracking.completed_at'
        )->join(
            'stats_product',
            'event_flags.event_id',
            'stats_product.event_id'
        )->join(
            'event_flag_reasons',
            'event_flag_reasons.id',
            'event_flags.flag_reason_id'
        )->leftJoin(
            'qa_tracking',
            'qa_tracking.event_id',
            'event_flags.event_id'
        )->whereNull(
            'call_review_type_id'
        )->whereNull(
            'reviewed_by'
        )->whereNull('qa_tracking.deleted_at')
            ->whereNull('event_flags.deleted_at')
            ->whereNull(
                'stats_product.deleted_at'
            )->where(function ($query) {
                $query->whereNull('qa_tracking.completed_at')
                    ->orWhere('qa_tracking.completed_at', '<', 'event_flags.created_at');
            });
        // ->whereNull(
        //     'event_flags.deleted_at'
        // )->groupBy(
        //     'event_flags.event_id'
        // );

        if ($filter) {
            switch ($filter) {
                case 'fd':
                    $eventFlags = $eventFlags->where(
                        'flag_reason_id',
                        '00000000000000000000000000000000'
                    );
                    break;
                case 'cc':
                    $eventFlags = $eventFlags->where(
                        'event_flags.flag_reason_id',
                        '5dcdd6fb-faa2-4f3a-8e87-cc98db16b8b0'
                    );
                    break;
                case 'ch':
                    $eventFlags = $eventFlags->where(
                        'event_flags.flag_reason_id',
                        '0afb2c0a-ffd1-4488-a258-eb628679e228'
                    );
                    break;
                case 'iv':
                    $ivr = EventFlagReason::where('description', 'IVR Voice Response lacks confidence')->first();
                    $eventFlags = $eventFlags->where(
                        'event_flags.flag_reason_id',
                        '=',
                        $ivr->id
                    );
                    break;
                case 'cr':
                    $ivr = EventFlagReason::where('description', 'IVR Voice Response lacks confidence')->first();
                    $eventFlags = $eventFlags->where(
                        'event_flags.flag_reason_id',
                        '!=',
                        '00000000000000000000000000000000'
                    )->where( // Call unusually long
                        'event_flags.flag_reason_id',
                        '!=',
                        '0afb2c0a-ffd1-4488-a258-eb628679e228'
                    )->where( // Closed Calls
                        'event_flags.flag_reason_id',
                        '!=',
                        '5dcdd6fb-faa2-4f3a-8e87-cc98db16b8b0'
                    )->where(
                        'event_flags.flag_reason_id',
                        '!=',
                        $ivr->id
                    );
                    break;
            }
        }

        if ($request->search) {
            $eventFlags = $eventFlags->where('stats_product.confirmation_code', $request->search);
        }

        $ef = $eventFlags->groupBy('event_flags.event_id')->orderBy($column, $direction)->paginate(10);

        return $ef;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    //end create()

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

    //end store()

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $flag = Cache::remember(
            'flag_' . $id,
            900,
            function () use ($id) {
                $ef = EventFlag::select(
                    'event_flags.created_at',
                    'event_flags.id',
                    'event_flags.flag_reason_id',
                    'event_flags.notes',
                    'event_flags.interaction_id',
                    'events.id AS event_id',
                    'events.confirmation_code',
                    'channels.channel',
                    'tpv_staff.first_name',
                    'tpv_staff.last_name',
                    'brands.id AS brand_id',
                    'brands.name AS brand_name',
                    'event_flag_reasons.description',
                    'interaction_types.name',
                    'interactions.interaction_time',
                    'interactions.event_result_id',
                    'interactions.created_at AS interaction_created_at',
                    'interaction_types.name AS interaction_type',
                    /*'recordings.recording',
                    'recordings.remote_status',
                    'recordings.remote_error_code',*/
                    'dispositions.reason',
                    'event_sources.source'
                )
                    ->leftJoin('events', 'event_flags.event_id', 'events.id')
                    ->leftJoin('interactions', 'interactions.id', 'event_flags.interaction_id')
                    ->leftJoin('tpv_staff', 'tpv_staff.id', 'event_flags.flagged_by_id')
                    ->leftJoin('brands', 'brands.id', 'events.brand_id')
                    ->leftJoin('channels', 'events.channel_id', 'channels.id')
                    ->leftJoin('event_flag_reasons', 'event_flag_reasons.id', 'event_flags.flag_reason_id')
                    /*->leftJoin('recordings', 'interactions.id', 'recordings.interaction_id')*/
                    ->leftJoin('event_sources', 'interactions.event_source_id', 'event_sources.id')
                    ->leftJoin('interaction_types', 'interactions.interaction_type_id', 'interaction_types.id')
                    ->leftJoin('dispositions', 'interactions.disposition_id', 'dispositions.id')
                    ->where('event_flags.id', $id)
                    // ->where('interactions.interaction_type_id', '2')
                    ->first();

                $r = Recording::where('interaction_id', $ef->interaction_id)->orderBy('duration', 'desc')->first();
                $ef->recording = $r->recording;
                $ef->remote_status = $r->remote_status;
                $ef->remote_error_code = $r->remote_error_code;
                return $ef;
            }
        );

        $event_products = Cache::remember(
            'event_products_for_' . $flag->event_id,
            900,
            function () use ($flag) {
                $eps = EventProduct::select(
                    'event_product.id',
                    'event_types.event_type',
                    'event_product.rate_id',
                    'event_product.utility_id',
                    'home_types.home_type',
                    'event_product.bill_first_name',
                    'event_product.bill_last_name',
                    'event_product.company_name'
                )
                    ->leftJoin('event_types', 'event_product.event_type_id', 'event_types.id')
                    ->leftJoin('home_types', 'event_product.home_type_id', 'home_types.id')
                    ->where('event_product.event_id', $flag->event_id)
                    ->get()
                    ->toArray();

                for ($i = 0; $i < count($eps); ++$i) {
                    $epi = EventProductIdentifier::select(
                        'utility_account_types.account_type',
                        'event_product_identifiers.identifier'
                    )
                        ->where('event_product_id', $eps[$i]['id'])
                        ->join('utility_account_types', 'event_product_identifiers.utility_account_type_id', 'utility_account_types.id')
                        ->get()
                        ->toArray();

                    if (count($epi) > 0) {
                        if ($epi[0] && $epi[0]['identifier']) {
                            $eps[$i]['identifier'] = $epi[0]['identifier'];
                        }

                        if ($epi[0] && $epi[0]['account_type']) {
                            $eps[$i]['account_type'] = $epi[0]['account_type'];
                        }
                    }

                    $al = AddressLookup::select(
                        'address_lookup.id_type',
                        'addresses.line_1',
                        'addresses.line_2',
                        'addresses.line_3',
                        'addresses.city',
                        'addresses.state_province',
                        'addresses.zip'
                    )
                        ->where('type_id', $eps[$i]['id'])
                        ->join('addresses', 'address_lookup.address_id', 'addresses.id')
                        ->get()
                        ->toArray();
                    $eps[$i]['addresses'] = $al;

                    $rate = Rate::select(
                        'products.name',
                        'rates.program_code',
                        'utilities.name AS utility_name'
                    )
                        ->join('products', 'products.id', 'rates.product_id')
                        ->join('utilities', 'utilities.id', 'rates.utility_id')
                        ->where('rates.id', $eps[$i]['rate_id'])
                        ->get()
                        ->toArray();

                    if (count($rate) > 0) {
                        if ($rate[0]) {
                            $eps[$i]['rate'] = $rate[0];
                        }
                    }
                } //end for

                return $eps;
            }
        );

        $dispositions = Cache::remember(
            'dispositions_' . $flag->brand_id,
            900,
            function () use ($flag) {
                return Disposition::where('brand_id', $flag->brand_id)
                    ->orderBy('reason')
                    ->get();
            }
        );

        $call_review_types = Cache::remember(
            'call_review_types',
            3600,
            function () {
                $types = [];
                $categories = CallReviewTypeCategory::select('id', 'call_review_type_category')->get();

                foreach ($categories as $category) {
                    $type = CallReviewType::select('id', 'call_review_type')
                        ->where('call_review_type_category_id', $category->id)
                        ->orderBy('call_review_type')
                        ->get()
                        ->toArray();
                    $types[$category->call_review_type_category] = $type;
                }

                return $types;
            }
        );

        return view(
            'qa.review.show',
            [
                'flag' => $flag,
                'call_reviews' => $call_review_types,
                'dispositions' => $dispositions,
                'event_products' => $event_products,
            ]
        );
    }

    //end show()

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

    //end edit()

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
        SendTeamMessage('engineering', 'Inactive qa route used ```' . json_encode($request->all()) . '```');
        abort(400, 'This endpoint should not be used, please notify IT');
        $rules = [
            'result' => 'required',
            'call_review_type' => 'required',
        ];

        $ruleNoSaleCall = ['disposition' => 'required_if:result,2'];

        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails()) {
            return redirect()->route('qa_review.show', $id)
                ->withErrors($validator)
                ->withInput();
        } else {
            // add validation, when updated/changed to "No Sale", disposition is required.
            $validatoraux = Validator::make(Input::all(), $ruleNoSaleCall);
            if ($validatoraux->fails()) {
                return redirect()->route('qa_review.show', $id)
                    ->withErrors($validatoraux)
                    ->withInput();
            } else {
                $ef = EventFlag::find($id);
                $ef->call_review_type_id = $request->call_review_type;
                $ef->reviewed_by = Auth::user()->id;
                $ef->interaction_id = $request->interaction_id;
                $ef->save();

                $interaction = Interaction::find($ef->interaction_id);
                $interaction->event_result_id = $request->result;

                if ($request->disposition) {
                    $interaction->disposition_id = $request->disposition;
                }

                $interaction->save();

                // Update for live enrollment
                Interaction::where('event_id', $interaction->event_id)->get()->each(function ($i) {
                    $i->enrolled = null;
                    $i->save();
                });

                EventProduct::where('event_id', $interaction->event_id)->get()->each(function ($i) {
                    $i->live_enroll = null;
                    $i->save();
                });

                $event = Event::find($interaction->event_id);
                if ($event) {
                    \App\Http\Controllers\QaController::event_reprocess($event);
                }

                $ef->delete();

                // //disposition time tracking
                // $tracking = QaTracking::find($request->tracking_id);
                // $tracking->completed_at = NOW();
                // $tracking->save();
                Cache::forget('flags');
                Cache::forget('flag_' . $id);

                session()->flash('flash_message', 'Interaction was successfully reviewed!');
                // redirect back to same page
                return redirect()->route('events.show', ['id' => $request->event_id]);
                // return redirect()->route('qa_review.index');
            } //end if
        } //end if
    }

    //end update()

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

    //end destroy()
}//end class
