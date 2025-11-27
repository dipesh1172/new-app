<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendSMSMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:delivery {--to=} {--message=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use the standard SendSMS() to send a text message.';

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
        if (!$this->option('to')) {
            echo 'A to phone number is required.';
            exit();
        }

        if (!$this->option('message')) {
            echo 'A message is required.';
            exit();
        }

        $from = config('services.twilio.default_number');

        echo 'Sending "' . $this->option('message') . '" to ' . $this->option('to') . ' from ' . $from . "\n";
        SendSMS($this->option('to'), $from, $this->option('message'));
    }
}
