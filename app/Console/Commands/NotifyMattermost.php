<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client as GuzzleClient;


class NotifyMattermost extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mattermost:notify {--channel=} {--icon=} {--botname=} {message}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a notification to Mattermost';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $c = new GuzzleClient([
            'base_uri' => config('services.mattermost.server', null),
            'http_errors' => false, // don't throw exceptions on error
        ]);

        $appPath = config('services.mattermost.token', null);
        $channel = $this->option('channel');

        if (!isset($channel)) {
            $channel = config('services.mattermost.default_channel', 'general');
        }

        if ($appPath !== null) {
            $appPath = implode('/', ['hooks', $appPath]);

            $data = [
                'text' => $this->argument('message'),
                'channel' => $channel
            ];

            $response = $c->post($appPath, [
                'json' => $data,
            ]);

            if ($response->getStatusCode() == 200) {
                info('Sent Mattermost Notification', $data);
                $this->line('Message Sent');
                return 0;
            }
            else {
                info('Unable to send Mattermost Notification', [$data, $response]);
                $this->line('Message Not Sent: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
                return 1;
            }
        }
        else {
            throw new \Exception('Mattermost Application URL is not set (MATTERMOST_HOOK_URL)');
        }
    }
}
