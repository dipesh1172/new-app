<?php

namespace App\Http\Controllers;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Models\TpvStaffGroup;
use App\Models\TpvStaff;
use App\Models\CallCenter;

class SkillProfileController extends Controller
{
    private $client;
    private $workspace_id;
    private $workspace;

    public function __construct()
    {
        $this->client = new TwilioClient(config('services.twilio.account'), config('services.twilio.auth_token'));
        $this->workspace_id = config('services.twilio.workspace');
        $this->workspace = $this->client->taskrouter->workspaces($this->workspace_id);
    }

    public function show_app()
    {
        return view('vue')->with([
            'appName' => 'SkillProfiles',
            'title' => 'Agent Groups',
        ]);
    }

    public function agents_by_skill_profile($id)
    {
        $agents = Cache::remember('agents_by_skill_profile_' . $id, 300, function () use ($id) {
            return TpvStaff::where('tpv_staff_group_id', $id)
                ->orderBy('last_name')
                ->get()
                ->toArray();
        });

        return response()->json(
            [
                'skill_profile_agents' => $agents,
            ]
        );
    }

    public function list_skill_profiles()
    {
        $groups = Cache::remember(
            'agent_groups_with_count',
            1800,
            function () {
                $g = TpvStaffGroup::orderBy('group')->get()->toArray();

                for ($i = 0; $i < count($g); ++$i) {
                    if (!isset($g[$i]['config']['skills'])) {
                        $g[$i]['config']['skills'] = [];
                    }

                    $counts = TpvStaff::where('tpv_staff_group_id', $g[$i]['id'])->count();
                    $g[$i]['agent_count'] = $counts;
                }

                return $g;
            }
        );

        // Log::debug(print_r($groups, true));

        return response()->json(
            [
                'skill_profiles' => $groups,
            ]
        );
    }

    public function available_skills()
    {
        return response()->json(
            [
                'skills' => Cache::remember(
                    'twilio-skills',
                    900,
                    function () {
                        $taskQueues = $this->workspace->taskQueues->read();
                        $out = [];
                        foreach ($taskQueues as $queue) {
                            //$out[] = trim(explode('-', $queue->friendlyName)[0]);
                            $out[] = trim($queue->friendlyName);
                        }

                        return array_unique($out);
                    }
                ),
            ]
        );
    }

    public function add_skill_profile()
    {
        $x = new TpvStaffGroup(['service_type_id' => 3, 'group' => request()->input('group'), 'config' => null]);
        $x->save();

        Cache::forget('agent_groups_with_count');
        Cache::forget('agent_group_list');
        Cache::forget('agent_skills');

        return response()->json(['error' => null, 'message' => 'Added Skill Profile.']);
    }

    public function remove_skill_profile($id)
    {
        $group = TpvStaffGroup::find($id);
        if ($group) {
            $group->delete();
        }
        Cache::forget('agent_groups_with_count');
        Cache::forget('agent_group_list');
        Cache::forget('agent_skills');
        return redirect('/agent/groups');
    }

    public function updateTwilioAttributes(TpvStaff $tpv_staff, $taskqueues)
    {
        $service_login = $tpv_staff->logins->first();
        if (empty($service_login)) {
            info('ERROR: no logins exist for user: ' . $tpv_staff->id, [$tpv_staff->toArray()]);

            return;
        }

        $skills = $taskqueues['skills'];

        $languages = array();
        if ($tpv_staff->language_id) {
            switch ($tpv_staff->language_id) {
                case 1:
                    $languages = array('en');
                    break;
                case 2:
                    $languages = array('es');
                    break;
                case 3:
                    $languages = array('en', 'es');
                    break;
            }
        }

        $attributes = array(
            //"channels" => $channels,
            'languages' => $languages,
            'contact_uri' => 'client:' . $service_login->username,
        );

        if (count($skills) > 0) {
            $attributes['skills'] = $skills;
        }

        if ($tpv_staff->call_center_id) {
            $location = Cache::remember('callcenter_' . $tpv_staff->call_center_id, 60, function () use ($tpv_staff) {
                return CallCenter::where('id', $tpv_staff->call_center_id)->first();
            });
            $attributes['location'] = strtolower($location->call_center);
        }

        $worker = $this->workspace->workers($service_login->password)
            ->update(
                array('attributes' => json_encode($attributes, true))
            );

        Cache::forget('worker_' . $service_login->password);
    }

    public function update_skill_profile()
    {
        set_time_limit(300);
        $id = request()->input('id');
        $skills = request()->input('skills');
        $group = request()->input('group');

        $x = TpvStaffGroup::find($id);

        if ($x) {
            $x->service_type_id = 3;
            $x->group = $group;

            if (!empty($skills)) {
                info('updating skills for group [' . $group . '] from ' . json_encode($x->config) . ' to ' . json_encode($skills));
                $x->config = $skills;
            }

            $x->save();

            $agents = TpvStaff::select([
                'id',
                'hire_date',
                'first_name',
                'middle_name',
                'last_name',
                'username',
                'call_center_id',
                'language_id',
                'role_id',
                'client_login',
                'status'
            ])->where('tpv_staff_group_id', $id)
                ->whereHas('logins')
                ->with(['logins'])
                ->get();


            for ($i = 0; $i < count($agents); ++$i) {
                $this->updateTwilioAttributes($agents[$i], $skills);
            }

            Cache::forget('agent_groups_with_count');
            Cache::forget('agent_group_list');
            Cache::forget('agent_skills');

            return response()->json(['error' => null, 'message' => 'Updated Skill Profile.']);
        } else {
            return response()->json(['error' => 'Invalid id']);
        }
    }
}
