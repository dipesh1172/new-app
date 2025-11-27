<?php

namespace App\Http\Controllers;

use App\Models\StatsProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class ContractGen extends Controller
{
    /**
     * Contract Gen Routes.
     */
    public static function routes()
    {
        Route::group(
            ['middleware' => ['auth']],
            function () {
                Route::get('contract-generations', 'ContractGen@index')->name('contractgen.index');
                Route::get('contract-generations/list', 'ContractGen@list')->name('contractgen.list');
            }
        );
    }

    public function index()
    {
        return view('contract-generations/index');
    }

    public function list()
    {
        $sps = StatsProduct::select(
            'stats_product.event_id',
            'stats_product.confirmation_code',
            DB::raw('IF(eztpvs.signature IS NULL, 0, 1) AS customer_signature'),
            DB::raw('IF(eztpvs.signature2 IS NULL, 0, 1) AS sales_agent_signature'),
            'stats_product.eztpv_id'
        )->leftJoin(
            'eztpvs',
            'stats_product.eztpv_id',
            'eztpvs.id'
        )->where(
            'stats_product.result',
            'Sale'
        )->where(
            'eztpvs.processed',
            0
        )->groupBy(
            'stats_product.confirmation_code'
        )->orderBy(
            'stats_product.created_at',
            'desc'
        )->paginate();

        return $sps;
    }
}
