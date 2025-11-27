<?php

namespace App\Console\Commands;

use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;
use Illuminate\Console\Command;

class TwilioCallIvr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:call-ivr {--to=} {--url=} {--from=} {--incomplete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $twilio;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->twilio = new Client(config('services.twilio.account'), config('services.twilio.auth_token'));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $to = $this->option('to');
        $from = $this->option('from');
        if ($from === null) {
            $from = config('services.twilio.default_number');
        }
        $url = $this->option('url');
        $isContinue = $this->option('incomplete');
        if ($isContinue) {
            $url .= '&continue=1';
        }

        info('Starting callback from ' . $from . ' to ' . $to . ' to url: ' . $url);

        try {
            $call = $this->twilio->calls->create(
                $to, // to
                $from,
                [
                    'url' => $url,
                ]
            );
            $this->line($call->sid);
            return 0;
        } catch (RestException $e) {
            info('Exception while trying to start ivr callback', [$e]);
            return 42;
        }
    }
}
