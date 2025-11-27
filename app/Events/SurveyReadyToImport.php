<?php

namespace App\Events;

use App\Models\Upload;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class SurveyReadyToImport
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $upload;
    public $script_id;
    public $brand;
    public $state;

    /**
     * Create a new event instance.
     */
    public function __construct(Upload $upload, $script_id, $brand, $state)
    {
        info('SurveyReadyToImport Event was triggered.');

        $this->upload = $upload;
        $this->script_id = $script_id;
        $this->brand = $brand->id;
        $this->state = $state;
    }
}
