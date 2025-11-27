<?php

namespace App\Http\Middleware;

use Closure;

class AuthIfHasOnePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $perm)
    {
        if (!has_perm($perm)) {
            abort(403);
        }
        return $next($request);
    }
}
