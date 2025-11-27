<?php

namespace App\Console\Commands;

use Twilio\Rest\Client;
use Illuminate\Console\Command;
use Exception;

class SendTextMessage extends Command
{
    private $client;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:sms
        {--status= : URI that Twilio will POST status updates to}
        {--poll-status : If specified the system will check each message to get its final status}
        {--multiple= : Either a comma separated list of numbers to send the message to OR a file containing the list}
        {--to= : The single number the message will be sent to}
        {--from= : The number the message will be sent from}
        {--media= : Image to attach to message}
        {message : The actual message to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(config('services.twilio.account'), config('services.twilio.auth_token'));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $multipleRaw = $this->option('multiple');
        $to = $this->option('to');
        if ($to === null && $multipleRaw === null) {
            $this->error('You must provide the number to send the message to.');
        }
        if ($multipleRaw !== null) {
            if (strstr($multipleRaw, ',') !== FALSE) {
                $multiple = explode(',', $multipleRaw);
            } else {
                if (file_exists($multipleRaw)) {
                    $content = file_get_contents($multipleRaw);
                    $multiple = explode(',', $content);
                } else {
                    $this->error('Multiple file specified does not exist: ' . $multipleRaw);
                }
            }
        }
        $from = $this->option('from');
        if ($from === null) {
            $from = config('services.twilio.default_number');
        }
        $message = trim($this->argument('message'));
        if ($message === null || strlen($message) == 0) {
            $this->error('Cannot send empty message.');
        }
        $pollStatus = $this->option('poll-status');

        $statusCallback = $this->option('status');
        $mediaUrl = $this->option('media');

        if (isset($multiple)) {
            foreach ($multiple as $mto) {
                $this->doSendSms($mto, $from, $message, $statusCallback, $mediaUrl, $pollStatus, true);
            }
        } else {
            $this->doSendSms($to, $from, $message, $statusCallback, $mediaUrl, $pollStatus);
        }
    }

    private function doSendSms($to, $from, $message, $statusCallback, $mediaUrl, $pollStatus, $noErrors = false)
    {
        try {
            $msgSid = null;
            if ($this->option('verbose')) {
                $this->info('Attempting to send: {' . $message . '} to ' . $to . ' from ' . $from);
            }
            $out = ['body' => $message, 'from' => $from];
            if ($statusCallback !== null) {
                $out['statusCallback'] = $statusCallback;
            }
            if ($mediaUrl !== null) {
                $out['mediaUrl'] = $mediaUrl;
            }
            $msg = $this->client->messages->create($to, $out);
            $msgSid = $msg->sid;
            $status = $msg->status;
        } catch (Exception $e) {
            if (!$noErrors) {
                $this->error('Error sending message: ' . $e->getMessage());
            } else {
                $this->info('Error sending message: ' . $e->getMessage());
            }
        }

        if ($pollStatus && $msgSid !== null) {
            $exit = false;
            $lastStatus = null;
            while (!$exit) {
                if ($status !== $lastStatus) {
                    switch ($status) {
                        case 'queued':
                            $this->info('Message has been queued');
                            break;
                        case 'sent':
                            $this->info('Message has been sent.');
                            break;
                        case 'failed':
                            $this->info('Message sending failed');
                            $exit = true;
                            break;
                        case 'delivered':
                            $this->info('Message was delivered');
                            $exit = true;
                            break;
                        default:
                        case 'undelivered':
                            $this->info('Message was not delivered');
                            $exit = true;
                            $break;
                    }
                }
                usleep(500);
                $msg = $this->client->messages($msgSid)->fetch();
                $lastStatus = $status;
                $status = $msg->status;
            }
        }
    }
}
