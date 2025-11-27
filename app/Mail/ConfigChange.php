<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ConfigChange extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private $data;
    public $passed_subject;

    /**
     * Create a new message instance.
     */
    public function __construct($data, $passed_subject)
    {
        $this->data = $data;
        $this->passed_subject = $passed_subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->passed_subject)->view('emails.sendConfigChange')->with($this->data);
    }
}
