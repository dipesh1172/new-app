<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use App\Models\TpvStaffPermission;

class Permissions
{
    public function hasPermission($perm, $user = null)
    {

        if ($user === null) {
            $user = Auth::user();
        }
        if ($user == null) {
            return false;
        }

        /*if ($user->id === env('TPV_ADMIN')) {
            return true;
        }*/

        if (is_numeric($perm)) {
            try {
                $perm = TpvStaffPermission::find($perm)->short_name;
            } catch (\Exception $e) {
                return false;
            }
        }

        if (Auth::check() && $user->id == Auth::user()->id) {
            $perms = session('user.permissions', null);
        } else {
            $perms = null;
        }

        if ($perms == null) {
            $perms = get_perms($user);
        }
        if ($perms == null) {
            return false;
        }
        $cval = array_search($perm, $perms);
        return $cval !== false;
    }
}
