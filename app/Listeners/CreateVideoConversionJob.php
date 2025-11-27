<?php

namespace App\Listeners;

use App\Events\VideoUploaded;
use App\Jobs\StartVideoConversion;
use App\Models\KB\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateVideoConversionJob implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     *
     * @param  VideoUploaded  $event
     * @return void
     */
    public function handle(VideoUploaded $event)
    {
        dispatch(new StartVideoConversion($event->video));
    }
}
