<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use App\Models\TpvStaff;

class ResetAPITokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets all staff api tokens.';

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
        DB::table('tpv_staff')
            ->whereNotNull('api_token')
            ->orWhereNotNull('remember_token')
            ->update(['api_token' => null, 'remember_token' => null]);
        $this->info('All Staff API Tokens Reset');
    }
}
