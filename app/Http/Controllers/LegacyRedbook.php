<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\KB\RedbookEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class LegacyRedbook extends Controller
{

    public static function routes()
    {
        Route::group([], function () {
            Route::get('manifest.redbook.json', 'LegacyRedbook@manifest')->name('redbook.manifest');
            Route::get('/redbook.php', 'LegacyRedbook@index')->name('redbook');
            Route::get('js/data.js', 'LegacyRedbook@get_js')->name('redbook.data');
            Route::post('log.php', 'LegacyRedbook@no_op')->name('redbook.log');
            Route::get('update.php', 'LegacyRedbook@no_op')->name('redbook.update');
            Route::get('api/v1/report', 'LegacyRedbook@no_op');
            Route::post('api/v1/report', 'LegacyRedbook@no_op');
        });
    }

    public function no_op()
    {
        return response('', 200);
    }

    public function index()
    {
        return view('redbook', ['version' => '4']);
    }

    public function manifest()
    {
        return response()->json([
            'short_name' => 'Redbook',
            'name' => 'Redbook',
            'icons' => [
                [
                    'src' => 'img/redbook-logo.png',
                    'sizes' => '64x64',
                    'type' => 'image/png'
                ],
            ],
            'start_url' => 'redbook.php',
            'display' => 'standalone',
        ]);
    }

    public function get_js()
    {
        $data = Cache::remember('redbook_data', 6000, function () {
            return str_replace('\/', '/', json_encode(RedbookEntry::all()->sortBy('keyword')->flatten()->map(function ($item) {
                return ['name' => $item->keyword, 'url' => $item->url, 'IndexVisible' => ($item->visibleOnIndex == '1' ? true : false)];
            })));
        });
        $response = 'var companies = ' . $data . ';';
        return response($response, 200)->header('Content-Type', 'text/javascript');
    }
}
