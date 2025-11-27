<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\State;
use App\Models\ZipCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class ZipMgmtController extends Controller
{
    public static function routes()
    {
        Route::get('zcm', 'ZipMgmtController@index');
        Route::get('zcm/zips-for-state/{state_id}', 'ZipMgmtController@zips_for_state');
        Route::get('zcm/{country_id}/{state_id}', 'ZipMgmtController@for_state');

        Route::post('zcm/save', 'ZipMgmtController@save_zip');
        Route::post('zcm/update', 'ZipMgmtController@update_zip');

        Route::get('zcm/{zipcode}', 'ZipMgmtController@edit_zip');
        Route::delete('zcm/{zip}', 'ZipMgmtController@remove_zip');
    }

    public function index()
    {
        $states = State::all();
        $countries = Country::whereIn('id', [1, 2])->get();

        return view('generic-vue')->with([
            'title' => 'Zip Code Managment',
            'componentName' => 'zcm-index',
            'parameters' => [
                'states' => json_encode($states),
                'countries' => json_encode($countries),
            ],
        ]);
    }

    public function for_state($country_id, $state_id)
    {
        return view('generic-vue')->with([
            'title' => 'Zip Code Managment',
            'componentName' => 'zcm-state',
            'parameters' => [
                'country' => $country_id,
                'state' => json_encode($state_id),
            ],
        ]);
    }

    public function zips_for_state($state_id)
    {
        $search = request()->input('search');
        if ($search === null) {
            return ZipCode::where('state', $state_id)->get();
        }
        return ZipCode::where('state', $state_id)->where('zip', 'LIKE', $search . '%')->get();
    }

    public function remove_zip($zip)
    {
        $z = ZipCode::where('zip', $zip)->first();
        $z->delete();
        Cache::forget('zipcode_' . $zip);
    }

    public function save_zip()
    {
        $data = request()->validate([
            'zip' => 'required|string|min:5|max:6',
            'city' => 'required|string',
            'state' => 'required|string',
            'county' => 'required|string',
            'lat' => 'required|numeric|min:-90|max:90',
            'lon' => 'required|numeric|min:-180|max:180',
            'timezone' => 'required|numeric|min:-12|max:12',
            'dst' => 'required|boolean',
            'country' => 'required|exists:countries,id'
        ]);

        try {
            $z = new ZipCode();
            $z->zip = $data['zip'];
            $z->city = $data['city'];
            $z->county = $data['county'];
            $z->state = $data['state'];
            $z->lat = $data['lat'];
            $z->lon = $data['lon'];
            $z->timezone = $data['timezone'];
            $z->dst = $data['dst'];
            $z->country = $data['country'];
            $z->save();
            Cache::forget('zipcode_' . $z->zip);
            return response()->json(['error' => false]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function update_zip()
    {
        $data = request()->validate([
            'zip' => 'required|string|min:5|max:6',
            'city' => 'required|string',
            'state' => 'required|string',
            'county' => 'required|string',
            'lat' => 'required|numeric|min:-90|max:90',
            'lon' => 'required|numeric|min:-180|max:180',
            'timezone' => 'required|numeric|min:-12|max:12',
            'dst' => 'required|boolean',
            'country' => 'required|exists:countries,id'
        ]);

        try {
            $z = ZipCode::where('zip', $data['zip'])->first();
            //$z->zip = $data['zip'];
            $z->city = $data['city'];
            $z->county = $data['county'];
            $z->state = $data['state'];
            $z->lat = $data['lat'];
            $z->lon = $data['lon'];
            $z->timezone = $data['timezone'];
            $z->dst = $data['dst'];
            $z->country = $data['country'];
            $z->save();
            Cache::forget('zipcode_' . $z->zip);
            return response()->json(['error' => false]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function edit_zip($zipcode)
    {
        $zip = ZipCode::where('zip', $zipcode)->first();
        $state = request()->input('state');
        $country = request()->input('country');
        return view('generic-vue')->with([
            'title' => 'Zip Code Editor',
            'componentName' => 'zcm-edit',
            'parameters' => [
                'zip' => json_encode($zip),
                'state' => json_encode($state),
                'country' => $country,
                'zipcode' => json_encode($zipcode),
            ],
        ]);
    }
}
