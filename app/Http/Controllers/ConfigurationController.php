<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Models\TpvStaffRolePermission;
use App\Models\TpvStaffRole;
use App\Models\TpvStaffPermission;
use App\Models\TpvStaffDepartment;
use App\Models\RuntimeSetting;
use App\Models\InvoiceableType;
use App\Models\BrandServiceType;
use App\Models\BrandService;
use App\Models\Brand;
use App\Models\Alert;

class ConfigurationController extends Controller
{
    public static function routes()
    {
        Route::prefix('/config')->middleware(['auth'])->group(function () {
            Route::get('/', 'ConfigurationController@index')->name('config.index');
            Route::get('permissions', 'ConfigurationController@permissionsIndex')->name('config.permissions');
            Route::post('permissions', 'ConfigurationController@add_permission');
            Route::get('departments', 'ConfigurationController@departmentsIndex')->name('config.departments');
            Route::get('list_departments', 'ConfigurationController@list_departments')->name('config.list_departments');

            Route::get('departments/{dept}/role/{role}', 'ConfigurationController@editRole')->name('config.edit_role');
            Route::patch('departments/{dept}/role/{role}', 'ConfigurationController@saveRole')->name('config.save_role');
            Route::get('departments/{dept}/role/{role}/del_dept', 'ConfigurationController@deleteRole')->name('config.role_delete');
            Route::post('departments/{dept}/role', 'ConfigurationController@newRole')->name('config.role_create');
            Route::get('departments/{dept}', 'ConfigurationController@editRoles')->name('config.roles');
            Route::get('departments/{dept}/list_roles_by_dept', 'ConfigurationController@list_roles_by_dept')->name('config.list_roles_by_dept');

            Route::get('runtime', 'ConfigurationController@runtimeSettingsIndex')->name('config.runtime_settings');
            Route::post('runtime', 'ConfigurationController@runtimeSettingsUpdate')->name('config.save_runtime_settings');

            Route::get('site-alerts', 'ConfigurationController@alertsIndex')->name('config.alerts');
            Route::get('site-alerts/new', 'ConfigurationController@alertsNew');
            Route::get('site-alerts/{alert}', 'ConfigurationController@alertsEdit')->name('config.alert-edit');
            Route::post('site-alerts/store', 'ConfigurationController@alertsStore');

            Route::get('brand_service_types', 'ConfigurationController@bstIndex')->name('config.bst');
            Route::post('brand_service_types', 'ConfigurationController@bstList')->name('config.bst_list');
            Route::post('brand_service_type', 'ConfigurationController@bstSave')->name('config.bst_save');
            Route::delete('brand_service_type', 'ConfigurationController@bstRemove')->name('config.bst_remove');
        });
    }

    public function index()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'config-index',
                'title' => 'Company Configuration'
            ]
        );
    }

    public function bstIndex()
    {
        $its = InvoiceableType::all();
        return view('config.bst_index')->with(['invoiceableTypes' => $its]);
    }

    public function bstList()
    {
        $column = request()->input('column');
        if (!in_array($column, ['name', 'description', 'pricing_type', 'invoicable_type_id'])) {
            $column = 'name';
        }
        $dir = request()->input('dir');
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        $results = BrandServiceType::orderBy($column, $dir);

        return response()->json($results->paginate());
    }

    public function bstRemove()
    {
        $record = BrandServiceType::find(request()->input('id'));
        if ($record == null) {
            abort(400);
        }
        $uses = BrandService::where('brand_service_type_id', $record->id)->count();

        if ($uses > 0) {
            session()->flash('message', 'Cannot delete type (' . $record->name . ') because it is currently in use by ' . $uses . ' brands.');
            return redirect('/config/brand_service_types');
        }

        $record->delete();

        session()->flash('message', 'Brand Service Type (' . $record->name . ') deleted.');

        return redirect('/config/brand_service_types');
    }

    public function bstSave()
    {
        $validated = request()->validate([
            'id' => 'nullable|exists:brand_service_types',
            'name' => 'required|max:50',
            'description' => 'required',
            'pricing_type' => 'required|in:fixed,per-use,other',
            'invoiceable_type_id' => 'nullable|exists:invoiceable_types,id'
        ]);
        $id = $validated['id'];
        $name = $validated['name'];
        $desc = $validated['description'];
        $pType = $validated['pricing_type'];
        $invoicableType = $validated['invoiceable_type_id'];

        if ($id !== null) {
            $record = BrandServiceType::find($id);
        } else {
            $record = new BrandServiceType();
        }

        $record->name = $name;
        $record->description = $desc;
        $record->pricing_type = $pType;
        $record->invoiceable_type_id = $invoicableType;

        $record->save();

        return redirect('/config/brand_service_types');
    }

    public function runtimeSettingsIndex()
    {
        $settings = RuntimeSetting::orderBy('namespace', 'ASC')->orderBy('name', 'ASC')->get()->groupBy('namespace')->toArray();
        return view('config.runtime-settings')->with(['settings' => $settings]);
    }

    public function runtimeSettingsUpdate(Request $request)
    {
        $input = $request->input();
        foreach ($input as $key => $value) {
            if (str_contains($key, ':')) {
                continue;
            }
            $components = explode('-', $key);
            if (count($components) != 2) {
                continue;
            }
            $namespace = $components[0];
            $settingName = $components[1];
            $setting = RuntimeSetting::where('namespace', $namespace)->where('name', $settingName)->first();
            if ($setting) {
                if ($namespace == 'system' && $settingName == 'high_volume' && $value > 1) {
                    $setting->value = $request->input($namespace . '-' . $settingName . ':custom');
                } else {
                    $setting->value = $value;
                }
                $setting->save();
                Cache::forget('runtime_setting_' . $settingName);
            }
        }

        $request->session()->flash('status', 'Settings updated.');

        return redirect('/config/runtime');
    }

    public function alertsIndex()
    {
        $alerts = Alert::all();
        $brands = Brand::whereNotNull('client_id')->get();
        return view('config.alerts')->with(['alerts' => $alerts, 'brands' => $brands]);
    }

    public function alertsEdit(Request $request, Alert $alert)
    {
        $brands = Brand::whereNotNull('client_id')->orderBy('name')->get();
        return view('config.alert-edit')->with(['alert' => $alert, 'brands' => $brands]);
    }

    public function alertsNew()
    {
        $brands = Brand::whereNotNull('client_id')->orderBy('name')->get();
        return view('config.alert-edit')->with(['alert' => null, 'brands' => $brands]);
    }

    public function alertsStore(Request $request)
    {

        $validatedData = $request->validate([
            'scope' => 'required',
            'title' => 'required',
            'alert' => 'required',
        ], [
            'scope.required' => 'Scope is required.',
            'title.required' => 'Title is required.',
            'alert.required' => 'Message is requried.'
        ]);

        $id = $request->id;
        $command = $request->command;

        if ($command == 'delete' && $id !== null) {
            Alert::find($id)->delete();
            return redirect('/config/site-alerts');
        }

        $alert = null;
        if ($id == null) {
            $alert = new Alert();
        } else {
            $alert = Alert::find($id);
        }

        $alert->scope = $request->scope;
        $alert->brand_id = $request->brand_id;
        $alert->title = $request->title;
        $alert->alert = $request->alert;
        $alert->alert_type = '';

        $alert->save();
        return redirect('/config/site-alerts');
    }

    public function permissionsIndex()
    {
        return view('config.permissions')->with(['permissions' => TpvStaffPermission::all()]);
    }

    public function add_permission()
    {
        $validated = request()->validate([
            'short_name' => 'required|unique:tpv_staff_permissions,short_name|max:50',
            'friendly_name' => 'max:255',
            'description' => 'required|max:1024'
        ]);

        $perm = new TpvStaffPermission();
        $perm->short_name = request()->input('short_name');
        $perm->friendly_name = request()->input('friendly_name');
        $perm->description = request()->input('description');
        $perm->save();

        return redirect('/config/permissions');
    }

    public function departmentsIndex()
    {
        return view('config.departments.list');
    }

    public function list_departments()
    {
        $depts = TpvStaffDepartment::orderBy('name')->get()->map(function ($dept) {
            $dept->roles = $dept->roleCount();
            $dept->members = $dept->memberCount();
            $dept->head_name = ($dept->head) ? $dept->head->name : null;
            return $dept;
        });
        return $depts;
    }

    public function editRoles()
    {
        return view('config.departments.roles-list');
    }

    public function list_roles_by_dept(TpvStaffDepartment $dept)
    {
        $roles = TpvStaffRole::where(
            'dept_id',
            $dept->id
        )->orderBy('name')->get()->map(function ($r) {
            $r->members = $r->memberCount();
            return $r;
        });
        return [
            'dept' => $dept,
            'roles' => $roles
        ];
    }

    public function editRole(TpvStaffDepartment $dept, TpvStaffRole $role)
    {
        return view('config.departments.role-edit')->with([
            'role' => $role,
            'dept' => $dept,
            'allperms' => TpvStaffPermission::orderBy('short_name', 'asc')->get()
        ]);
    }

    public function deleteRole($dept, TpvStaffRole $role)
    {
        if ($role->memberCount() == 0) {
            $role->delete();
            return redirect()->back()->with('flash_message', 'The role has been successfully deleted.');
        } else {
            return redirect()->back()->withErrors(['errors' => ['The role is in use.']]);
        }
    }

    public function newRole($dept)
    {
        $rolename = request()->input('rolename');
        if ($rolename && strlen($rolename) > 0) {
            $exist = TpvStaffRole::where(
                'name',
                $rolename
            )->where(
                'dept_id',
                $dept
            )->first();
            if ($exist) {
                return back()->withInput()->withErrors(['msg' => ['This role name exist for department with id:' . $dept]]);
            }
        }
        if (strlen($rolename) == 0) {
            return response()->json(['rolename' => ['Role name cannot be empty']], 422);
        }
        $role = new TpvStaffRole();
        $role->name = $rolename;
        $role->dept_id = $dept;
        $role->save();
        return redirect()->route('config.edit_role', ['dept' => $dept, 'role' => $role->id]);
    }

    public function saveRole($dept, TpvStaffRole $role)
    {
        $rolename = request()->input('rolename');
        $jobdesc = request()->input('jobdesc');
        $perms = request()->input('permissions');
        //dd(request()->input());
        $errors = [];
        if (strlen($rolename) == 0) {
            $errors['rolename'][] = 'Role name cannot be empty';
        }
        /*if (strlen($jobdesc) == 0) {
            $errors['jobdesc'][] = 'Job Description cannot be empty';
        }*/

        if (count($errors) > 0) {
            return response()->json($errors, 422);
        }
        try {
            DB::transaction(function () use ($role, $rolename, $jobdesc, $perms) {
                $checkDelete = TpvStaffRolePermission::where('role_id', $role->id)->get();
                foreach ($checkDelete as $check) {
                    if (array_search($check->short_name, $perms) === false) {
                        if (!has_perm($check->short_name)) {
                            throw new \Exception('Cannot restrict privileges that are not granted to you.');
                        }
                    }
                }
                TpvStaffRolePermission::where('role_id', $role->id)->delete();

                if (!empty($perms)) {

                    foreach ($perms as $perm_name => $value) {
                        if (!has_perm($perm_name)) {
                            throw new \Exception('Cannot grant privileges that are not granted on you.');
                        }
                        $perm = TpvStaffPermission::where('short_name', $perm_name)->first();
                        if ($perm != null) {
                            info('creating role permission');
                            $rp = new TpvStaffRolePermission();
                            $rp->role_id = $role->id;
                            $rp->perm_id = $perm->id;
                            $rp->save();
                        } else {
                            info('permission ' . $perm_name . ' not found');
                        }
                    }
                }


                $role->name = $rolename;
                $role->description = $jobdesc;
                $role->save();
            });
            Cache::forget('perms_for_role_' . $role->id);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e->getMessage()], 200);
        }

        //return response()->json(['errors' => null], 200);
        return redirect()->route('config.roles', $dept);
    }
}
