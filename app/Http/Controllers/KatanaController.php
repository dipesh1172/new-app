<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

class KatanaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {       
        switch ($request->mode) {
            case 'events_confirmation_code':
                $table = 'events';
                $query = Event::katanaByConfirmationCode($request->parameter)
                    ->first();
                
                if ($query) {
                    $query = $query->toArray();
                    $relations = [];
                    foreach ($query as $key => $value) {
                        if (is_array($value)) {
                            $relations[$key] = $value;
                            unset($query[$key]);
                        }
                    }
                } else {
                    $query = null;
                    $relations = null;
                }

                break;

            case 'users_user_id':
                $table = 'users';
                $query = User::katanaByUserId($request->parameter)
                    ->first();
                
                if ($query) {
                    $query = $query->toArray();
                    $relations = [];
                    foreach ($query as $key => $value) {
                        if (is_array($value)) {
                            $relations[$key] = $value;
                            unset($query[$key]);
                        }
                    }
                } else {
                    $query = null;
                    $relations = null;
                }

                break;
            
            default:
                $table = null;
                $query = null;
                $relations = null;
                break;
        }
        
        return view(
            'katana.katana', 
            [
                'parameter' => $request->parameter,
                'mode' => $request->mode,
                'table' => $table, 
                'query' => $query, 
                'relations' => $relations
            ]
        );
    }

    public function lookupConfirmationCode($code)
    {
        return Event::select(
            'events.id',
            'events.brand_id',
            'events.created_at',
            'events.confirmation_code',
            'events.channel_id',
            'events.sales_agent_id',
            'events.vendor_id',
            'events.eztpv_id'
        )->with(
            'brand',
            'channel',
            'eztpv',
            'eztpv.eztpv_docs',
            'eztpv.eztpv_docs.uploads',
            'eztpv.eztpv_docs.uploads.type',
            'phone',
            'phone.phone_number',
            'products',
            'products.event_type',
            'products.identifiers',
            'products.identifiers.utility_account_type',
            'products.rate',
            'products.rate.utility',
            'products.rate.utility.utility',
            'products.rate.rate_type',
            'products.rate.rate_uom',
            'products.rate.term_type',
            'products.rate.rate_currency',
            'products.rate.product',
            'products.rate.product.intro_term_type',
            'products.rate.product.term_type',
            'products.rate.product.rate_type',
            'products.addresses',
            'interactions',
            'interactions.result',
            'interactions.source',
            'interactions.disposition',
            'interactions.interaction_type',
            'interactions.recordings',
            'interactions.service_types',
            'interactions.tpv_agent',
            'interactions.event_flags',
            'interactions.event_flags.flag_reason',
            'sales_agent',
            'sales_agent.user',
            'vendor'
        )->where(
            'events.confirmation_code', 
            $code
        )->first();
    }

    public function compareConfirmationCodes(Request $request)
    {
        if ($request->code1 && $request->code2) {
            return view(
                'katana.compare',
                [
                    'nodata' => false,
                    'code1' => $request->code1,
                    'code2' => $request->code2,
                    'results1' => $this
                        ->lookupConfirmationCode($request->code1)
                        ->toArray(),
                    'results2' => $this
                        ->lookupConfirmationCode($request->code2)
                        ->toArray(),
                ]
            );
        } else {
            return view(
                'katana.compare',
                [
                    'nodata' => true,
                    'code1' => "",
                    'code2' => "",
                ]
            );
        }
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
        //
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
        //
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
