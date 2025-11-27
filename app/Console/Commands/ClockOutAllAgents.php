<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\CarbonImmutable;
use Carbon\Carbon;
use App\Models\TimeClock;

class ClockOutAllAgents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agents:clock-out {--date=} {--no-email} {--dryrun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clocks out any agents still on the clock and flags them';

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
        $distroEmailList = ['engineering@tpv.com', 'eduardo@tpv.com'];
        $dryRun = $this->option('dryrun') === true;
        if ($this->option('verbose') && $dryRun) {
            $this->warn('DRY RUN');
        }
        $sendEmail = $this->option('no-email') !== true;
        if ($this->option('verbose') && !$sendEmail) {
            $this->warn('Not Sending Email');
        }
        $date = CarbonImmutable::now('America/Chicago')->yesterday();
        if ($this->option('date') !== null) {
            $date = CarbonImmutable::parse($this->option('date') . ' 00:00:00', 'America/Chicago');
        }
        if ($this->option('verbose')) {
            $this->warn('Processing Date: ' . $date->format('Y-m-d'));
        }
        $clockOutTime = $date->setTime(23, 59);

        if ($this->option('verbose')) {
            $this->info('Clock Out Time will be: ' . $clockOutTime->format('Y-m-d H:i:s'));
        }

        $results = DB::select(
            'select id, tpv_staff_id, tpv_name, username, last_time_punch
                from (
                    select tc.id, tc.tpv_staff_id, concat(ts.first_name, " ", ts.last_name) as tpv_name, ts.username, max(tc.time_punch) as last_time_punch, count(*) as row_cnt
                    from time_clocks tc
                    left join tpv_staff ts on ts.id = tc.tpv_staff_id
                    where date(tc.time_punch) = ?
                    group by tc.tpv_staff_id
                ) as t
                    where mod(row_cnt, 2) = 1',
            [
                $date->format('Y-m-d')
            ]
        );

        $emailTable = '';

        foreach ($results as $result) {
            if (!$dryRun) {
                $tc = new TimeClock();
                $tc->tpv_staff_id = $result->tpv_staff_id;
                $tc->agent_status_type_id = 41;
                $tc->comment = 'AUTO';
                $tc->time_punch = $clockOutTime->format('Y-m-d H:i:s');
                $tc->save();
            }
            $emailTable .= '<tr><td>' . $result->username . '</td><td>' . $result->tpv_name . '</td><td>' . $result->last_time_punch . '</td><td><a href="https://mgmt.' . (config('app.env') !== 'production' ? 'staging.' : '') . 'tpvhub.com/tpv_staff/' . $result->tpv_staff_id . '/time?date=' . $date->format('Y-m-d') . '">Edit</a></td></tr>';
        }

        if ($sendEmail) {
            $emailTable = '<table border="1"><thead><tr><th>ID</th><th>TPV Name</th><th>Last Punch</th><th>Edit</th></tr></thead><tbody>' . $emailTable . '</tbody></table>';

            foreach ($distroEmailList as $destination) {
                if ($this->option('verbose')) {
                    $this->line('Sending Email to: ' . $destination);
                }
                try {
                    SimpleSendEmail($destination, 'no-reply@tpvhub.com', 'TPVs Failed to Clock Out', $emailTable);
                } catch (\Exception $e) {
                    $this->warn('Could not send to ' . $destination);
                    $this->line('Error: ' . $e->getMessage());
                }
            }
        }
    }
}
