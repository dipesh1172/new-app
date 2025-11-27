<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Signature;

class SignatureCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'signature:cleanup {--dryrun} {--days=90}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup Signatures table rows';

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
        if ($this->option('days') < 60) {
            $this->info('Must keep signature records for at least 60 days.');
            exit();
        }

        $signatures = Signature::where(
            'created_at',
            '<=',
            Carbon::now()->subDays($this->option('days'))
        );

        if ($this->option('dryrun')) {
            $signatures = $signatures->get();

            foreach ($signatures as $signature) {
                $this->info('Would delete ' . $signature->id);
            }

            $this->info(' -- Would have deleted ' . $signatures->count() . ' record(s)');
        } else {
            $signatures->forceDelete();
        }
    }
}
