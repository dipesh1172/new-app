<?php

namespace App\Listeners;

use App\Events\Twilio\RecordingCompleted;

class DispatchRecordingTransfer
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param RecordingCompleted $event
     */
    public function handle(RecordingCompleted $event)
    {
        dispatch(new App\Jobs\RetrieveTwilioRecording($event));
    }
}
