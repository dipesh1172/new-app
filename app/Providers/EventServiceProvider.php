<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Illuminate\Auth\Events\Authenticated' => [
            'App\Listeners\LoginPermissionFillerEventHandler',
        ],
        'App\Events\VideoUploaded' => [
            'App\Listeners\CreateVideoConversionJob',
        ],
        'App\Events\Amazon\SnsNotification' => [
            'App\Listeners\VideoProgress',
        ],
        'App\Events\Twilio\RecordingCompleted' => [
            'App\Listeners\DispatchRecordingTransfer',
        ],
        'App\Events\SurveyReadyToImport' => [
            'App\Listeners\ImportSurvey',
        ],
        'App\Events\ProductStatsToProcess' => [
            'App\Listeners\ProductStatsNG',
        ],
        'App\Events\ProductlessStatsToProcess' => [
            'App\Listeners\ProductlessStatsNG',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot()
    {
        parent::boot();
    }
}
