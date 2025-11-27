<?php

namespace App\Http\Middleware;

use Closure;

class AuthIfHasAllPermissions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $perms)
    {
        $operms = explode('|', $perms);
        if (!all_perm_in($operms)) {
            abort(403);
        }
        return $next($request);
    }
}
