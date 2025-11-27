<?php

namespace App\Console\Commands;

use App\Models\EztpvContactError;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EztpvContactErrors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:EztpvContactErrors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify Engineering of Eztpv Contact Errors';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $errors = EztpvContactError::get();

        if (isset($errors) && count($errors) > 0)
        {
            $count = count($errors);
            $query = EztpvContactError::toSql();

            $email_data = array(
                'query' => $query,
                'count'  => $count
            );
            $email_subject = 'Unresovled Eztpv Contact Errors';
            $email_sendTo = 'engineering@tpv.com';
            Mail::send(
                'emails.notifyEngineeringOfEztpvContactErrors', 
                $email_data, 
                function ($message) use ($email_subject, $email_sendTo) {
                    $message->subject($email_subject);
                    $message->from('no-reply@tpvhub.com');
                    $message->to(trim($email_sendTo));
                }
            );
        }
    }
}
