<?php

namespace App\Http\Controllers;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Traits\SearchFormTrait;
use App\Models\State;
use App\Models\Market;
use App\Models\Dnis;
use App\Models\Country;
use App\Models\Channel;
use App\Models\BrandState;
use App\Models\BrandHour;
use App\Models\Brand;

class DnisController extends Controller
{
    use SearchFormTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('dnis.dnis');
    }

    public function listDnis(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $brand_id = $request->get('brand_id');

        if (!$column || !$direction) {
            $column = 'brands.name';
            $direction = 'asc';
        }

        $dnises = Dnis::select(
            'dnis.id',
            'dnis.brand_id',
            'dnis.dnis',
            'brands.name',
            'dnis.dnis_type',
            'dnis.states',
            'dnis.deleted_at',
            'service_types.name as service_name',
            'channels.channel',
            'scripts.title'
        )->leftJoin(
            'brands',
            'dnis.brand_id',
            'brands.id'
        )->leftJoin(
            'service_types',
            'dnis.service_type_id',
            'service_types.id'
        )->leftJoin(
            'scripts',
            'dnis.id',
            'scripts.dnis_id'
        )->leftJoin(
            'channels',
            'scripts.channel_id',
            'channels.id'
        )->whereNull('scripts.deleted_at')->withTrashed();

        if ($brand_id) {
            $dnises = $dnises->where('dnis.brand_id', $brand_id);
        }

        $dnises = $dnises->orderBy(
            $column,
            $direction
        )->paginate(20);

        foreach ($dnises as $dnis) {
            if ($dnis->states) {
                $config = [];
                $states = explode(',', $dnis->states);

                for ($i = 0; $i < count($states); ++$i) {
                    $state = Cache::remember(
                        'state_by_id_'.$states[$i],
                        1800,
                        function () use ($states, $i) {
                            return State::select('states.name')
                                ->where('id', $states[$i])->first();
                        }
                    );

                    if ($state) {
                        $config['state'][$state->name] = [];

                        $hours = Cache::remember(
                            'hoo_brand_state_'.$dnis->brand_id.'_'.$states[$i],
                            1800,
                            function () use ($dnis, $states, $i) {
                                return BrandHour::select('data')
                                    ->where('brand_id', $dnis->brand_id)
                                    ->where('state_id', $states[$i])
                                    ->first();
                            }
                        );

                        if ($hours) {
                            $config['state'][$state->name][] = $hours->data;
                        }
                    }
                }

                $dnis->config = $config;
            }
        }

        return $dnises;
    }

    public function enableDnis($phone)
    {
        $dnis = Dnis::where('id', $phone)->withTrashed()->first();
        $dnis->restore();

        return redirect()->back();
    }

    public function disableDnis(Dnis $phone)
    {
        $phone->delete();

        return redirect()->back();
    }

    public function listBrands()
    {
        $dnises = Dnis::select(
            'dnis.brand_id',
            'brands.name'
        )->leftJoin(
            'brands',
            'dnis.brand_id',
            'brands.id'
        )->groupBy(
            'dnis.brand_id'
        )->orderBy(
            'brands.name'
        )->get()->toArray();

        return response()->json($dnises);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('dnis.create');
    }

    public function createExternal()
    {
        return view('dnis.createExternal');
    }

    public function lookupPhone()
    {
        return view('dnis.lookup');
    }

    public function list_lookupPhone(Request $request)
    {
        $client = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $numbers = [];
        if ($request->type) {
            switch ($request->type) {
                case 2:
                    $numbers = $client->availablePhoneNumbers('US')
                        ->tollFree->read();
                    break;
                default:
                    $numbers = $client->availablePhoneNumbers('US')->local->read(
                        array('areaCode' => $request->areacode)
                    );
                    break;
            }
        }

        if (count($numbers)) {
            $numbers = array_map(function ($n) {
                return [
                    'friendlyName' => $n->friendlyName,
                    'rateCenter' => $n->rateCenter,
                    'region' => $n->region,
                    'phoneNumber' => $n->phoneNumber,
                ];
            }, $numbers);
        }

        return $numbers;
    }

    public function choosePhone()
    {
        return view(
            'dnis.choose',
            [
                'brands' => $this->get_brands(),
            ]
        );
    }

    public function choosePhoneExternal()
    {
        // Make sure this is not a duplicate number
        $dnisCheck = Dnis::where(
            'dnis', ('+' . request()->number) // We're getting 12223334444 format add the + when searching for duplicate
        )->first();

        if($dnisCheck) {
            return redirect()->route('dnis.createExternal')
                ->withErrors(['msg' => "Dnis '" . request()->number . "' has already been taken."])
                ->withInput();
        }

        return view(
            'dnis.chooseExternal',
            [
                'brands' => $this->get_brands(),
            ]
        );
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
        $rules = array(
            'dnis' => 'required',
            'brand_id' => 'required',
            'dnis_type' => 'required',
            'service_type_id' => 'required'
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('dnis.create')
                ->withErrors($validator)
                ->withInput();
        } else {

            if(!isset($request->platform) || $request->platform == 'focus' || $request->platform == 'dxc') { // Only run Twilio client code for Focus and DXC DNIS's. If platform is not provided, assume focus/dxc
                $client = new TwilioClient(config('services.twilio.account'), config('services.twilio.auth_token'));
                $number = $client->incomingPhoneNumbers->create(
                    array(
                        'phoneNumber' => $request->dnis,
                        'voiceApplicationSid' => config('services.twilio.app'),
                    )
                );
            }

            $dnis = new Dnis();
            $dnis->dnis = $request->dnis;
            $dnis->brand_id = $request->brand_id;
            $dnis->dnis_type = ($request->dnis_type == 1) ? 'local' : 'tollfree';
            $dnis->service_type_id = $request->service_type_id;

            if(isset($request->platform)) {
                $dnis->platform = strtolower($request->platform);
            }

            $dnis->save();

            session()->flash(
                'flash_message',
                'DNIS was successfully added!  Please allow up to 5 minutes for these changes to propogate.'
            );

            return redirect()->route('dnis.index');
        }
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
        $dnis = Dnis::select(
            'dnis.id',
            'dnis.dnis',
            'brands.name'
        )
            ->leftJoin('brands', 'dnis.brand_id', 'brands.id')
            ->where('dnis.id', $id)
            ->first();

        return view('dnis.show', ['dnis' => $dnis]);
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
        $states = State::select('id', 'name', 'state_abbrev', 'country_id')
            ->where('status', 1)
            ->get();

        $brand_states = BrandState::select(
            'brand_id',
            'state_id'
        )->whereNull('deleted_at')->get()->groupBy('brand_id');

        $countries = Country::select('id', 'country AS name')->whereIn(
            'id',
            [
                1,
                2,
            ]
        )->get();

        $brands = Brand::select('id', 'name')
            ->whereNotNull('client_id')
            ->orderBy('name')
            ->get();

        $dnis = Dnis::select(
            'dnis.id',
            'dnis.dnis',
            'dnis.brand_id',
            'dnis.dnis_type',
            'dnis.service_type_id',
            'dnis.states',
            'dnis.platform',
            'dnis.skill_name',
            'dnis.channel_id',
            'dnis.market_id',
            'dnis.config'
        )->leftJoin(
            'brands',
            'dnis.brand_id',
            'brands.id'
        )->where(
            'dnis.id',
            $id
        )->withTrashed()->first();

        // echo "<pre>";
        // print_r($dnis);
        // exit();

        $channels = Channel::orderBy('channel')->get()->toArray();
        $markets = Market::orderBy('market')->get()->toArray();

        return view(
            'dnis.edit',
            [
                'dnis' => $dnis,
                'brands' => $brands,
                'brand_states' => $brand_states,
                'channels' => $channels,
                'markets' => $markets,
                'states' => $states,
                'countries' => $countries,
            ]
        );
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
        $rules = array(
            'brand_id' => 'required',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('dnis.edit', $id)
                ->withErrors($validator)
                ->withInput();
        } else {
            $dnis = Dnis::where('id', $id)->withTrashed()->first();
            if ($dnis) {
                $dnis->brand_id = $request->brand_id;

                if (is_iterable($request->states) && count($request->states) > 0) {
                    $dnis->states = implode(',', $request->states);
                } else {
                    $dnis->states = null;
                }

                $dnis->platform = $request->platform;
                $dnis->skill_name = $request->skill_name;
                $dnis->channel_id = $request->channel_id;
                $dnis->market_id = $request->market_id;
                $dnis->config = $request->config;
                $dnis->save();
            }

            // Cache::tags(['dnis_states'])->flush();

            session()->flash(
                'flash_message',
                'DNIS was successfully edited!  Please allow up to 5 minutes for these changes to propogate.'
            );

            return redirect()->route('dnis.index');
        }
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
        $dnis = Dnis::find($id);
        $dnis->delete();

        // Cache::tags(['dnis_states'])->flush();

        session()->flash(
            'flash_message',
            'DNIS was successfully deleted! Please allow up to 5 minutes for these changes to propogate.'
        );

        return redirect()->route('dnis.index');
    }
}
