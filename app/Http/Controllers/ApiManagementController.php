<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;
use App\Models\Vendor;
use App\Models\Office;
use App\Models\Brand;

class ApiManagementController extends Controller
{
    public static function routes()
    {
        Route::get('/brands/{brand}/api_tokens', 'ApiManagementController@index');
        Route::get('/brand/api/vendor/{vendor}/get/offices', 'ApiManagementController@getOfficesForVendor');
        Route::post('/brands/{brand}/api_tokens', 'ApiManagementController@createToken');
        Route::delete('/brands/api/token/{token}', 'ApiManagementController@revokeToken');
    }

    public function index(Brand $brand)
    {
        $tokens = DB::table('api_tokens')->where('brand_id', $brand->id)->whereNull('deleted_at')->orderBy('created_at')->get();
        $vendors = Vendor::where('brand_id', $brand->id)->get();
        $view = request()->input('view');

        return view('generic-vue')->with(
            [
                'componentName' => 'brand-api-token-mgmt',
                'parameters' => [
                    'tokens' => json_encode($tokens),
                    'brand' => json_encode($brand),
                    'vendors' => json_encode($vendors),
                    'view' => $view == null ? -1 : intval($view),
                ]
            ]
        );
    }

    public function getOfficesForVendor(Vendor $vendor)
    {
        $offices = Office::where('vendor_id', $vendor->id)->get();
        return $offices;
    }

    public function createToken(Brand $brand)
    {
        request()->validate([
            'label' => 'required|string|max:30',
            'office' => 'nullable|exists:offices,id',
            'vendor' => 'nullable|exists:vendors,id'
        ]);

        $office_id = request()->input('office');
        $vendor_id = request()->input('vendor');
        $label = request()->input('label');
        $now = Carbon::now();

        $secret = openssl_random_pseudo_bytes(32);
        $verify = true;
        $cnt = 0;
        while ($verify !== null) {
            if ($cnt > 10) {
                return response()->json(['error' => 'Unable to generate sufficient randomness for token creation']);
            }
            $appToken = 'AP' . $this->generateRandomString(46);

            $verify = DB::table('api_tokens')->where('app_token', $appToken)->first();
            $cnt += 1;
        }

        $newId = DB::table('api_tokens')->insertGetId([
            'brand_id' => $brand->id,
            'vendor_id' => $vendor_id,
            'office_id' => $office_id,
            'label' => $label,
            'created_at' => $now,
            'updated_at' => $now,
            'secret' => base64_encode($secret),
            'app_token' => $appToken,
        ]);

        $token = DB::table('api_tokens')->where('id', $newId)->first();

        return response()->json(['token' => $token]);;
    }

    private function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function revokeToken($token)
    {
        $tokenO = DB::table('api_tokens')->where('id', $token)->first();
        if ($tokenO == null) {
            abort(400);
        }

        try {
            DB::table('api_tokens')->where('id', $token)->update(['deleted_at' => Carbon::now()]);
            return response()->json(['error' => false]);
        } catch (Exception $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
