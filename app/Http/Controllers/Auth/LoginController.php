<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use App\Models\TpvStaff;
use App\Http\Controllers\Controller;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function username()
    {
        return 'username';
    }

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    // protected function credentials(Request $request)
    // {
    //     error_log("getting here...");

    //     $field = filter_var($request->get($this->username()), FILTER_VALIDATE_EMAIL)
    //         ? $this->username()
    //         : 'username';

    //     return [
    //         $field => $request->get($this->username()),
    //         'password' => $request->password,
    //     ];
    // }

    public function validateLogin(Request $request)
    {
        $request->validate([
            $this->username() => [
                'required',
                'string',
                function ($attr, $value, $fail) {
                    $staff = TpvStaff::where('username', $value)->with(['role'])->first();
                    if ($staff) { // we aren't checking for anything but the role here because other items are checked elsewhere
                        if ($staff->role_id !== null && $staff->role !== null) {
                            if ($staff->role->name === 'Agent') {
                                $fail('TPV Agents are not allowed to login to this portal.');
                            }
                        }
                    }
                },
            ],
            'password' => 'required|string',
        ]);
    }

    /**
     * Logout the user and clear cache (if applicable).
     */
    public function logout(TpvStaff $user)
    {
        Cache::forget('user_perms_'.$user->id);
        Auth::logout();

        return redirect('login');
    }
}
