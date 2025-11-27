<?php

namespace App\Http\Controllers;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\TpvStaffRole;
use App\Models\TpvStaffPermission;
use App\Models\TpvStaffGroup;
use App\Models\TpvStaffDepartment;
use App\Models\TpvStaff;
use App\Models\Timezone;
use App\Models\TimeClock;
use App\Models\ServiceLogin;
use App\Models\PhoneNumberLookup;
use App\Models\PhoneNumber;
use App\Models\Language;
use App\Models\EmailAddressLookup;
use App\Models\EmailAddress;
use App\Models\CallCenter;
use App\Models\BrandUser;
use App\Models\BrandTaskQueue;
use App\Models\Brand;

class TpvStaffController extends Controller
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

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = TpvStaffRole::orderBy('name', 'asc')->get();
        $groups = TpvStaffGroup::orderBy('group', 'asc')->get();

        return view('generic-vue')->with(
            [
                'componentName' => 'tpv-staff-index',
                'title' => 'TPV Staff',
                'parameters' => [
                    'create-url' => json_encode(route('tpv_staff.create')),
                    'has-flash-message' => json_encode(Session::has('flash_message')),
                    'flash-message' => json_encode(session('flash_message')),
                    'roles' => json_encode($roles),
                    'groups' => json_encode($groups),
                ]
            ]
        );
    }

    public function listTpvStaff(Request $request)
    {
        $column = $request->get('column') ?? 'created_at';
        $direction = $request->get('direction') ?? 'desc';
        $search = $request->get('search');
        $perPage = is_numeric($request->get('perPage')) ? intval($request->get('perPage')) : 30;
        $status = $request->has('status') ? $request->get('status') : 'active';
        $role = $request->get('role');
        if ($role === 'null') {
            $role = null;
        }
        $group = $request->get('group');
        if ($group === 'null') {
            $group = null;
        }

        $tpv_staff = TpvStaff::select([
            'tpv_staff.id',
            'tpv_staff.hire_date',
            'tpv_staff.username',
            'tpv_staff.payroll_id',
            'tpv_staff.created_at',
            'tpv_staff.first_name',
            'tpv_staff.middle_name',
            'tpv_staff.last_name',
            'tpv_staff_roles.name AS role_name',
            'call_centers.call_center',
            'languages.language',
            'tpv_staff.status',
            'tpv_staff_groups.group',
            'tpv_staff.supervisor_id',
            'tpv_staff.manager_id'
        ])
            ->leftjoin('tpv_staff_roles', 'tpv_staff.role_id', 'tpv_staff_roles.id')
            ->leftjoin('call_centers', 'call_centers.id', 'tpv_staff.call_center_id')
            ->leftjoin('languages', 'languages.id', 'tpv_staff.language_id')
            ->leftJoin(
                'tpv_staff_groups',
                'tpv_staff.tpv_staff_group_id',
                'tpv_staff_groups.id'
            );

        if (!empty($role)) {
            $tpv_staff = $tpv_staff->where('tpv_staff.role_id', $role);
        }

        if (!empty($group)) {
            if ($group === 'is_empty') {
                $tpv_staff = $tpv_staff->whereNull('tpv_staff_group_id');
            } else {
                $tpv_staff = $tpv_staff->where('tpv_staff_group_id', $group);
            }
        }

        switch ($status) {
            default:
            case 'active':
                $tpv_staff = $tpv_staff->where('status', 1);
                break;
            case 'inactive':
                $tpv_staff = $tpv_staff->where('status', 0)->withTrashed();
                break;

            case 'all':
                $tpv_staff = $tpv_staff->withTrashed();
                break;
        }

        if (!empty($search)) {
            $tpv_staff = $tpv_staff->search($search);
        }

        $tpv_staff = $tpv_staff->orderBy($column, $direction)->groupBy('tpv_staff.id');

        return $tpv_staff->paginate($perPage);
    }

    public function agents()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'agents-index',
                'title' => 'Agents',
                'parameters' => [
                    'create-url' => json_encode(route('tpv_staff.create')),
                ],
            ]
        );
    }

    public function listAgents(Request $request)
    {
        $column = $request->get('column') ?? 'created_at';
        $direction = $request->get('direction') ?? 'desc';
        $search = $request->get('search');

        $agents = TpvStaff::select(
            'tpv_staff.id',
            'tpv_staff.hire_date',
            'tpv_staff.created_at',
            'tpv_staff.first_name',
            'tpv_staff.middle_name',
            'tpv_staff.last_name',
            'tpv_staff_roles.name AS role_name',
            'call_centers.call_center',
            'languages.language',
            'tpv_staff.status',
            'tpv_staff_groups.group',
            'tpv_staff.supervisor_id',
            'tpv_staff.manager_id',
            'tpv_staff.payroll_id'
        )->leftjoin(
            'tpv_staff_roles',
            'tpv_staff.role_id',
            'tpv_staff_roles.id'
        )->leftjoin(
            'call_centers',
            'call_centers.id',
            'tpv_staff.call_center_id'
        )->leftjoin(
            'languages',
            'languages.id',
            'tpv_staff.language_id'
        )->leftJoin(
            'tpv_staff_groups',
            'tpv_staff.tpv_staff_group_id',
            'tpv_staff_groups.id'
        )->where(
            'tpv_staff_roles.id',
            9
        )->withTrashed();

        if ($search != null) {
            $agents = $agents->search($search);
        }

        return $agents->orderBy($column, $direction)->paginate(20);
    }

    public function addclient(Request $request, $id)
    {
        $tpv_staff = Cache::remember(
            'tpv_staff_' . $id,
            1800,
            function () use ($id) {
                return TpvStaff::select(
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
                    'status',
                    'timezone_id'
                )->find($id);
            }
        );

        if (!$tpv_staff->client_login) {
            $user = new User();
            $user->created_at = Carbon::now();
            $user->updated_at = Carbon::now();
            $user->first_name = $tpv_staff->first_name;
            $user->last_name = $tpv_staff->last_name;
            $user->username = $tpv_staff->username;
            $user->save();

            // Must create a brand_user
            $brand = Brand::select('id')->where('name', 'Forward Thinking Energy')->first();
            $brand_user = new BrandUser();
            $brand_user->created_at = Carbon::now();
            $brand_user->updated_at = Carbon::now();
            $brand_user->employee_of_id = $brand->id;
            $brand_user->works_for_id = $brand->id;
            $brand_user->user_id = $user->id;
            $brand_user->role_id = 1;
            $brand_user->save();

            // Add client_login reference
            $tpv_staff->client_login = $user->id;
            $tpv_staff->save();

            Cache::forget('tpv_staff_' . $id);
        }

        return redirect()->route('tpv_staff.edit', $tpv_staff->id);
    }

    public function updateTwilioAttributes($id, $taskqueues, $isGroupUpdate = false)
    {
        $tpv_staff = Cache::remember(
            'tpv_staff_' . $id,
            1800,
            function () use ($id) {
                return TpvStaff::select(
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
                )
                    ->where('id', $id)
                    ->first();
            }
        );

        $service_login = $tpv_staff->logins()->first();
        if (!$service_login) {
            info('ERROR: no logins exist for user: ' . $tpv_staff->id);

            return;
        }

        // echo "<pre>";
        // print_r($taskqueues);
        // exit();

        if ($isGroupUpdate) {
            $skills = $taskqueues;
        } else {
            $skills = [];
            if (
                isset($taskqueues)
                && count($taskqueues) > 0
            ) {
                for ($i = 0; $i < count($taskqueues); ++$i) {
                    if (strlen(trim($taskqueues[$i])) > 0) {
                        $btc = BrandTaskQueue::where('id', $taskqueues[$i])
                            ->first();
                        $skills[] = $btc->task_queue;
                    }
                }
            }
        }

        //$channels = array("phone");

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
            $location = CallCenter::where('id', $tpv_staff->call_center_id)->first();
            $attributes['location'] = strtolower($location->call_center);
        }

        try {
            $worker = $this->workspace
                ->workers($service_login->password)
                ->update(
                    array('attributes' => json_encode($attributes, true))
                );
        } catch (\Exception $e) {
        }

        Cache::forget('worker_' . $service_login->password);
    }

    public function createServiceLogin($id)
    {
        $tpv_staff = Cache::remember('tpv_staff_' . $id, 1800, function () use ($id) {
            return TpvStaff::select('id', 'hire_date', 'first_name', 'middle_name', 'last_name', 'username', 'call_center_id', 'language_id', 'role_id', 'client_login', 'status')
                ->where('id', $id)
                ->withTrashed()
                ->first();
        });

        //$channels = array("phone");
        if ($tpv_staff === null) {
            session()->flash('flash_message', 'Could not create service login, invalid tpv staff id. Most likely this is a temporary issue and you should try again.');

            return;
        }

        $languages = [];
        if ($tpv_staff->language_id) {
            switch ($tpv_staff->language_id) {
                case 1:
                    $languages = ['en'];
                    break;
                case 2:
                    $languages = ['es'];
                    break;
                case 3:
                    $languages = ['en', 'es'];
                    break;
            }
        }

        $attributes = array(
            //"channels" => $channels,
            'languages' => $languages,
            'contact_uri' => 'client:' . $tpv_staff->username,
        );

        if ($tpv_staff->call_center_id) {
            $location = CallCenter::where('id', $tpv_staff->call_center_id)->first();
            $attributes['location'] = strtolower($location->call_center);
        }

        try {
            $worker = $this->workspace->workers->create(
                $tpv_staff->username,
                ['attributes' => json_encode($attributes, true)]
            );

            $password = $worker->sid;
        } catch (\Exception $e) {
            $worker = $this->client->taskrouter->v1->workspaces($this->workspace_id)->workers
                ->read(
                    [
                        'FriendlyName' => $tpv_staff->username,
                    ]
                );

            $password = $worker[0]->sid;
        }

        $service_login = $tpv_staff->logins()->first();
        if (!$service_login) {
            $sl = new ServiceLogin();
            $sl->created_at = Carbon::now();
            $sl->updated_at = Carbon::now();
            $sl->tpv_staff_id = $tpv_staff->id;
            $sl->service_type_id = 3;
            $sl->username = $tpv_staff->username;
            $sl->motion_username = (strtolower(trim($tpv_staff->first_name)) . "." . strtolower(trim($tpv_staff->last_name)));

            if (isset($worker)) {
                $sl->password = $password;
            }

            $sl->save();
        }
    }

    public function addservicelogin(Request $request, $id)
    {
        $this->createServiceLogin($id);

        return redirect()->route('tpv_staff.edit', $id);
    }

    public function timeclock_mgmt(Request $request, TpvStaff $staff)
    {
        $now = Carbon::now('America/Chicago');
        $date = $request->has('date') ? Carbon::parse($request->input('date') . ' 00:00:00', 'America/Chicago') : $now;

        if ($date->isAfter($now)) {
            abort(400, 'Invalid Date, cannot edit future time punches');
        }

        $punches = TimeClock::whereDate('time_punch', $date)->where('tpv_staff_id', $staff->id)->orderBy('time_punch', 'asc')->get();

        return view('generic-vue')->with(
            [
                'componentName' => 'time-clock-mgmt',
                'title' => 'Time Clock',
                'parameters' => [
                    'punches' => json_encode($punches),
                    'active-date' => json_encode($date->format('Y-m-d')),
                    'tpv-staff' => json_encode($staff),
                    'error' => json_encode(session('error')),
                ]
            ]
        );
    }

    public function timeclock_rm_punch(Request $request, TimeClock $punch)
    {
        $activeDate = $request->input('active_date');
        $user = Auth::user();

        info('Removing time punch', ['punch' => $punch->toArray(), 'input' => $request->input(), 'user' => $user]);

        if (!empty($user)) {
            if ($punch->tpv_staff_id !== Auth::id()) {
                $punch->comment = 'Deleted by ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->username . ')';
            }
            $punch->synced = 0;
            $punch->save();
            $punch->delete();

            Artisan::queue('stats:agent', [
                '--agent' => $punch->tpv_staff_id,
                '--date' => $punch->created_at->format('Y-m-d')
            ]);
        }

        return redirect('/tpv_staff/' . $punch->tpv_staff_id . '/time?date=' . $activeDate);
    }

    public function timeclock_update_punch(Request $request, TimeClock $punch)
    {
        $request->validate([
            'hour' => 'required|integer|min:0|max:23',
            'minutes' => 'required|integer|min:0|max:59'
        ]);

        $activeDate = $request->input('active_date');

        $user = Auth::user();

        info('Updating time punch', ['punch' => $punch->toArray(), 'input' => $request->input(), 'user' => $user]);

        if (!empty($user)) {
            $pdate = Carbon::parse($punch->time_punch, 'America/Chicago');
            $pdate->hour = intval($request->input('hour'));
            $pdate->minute = intval($request->input('minutes'));
            $pdate->second = 0;
            $punch->time_punch = $pdate->format('Y-m-d H:i:s');
            $punch->synced = 0;
            if ($punch->tpv_staff_id !== Auth::id()) {
                $punch->comment = 'Updated by ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->username . ')';
            }
            $existing = TimeClock::where('tpv_staff_id', $punch->tpv_staff_id)->where('time_punch', 'like', $pdate->format('Y-m-d H:i') . ':%')->first();
            if (!empty($existing)) {
                return back()->with(['error' => 1]);
            }
            $punch->save();

            Artisan::queue('stats:agent', [
                '--agent' => $punch->tpv_staff_id,
                '--date' => $pdate->format('Y-m-d')
            ]);
        }
        return redirect('/tpv_staff/' . $punch->tpv_staff_id . '/time?date=' . $activeDate);
    }

    public function timeclock_add_punch(Request $request, TpvStaff $staff, string $idate)
    {
        $request->validate([
            'type' => ['required', 'string', Rule::in(['meal', 'normal'])],
        ]);

        $pdate = Carbon::parse($idate . ' 00:00:00', 'America/Chicago');
        $now = Carbon::now('America/Chicago');
        $user = Auth::user();
        $punchType = $request->input('type');

        info('adding time punch', ['idate' => $idate, 'staff' => $staff->toArray(), 'input' => $request->input(), 'user' => $user]);

        if (!empty($user)) {
            $pdate->hour = $now->hour;
            $pdate->minute = $now->minute;
            $pdate->second = 0;
            $punch = new TimeClock();
            $punch->tpv_staff_id = $staff->id;
            if ($punchType === 'meal') {
                $punch->agent_status_type_id = 18;
            } else {
                $punch->agent_status_type_id = 41;
            }
            $punch->time_punch = $pdate->format('Y-m-d H:i:s');
            if ($staff->id !== Auth::id()) {
                $punch->comment = 'Added by ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->username . ')';
            }
            $existing = TimeClock::where('tpv_staff_id', $staff->id)->where('time_punch', 'like', $pdate->format('Y-m-d H:i') . ':%')->first();
            if (!empty($existing)) {
                return back()->with(['error' => 1]);
            }
            $punch->save();

            Artisan::queue('stats:agent', [
                '--agent' => $punch->tpv_staff_id,
                '--date' => $pdate->format('Y-m-d')
            ]);
        }

        return redirect('/tpv_staff/' . $staff->id . '/time?date=' . $idate);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!request()->ajax()) {
            return view('tpv_staff.create');
        }

        $roles = Cache::remember('roles', 1800, function () {
            return TpvStaffRole::select('id', 'name', 'dept_id')->orderBy('name')->get();
        });

        $call_centers = Cache::remember('call_centers', 1800, function () {
            return CallCenter::select('id', 'call_center')->orderBy('call_center')->get();
        });

        $depts = Cache::remember('tpv_staff_departments', 1800, function () {
            return TpvStaffDepartment::select('id', 'name')->orderBy('name')->get();
        });

        $languages = Cache::remember('languages', 1800, function () {
            return Language::select('id', 'language')->orderBy('language')->get();
        });

        $supervisors = Cache::remember('supervisors', 3600, function () {
            return TpvStaff::select('id', DB::raw("CONCAT(`first_name`, ' ', `last_name`) as name"))->where('status', 1)->whereIn('role_id', array(1, 3, 5, 8, 10))->orderByRaw('FIELD(`role_id`, 8, 3, 5, 10, 1)')->get();
        });

        $timezones = Cache::remember('timezones', 1800, function () {
            return Timezone::select('id', 'timezone')->orderBy('timezone')->get();
        });

        return [
            'roles' => $roles,
            'call_centers' => $call_centers,
            'depts' => $depts,
            'languages' => $languages,
            'supervisors' => $supervisors,
            'timezones' => $timezones,
        ];
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
        $rules = array(
            'first_name' => 'required',
            'last_name' => 'required',
            'username' => 'required|unique:tpv_staff',
            'password' => 'required',
            'role_id' => 'required|exists:tpv_staff_roles,id',
            'timezone_id' => 'required|exists:timezones,id',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('tpv_staff.create')
                ->withErrors($validator)
                ->withInput();
        } else {
            $tpv_staff = new TpvStaff();
            $tpv_staff->hire_date = NOW();
            $tpv_staff->first_name = $request->first_name;
            $tpv_staff->middle_name = $request->middle_name;
            $tpv_staff->last_name = $request->last_name;
            $tpv_staff->username = $request->username;
            $tpv_staff->password = bcrypt($request->password);
            $tpv_staff->call_center_id = $request->call_center_id;
            $tpv_staff->language_id = $request->language_id;
            $tpv_staff->role_id = $request->role_id;
            $tpv_staff->status = 1;
            $tpv_staff->supervisor_id = $request->supervisor_id;
            $tpv_staff->manager_id = $request->manager_id;
            $tpv_staff->payroll_id = $request->tpv_staff_payroll_id;
            $tpv_staff->timezone_id = $request->timezone_id;
            $tpv_staff->save();

            $this->createServiceLogin($tpv_staff->id);

            Cache::forget('agent_groups_with_count');

            session()->flash('flash_message', 'TPV Staff was successfully added!');

            return redirect()->route('tpv_staff.edit', $tpv_staff->id);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     * w.
     *
     * @param TpvStaff $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        if (!$request->ajax()) {
            return view('tpv_staff.edit');
        }

        $tpv_staff = TpvStaff::select(
            'tpv_staff.id',
            'hire_date',
            'tpv_staff_roles.dept_id',
            'first_name',
            'middle_name',
            'last_name',
            'username',
            'call_center_id',
            'language_id',
            'role_id',
            'client_login',
            'status',
            'tpv_staff_group_id',
            'supervisor_id',
            'manager_id',
            'tpv_staff.payroll_id',
            'tpv_staff.timezone_id'
        )->leftJoin(
            'tpv_staff_roles',
            'tpv_staff_roles.id',
            'tpv_staff.role_id'
        )->where(
            'tpv_staff.id',
            $id
        )->withTrashed()->first();

        if ($tpv_staff === null) {
            return [
                'error' => 'Invalid tpv staff id',
            ];
        }

        $phones = PhoneNumberLookup::select(
            'phone_number_lookup.id',
            'phone_numbers.phone_number'
        )->join(
            'phone_numbers',
            'phone_number_lookup.phone_number_id',
            'phone_numbers.id'
        )->where(
            'phone_number_type_id',
            5
        )->where(
            'phone_number_lookup.type_id',
            $id
        )->get();
        if ($tpv_staff && count($phones) > 0) {
            $tpv_staff->phones = $phones;
        }

        $emails = EmailAddressLookup::select(
            'email_address_lookup.id',
            'email_addresses.email_address'
        )->join(
            'email_addresses',
            'email_address_lookup.email_address_id',
            'email_addresses.id'
        )->where(
            'email_address_type_id',
            5
        )->where(
            'email_address_lookup.type_id',
            $id
        )->get();
        if ($tpv_staff && count($emails) > 0) {
            $tpv_staff->emails = $emails;
        }

        $roles = Cache::remember('roles', 1800, function () {
            return TpvStaffRole::select('id', 'name', 'dept_id')->orderBy('name')->get();
        });

        $call_centers = Cache::remember('call_centers', 1800, function () {
            return CallCenter::select('id', 'call_center')->orderBy('call_center')->get();
        });

        $depts = Cache::remember('tpv_staff_departments', 1800, function () {
            return TpvStaffDepartment::select('id', 'name')->orderBy('name')->get();
        });

        $languages = Cache::remember('languages', 1800, function () {
            return Language::select('id', 'language')->orderBy('language')->get();
        });

        $timezones = Cache::remember('timezones', 1800, function () {
            return Timezone::select('id', 'timezone')->orderBy('timezone')->get();
        });

        $groups = Cache::remember('agent_group_list', 1800, function () {
            return TpvStaffGroup::orderBy('group')->get();
        });

        $linked_user = Cache::remember(
            'linked_user_' . $tpv_staff->client_login,
            1800,
            function () use ($tpv_staff) {
                return User::where('id', $tpv_staff->client_login)->first();
            }
        );

        $supervisors = Cache::remember('supervisors', 3600, function () {
            return TpvStaff::select('id', DB::raw("CONCAT(`first_name`, ' ', `last_name`) as name"))->whereIn('role_id', array(1, 3, 5, 8, 10))->orderByRaw('FIELD(`role_id`, 8, 3, 5, 10, 1)')->get();
        });

        $service_login = $tpv_staff->logins()->first();
        $taskqueue = BrandTaskQueue::select(
            'brand_task_queues.id',
            'brand_task_queues.task_queue',
            'brand_task_queues.task_queue_sid'
        )->leftJoin(
            'brands',
            'brand_task_queues.brand_id',
            'brands.id'
        )->whereNull(
            'brands.deleted_at'
        )->orderBy('task_queue')->get();

        $worker = null;
        if ($service_login) {
            $workspaces = $this->workspace;
            $worker = Cache::remember('worker_' . $service_login->password, 1800, function () use ($service_login, $workspaces) {
                return $workspaces->workers($service_login->password)->fetch();
            });
        }

        $agent = false;
        if ($request->query('agent')) {
            $agent = true;
        }

        return [
            'agent' => $agent,
            'call_centers' => $call_centers,
            'depts' => $depts,
            'languages' => $languages,
            'linked_user' => $linked_user,
            'groups' => $groups,
            'roles' => $roles,
            'service_login' => $service_login,
            'taskqueue' => $taskqueue,
            'tpv_staff' => $tpv_staff,
            'worker' => ($worker && $worker->attributes) ? json_decode($worker->attributes) : [],
            'supervisors' => $supervisors,
            'timezones' => $timezones,
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param TpvStaff                 $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = array(
            'first_name' => 'required',
            'last_name' => 'required',
            'role_id' => 'required|exists:tpv_staff_roles,id',
            'timezone_id' => 'required|exists:timezones,id',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('tpv_staff.edit', $id)
                ->withErrors($validator)
                ->withInput();
        } else {
            $tpv_staff = TpvStaff::withTrashed()->find($id);
            $tpv_staff->first_name = $request->first_name;
            $tpv_staff->middle_name = $request->middle_name;
            $tpv_staff->last_name = $request->last_name;

            if (!empty($request->password) && !is_null($request->password)) {
                $tpv_staff->password = bcrypt($request->password);
            }

            $tpv_staff->call_center_id = $request->call_center_id;
            $tpv_staff->language_id = $request->language_id;
            $tpv_staff->role_id = $request->role_id;
            $tpv_staff->supervisor_id = $request->supervisor_id;
            $tpv_staff->manager_id = $request->manager_id;
            $isGroupChange = $tpv_staff->tpv_staff_group_id != $request->tpv_staff_group_id;
            $tpv_staff->tpv_staff_group_id = $request->tpv_staff_group_id;
            $tpv_staff->payroll_id = $request->tpv_staff_payroll_id;
            $tpv_staff->timezone_id = $request->timezone_id;
            $tpv_staff->save();

            $service_login = $tpv_staff->logins()->first();

            // echo "<pre>";
            // print_r($request->taskqueues);
            // exit();

            if ($service_login) {
                if ($isGroupChange) {
                    $group = TpvStaffGroup::find($tpv_staff->tpv_staff_group_id);
                    if ($group) {
                        $this->updateTwilioAttributes($tpv_staff->id, $group->config['skills'], true);
                        session()->flash('flash_message', 'TPV Staff skills updated.');
                    }
                } else {
                    $this->updateTwilioAttributes($tpv_staff->id, $request->taskqueues);
                    session()->flash('flash_message', 'TPV Staff skills updated.');
                }
            }

            if ($request->phone_number) {
                $phone = '+1' . preg_replace('/[^0-9]/', '', $request->phone_number);
                $exists = PhoneNumber::where('phone_number', $phone)->withTrashed()->first();

                if (!$exists) {
                    $pn = new PhoneNumber();
                    $pn->phone_number = $phone;
                    $pn->save();

                    $pnid = $pn->id;
                } else {
                    $pnid = $exists->id;
                }

                $pnlexists = PhoneNumberLookup::where('type_id', $tpv_staff->id)
                    ->where('phone_number_type_id', 5)
                    ->where('phone_number_id', $pnid)
                    ->withTrashed()
                    ->first();
                if (!$pnlexists) {
                    $pnl = new PhoneNumberLookup();
                    $pnl->phone_number_type_id = 5;
                    $pnl->type_id = $tpv_staff->id;
                    $pnl->phone_number_id = $pnid;
                    $pnl->save();
                } else {
                    $pnlexists->restore();
                }
            }

            if ($request->email_address) {
                $email = $request->email_address;
                $exists = EmailAddress::where('email_address', $email)
                    ->withTrashed()
                    ->first();
                if (!$exists) {
                    $ea = new EmailAddress();
                    $ea->email_address = $email;
                    $ea->save();

                    $eaid = $ea->id;
                } else {
                    $eaid = $exists->id;
                }

                $ealexists = EmailAddressLookup::where('type_id', $tpv_staff->id)
                    ->where('email_address_type_id', 5)
                    ->where('email_address_id', $eaid)
                    ->withTrashed()
                    ->first();
                if (!$ealexists) {
                    $eal = new EmailAddressLookup();
                    $eal->email_address_type_id = 5;
                    $eal->type_id = $tpv_staff->id;
                    $eal->email_address_id = $eaid;
                    $eal->save();
                } else {
                    $ealexists->restore();
                }
            }

            Cache::forget('tpv_staff_' . $id);
            Cache::forget('agent_groups_with_count');

            $msg = 'TPV Staff';
            $params = [$tpv_staff->id];
            if ($request->agent) {
                $params['agent'] = 'true';
                $msg = 'Agent';
            } else {
                $params['tpvStaff'] = 'true';
            }

            session()->flash('flash_message', $msg . ' was successfully edited!');

            return redirect()->route('tpv_staff.edit', $params);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $tpv_staff = TpvStaff::withTrashed()->find($id);

        if ($tpv_staff->client_login) {
            $user = User::where('id', $tpv_staff->client_login)->delete();
            $brand_user = BrandUser::where('user_id', $tpv_staff->client_login)->delete();
        }

        $sl = ServiceLogin::where('tpv_staff_id', $tpv_staff->id);

        if ($sl->first() && $sl->first()->password) {
            try {
                $worker = $this->workspace->workers($sl->first()->password)->delete();
            } catch (\Exception $e) {
            }

            $sl->forceDelete();
        }

        $tpv_staff->status = 0;
        $tpv_staff->save();
        $tpv_staff->delete();

        Cache::forget('tpv_staff_' . $id);
        Cache::forget('agent_groups_with_count');

        session()->flash('flash_message', $tpv_staff->first_name . ' ' . $tpv_staff->last_name . ' was successfully disabled!');

        return back();
    }

    public function restore($id)
    {
        $tpv_staff = TpvStaff::withTrashed()->find($id);

        if ($tpv_staff->client_login) {
            $user = User::withTrashed()->where('id', $tpv_staff->client_login)->restore();
            $brand_user = BrandUser::withTrashed()->where('user_id', $tpv_staff->client_login)->restore();
        }

        $sl = ServiceLogin::withTrashed()
            ->where('tpv_staff_id', $tpv_staff->id)
            ->forceDelete();

        $this->createServiceLogin($tpv_staff->id);

        $tpv_staff->status = 1;
        $tpv_staff->save();
        $tpv_staff->restore();

        session()->flash('flash_message', $tpv_staff->first_name . ' ' . $tpv_staff->last_name . ' was successfully enabled!');

        return back();
    }

    public function export()
    {
        $headers = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0', 'Content-type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=tpv_staff.csv', 'Expires' => '0', 'Pragma' => 'public',
        ];

        $list = TpvStaff::where('deleted_at', null)
            ->get()
            ->map(
                function ($item) {
                    return collect($item)->except(['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token']);
                }
            )->toArray();

        // add headers for each column in the CSV download
        array_unshift($list, array_keys($list[0]));

        $callback = function () use ($list) {
            $FH = fopen('php://output', 'w');
            foreach ($list as $row) {
                fputcsv($FH, $row);
            }
            fclose($FH);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function search(Request $request)
    {
        $search = trim($request->search);

        $tpv_staff = TpvStaff::select('tpv_staff.id', 'tpv_staff.created_at', 'tpv_staff.first_name', 'tpv_staff.last_name', 'tpv_staff_roles.name AS role_name')
            ->leftjoin('tpv_staff_roles', 'tpv_staff.role_id', '=', 'tpv_staff_roles.id')
            ->search($search)
            ->orderBy('tpv_staff.created_at', 'desc')
            ->paginate(30);

        return view('tpv_staff.tpv_staff', ['tpv_staff' => $tpv_staff, 'search' => $search]);
    }

    public function edit_permissions(TpvStaff $staff)
    {
        $perms = TpvStaffPermission::orderBy('short_name', 'ASC')->get();

        return view('tpv_staff.permissions')->with(['tpv_staff' => $staff, 'allperms' => $perms, 'role' => $staff->role]);
    }

    public function save_permissions(TpvStaff $staff)
    {
        $perms = request()->input('permissions');
        $user = $staff;
        try {
            DB::transaction(function () use ($user, $perms) {
                TpvStaffUserPermission::where('user_id', $user->id)->delete();
                foreach ($perms as $perm_name => $value) {
                    if (has_perm($perm_name)) {
                        $perm = TpvStaffPermission::where('short_name', $perm_name)->first();
                        if ($value == 'true') {
                            if ($user->role->permissions->where('perm_id', $perm->id)->count() == 0) {
                                $up = new TpvStaffUserPermission();
                                $up->user_id = $user->id;
                                $up->perm_id = $perm->id;
                                $up->is_revoked = false;
                                $up->save();
                            }
                            //if the user's role already assigns the permission don't duplicate it here
                        } else {
                            if ($user->role->permissions->where('perm_id', $perm->id)->count() != 0) {
                                //role has the permission but this user should not
                                $up = new TpvStaffUserPermission();
                                $up->user_id = $user->id;
                                $up->perm_id = $perm->id;
                                $up->is_revoked = true;
                                $up->save();
                            }
                        }
                    }
                }
            });
            Cache::forget('perms_for_user_' . $user->id);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e->getMessage()]);
        }

        return response()->json(['errors' => null]);
    }

    public function removePhone(TpvStaff $tpv_staff, PhoneNumberLookup $pnl)
    {
        $pnl->delete();

        Cache::forget('tpv_staff_phones_' . $tpv_staff->id);

        session()->flash('flash_message', 'Phone Number was successfully removed!');

        return redirect()->route('tpv_staff.edit', $tpv_staff->id);
    }

    public function removeEmail(TpvStaff $tpv_staff, EmailAddressLookup $eal)
    {
        $eal->delete();

        Cache::forget('tpv_staff_emails_' . $tpv_staff->id);

        session()->flash('flash_message', 'Email Address was successfully removed!');

        return redirect()->route('tpv_staff.edit', $tpv_staff->id);
    }
}
