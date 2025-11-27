<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\JsonDocument;
use Carbon\Carbon;

class JsonDocController extends Controller
{
    public $ignoredTypes = [
        'site-errors',
        'tpv-api',
        'live-agent-stats',
        'stats-job-2',
    ];

    public static function routes()
    {
        Route::group(
            ['middleware' => ['auth']],
            function () {
                Route::get('json/docs', 'JsonDocController@json_documents')->name('json_documents.docs');
                Route::get('json/docs/list', 'JsonDocController@list')->name('json_documents.list');
            }
        );
    }

    public function json_documents()
    {
        $selectedType = request()->input('type');
        if (empty($selectedType)) {
            $selectedType = '';
        }
        return view('generic-vue')->with(
            [
                'componentName' => 'json-documents',
                'title' => 'JSON Documents',
                'parameters' => [
                    'types' => $this->getDocumentTypes(),
                    'selected-type' => json_encode($selectedType),
                ]
            ]
        );
    }

    public function getDocumentTypes()
    {
        return DB::table('json_documents')
            ->whereNotIn('document_type', $this->ignoredTypes)
            ->select('document_type')
            ->groupBy('document_type')
            ->get()
            ->pluck('document_type')
            ->map(function ($item) {
                return ['title' => str_replace('-', ' ', str_replace('_', ' ', Str::title($item))), 'value' => $item];
            })->toJson();
    }

    public function list(Request $request)
    {
        $selectedType = $request->input('type');
        $searchV = $request->input('search');

        $query = JsonDocument::query()
            ->whereNotIn('document_type', $this->ignoredTypes);

        if (!empty($selectedType)) {
            $query->where('document_type', $selectedType);
        }

        if (!empty($searchV)) {
            $query->where(function ($q) use ($searchV) {
                $q->where('ref_id', $searchV)
                    ->orWhere('document', 'like', '%' . $searchV . '%');
            });
        } else {
            $query->where('created_at', '>', Carbon::now()->subDays(7))
                ->orderBy('created_at', 'desc');
        }

        return $query->paginate(30);
    }
}
