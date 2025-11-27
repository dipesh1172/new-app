<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Eztpv;

class ContractRunFailed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contract:run:failed {--startDate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-run failed contracts (processed = 3)';

    /**
     * Default timezone
     */
    protected $tz = 'America/Chicago';

    protected const BRAND_IDS = [
        'energy_bpo' => [
            '09dcd3eb-ecc2-4075-9bf1-a8d3bee2a83f', // Prod
            'c4e9c8d1-a1e7-45a3-ab11-e48452fdcb26'  // Stage
        ]
    ];

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
        $startDate = ($this->option('startDate'))
            ? Carbon::parse($this->option('startDate'), $this->tz)->format('Y-m-d')
            : Carbon::now($this->tz)->format('Y-m-d');

        $contracts = Eztpv::select(
            'eztpvs.id',
            'eztpvs.deleted_at'
        )->where(
            'processed',
            3
        )->where(
            'pre_processing',
            0
        )->where(
            'eztpvs.finished',
            1
        )->whereDate(
            'eztpvs.created_at',
            $startDate
        )->where(
            'eztpvs.created_at',
            '>=',
            Carbon::now($this->tz)->subMinutes(30)
        )->get();

        if ($contracts) {
            foreach ($contracts as $contract) {
                $opts = [
                    '--eztpv_id' => $contract->id,
                    '--no-ansi' => true,
                    '--debug' => true
                ];

                if (config('app.env') === 'local') {
                    $opts['--override-local'] = true;
                }

                $this->info('Re-running ' . $contract->id);

                SendTeamMessage(
                    'triage',
                    'Auto re-running contract processing for EZTPV ID: ' . $contract->id
                );

                $command = 'eztpv:generateContracts';

                if(in_array($contract->brand_id, self::BRAND_IDS['energy_bpo'])) {
                    $command = 'eztpv:generateContractsProductless';
                }

                Artisan::call($command, $opts);

                $contract->pre_processing = 1;
                $contract->save();
            }
        } else {
            $this->info('No contracts to re-run.');
        }
    }
}
