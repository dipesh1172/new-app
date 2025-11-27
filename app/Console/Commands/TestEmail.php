<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an email test to make sure delivery is working.';

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
        info('Running an email test...');

        try {
            SimpleSendEmail(
                'engineering@tpv.com',
                'no-reply@tpvhub.com',
                'Test Email via SimpleSendEmail',
                'This was just a test email to confirm things are working.'
            );
        } catch (\Exception $e) {
            $this->line('Error: ' . $e->getMessage());
        }
    }
}
