<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LoginPermissionFillerEventHandler
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  Authenticated  $event
     * @return void
     */
    public function handle(Authenticated $event)
    {
        $perms = Cache::remember('user_perms_' . $event->user, 60, function () use ($event) {
            return get_perms($event->user);
        });

        $user = (object) Auth::user()->getAttributes();
        $user->permissions = $perms;
        session(['user' => $user]);
    }
}
