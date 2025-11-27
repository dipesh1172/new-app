<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Predis\Command\ServerFlushDatabase;

class RedisClusterCacheClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:flushdb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Performs the equivalent of a full cache clear';

    /**
     * Create a new command instance.
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
        $flushDbCommand = new ServerFlushDatabase();

        foreach (Redis::connection()->getConnection() as $node) {
            $node->executeCommand($flushDbCommand);
        }
    }
}
