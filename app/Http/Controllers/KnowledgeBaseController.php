<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\KB\Video;
use App\Models\KB\EntryVersion as KnowledgeBaseVersion;
use App\Models\KB\Entry as KnowledgeBase;
use App\Models\KB\Category;
use App\Events\VideoUploaded;

class KnowledgeBaseController extends Controller
{
    public static function routes()
    {
        Route::group(
            ['prefix' => 'kb'],
            function () {
                Route::get('/', 'KnowledgeBaseController@showHome')->middleware(['auth'])->name('kb/home');
                Route::get('AllPages', 'KnowledgeBaseController@index')
                    ->middleware(['auth'])->name('kb/list-all');
                Route::get('create', 'KnowledgeBaseController@create')
                    ->middleware(['auth'])->name('kb/create');
                Route::post('create', 'KnowledgeBaseController@store')
                    ->middleware(['auth'])->name('kb/create/save');
                Route::get('get_categories', 'KnowledgeBaseController@get_categories')
                    ->middleware(['auth'])->name('kb/get_categories');
                Route::get('list', 'KnowledgeBaseController@list')
                    ->middleware(['auth']);
                Route::get('list_kb', 'KnowledgeBaseController@list_kb')
                    ->middleware(['auth'])->name('list_kb');
                Route::get(
                    'versions/{knowledgeBase}',
                    'KnowledgeBaseController@viewVersions'
                )->middleware(['auth']);
                Route::patch('edit/{knowledgeBase}', 'KnowledgeBaseController@update')->middleware(['auth']);
                Route::delete('del/{knowledgeBase}', 'KnowledgeBaseController@destroy')->middleware(['auth'])->name('kb.destroy');
                Route::get('del/{knowledgeBase}', 'KnowledgeBaseController@destroy')->middleware(['auth'])->name('kb.destroy');
                Route::get('edit/{knowledgeBase}', 'KnowledgeBaseController@edit')->middleware(['auth'])->name('kb.edit');
                Route::get('get_edit/{knowledgeBase}', 'KnowledgeBaseController@get_edit')->middleware(['auth'])->name('kb.get_edit');
                Route::group(
                    ['prefix' => 'video'],
                    function () {
                        Route::get('/', 'KnowledgeBaseController@videos_home')->middleware(['auth']);
                        Route::get('list', 'KnowledgeBaseController@list_videos')->middleware(['auth']);
                        Route::get('list_videos_home', 'KnowledgeBaseController@list_videos_home')->middleware(['auth']);
                        Route::get('upload', 'KnowledgeBaseController@video_create')->middleware(['auth']);
                        Route::post('upload', 'KnowledgeBaseController@upload_video')->middleware(['auth']);
                        Route::get('edit/{video}', 'KnowledgeBaseController@edit_video')->middleware(['auth']);
                        Route::get('play/{video}', 'KnowledgeBaseController@view_video');
                        Route::delete('{video}', 'KnowledgeBaseController@delete_video')->middleware(['auth'])->name('kb_video.destroy');
                        Route::get('delete/{video}', 'KnowledgeBaseController@delete_video')->middleware(['auth'])->name('kb_video.delete');
                        Route::get('{video}', 'KnowledgeBaseController@link_to_video');
                    }
                );

                Route::get('w/{slug}', 'KnowledgeBaseController@showArticle')
                    ->name('kb.show-by-slug');

                Route::get('{knowledgeBase}', 'KnowledgeBaseController@show')
                    ->name('kb.show-by-id')
                    ->where('knowledgeBase', '[0-9]+');



                Route::get('get_kb/{knowledgeBase}', 'KnowledgeBaseController@get_kb')
                    ->name('kb.get_kb');
            }
        );
    }

    public function videos_home()
    {
        return view('kb/video/home');
    }

    public function showHome()
    {
        $kbv = KnowledgeBaseVersion::where('title', 'Home')->first();
        if ($kbv) {
            $kb = KnowledgeBase::find($kbv->kb_id);
            if ($kb) {
                return $this->show($kb);
            }
        }
        return redirect('/kb/AllPages');
    }

    public function list_videos_home(Request $request)
    {
        $column = $request->column;
        $direction = $request->direction;

        $videos = Video::select('*');

        if ($direction && $column) {
            $videos = $videos->orderBy($column, $direction);
        }

        $videos = $videos->paginate(25)
            ->withPath(str_replace('http://', 'https://', config('app.urls.mgmt')) . '/kb/video');

        return $videos;
    }

    public function view_video($video)
    {
        $vid = Video::where('slug', $video)->first();

        return view('kb/video/play')->with(['video' => $vid]);
    }

    public function delete_video($video)
    {
        $vid = Video::where('slug', $video)->first();
        if ($vid == null) {
            return response('', 404);
        }
        req_perm('kb.create');
        Storage::disk('s3-videos')->delete($vid->path);
        $vid->delete();
    }

    public function list_videos()
    {
        $allCount = Video::all()->count();

        if (is_array(request()->input('queries'))) {
            $search = request()->input('queries')['search'];
        } else {
            $search = request()->input('queries[search]');
        }
        if (is_array(request()->input('sorts'))) {
            $sorts = request()->input('sorts');
            $raw_sort_d = intval(reset($sorts));
            $sort_by = key($sorts);
            if (!in_array($sort_by, ['title', 'author', 'created_at', 'updated_at'])) {
                $sort_by = null;
            }

            if ($raw_sort_d > 0) {
                $sort_d = 'ASC';
            } else {
                $sort_d = 'DESC';
            }
        } else {
            $sort_by = null;
            $sort_d = 'DESC';
        }

        $per_page = intval(request()->input('perPage'));
        $page = intval(request()->input('page'));
        $offset = intval(request()->input('offset'));

        $results = Video::select('*');

        if ($sort_by == null || $sort_by == '') {
            $sort_by = 'created_at';
        }

        if ($search !== null && $search !== '') {
            $results->where('title', 'like', '%' . $search . '%');
        } else {
            $search = null;
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

        return response()->json([
            'search' => $search,
            'totalRecordCount' => $allCount,
            'queryRecordCount' => ($search == null ? $allCount : $results->count()),
            'records' => $results->map(function ($item) {
                $newItem = (object) [];
                $newItem->id = $item->id;

                $newItem->created_at = $item->created_at->format('c');
                $newItem->updated_at = $item->updated_at->format('c');
                $newItem->title = $item->title;
                $author = optional(\App\Models\TpvStaff::withTrashed()->where('id', $item->author_id)->first());
                $newItem->author = trim($author->first_name . ' ' . $author->last_name);
                $newItem->authorId = $item->author_id;
                $newItem->status = $item->status;
                $newItem->slug = trim($item->slug);

                return $newItem;
            }),
        ]);
    }

    public function video_create()
    {
        return view('kb/video/edit')->with(['video' => null]);
    }

    public function upload_video()
    {
        $id = request()->input('id');
        $title = request()->input('title');
        $slug = str_slug($title, '-');
        $desc = strip_tags(request()->input('desc'));
        request()->validate([
            'title' => 'bail|required|max:150',
            'desc' => 'required|max:1024',
            'id' => 'nullable|exists:videos',
        ]);
        $s3 = '';
        if (request()->file('videofile')) {
            $file = request()->file('videofile');
            $s3 = $file->store('to-convert', 's3-vid-upload');
        }
        $video = null;
        if ($id != null) {
            $video = Video::find($id);
        } else {
            $video = new Video();
        }
        $video->title = $title;
        $video->slug = $slug;
        $video->description = $desc;
        if ($s3 != '') {
            $video->path = $s3;
        }
        $video->author_id = Auth::user()->id;
        $video->save();
        if ($s3 != '') {
            event(new VideoUploaded($video));
        }

        return redirect('/kb/video/edit/' . $video->id);
    }

    public function edit_video(Video $video)
    {
        return view('kb/video/edit')->with(['video' => $video]);
    }

    public function link_to_video($video)
    {
        $vid = Video::where('slug', $video)->first();
        if ($vid == null) {
            return response(404);
        }

        if ($vid->status != 'Conversion Complete') {
            return response('Video is still processing, try again later', 202);
        }
        $cloudFront = new \Aws\CloudFront\CloudFrontClient([
            'region' => config('auth.aws.region'),
            'version' => '2014-11-06',
        ]);

        $resourceKey = 'https://' . config('auth.aws.cloudfront.domain') . '/' . $vid->path;
        $expires = time() + (60 * 5);

        // Create a signed URL for the resource using the canned policy
        $signedUrl = $cloudFront->getSignedUrl([
            'url' => $resourceKey,
            'expires' => $expires,
            'private_key' => config('auth.aws.cloudfront.private_key'),
            'key_pair_id' => config('auth.aws.cloudfront.key_id'),
        ]);

        return redirect($signedUrl);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('kb/home');
    }

    public function list_kb(Request $request)
    {
        $column = $request->column;
        $direction = $request->direction;

        $articles = KnowledgeBase::select(
            'kb_entries.id',
            'kb_entry_versions.title',
            'kb_entries.views',
            'kb_entries.created_at',
            'kb_entries.updated_at'
        )
            ->join(
                'kb_entry_versions',
                function ($join) {
                    $join->on('kb_entry_versions.version', 'kb_entries.current_version')
                        ->whereRaw('kb_entry_versions.kb_id = kb_entries.id');
                }
            );

        if ($direction && $column) {
            $articles = $articles->orderBy($column, $direction);
        }

        $articles = $articles->paginate()
            ->withPath(str_replace('http://', 'https://', config('app.urls.mgmt')) . '/kb');

        return $articles;
    }

    public function get_categories()
    {
        return Category::all();
    }

    public function viewVersions(KnowledgeBase $knowledgeBase)
    {
        $alerts = \App\Models\KB\Alert::forKB($knowledgeBase->id);
        $specific = request()->input('show');
        $set = request()->input('set');
        if ($specific == null) {
            if ($set == null) {
                $versions = KnowledgeBaseVersion::where('kb_id', $knowledgeBase->id)->select(['version', 'created_at', 'title', 'author'])->orderBy('version', 'desc')->get();

                return view('generic-vue')->with(
                    [
                        'componentName' => 'kb-version-history',
                        'title' => 'Version History',
                        'parameters' => [
                            'knowledge-base' => json_encode($knowledgeBase),
                            'versions' => json_encode($versions),
                        ]
                    ]
                );
                //return view('kb/version')->with(['kb' => $knowledgeBase, 'kb_id' => $knowledgeBase->id, 'versions' => $versions]);
            } else {
                $knowledgeBase->current_version = $set;
                $knowledgeBase->save();
            }
        } else {
            $currentVersion = KnowledgeBaseVersion::where('kb_id', $knowledgeBase->id)->where('version', $specific)->first();

            return view('kb/show-raw')->with(['kb' => $currentVersion, 'alerts' => $alerts]);
        }
    }

    public function list()
    {
        $allCount = KnowledgeBase::all()->count();

        if (is_array(request()->input('queries'))) {
            $search = request()->input('queries')['search'];
        } else {
            $search = request()->input('queries[search]');
        }
        if (is_array(request()->input('sorts'))) {
            $sorts = request()->input('sorts');
            $raw_sort_d = intval(reset($sorts));
            $sort_by = key($sorts);
            if (!in_array($sort_by, ['id', 'title', 'created_at', 'updated_at', 'views'])) {
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

        $results = KnowledgeBase::select('*');

        if ($sort_by == null || $sort_by == '') {
            $sort_by = 'id';
        }
        $sort_by = 'kb_entries.' . $sort_by;

        if ($search !== null && $search !== '') {
            /*$results = $results->filter(function ($item) use ($search) {
                return strpos(optional($item->currentVersion)->title, $search) !== false;
            });*/
            $results->leftJoin('kb_entry_versions', 'kb_entries.id', '=', 'kb_entry_versions.kb_id')->where('kb_entry_versions.version', 'knowledge_bases.current_version');
            $results = $results->where('kb_entry_versions.title', 'like', '%' . $search . '%');
        } else {
            $search = null;
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

        return response()->json([
            'search' => $search,
            'totalRecordCount' => $allCount,
            'queryRecordCount' => ($search == null ? $allCount : $results->count()),
            'records' => $results->map(function ($item) {
                $newItem = (object) [];
                $newItem->id = $item->id;
                $newItem->views = $item->views;
                $newItem->cversion = $item->current_version;
                $newItem->created_at = $item->created_at->format('c');
                $newItem->updated_at = $item->updated_at->format('c');
                $newItem->title = optional($item->getCurrentVersion())->title;
                $author = optional(\App\Models\TpvStaff::find(optional($item->getCurrentVersion())->author));
                $newItem->author = trim($author->first_name . ' ' . $author->last_name);
                $newItem->authorId = optional($item->getCurrentVersion())->author;

                return $newItem;
            }),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('kb/create');
        //->with(['categories' => $this->get_categories()]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'content' => 'required',
        ]);

        $kb = new KnowledgeBase();
        $kb->current_version = 1;
        $kb->save();
        $kv = new KnowledgeBaseVersion();
        $kv->title = $request->input('title');
        $kv->content = $request->input('content');
        $kv->author = Auth::user()->id;
        $kv->version = 1;
        $kv->kb_id = $kb->id;
        /*$cat = KbCategory::where('name', $rcat)->first();
            if ($cat == null && $rcat != null && $rcat != '') {
                $cat = new KbCategory();
                $cat->name = $rcat;
                $cat->save();
            }
        */

        $kv->save();

        return $kb;
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\Redbook\KnowledgeBase $knowledgeBase
     *
     * @return \Illuminate\Http\Response
     */
    public function show(KnowledgeBase $knowledgeBase)
    {
        //dd($knowledgeBase);
        if ($knowledgeBase->exists()) {
            $knowledgeBase->increment('views');
            $alerts = \App\Models\KB\Alert::forKB($knowledgeBase->id);
            $currentVersion = KnowledgeBaseVersion::where('kb_id', $knowledgeBase->id)->where('version', $knowledgeBase->current_version)->first();
            if ($currentVersion->title == 'Home' && auth()->guest()) {
                abort(403);
            }
            if (auth()->guest() || request()->input('raw') == 1) {
                return
                    view('kb/show-raw')
                    ->with(['kb' => $currentVersion, 'alerts' => $alerts]);
            }

            return view('kb/show')->with(['kb' => $currentVersion, 'alerts' => $alerts]);
        }
        abort(404);
    }

    public function showArticle($slug)
    {
        $raw_slug = str_replace('_', ' ', $slug);
        //dd($raw_slug);
        $kbv = KnowledgeBaseVersion::where('title', $raw_slug)->orderBy('kb_id', 'DESC')->orderBy('version', 'DESC')->first();

        if ($kbv) {
            //dd($kbv->toArray());
            $kb = KnowledgeBase::find($kbv->kb_id);
            if ($kb) {
                //dd($kb->toArray());
                return $this->show($kb);
            }
        }
        abort(404);
    }

    public function get_kb(KnowledgeBase $knowledgeBase)
    {
        abort(404);
        if ($knowledgeBase->exists()) {
            $knowledgeBase->increment('views');
            $alerts = \App\Models\KB\Alert::forKB($knowledgeBase->id);
            $currentVersion = KnowledgeBaseVersion::where('kb_id', $knowledgeBase->id)->where('version', $knowledgeBase->current_version)->first();
            return ['kb' => $currentVersion, 'alerts' => $alerts];
        } else {
            return ['kb' => [], 'alerts' => []];
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Redbook\KnowledgeBase $knowledgeBase
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(KnowledgeBase $knowledgeBase)
    {
        if ($knowledgeBase->exists()) {
            return view('kb/edit');
        }
        abort(404);
    }

    public function get_edit(KnowledgeBase $knowledgeBase)
    {
        if ($knowledgeBase->exists()) {
            $currentVersion = KnowledgeBaseVersion::where('kb_id', $knowledgeBase->id)->where('version', $knowledgeBase->current_version)->first();
            //Since I can use optional here then 'version' => $currentVersion ?? []
            return [
                'kb' => $knowledgeBase,
                'version' => $currentVersion ?? [],
                //'categories' => $this->get_categories()
            ];
        }
        return [
            'kb' => [],
            'version' => [],
            //'categories' => []
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request          $request
     * @param \App\Models\Redbook\KnowledgeBase $knowledgeBase
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, KnowledgeBase $knowledgeBase)
    {
        $this->validate($request, [
            'title' => 'required',
            'content' => 'required',
        ]);

        $kb = $knowledgeBase;
        $currentVersion = KnowledgeBaseVersion::where('kb_id', $knowledgeBase->id)->where('version', $knowledgeBase->current_version + 1)->first();
        if ($currentVersion != null && $currentVersion->exists()) {
            $currentVersion = KnowledgeBaseVersion::where('kb_id', $knowledgeBase->id)->orderBy('version', 'desc')->first();
            $kb->current_version = $currentVersion->version++;
        } else {
            ++$kb->current_version;
        }
        $kb->save();
        $kv = new KnowledgeBaseVersion();
        $kv->title = $request->input('title');
        $kv->content = $request->input('content');
        $kv->author = Auth::user()->id;
        $kv->version = $kb->current_version;
        $kv->kb_id = $kb->id;
        $rcat = $request->input('category');
        info('Saving category: ' . $rcat);
        $cat = Category::where('name', $rcat)->first();
        if ($cat == null && $rcat != null && $rcat != '') {
            $cat = new Category();
            $cat->name = $rcat;
            $cat->save();
        }
        $kv->category = $cat->id ?? null;
        $kv->save();

        return response()->json($kb);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Redbook\KnowledgeBase $knowledgeBase
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(KnowledgeBase $knowledgeBase)
    {
        $knowledgeBase->delete();

        return back();
    }
}
