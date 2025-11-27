<?php

use App\Facades\Permissions;
use App\Models\TpvStaff;
use App\Models\TpvStaffPermission;
use App\Models\TpvStaffRolePermission;
use App\Models\TpvStaffUserPermission;
use Illuminate\Support\Facades\Cache;

/**
 * Checks if the user has the speficied permission
 * @param  mixed         $pname the permission to check for or the id of the permission to check for
 * @param  \App\Models\TpvStaff|null $user  if null check the authorized user otherwise check the specified user
 * @return bool                  true if the user has permission, false otherwise
 */
function has_perm($pname, TpvStaff $user = null): bool
{
    return Permissions::hasPermission($pname, $user);
}

function permission_info($pname)
{
    if (is_numeric($pname)) {
        return TpvStaffPermission::find($pname);
    } else {
        return TpvStaffPermission::where('short_name', $pname)->first();
    }
}

/**
 * Retrieves an array of all permissions the specified user has
 * @param  TpvStaff $user The user to gather permissions for
 * @return array
 */
function get_perms(TpvStaff $user): array
{
    $rolePerms = TpvStaffRolePermission::where('role_id', $user->role_id)->get();
    $userPerms =  TpvStaffUserPermission::where('user_id', $user->id)->get();

    $perms = [];
    foreach ($rolePerms as $rp) {
        if ($rp->permission !== null) {
            $perms[] = $rp->permission->short_name;
        }
    }

    foreach ($userPerms as $up) {
        if ($up->revoked == 1 || $up->revoked == true) {
            for ($i = 0, $len = count($perms); $i < $len; $i++) {
                if ($perms[$i] == $up->permission->short_name) {
                    unset($perms[$i]);
                    break;
                }
            }
            $perms = array_values($perms);
        } else {
            $perms[] = $up->permission->short_name;
        }
    }

    return $perms;
}

/**
 * Required permission or abort
 * @param  mixed         $pname the permission to check for or the id of the permission to check for
 * @param  \App\Models\TpvStaff|null $user  if null check the authorized user otherwise check the specified user
 */
function req_perm($pname, TpvStaff $user = null)
{
    if (!TpvStaffPermissions::hasPermission($pname, $user)) {
        request()->session()->flash('error', 'No Permission');
        request()->session()->flash('error_detail', $pname);
        abort(403);
    }
}

/**
 * Checks if the user has any of the permissions in the array
 * @param  array          $permissions simple array of strings
 * @param  \App\Models\TpvStaff|null $user        if null check the authorized user otherwise check the specified user
 * @return bool                        true if the user has any permission specified or false
 */
function any_perm_in(array $permissions, TpvStaff $user = null): bool
{
    $found = false;
    foreach ($permissions as $perm) {
        $found = has_perm($perm, $user);
        if ($found) {
            break;
        }
    }
    return $found;
}

/**
 * Checks if the user has ALL of the permissions in the array
 * @param  array          $permissions simple array of strings
 * @param  \App\Models\TpvStaff|null $user        if null check the authorized user otherwise check the specified user
 * @return bool                        true if the user has any permission specified or false
 */
function all_perm_in(array $permissions, TpvStaff $user = null): bool
{
    $found = false;
    foreach ($permissions as $perm) {
        $found = has_perm($perm, $user);
        if (!$found) {
            break;
        }
    }
    return $found;
}
