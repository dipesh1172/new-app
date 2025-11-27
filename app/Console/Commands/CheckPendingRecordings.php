<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;
use Exception;
use App\Models\Recording;

class CheckPendingRecordings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:pending-recordings';

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
        $pendingRecordings = Recording::where('remote_status', 'pending')->get();
        $totalPending = count($pendingRecordings);
        if ($totalPending === 0) {
            $this->info('No pending records to update');
            return 0;
        }
        $bar = $this->output->createProgressBar($totalPending);
        $bar->start();
        $cnt = 0;
        foreach ($pendingRecordings as $pending) {
            if ($this->checkIfRecordingReady($pending->recording)) {
                $ret = Artisan::call('fetch:recording:single', [
                    '--interaction' => $pending->interaction_id,
                    '--url' => $pending->recording,
                    '--brand' => $pending->remote_error_code,
                    '--duration' => $pending->duration,
                    '--callid' => $pending->call_id,
                    '--force' => true,
                ]);
                if ($ret == 0) {
                    $cnt += 1;
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info('Updated ' . $cnt . ' of ' . $totalPending);
    }

    private function checkIfRecordingReady(string $url)
    {
        try {
            $newUrl = $url . '.json';
            $raw = file_get_contents($newUrl);
            $jdata = json_decode($raw, true);
            return $jdata['status'] === 'completed';
        } catch (Exception $e) {
            $this->info('Error checking recording status: ' . $e->getMessage());
            return false;
        }
    }
}
