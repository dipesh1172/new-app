<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestSendSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sms:send {--from=} {--to=} {--message=} {--brand-id=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to test sending text messages';

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
        if(!$this->option('from')) {
            $this->error("'from' arg is required");
            return;
        }

        if(strlen($this->option('from')) != 12) {
            $this->error("Malformed phone number in arg 'from'");
            return ;
        }

        if(!$this->option('to')) {
            $this->error("'to' arg is required");
            return;
        }

        if(strlen($this->option('to')) != 12) {
            $this->error("Malformed phone number in arg 'to'");
            return ;
        }

        $from    = $this->option('from');
        $to      = $this->option('to');
        $body    = $this->option('message') ? $this->option('message') : "This is a test message";
        $brandId = $this->option('brand-id') ? $this->option('brand-id') : null;

        $this->info("Sending SMS:");
        $this->info("  From: " . $from);
        $this->info("  To:   " . $to);
        $this->info("  Body: " . $body);
        $this->info("");
        
        if(!$this->option('dry-run')) {
            SendSMS($to, $from, $body, null, $brandId);

            $this->info("Message sent!");
        } else {
            $this->info("Dry run. Message was not actually sent. :D");
        }
    }
}
