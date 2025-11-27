<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('Permissions', 'App\Helpers\Permissions');
    }
}
