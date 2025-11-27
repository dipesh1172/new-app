<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\UtilityType;
use App\Models\UtilitySupportedFuel;
use App\Models\UtilityAccountType;
use App\Models\UtilityAccountIdentifier;
use App\Models\Utility;
use App\Models\State;
use App\Models\Rate;
use App\Models\Country;
use App\Models\BrandUtilitySupportedFuel;
use App\Models\BrandUtility;
use App\Models\Brand;

class UtilityController extends Controller
{
    public function utilities()
    {
        $states = State::where('status', 1)->get()->groupBy('country_id');

        return view('utilities.index')->with(['states' => $states]);
    }

    public function listUtilities(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        if ($direction !== 'asc') {
            $direction = 'desc';
        }

        $status = $request->get('status');
        if ($status === 'null') {
            $status = 'active';
        }
        $state = $request->get('state');
        if ($state === 'null') {
            $state = null;
        }
        $search = $request->get('search');
        if ($search === 'null') {
            $search = null;
        }

        $utilities = Utility::select(
            'utilities.id',
            'utilities.ldc_code',
            'utilities.name',
            'states.name AS state_name',
            'utilities.deleted_at'
        )->leftJoin(
            'states',
            'utilities.state_id',
            'states.id'
        );

        if (!empty($status)) {
            if ($status === 'active') {
                $utilities = $utilities->whereNull('utilities.deleted_at');
            } else {
                $utilities = $utilities->withTrashed()->whereNotNull('utilities.deleted_at');
            }
        }

        if (!empty($state)) {
            $utilities = $utilities->where('states.id', $state);
        }

        if (!empty($search)) {
            $utilities = $utilities->where('utilities.ldc_code', 'like', '%' . $search . '%')
                ->orWhere('utilities.name', 'like', '%' . $search . '%');
        }

        if (!empty($column) && !empty($direction)) {
            $utilities = $utilities->orderBy($column, $direction);
        } else {
            $utilities = $utilities->orderBy('utilities.name', 'asc');
        }

        return $utilities->paginate(30);
    }

    public function utilsbybrand(Brand $brand)
    {
        return view('brands.utilities', ['brand' => $brand]);
    }

    public function listUtilsByBrand(Request $request)
    {
        $brand_id = $request->get('brand_id');
        $column = $request->get('column');
        $direction = $request->get('direction');

        $utilities = BrandUtility::select(
            'brand_utilities.deleted_at',
            'brand_utilities.id AS brand_utility_id',
            'brand_utilities.utility_label',
            'brand_utilities.utility_external_id',
            'brand_utilities.service_territory',
            'utilities.id',
            'utilities.ldc_code',
            'utilities.name',
            'states.name AS state_name'
        )->leftJoin(
            'utilities',
            'brand_utilities.utility_id',
            'utilities.id'
        )->leftJoin(
            'states',
            'utilities.state_id',
            'states.id'
        )->where(
            'brand_utilities.brand_id',
            $brand_id
        )->withTrashed();

        if ($column && $direction) {
            $utilities = $utilities->orderBy($column, $direction);
        } else {
            $utilities = $utilities->orderBy('utilities.name', 'asc');
        }

        return response()->json($utilities->paginate(30));
    }

    public function createUtilityForBrand(Brand $brand)
    {
        $states = State::select(
            'id',
            'name',
            'state_abbrev'
        )->orderBy(
            'name',
            'asc'
        )->get();

        $account_types = UtilityAccountType::orderBy(
            'account_type',
            'asc'
        )->get();

        $brand_utilities = BrandUtility::where(
            'brand_id',
            $brand->id
        )->withTrashed()->pluck('utility_id');

        $all_utilities = Utility::select(
            'utilities.id',
            'utilities.ldc_code',
            'utilities.name',
            'states.name AS state_name'
        )->leftJoin(
            'states',
            'utilities.state_id',
            'states.id'
        )->whereNotIn(
            'utilities.id',
            $brand_utilities
        )->orderBy(
            'utilities.name'
        )->get();

        $bystate = array();
        foreach ($all_utilities->toArray() as $utility) {
            $bystate[$utility['state_name']][] = $utility;
        }

        ksort($bystate);

        return view(
            'brands.createUtility',
            [
                'account_types' => $account_types,
                'all_utilities' => $bystate,
                'brand' => $brand,
                'states' => $states,
            ]
        );
    }

    public function createUtility()
    {
        return view(
            'utilities.createUtility',
            [
                'countries' => $this->get_form_fields()['countries'],
                'states' => $this->get_form_fields()['states'],
                'utility_types' => $this->get_form_fields()['utility_types'],
            ]
        );
    }

    public function updateUtility(Request $request, Utility $utility)
    {
        $rules = array(
            'name' => 'required',
            'state' => 'required',
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route(
                'brands.editUtility',
                [
                    'utilities' => $utility,
                ]
            )->withErrors(
                $validator
            )->withInput();
        } else {
            $utility->name = $request->name;

            if ($request->ldc_code) {
                $utility->ldc_code = $request->ldc_code;
            }

            $utility->state_id = $request->state;

            if ($request->service_number) {
                $utility->customer_service = '+1' . preg_replace('/[^A-Za-z0-9]/', '', $request->service_number);
            }

            if ($request->duns) {
                $utility->duns = $request->duns;
            }

            $utility->address1 = $request->address;
            $utility->city = $request->city;

            if ($request->state) {
                $utility->state = State::find($request->state)->state_abbrev;
            } else {
                $utility->state = null;
            }

            $utility->zip = $request->zip;
            $utility->country = $request->country;
            $utility->website = $request->website;
            $utility->disclosure_document = $request->disclosure_document;
            $utility->discount_program = $request->discount_program;
            $utility->service_zips = $request->service_zips;
            $utility->multiple_meter_numbers = $request->multiple_meter_numbers ?? false;
            $utility->name_ivr = $request->name_ivr ?? null;

            $utility->save();

            if ($request->utility_account_type_id) {
                $uai = new UtilityAccountIdentifier();
                $uai->utility_id = $request->supported_type_id;
                $uai->utility_account_type_id = $request->utility_account_type_id;
                $uai->validation_regex = $request->validation_regex;
                $uai->description = $request->description;
                $uai->bill_location = ['en' => $request->bill_location_en, 'sp' => $request->bill_location_sp];
                $uai->save();
            }

            $electricUtilityId = $request->input('utility-1-id');
            if ($electricUtilityId !== null) {
                $usfEl = UtilitySupportedFuel::find($electricUtilityId);
                $usfEl->utility_monthly_fee = $request->input('utility_monthly_fee_1');
                $usfEl->utility_rate_addendum = $request->input('utility_rate_addendum_1');
                $usfEl->save();
            }
            $gasUtilityId = $request->input('utility-2-id');
            if ($gasUtilityId !== null) {
                $usfGas = UtilitySupportedFuel::find($gasUtilityId);
                $usfGas->utility_monthly_fee = $request->input('utility_monthly_fee_2');
                $usfGas->utility_rate_addendum = $request->input('utility_rate_addendum_2');
                $usfGas->save();
            }

            session()->flash('flash_message', 'Utility was successfully updated!');

            return redirect()->route('utilities.editUtility', $utility->id);
        }
    }

    public function updateUtilityForBrand(
        Request $request,
        Brand $brand,
        Utility $utility
    ) {
        $rules = array(
            'utility_label' => 'required',
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route(
                'brands.editUtility',
                [
                    'brand' => $brand->id,
                    'utility' => $utility->id,
                ]
            )->withErrors(
                $validator
            )->withInput();
        } else {
            $bu = BrandUtility::where('brand_id', $brand->id)
                ->where('utility_id', $utility->id);

            if ($bu->exists()) {
                $bu = $bu->first();
            } else {
                $bu = new BrandUtility();
            }

            $bu->utility_label = $request->utility_label;
            $bu->utility_external_id = $request->utility_external_id;
            $bu->commodity = $request->commodity;
            $bu->service_territory = $request->service_territory;
            $bu->save();

            foreach ($request->supported_fuel as $key => $value) {
                $busf = BrandUtilitySupportedFuel::where(
                    'brand_id',
                    $brand->id
                )->where(
                    'utility_id',
                    $utility->id
                )->where(
                    'utility_supported_fuel_id',
                    $request->supported_fuel[$key]
                );

                if ($busf->exists()) {
                    $busf = $busf->first();
                } else {
                    $busf = new BrandUtilitySupportedFuel();
                }

                $busf->brand_id = $brand->id;
                $busf->utility_id = $utility->id;
                $busf->utility_supported_fuel_id = $request->supported_fuel[$key];
                $busf->ldc_code = $request->ldc_code[$key];
                $busf->external_id = $request->external_id[$key];

                if ($request->supported_commodity[$key]) {
                    $busf->commodity = $request->commodity[$key];
                }

                $busf->save();
            }

            session()->flash('flash_message', 'Utility was successfully updated!');

            return redirect()->route('brands.utilities', $brand->id);
        }
    }

    public function storeUtilityForBrand(Request $request, Brand $brand)
    {
        $rules = array(
            'utility' => 'required',
            'utility_label_add' => 'required',
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('brands.createUtilityForBrand',$brand)
                ->withErrors($validator);
        }
        if ($request->utility) {
            $utility = new BrandUtility();
            $utility->brand_id = $brand->id;
            $utility->utility_id = $request->utility;
            $utility->utility_label = $request->utility_label_add;

            if ($request->utility_external_id) {
                $utility->utility_external_id = $request->utility_external_id;
            }

            if ($request->commodity) {
                $utility->commodity = $request->commodity;
            }

            if ($request->service_territory) {
                $utility->service_territory = $request->service_territory;
            }

            $utility->save();

            session()->flash('flash_message', 'Utility was successfully added!');

            return redirect()->route('brands.utilities', $brand->id);
        }
    }

    public function storeUtility(Request $request)
    {
        $rules = array(
            'name' => 'required',
            'state' => 'required',
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        } else {
            $utility = new Utility();
            $utility->name = $request->name;
            $utility->ldc_code = $request->ldc_code;
            $utility->state_id = $request->state;

            $utility->address1 = $request->address;
            $utility->city = $request->city;

            if ($request->state) {
                $utility->state = State::find($request->state)->state_abbrev;
            } else {
                $utility->state = null;
            }

            $utility->zip = $request->zip;
            $utility->country = $request->country;
            $utility->website = $request->website;
            $utility->disclosure_document = $request->disclosure_document;
            $utility->discount_program = $request->discount_program;
            $utility->service_zips = $request->service_zips;

            if ($request->service_number) {
                $utility->customer_service = '+1' . preg_replace('/[^A-Za-z0-9]/', '', $request->service_number);
            }

            if ($request->duns) {
                $utility->duns = $request->duns;
            }

            $utility->name_ivr = $request->name_ivr ?? null;

            $utility->save();

            if ($request->supported) {
                if (count($request->supported) > 0) {
                    foreach ($request->supported as $rs => $rsv) {
                        $usf = new UtilitySupportedFuel();
                        $usf->utility_id = $utility->id;
                        $usf->utility_fuel_type_id = $rs;
                        $usf->save();
                    }
                }
            }

            session()->flash('flash_message', 'Vendor was successfully added!');

            return redirect()->route('utilities.editUtility', $utility->id);
        }
    }

    public function editUtilityForBrand(Request $request, Brand $brand, $id)
    {
        $utility = Utility::select(
            'brand_utilities.id AS brand_utility_id',
            'brand_utilities.utility_label',
            'brand_utilities.utility_external_id',
            'brand_utilities.commodity',
            'utilities.address1',
            'utilities.city',
            'utilities.zip',
            'utilities.website',
            'utilities.disclosure_document',
            'utilities.discount_program',
            'utilities.service_zips',
            'utilities.name',
            'utilities.id'
        )->leftJoin(
            'brand_utilities',
            'utilities.id',
            'brand_utilities.utility_id'
        )->where(
            'brand_utilities.brand_id',
            $brand->id
        )->where(
            'brand_utilities.utility_id',
            $id
        )->with(
            'supported_fuels',
            'supported_fuels.utility_fuel_type'
        )->first();
        if ($utility) {
            for ($i = 0; $i < count($utility->supported_fuels); ++$i) {
                $utility->supported_fuels[$i]['brand_utility_supported_fuels'] = [];
                $busf = BrandUtilitySupportedFuel::where(
                    'brand_id',
                    $brand->id
                )->where(
                    'utility_supported_fuel_id',
                    $utility->supported_fuels[$i]['id']
                )->first();
                if ($busf) {
                    $utility->supported_fuels[$i]['brand_utility_supported_fuels']
                        = $busf->toArray();
                }
            }
        }

        info(print_r($utility->toArray(), true));

        return view(
            'brands.editUtility',
            [
                'brand' => $brand,
                'utility' => $utility,
            ]
        );
    }

    public function updateUtilityIdentifier(Request $request, $sf)
    {
        $ident = UtilityAccountIdentifier::where('id', $sf)->first();
        $description = $request->input('description');
        $regex = $request->input('regex');
        $billLocation = $request->input('bill_location');
        $type = $request->input('uan');
        if ($type < 1 || $type > 3) {
            $type = 1;
        }

        $ident->description = $description;
        $ident->validation_regex = $regex;
        $ident->bill_location = $billLocation;
        $ident->utility_account_number_type_id = $type;
        $ident->save();

        Cache::forget('utility-identifiers-' . $sf);

        return response()->json(['error' => null]);
    }

    protected function get_form_fields()
    {
        $states = Cache::remember('utilities_states', 3600, function () {
            return State::select('id', 'name', 'country_id')->get();
        });
        $utility_types = Cache::remember('utilities_types', 3600, function () {
            return UtilityType::orderBy('utility_type', 'asc')->get();
        });
        $countries = Cache::remember('utilities_countries', 3600, function () {
            return Country::select('id', 'country AS name')->get();
        });

        return compact('states', 'utility_types', 'countries');
    }

    public function editUtility(Request $request, $id)
    {
        if ($request->get('add_type') && is_numeric($request->get('add_type'))) {
            $usf = new UtilitySupportedFuel();
            $usf->utility_id = $id;
            $usf->utility_fuel_type_id = $request->get('add_type');
            $usf->save();

            return redirect()->route('utilities.editUtility', $id);
        }

        if (!$request->ajax()) {
            return view('utilities.editUtility');
        }

        $brands = BrandUtility::where('utility_id', $id)->with(['brand'])->get();

        $account_types = UtilityAccountType::orderBy('account_type', 'asc')->get();

        $utility = Utility::select(
            'utilities.id',
            'utilities.ldc_code',
            'utilities.name',
            'utilities.duns',
            'utilities.customer_service',
            'utilities.state_id',
            'utilities.address1',
            'utilities.city',
            'utilities.zip',
            'utilities.website',
            'utilities.disclosure_document',
            'utilities.discount_program',
            'utilities.service_zips',
            'utilities.name_ivr',
            'states.name AS state_name',
            'utilities.multiple_meter_numbers'
        )->leftJoin(
            'states',
            'utilities.state_id',
            'states.id'
        )->where(
            'utilities.id',
            $id
        )->first();

        if (isset($utility) && isset($utility->customer_service)) {
            $utility->customer_service
                = str_replace('+1', '', $utility->customer_service);
        }

        $utility_supported_fuels = UtilitySupportedFuel::select(
            'utility_supported_fuels.id',
            'utility_supported_fuels.utility_monthly_fee',
            'utility_supported_fuels.utility_rate_addendum',
            'utility_account_identifiers.id AS utility_account_identifiers_id',
            'utility_account_identifiers.validation_regex',
            'utility_account_identifiers.description',
            'utility_account_identifiers.bill_location',
            'utility_account_identifiers.utility_account_number_type_id',
            'utility_account_types.account_type',
            'utility_types.utility_type',
            'utility_types.id AS utility_type_id'
        )->leftJoin(
            'utility_account_identifiers',
            'utility_supported_fuels.id',
            'utility_account_identifiers.utility_id'
        )->leftJoin(
            'utility_account_types',
            'utility_account_identifiers.utility_account_type_id',
            'utility_account_types.id'
        )->leftJoin(
            'utility_types',
            'utility_supported_fuels.utility_fuel_type_id',
            'utility_types.id'
        )->where(
            'utility_supported_fuels.utility_id',
            $utility->id
        )->whereNull(
            'utility_account_identifiers.deleted_at'
        )->whereNull(
            'utility_supported_fuels.deleted_at'
        )->get();

        $fields = $this->get_form_fields();
        $utility_types = $fields['utility_types'];
        $states = $fields['states'];

        $array1 = $utility_types->toArray();
        $array2 = $utility_supported_fuels->toArray();
        $array_diff = [];

        for ($i = 0; $i < count($array1); ++$i) {
            $array_diff[$array1[$i]['id']] = $array1[$i]['utility_type'];
        }

        for ($i = 0; $i < count($array2); ++$i) {
            $key = array_search($array2[$i]['utility_type'], $array_diff);
            if ($key !== false) {
                unset($array_diff[$key]);
            }
        }

        return [
            'brands' => $brands,
            'account_types' => $account_types,
            'states' => $states,
            'utility' => $utility,
            'utility_supported_fuels' => $utility_supported_fuels,
            'utility_types' => $utility_types,
            'add_supported' => $array_diff,
        ];
    }

    public function disableUtility(Brand $brand, $id)
    {
        $utility = BrandUtility::where('id', $id)->first();
        $utility->delete();


        $rates = Rate::join(
            'utility_supported_fuels',
            'utility_supported_fuels.id',
            'rates.utility_id'
        )->join(
            'brand_utilities',
            'brand_utilities.utility_id',
            'utility_supported_fuels.utility_id'
        )->where(
            'brand_utilities.id',
            $id
        )->where(
            'brand_utilities.brand_id',
            $brand->id
        )->whereNull(
            'rates.deleted_at'
        )->get();

        $rates->each(function ($rate) {
            $rate->delete();
        });

        Cache::forget('utilities_' . $brand->id);

        session()->flash('flash_message', 'Utility was successfully disabled!');

        return back();
    }

    public function enableUtility(Brand $brand, $id)
    {
        $utility = BrandUtility::where('id', $id)->withTrashed()->first();
        $utility->restore();

        $firstr = Rate::select(
            'rates.deleted_at'
        )->join(
            'utility_supported_fuels',
            'utility_supported_fuels.id',
            'rates.utility_id'
        )->join(
            'brand_utilities',
            'brand_utilities.utility_id',
            'utility_supported_fuels.utility_id'
        )->where(
            'brand_utilities.id',
            $id
        )->where(
            'brand_utilities.brand_id',
            $brand->id
        )->orderBy(
            'rates.deleted_at',
            'DESC'
        )->first();
        if ($firstr !== null) {
            $rates = Rate::join(
                'utility_supported_fuels',
                'utility_supported_fuels.id',
                'rates.utility_id'
            )->join(
                'brand_utilities',
                'brand_utilities.utility_id',
                'utility_supported_fuels.utility_id'
            )->where(
                'brand_utilities.id',
                $id
            )->where(
                'brand_utilities.brand_id',
                $brand->id
            )->whereRaw(
                'DATE(rates.deleted_at) = DATE(\'' . $firstr->deleted_at . '\')'
            )->get();
            $rates->each(function ($rate) {
                if ($rate->isTrashed()) {
                    $rate->restore();
                }
            });
        }

        Cache::forget('utilities_' . $brand->id);

        session()->flash('flash_message', 'Utility was successfully enabled!');

        return back();
    }
}
