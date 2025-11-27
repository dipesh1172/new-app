<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\TpvStaff;
use App\Models\RuntimeSetting;
use App\Events\NewMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function chat_test()
    {
        $chat_enabled = runtime_setting('chat_enabled');
        return view('chat.settings', ['chat_enabled' => $chat_enabled]);
    }

    public function index()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'chat-settings',
                'title' => 'Chat: Settings',
                'parameters' => [
                    'chat-enabled' => runtime_setting('chat_enabled')
                ]
            ]
        );
    }

    public function update(Request $request)
    {
        $chat_enabled = $request->chat_enabled ? '1' : '0';
        RuntimeSetting::where(
            'name',
            'chat_enabled'
        )->update([
            'value' => $chat_enabled,
        ]);

        Cache::forget('runtime_setting_chat_enabled');
        Cache::remember(
            'runtime_setting_chat_enabled',
            7200,
            function () use ($chat_enabled) {
                return $chat_enabled;
            }
        );

        return back();
    }

    public function getContacts()
    {
        $contacts = TpvStaff::select(
            'tpv_staff.id',
            'tpv_staff.first_name',
            'tpv_staff.last_name',
            'tpv_staff_roles.name as role',
            'call_centers.call_center'
        )
            ->join('tpv_staff_roles', 'tpv_staff.role_id', 'tpv_staff_roles.id')
            ->join('call_centers', 'tpv_staff.call_center_id', 'call_centers.id')
            ->join('agent_statuses', 'agent_statuses.tpv_staff_id', 'tpv_staff.id')
            ->where('tpv_staff.id', '!=', auth()->id())
            ->whereRaw('agent_statuses.created_at > DATE_SUB(curdate(), INTERVAL 2 WEEK)')
            ->groupBy('tpv_staff.id');

        $contacts = $contacts->get();
        //->get();

        $unreadIds = Message::select(DB::raw('`from_id` as sender_id, count(`from_id`) as messages_count'))
            ->where('to_id', auth()->id())
            ->where('is_read', 0)
            ->groupBy('from_id')
            ->get();

        $contacts = $contacts->map(function ($contact) use ($unreadIds) {
            $contactUnread = $unreadIds->where('sender_id', $contact->id)->first();

            $contact->unread = $contactUnread ? $contactUnread->messages_count : 0;

            return $contact;
        });

        return response()->json($contacts);
    }

    public function getMessagesFor($id)
    {
        Message::where('from_id', $id)->where('to_id', auth()->id())->update(['is_read' => true]);

        $messages = Message::where(function ($q) use ($id) {
            $q->where('from_id', auth()->id());
            $q->where('to_id', $id);
        })->orWhere(function ($q) use ($id) {
            $q->where('to_id', auth()->id());
            $q->where('from_id', $id);
        })->orderBy('created_at')->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request)
    {
        $message = Message::create([
            'id' => Uuid::uuid4(),
            'from_id' => auth()->id(),
            'to_id' => $request->to_id,
            'content' => $request->content,
            'is_read' => 0,
            'message_type_id' => 1,
        ]);

        broadcast(new NewMessage($message));

        return response()->json($message);
    }
}