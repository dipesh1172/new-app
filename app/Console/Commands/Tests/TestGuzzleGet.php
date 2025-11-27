<?php

namespace App\Console\Commands\Tests;

use Illuminate\Console\Command;

use GuzzleHttp\Client As GuzzleClient;

class TestGuzzleGet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:guzzle:get {--url=}';

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
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if(!$this->option('url')) {
            $this->error('Missing required option: --url');
            exit -1;
        }

        $client = new GuzzleClient();

        try {
            $res = $client->get($this->option('url'));

            $this->info($res->getBody()->getContents());

        } catch (\Exception $e) {
            $this->error('Line ' . $e->getLine() . ' - ' . $e->getMessage());
        }
    }
}
