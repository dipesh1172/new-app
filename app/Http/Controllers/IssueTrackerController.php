<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Exception;
use App\Models\JsonDocument;

class IssueTrackerController extends Controller
{
    public static function routes()
    {
        Route::get('issues', 'IssueTrackerController@showIssues')->middleware(['auth']);
        Route::get('errors', 'IssueTrackerController@showErrors')->middleware(['auth']);
        Route::post('errors/resolve', 'IssueTrackerController@markErrorAsResolved')->middleware(['auth']);
        Route::get('issue/new', 'IssueTrackerController@showSubmitIssueForm')->middleware(['auth']);
        Route::post('issue/new', 'IssueTrackerController@submitIssue')->middleware(['auth']);
    }

    public function showErrors(Request $request)
    {
        if (Auth::user()->role_id !== 1) {
            abort(403);
        }
        $perPage = $request->input('perPage');
        if ($perPage === null) {
            $perPage = 10;
        }
        $ref = $request->input('ref');
        $type = $request->input('type');
        if ($ref === null) {
            if ($request->ajax()) {
                $errors = JsonDocument::where('document_type', 'site-errors');
                if ($type == '404') {
                    $errors = $errors->where('ref_id', 'like', '404.%');
                }
                if ($type == '500') {
                    $errors = $errors->where('ref_id', 'like', '500.%');
                }
                $errors = $errors->orderBy('created_at', 'DESC')->paginate($perPage);
                return $errors;
            }

            return view('issues.errors');
        }
        $error = JsonDocument::where('ref_id', $ref)->first();
        if ($error === null) {
            abort(404);
        }

        if ($request->ajax()) {
            return $error;
        }

        return view('issues.error');
    }

    public function markErrorAsResolved(Request $request)
    {
        if (Auth::user()->role_id !== 1) {
            abort(403);
        }

        //dd($request->input());

        try {
            $refs = $request->input('ref');
            if (is_array($refs)) {
                foreach ($refs as $ref) {
                    $error = JsonDocument::where('ref_id', $ref)->first();
                    if ($error !== null) {
                        $error->delete();
                    } else {
                        throw new Exception('Ref Id not found' . $ref);
                    }
                }
            } else {
                $error = JsonDocument::where('ref_id', $refs)->first();
                if ($error !== null) {
                    $error->delete();
                } else {
                    throw new Exception('Ref Id not found' . $refs);
                }
            }

            return response()->json(['error' => false]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function showIssues()
    {
        $trello_token = '541fda35a52a70bbf2f7c37c2605d7b6ca4ee7ae6df4c5e4c58955a4263850ce';
        $trello_api = 'b1f39d6ec7536daf4e01bf2de4625e27';
        $trello_board_id = '5ae1e900e63abbc11551ca41';
        $trello_list_id = '5b7737f23a629c1682cf42a7';

        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => 'https://api.trello.com',
            ]
        );

        $response = Cache::rememberForever(
            'trello-issues',
            function () use ($client, $trello_token, $trello_api, $trello_board_id) {
                return $client->get(
                    '/1/search',
                    [
                        'query' => [
                            'token' => $trello_token,
                            'key' => $trello_api,
                            'card_fields' => 'id,name,desc',
                            'query' => 'label:ReportedIssue and is:open',
                            'cards_limit' => 1000,
                            'idBoards' => $trello_board_id,
                            'card_list' => true,
                            'card_members' => true,
                            'modelTypes' => 'cards',
                        ],
                    ]
                );
            }
        );

        $cards = [];

        if ($response->getStatusCode() == 200) {
            $cards = (json_decode($response->getBody(), true));
            if ($cards !== null && is_array($cards) && isset($cards['cards'])) {
                $cards = $cards['cards'];
            } else {
                $cards = [];
            }
        }

        if (request()->ajax()) {
            return $cards;
        }

        return view('issues.list');
    }

    public function submitIssue(Request $request)
    {
        $trello_token = '541fda35a52a70bbf2f7c37c2605d7b6ca4ee7ae6df4c5e4c58955a4263850ce';
        $trello_api = 'b1f39d6ec7536daf4e01bf2de4625e27';
        $trello_board_id = '5ae1e900e63abbc11551ca41';
        $trello_list_id = '5b7737f23a629c1682cf42a7';
        $trello_label_id = '5c17cc6977ef4d607affd259';

        $title = $request->input('title');
        $content = $request->input('content');
        $cat = $request->input('category');
        $agent = $request->input('agents');
        $center = $request->input('center');
        $reporter = Auth::user()->first_name . ' ' . Auth::user()->last_name;
        $when = $request->input('occurence');
        $confcodes = $request->input('confirmation-code');
        $add_to = $request->input('add_to');
        $template = "Agent(s): $agent \n Center: $center \n Reported By: $reporter \n Occured: $when \n Confirmation Codes: $confcodes \n\n ------ \n $content";
        $subject = '';

        if ($cat == 'existing') {
            $subject = '[Update Existing Issue] ' . $title;
        } else {
            $subject = '[New Issue] ' . $title;
        }

        $files = [];

        $screenshot = null;
        if ($request->hasFile('screenshot')) {
            $screenshot = $request->file('screenshot');
            $files[] = [
                'name' => 'file',
                'filename' => 'screenshot.jpg',
                'contents' => fopen($screenshot->path(), 'r'),
            ];
        }

        $log = null;
        if ($request->hasFile('log')) {
            $log = $request->file('log');
            $files[] = [
                'name' => 'file',
                'filename' => 'console.log.txt',
                'contents' => fopen($log->path(), 'r'),
            ];
        }

        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => 'https://api.trello.com',
            ]
        );

        if ($add_to === null) {
            $response = $client->post(
                '/1/cards',
                [
                    'query' => [
                        'pos' => 'top',
                        'name' => $subject,
                        'desc' => $template,
                        'idList' => $trello_list_id,
                        'idLabels' => $trello_label_id,
                        'key' => $trello_api,
                        'token' => $trello_token,
                    ],
                ]
            );
        } else {
            $response = $client->post(
                '/1/cards/' . $add_to . '/actions/comments',
                [
                    'query' => [
                        'text' => $content,
                        'key' => $trello_api,
                        'token' => $trello_token,
                    ],
                ]
            );
        }

        if ($response->getStatusCode() == 200) {
            $res = json_decode($response->getBody(), true);
            $card_id = null;
            if (isset($res['id']) && $add_to === null) {
                $card_id = $res['id'];
            } else {
                $card_id = $add_to;
            }
            if ($card_id !== null && count($files) > 0) {
                foreach ($files as $file) {
                    $presponse = $client->post(
                        '/1/cards/' . $card_id . '/attachments',
                        [
                            'query' => [
                                'token' => $trello_token,
                                'key' => $trello_api,
                                'name' => $file['name'],
                            ],
                            'multipart' => [$file],
                        ]
                    );
                }
            }

            if ($add_to === null) {
                session()->flash('flash_message', 'Your issue has been submitted, thank you!');
            } else {
                session()->flash('flash_message', 'Your additional instance has been submitted, thank you!');
            }

            Cache::forget('trello-issues');

            return redirect('/issues');
        }

        return back();
    }

    public function showSubmitIssueForm(Request $request)
    {
        $add = $request->input('add');

        return view('issues/submit')->with(['add_to' => $add]);
    }
}
