<?php

namespace App\Http\Controllers;

use function GuzzleHttp\json_encode;
use Illuminate\Support\Facades\Cache;

use Illuminate\Http\Request;
use App\Models\UserRole;
use App\Models\User;
use App\Models\Brand;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Cache::remember('search_form_roles', 3600, function () {
            return UserRole::select(['id', 'name'])->orderBy('name', 'asc')->get();
        });
        return view('generic-vue')->with(
            [
                'componentName' => 'brand-users-index',
                'title' => 'Brand Users',
                'parameters' => [
                    'roles' => $roles
                ]
            ]
        );
    }

    public function listSalesAgents(Request $request)
    {
        $column = $request->get('column') ?? 'created_at';
        $direction = $request->get('direction') ?? 'desc';
        $search = $request->get('search');
        $brand_id = $request->get('brand_id');
        $vendor_id = $request->get('vendor_id');
        $role_id = $request->get('role_id');
        $users = User::select(
            'users.id',
            'users.created_at',
            'users.first_name',
            'users.last_name',
            'brand_users.status',
            'brand_users.tsr_id',
            'user_roles.name AS role_name',
            'offices.name AS office_name',
            'b.name AS works_for',
            'v.name AS employee_of'
        )->join(
            'brand_users',
            'users.id',
            'brand_users.user_id'
        )->leftjoin(
            'user_roles',
            'brand_users.role_id',
            'user_roles.id'
        )->leftJoin(
            'brand_user_offices',
            'brand_users.id',
            'brand_user_offices.brand_user_id'
        )->leftJoin(
            'offices',
            'brand_user_offices.office_id',
            'offices.id'
        )->leftJoin(
            'brands AS v',
            'brand_users.employee_of_id',
            'v.id'
        )->leftJoin(
            'brands AS b',
            'brand_users.works_for_id',
            'b.id'
        );

        if ($search != null) {
            $users = $users->search($search);
        }

        if ($brand_id) {
            $users = $users->where(
                'brand_users.works_for_id',
                $brand_id
            );
        }

        if ($vendor_id) {
            $users = $users->where(
                'brand_users.employee_of_id',
                $vendor_id
            );
        }

        if ($role_id) {
            $users = $users->where('brand_users.role_id', $role_id);
        }

        $users = $users->whereNull(
            'brand_users.deleted_at'
        )
            ->whereNull('v.deleted_at')
            ->whereNull(
                'users.deleted_at'
            )->whereNull(
                'brand_user_offices.deleted_at'
            );

        $users = $users->orderBy($column, $direction);

        return $users->paginate(30);
    }

    public function loginAs(Request $request)
    {
        $dest = $request->dest;
        if ($request->id) {
            $user = User::find($request->id);

            User::disableAuditing();

            info('USER is ' . json_encode($user));

            // Add token
            $unique_id = bin2hex(random_bytes(7));
            $user->staff_token = $unique_id;
            $user->save();

            $url = config('app.urls.clients') . '/staff?loginAs=1&token=' .
                $unique_id . '&id=' . $request->id;

            if ($dest) {
                $url .= '&dest=' . $dest;
            }

            User::enableAuditing();

            return redirect($url);
        }
    }
}
