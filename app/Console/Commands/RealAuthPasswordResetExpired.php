<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RealAuthPasswordResetExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:reset-expired-pw-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears expired password reset tokens';

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
        $now = Carbon::now('UTC');
        $hourAgo = $now->subHour();
        $this->info('Removing tokens before ' . $hourAgo->toString());
        $cnt = DB::table('password_resets')->where('created_at', '<', $hourAgo)->delete();
        $this->info('Removed ' . $cnt . ' expired password reset tokens.');
    }
}
