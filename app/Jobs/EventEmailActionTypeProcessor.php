<?php

namespace App\Jobs;

use App\Mail\GenericEmail;
use App\Models\Events\Template;
use App\Models\Users\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EventEmailActionTypeProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $template;
    public $vars;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, Template $template, $variables)
    {
        $this->user = $user;
        $this->template = $template;
        $this->vars = $variables;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->user->email != null) {
            if ($this->user->dept == $this->vars['dept']) {
                if (($this->user->role_id != $this->vars['role'] && $this->vars['invert']) || !$this->vars['invert']) {
                    $this->vars['name'] = $this->user->name;
                    info('Sending mail to ' . $this->user->name);
                    Mail::to($this->user)
                        ->send(
                            new GenericEmail(
                                $this->vars['subject'],
                                simple_template($this->template->template_content, $this->vars)
                            )
                        );
                } else {
                    info('Role didnt match');
                }
            } else {
                info('Dept didnt match');
            }

        } else {
            info('User ' . $this->user->id . ' has no email assigned');
        }
    }
}
