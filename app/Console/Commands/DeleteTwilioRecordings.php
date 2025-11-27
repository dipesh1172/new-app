<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Twilio\Rest\Client;
use Illuminate\Console\Command;

class DeleteTwilioRecordings extends Command
{
    protected $client;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:twilio:recordings {--count=} {--dryrun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Twilio recordings older than 90 days.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $recordings = $this->client->recordings->read(
            [
                'dateCreatedBefore' => Carbon::now()->subDays(180)->format('Y-m-d')
            ],
            ($this->option('count')) ? $this->option('count') : 100
        );

        $headers = ['Date', 'Source', 'CallSID', 'SID'];
        $data = [];

        foreach ($recordings as $recording) {
            //echo $record->sid . "\n";
            //print_r($recording);

            if ($this->option('dryrun')) {
                $data[] = [
                    $recording->dateCreated->format('Y-m-d'),
                    $recording->source,
                    $recording->callSid,
                    $recording->sid
                ];
            } else {
                // Delete it...
                // $this->client->recordings($recording->sid)->delete();
            }
        }

        if ($this->option('dryrun')) {
            $this->table($headers, $data);
        }
    }
}
