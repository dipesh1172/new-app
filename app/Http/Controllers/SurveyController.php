<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use App\Models\Survey;
use App\Models\Brand;

class SurveyController extends Controller
{
    public function listSurveys()
    {
        $all = Survey::select(
            DB::raw('COUNT(*) AS all_count'),
            DB::raw('CONCAT(brands.name, " - ", languages.language) AS name'),
            'brands.name AS brand_name',
            'surveys.brand_id'
        )->leftJoin(
            'brands',
            'surveys.brand_id',
            'brands.id'
        )->leftJoin(
            'languages',
            'surveys.language_id',
            'languages.id'
        )->where(
            'surveys.created_at',
            '<=',
            Carbon::now('America/Chicago')
        )->groupBy(
            'surveys.brand_id'
        )->orderBy(
            'brands.name'
        )->orderBy(
            'surveys.language_id'
        )->groupBy(
            'brands.name'
        )->groupBy(
            'surveys.language_id'
        )->get()->toArray();

        $ready = Survey::select(
            DB::raw('COUNT(*) AS ready'),
            DB::raw('CONCAT(brands.name, " - ", languages.language) AS name')
        )->leftJoin(
            'brands',
            'surveys.brand_id',
            'brands.id'
        )->leftJoin(
            'languages',
            'surveys.language_id',
            'languages.id'
        )->where(
            'surveys.created_at',
            '<=',
            Carbon::now('America/Chicago')
        )->where(
            'surveys.last_call',
            '<=',
            Carbon::now('America/Chicago')->subHours(4)
        )->orWhere(
            'surveys.last_call',
            null
        )->groupBy(
            'surveys.brand_id'
        )->orderBy(
            'brands.name'
        )->orderBy(
            'surveys.language_id'
        )->groupBy(
            'brands.name'
        )->groupBy(
            'surveys.language_id'
        )->get()->toArray();

        $combined = [];
        foreach ($all as $key => $value) {
            $ret = [
                'name' => $value['name'],
                'brand_name' => $value['brand_name'],
                'brand_id' => $value['brand_id'],
                'all_count' => $value['all_count'],
                'ready' => 0,
            ];
            foreach ($ready as $r) {
                if ($r['name'] === $value['name']) {
                    $ret['ready'] = $r['ready'];
                }
            }
            $combined[] = $ret;
        }

        return $combined;
    }

    /**
     * Manual Release from MGMT -> Call Center.
     *
     * @param int $trickle     - amount to manually release
     * @param int $language_id - language to release
     *
     * @return bool
     */
    public function manualRelease(int $trickle, int $language_id, $brand)
    {
        if (strlen(trim($brand)) > 0 && $brand !== 'all') {
            $brand = Brand::find($brand);
            $brand_debug = $brand->name;
        } else {
            $brand_debug = 'All Brands';
        }

        Log::debug('Releasing '.$trickle.' of language ID '.$language_id.' brands: '.$brand_debug);

        if ($trickle > 0) {
            $params = [
                '--trickle' => $trickle,
            ];

            if (isset($brand->id)) {
                $params['--brand'] = $brand->id;
            }

            if ($language_id == 2) {
                $params['--spanish'] = true;
            }

            // Log::debug('Processing surveys with:');
            // Log::debug($params);

            Artisan::call(
                'survey:processing',
                $params
            );

            return response()->json(
                [
                    'status' => true,
                ]
            );
        }

        return response()->json(
            [
                'status' => false,
            ]
        );
    }

    /**
     * Reset Surveys back to Defaults.
     *
     * @return bool
     */
    public function resetDefault()
    {
        if (config('app.env') != 'production') {
            $params = [
                '--reset' => true,
            ];

            Artisan::call(
                'survey:processing',
                $params
            );

            return response()->json(
                [
                    'status' => true,
                ]
            );
        }
    }

    /**
     * Reset Survey call_time to null.
     *
     * @return bool
     */
    public function resetCallTime()
    {
        if (config('app.env') != 'production') {
            $params = [
                '--clearcalltime' => true,
            ];

            Artisan::call(
                'survey:processing',
                $params
            );

            return response()->json(
                [
                    'status' => true,
                ]
            );
        }
    }
}
