<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\Audit;

class CleanupAudits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:audits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup the Audits table';

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
        $models = [
            // 60 days
            60 => [
                'App\\Models\\AgentStatus',
                'App\\Models\\CustomerList',
                'App\\Models\\DailyStat',
                'App\\Models\\Esiid',
                'App\\Models\\Lead',
                'App\\Models\\StatsProduct',
                'App\\Models\\UserFavoriteBrand',
            ],
        ];

        foreach ($models as $key => $values) {
            echo $key . " day(s)\n";
            foreach ($values as $value) {
                echo " -- Cleaning " . $value . " ...\n";
                Audit::where(
                    'created_at',
                    '<=',
                    Carbon::now()->subDays($key)
                )->where(
                    'auditable_type',
                    $value
                )->forceDelete();
            }
        }
    }
}
