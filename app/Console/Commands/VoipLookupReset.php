<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\PhoneNumberVoipLookup;

class VoipLookupReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voip:reset {--dryrun} {--days=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old voip lookup entries (>14 days) to pull in fresh information.';

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
        $now = Carbon::now();
        $days = $this->option('days');
        if (!empty($days)) {
            $days = intval($days);
        } else {
            $days = 14;
        }
        $daysago = $now->subDays($days)->endOfDay();
        if ($this->option('dryrun')) {
            $cnt = PhoneNumberVoipLookup::whereDate('updated_at', '<', $daysago)->count();
        } else {
            $cnt = PhoneNumberVoipLookup::whereDate('updated_at', '<', $daysago)->delete();
        }
        $this->info('Deleted ' . $cnt . ' entries.');
    }
}
