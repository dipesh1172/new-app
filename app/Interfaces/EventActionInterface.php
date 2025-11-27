<?php

namespace App\Interfaces;

use App\Jobs\EventActionProcessor;
use App\Models\Events\EventType;

class EventActionInterface
{
    protected function do_event_actions(EventType $et, $vars)
    {
        dispatch(new EventActionProcessor($et, $vars));
    }
}
