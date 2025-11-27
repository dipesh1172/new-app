<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TpvStaff;
use Carbon\Carbon;

class DailyDeactivationTpvAgents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deactivate:tpv-agents {--dryrun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to deactivate tpv agents with 10 days of inactivity';

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
        $dryRun = $this->option('dryrun');
        $tenAgo = Carbon::now()->subDays(10);

        if ($dryRun) {
            $agents = TpvStaff::whereDate('updated_at', '<', $tenAgo)
                ->where('role_id', 9)
                ->orderBy('updated_at', 'DESC')
                ->get();

            $this->info('Ten Days ago: '.$tenAgo);
            $this->info('The following '.$agents->count().' agents would be deactivated for inactivity:');
            $this->table(
                ['Agent Name', 'DXC ID', 'Last Activity'],
                $agents->map(
                    function ($item) {
                        return [$item->first_name.' '.$item->last_name, $item->username, $item->updated_at];
                    }
                )
            );

            return;
        }

        TpvStaff::whereDate('updated_at', '<', $tenAgo)
            ->where('role_id', 9)
            ->update(['status' => 0]);

        TpvStaff::whereDate('updated_at', '<', $tenAgo)
            ->where('role_id', 9)
            ->delete(); //Marks as disabled
    }
}
