<?php

namespace App\Http\Controllers;

use App\Models\KB\RedbookEntry;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class KbRedbookEntry extends Controller
{
    public static function routes()
    {
        Route::get('/redbook', 'KbRedbookEntry@index');
        Route::get('/redbook/list', 'KbRedbookEntry@list');
        Route::post('/redbook/save', 'KbRedbookEntry@store');
        Route::get('/redbook/entry/{redbookEntry}', 'KbRedbookEntry@show');
        Route::patch('/redbook/entry/{redbookEntry}', 'KbRedbookEntry@update');
        Route::delete('/redbook/entry/{redbookEntry}', 'KbRedbookEntry@destroy');
    }

    public function index()
    {
        return view('kb/redbook-entries');
    }

    public function list()
    {
        $allCount = RedbookEntry::all()->count();

        if (is_array(request()->input('queries'))) {
            $search = request()->input('queries')['search'];
        } else {
            $search = null;
        }
        if (is_array(request()->input('sorts'))) {
            $sorts = request()->input('sorts');
            $raw_sort_d = intval(reset($sorts));
            $sort_by = key($sorts);
            if (!in_array($sort_by, ['id', 'keyword', 'created_at', 'updated_at', 'visibleOnIndex'])) {
                $sort_by = null;
            }

            if ($raw_sort_d > 0) {
                $sort_d = 'ASC';
            } else {
                $sort_d = 'DESC';
            }
        } else {
            $sort_by = null;
            $sort_d = 'ASC';
        }

        $per_page = intval(request()->input('perPage'));
        $page = intval(request()->input('page'));
        $offset = intval(request()->input('offset'));

        $results = RedbookEntry::whereNull('deleted_at');

        if ($search !== null && $search !== '') {
            $results = $results->where('keyword', 'like', '%' . $search . '%');
        } else {
            $search = null;
        }
        if ($sort_by == null || $sort_by == '') {
            $sort_by = 'keyword';
        }
        if ($sort_d == 'ASC') {
            $results = $results->orderBy($sort_by, 'asc')->get();
        } else {
            $results = $results->orderBy($sort_by, 'desc')->get();
        }
        if (!in_array($per_page, [10, 25, 50])) {
            $per_page = 10;
        }
        if ($page < 1) {
            $page = 1;
        }
        $results = $results->slice($offset, $per_page)->flatten();

        return response()->json(['search' => $search, 'totalRecordCount' => $allCount, 'queryRecordCount' => ($search == null ? $allCount : $results->count()), 'records' => $results->map(function ($item) {
            $newItem = $item;
            if ($item->visibleOnIndex == '0') {
                $newItem->visibleOnIndex = false;
            } else {
                $newItem->visibleOnIndex = true;
            }
            return $newItem;
        })]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'keyword' => 'required|unique:redbook_entries',
            'url' => 'required|url',
            'visibleOnIndex' => 'required|boolean',
        ]);
        $redbookEntry = RedbookEntry::create($request->input());
        Cache::forget('redbook_data');
        return response()->json($redbookEntry);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Redbook\RedbookEntry  $redbookEntry
     * @return \Illuminate\Http\Response
     */
    public function show(RedbookEntry $redbookEntry)
    {
        if ($redbookEntry != null) {
            return response()->json($redbookEntry);
        } else {
            return response()->json(['error' => 'Unknown or Invalid ID']);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Redbook\RedbookEntry  $redbookEntry
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, RedbookEntry $redbookEntry)
    {
        $this->validate($request, [
            'keyword' => [
                'required',
                Rule::unique('redbook_entries')->ignore($redbookEntry->id),
            ],
            'url' => 'required|url',
            'visibleOnIndex' => 'required|boolean',
        ]);
        $redbookEntry->fill($request->input());
        $redbookEntry->save();
        Cache::forget('redbook_data');
        return response()->json($redbookEntry);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Redbook\RedbookEntry  $redbookEntry
     * @return \Illuminate\Http\Response
     */
    public function destroy(RedbookEntry $redbookEntry)
    {
        $redbookEntry->delete();
        Cache::forget('redbook_data');
        return response()->json(['error' => null]);
    }
}
