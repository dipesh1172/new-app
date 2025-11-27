<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client as GuzzleClient;


class NotifySlack extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:notify {--channel=} {--icon=} {--botname=} {message}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a notification to Slack';

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
        $c = new GuzzleClient([
            'base_uri' => 'https://hooks.slack.com/services/',
            'http_errors' => false, // don't throw exceptions on error
        ]);

        $appPath = config('services.slack.hook_url', null);
        $channel = $this->option('channel');
        if (!isset($channel)) {
            $channel = 'engineering';
        }
        $botName = $this->option('botname');
        if (!isset($botName)) {
            $botName = 'OpsBot';
        }
        $icon = $this->option('icon');
        if (!isset($icon)) {
            $icon = 'robot';
        }

        $icon = ':' . $icon . ':';

        if ($appPath !== null) {
            $data = [
                'text' => $this->argument('message'),
                'channel' => $channel,
                'username' => $botName,
                'icon_emoji' => $icon,
            ];

            $response = $c->post($appPath, [
                'json' => $data,
            ]);

            if ($response->getStatusCode() == 200) {
                info('Sent Slack Notification', $data);
                $this->line('Message Sent');
                return 0;
            } else {
                info('Unable to send Slack Notification', [$data, $response]);
                $this->line('Message Not Sent: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
                return 1;
            }
        } else {
            throw new \Exception('Slack Application URL is not set (SLACK_HOOK_URL)');
        }
    }
}
