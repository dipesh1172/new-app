<?php

namespace App\Console\Commands;

use App\Jobs\GetTwilioStats;
use Illuminate\Console\Command;

class GenerateStatsQueuedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:generate-stats-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds several stats jobs to the queue';

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
        for ($i = 0; $i < 10; $i += 1) {
            GetTwilioStats::dispatch()->onQueue('stats');
        }
    }
}
