<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use GuzzleHttp\Client;
use Carbon\Carbon;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

use App\Models\Brand;
use App\Models\Dnis;
use App\Models\Language;
use App\Models\Eztpv;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\Company;
use App\Models\StatsProduct;

/**
 * 2023-05-03 - Alex Kolosha
 * 
 * Controller for mapping a Focus brand, DNIS, and language combination to a motion Motion skill.
 */
class MotionClearTestCallsController extends Controller
{
    /**
     * Our HTTP client.
     */
    protected $httpClient;

    /**
     * Define controller's routes
     */
    public static function routes()
    {
        Route::group(
            ['middleware' => ['auth']],
            function () {
                Route::post('motion/clear_test_calls/list_clear_test_calls', 'MotionClearTestCallsController@list_clear_test_calls')->name('motion_clear_test_calls.list_clear_test_calls');
                Route::get('motion/clear_test_calls', 'MotionClearTestCallsController@clear_test_calls')->name('motion_clear_test_calls.clear_test_calls');
                Route::post('motion/clear_test_calls/delete_test_calls', 'MotionClearTestCallsController@delete_test_calls')->name('motion_clear_test_calls.delete_test_calls');
                Route::post('motion/clear_test_calls/client_test_calls', 'MotionClearTestCallsController@client_test_calls')->name('motion_clear_test_calls.client_test_calls');
            }
        );
    }

    public function clear_test_calls()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'motion-clear-test-calls',
                'title' => 'Motion: Clear Test Calls',
            ]
        );
    }

    public function list_clear_test_calls(Request $request)
    {
        // Set up HTTP client
        $this->httpClient = new HttpClient(['verify' => false]);

        if ($request->confirmation_codes === 'testvendor') {
            return $this->get_events_by_testvendor();
        }
        elseif (isset($request->confirmation_codes)) {
            $results = $this->get_events_by_confirmation_code($request);

            if (count($results) < 1) {
                $results = $this->get_mongo_data_by_api_callsearch($request);
                $results = $this->results($results);
                return response()
                    ->json($results);
            }

            return $results;
        }
        else {
            $results = $this->get_mongo_data_by_api_callsearch($request);
            $results = $this->results($results);
            return response()
                ->json($results);
        }
    }

    public function delete_test_calls(Request $request)
    {
        // Set up HTTP client
        $this->httpClient = new HttpClient(['verify' => false]);

        $events = $this->get_events_by_confirmation_code($request);

        foreach ($events as $event) {
            $sps = StatsProduct::where('confirmation_code', $event->confirmation_code)->get();
            foreach ($sps as $sp) {
                $sp->delete();
            }
            if ($event->eztpv_id != null) {
                $eztpv = Eztpv::where('id', $event->eztpv_id)->first();
                if ($eztpv) {
                    $eztpv->delete();
                }
            }
            $products = EventProduct::where('event_id', $event->id)->get();
            foreach ($products as $product) {
                $product->delete();
            }
            $event->delete();
        }

        $this->delete_mongo_test_calls($request);

        session()->flash('flash_message', 'All calls were successfully deleted!');

        return back();
    }

    public function client_test_calls(Request $request)
    {
        return $this->get_company_name_prefix();
    }

    public function get_events_by_confirmation_code($request)
    {
        $codes = [];
        if ($request->confirmation_codes) {
            $codes = explode(',', $request->confirmation_codes);
        }

        return Event::select(
            'events.id',
            'events.created_at',
            'events.confirmation_code',
            'brands.name as brand_name',
            'event_product.auth_first_name',
            'event_product.auth_last_name',
            'event_product.bill_first_name',
            'event_product.bill_last_name',
            'events.vendor_id'
        )->leftJoin(
            'brands',
            'brands.id',
            'events.brand_id'
        )->leftJoin(
            'event_product',
            'event_product.event_id',
            'events.id'
        )->whereIn(
            'events.confirmation_code',
            $codes
        )->with(['vendor'])->groupBy('events.id')->get();
    }

    private function get_events_by_testvendor()
    {
        $vendor = Brand::where('name', 'TPV.com Test Vendor')->first();

        return Event::select(
            'events.id',
            'events.created_at',
            'events.confirmation_code',
            'brands.name as brand_name',
            'event_product.auth_first_name',
            'event_product.auth_last_name',
            'event_product.bill_first_name',
            'event_product.bill_last_name',
            'events.vendor_id'
        )->leftJoin(
            'brands',
            'brands.id',
            'events.brand_id'
        )->leftJoin(
            'event_product',
            'event_product.event_id',
            'events.id'
        )->where(
            'events.vendor_id',
            $vendor->id
        )->with(['vendor'])->groupBy('events.id')->get();
    }

    public function get_mongo_data_by_api_callsearch($request)
    {
        $collection = $request->company_nm;

        if (isset($request->confirmation_codes)) {
            $searchText = $request->confirmation_codes;
        }
        elseif (isset($request->unique_ids)) {
            $searchText = $request->unique_ids;
        }
        else {
            $searchText = $request->ani_numbers;
        }

        $body = [
            'collection' => $collection,
            'SearchText' =>  $searchText,
        ];

        $response = $this->httpClient->post(
            'https://apiv2.tpvhub.com/api/common/getcallsearch', [
                'json' => $body
            ]
        );

        $res = json_decode($response->getBody()->getContents());

        return $res;
    }

    protected function results($results)
    {
        $data = [];

        foreach ($results as $result) 
        {
            $result->confirmation_code = $result->ConfirmationNumber;
            $result->created_at = $result->CallDetail->StartTime;
            $result->brand_name = (isset($result->SAName)) ? $result->SAName : '--';
            $result->unique_id = (isset($result->CallDetail->UNIQUEID)) ? $result->CallDetail->UNIQUEID : '--';
            $result->ani_number = (isset($result->CallDetail->ANI)) ? $result->CallDetail->ANI : '--';
           
            $data[] = $result;
        }

        return $data;
    }

    public function delete_mongo_test_calls($request)
    {
        $confirmationNumber = $request->confirmation_codes;
        $prefix = $request->company_nm;

        $unique_ids = $this->get_unique_ids_by_confirmation_number($request);
        
        $body = [
            'prefix' => $prefix,
            'unique_id' => $unique_ids,
        ];

        $response = $this->httpClient->post(
            'https://apiv2.tpvhub.com/api/util/deletefocustestrecords', [
                'json' => $body
            ]
        );

        $res = json_decode($response->getBody()->getContents());
    }

    public function get_unique_ids_by_confirmation_number($request)
    {
        $collection = $request->company_nm."TPV";

        $confirmationNumbers = explode(",", $request->confirmation_codes);
        
        $unique_ids = "";

        foreach ($confirmationNumbers as $confirmationNumber)
        {
            $body = [
                'collection' => $collection,
                'SearchText' =>  $confirmationNumber,
            ];

            $response = $this->httpClient->post(
                'https://apiv2.tpvhub.com/api/common/getcallsearch', [
                    'json' => $body
                ]
            );

            $results = json_decode($response->getBody()->getContents());

            foreach ($results as $result) 
            {
                $unique_ids .= "'".$result->CallDetail->UNIQUEID."',";
            }
        }

        $unique_ids = rtrim($unique_ids, ",");

        return $unique_ids;
    }

    public function get_company_name_prefix ()
    {
        return Company::select(
            'companies.id',
            'companies.name',
            'companies.qa_collection_name',
            'companies.answernet_api_prefix'
            )->whereNotNull(
            'companies.answernet_api_prefix'
            )->orderBy(
            'companies.name',
            'asc'
        )->get();
    }

}
