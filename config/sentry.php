<?php

return array(
    'dsn' => env('SENTRY_DSN'),

    // capture release as git sha
    'release' => env('TPV_RELEASE_VERSION', trim(exec('git log --pretty="%h" -n1 HEAD'))),

    // Capture bindings on SQL queries
    'breadcrumbs.sql_bindings' => true,

    'send_default_pii' => true,
    'environment' => env('APP_ENV'),

);
