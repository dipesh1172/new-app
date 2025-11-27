<?php

namespace App\Events\Twilio;

use Illuminate\Foundation\Events\Dispatchable;

class RecordingCompleted
{
    use Dispatchable;

    public $accountSid;
    public $callSid;
    public $recordingSid;
    public $recordingUri;
    public $recordingStatus;
    public $recordingDuration;
    public $recordingChannels;
    public $recordingStartTime;
    public $recordingSource;

    /**
     * Create a new event instance.
     */
    public function __construct($msg)
    {
        $this->accountSid = $msg['AccountSid'];
        $this->callSid = $msg['CallSid'];
        $this->recordingSid = $msg['RecordingSid'];
        $this->recordingUri = $msg['RecordingUrl'];
        $this->recordingStatus = $msg['RecordingStatus'];
        $this->recordingDuration = $msg['RecordingDuration'];
        $this->recordingChannels = $msg['RecordingChannels'];
        $this->recordingStartTime = $msg['RecordingStartTime'];
        $this->recordingSource = $msg['RecordingSource'];
    }
}
