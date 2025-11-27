<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\ApiError;

class ApiController extends Controller
{
    public static function routes()
    {
        Route::group(
            ['middleware' => ['auth']],
            function () {
                Route::get('api/errors', 'ApiController@errors')->name('api.errors');
                Route::get('api/errors/list', 'ApiController@errorList')->name('api.errorList');
            }
        );
    }

    public function errors()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'api-errors',
                'title' => 'API Errors',
            ]
        );
    }

    public static function errorList(Request $request)
    {
        $results = ApiError::select(
            'api_errors.created_at',
            'brands.name',
            'api_errors.message',
            'api_errors.body'
        )->leftJoin(
            'brands',
            'api_errors.brand_id',
            'brands.id'
        )->leftJoin(
            'events',
            'api_errors.event_id',
            'events.id'
        );

        if ($request->search) {
            $results = $results->where(
                'body',
                'LIKE',
                '%'.$request->search.'%'
            );
        }

        $results = $results->orderBy('api_errors.created_at', 'desc')->paginate(30);

        return $results;
    }
}
