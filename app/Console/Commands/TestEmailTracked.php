<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;

use App\Helpers\EmailHelper;

/**
 * Test command. Sends an email to specified recipient.
 */
class TestEmailTracked extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email:tracked {--from=} {--to=} {--subject=} {--body=} {--brand-id=} {--event-id=} {--track}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to test sending email';

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
        $from    = $this->option('from')     ? $this->option('from')     : 'engineering@tpv.com';
        $to      = $this->option('to')       ? $this->option('to')       : 'engineering@tpv.com';        
        $subject = $this->option('subject')  ? $this->option('subject')  : "Test Email " . Carbon::now("America/Chicago")->format("Y-m-d H:i:s");
        $body    = $this->option('body')     ? $this->option('body')     : "This is a test email";
        $brandId = $this->option('brand-id') ? $this->option('brand-id') : null;
        $eventId = $this->option('event-id') ? $this->option('event-id') : null;
        $track   = $this->option('track');

        $this->info("Test Email Options:");
        $this->info("  From:     " . $from);
        $this->info("  To:       " . $to);
        $this->info("  Subject:  " . $subject);
        $this->info("  Body:     " . $body);
        $this->info("  Brand ID: " . ($brandId ? $brandId : "Not provided"));
        $this->info("  Event ID: " . ($eventId ? $eventId : "Not provided"));
        $this->info("  Track:    " . ($track   ? "Yes" : "No"));

        $this->info("Sending Email");

        $status = EmailHelper::sendGenericEmail([
            'to'      => $to,
            'from'    => $from,
            'subject' => $subject,
            'body'    => $body,
            'track'   => $track
        ]);

        $this->info("Sent Successfully? " . ($status ? "Yes" : "No"));
    }
}
