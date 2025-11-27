<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\TpvStaff;
use App\Models\JsonDocument;
use App\Models\AgentStatus;
use App\Http\Controllers\TwilioController;

class NotReadyAgentCachingCommand extends Command
{
    protected $caching_timeout = 60;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notreadyagent:dashboard';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches stats used for the notready agent dashboard';

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
        $not_ready_statuses = [
            'Break',
            'Unscheduled Break',
            'Not Ready',
            'Lunch',
            'Meal',
            'Meeting',
            'Training',
            'Coaching',
            'After Call Work',
            'System',
        ];

        $over_2minute_statuses = [
            'After Call Work',
            'System',
        ];

        $twilio = new TwilioController();

        $workers = [];
        $all_workers = $twilio->get_workers_raw();
        foreach ($all_workers as $worker) {
            if (in_array($worker->activityName, $not_ready_statuses)) {
                $carbon_time = Carbon::createFromTimestamp($worker->dateStatusChanged->getTimestamp());
                if (in_array($worker->activityName, $over_2minute_statuses) && Carbon::now()->diffInMinutes($carbon_time) < 2) {
                    // If this is After Call Work or System, but less than 2 minutes, don't worry about it
                    break;
                }

                $tpv_staff = Cache::remember(
                    'tpv_staff_'.$worker->friendlyName,
                    120,
                    function () use ($worker) {
                        return TpvStaff::select(
                            'id',
                            DB::raw("CONCAT(tpv_staff.first_name, ' ', tpv_staff.last_name) as name")
                        )->where(
                            'tpv_staff.username',
                            $worker->friendlyName
                        )->latest()->first();
                    }
                );

                if ($tpv_staff) {
                    $call_center = '';
                    switch ($tpv_staff['call_center_id']) {
                        case 1:
                            $call_center = 'Tulsa';
                            break;

                        case 2:
                            $call_center = 'Tahlequah';
                            break;

                        case 3:
                            $call_center = 'Las Vegas';
                            break;

                        case 4:
                            $call_center = 'Work at Home';

                            // no break
                        default:
                            $call_center = 'Tulsa';
                            break;
                    }

                    $logged_in_time = Cache::remember(
                        'logged_in_time_'.$worker->friendlyName,
                        120,
                        function () use ($tpv_staff) {
                            return AgentStatus::select(
                                'created_at'
                            )->where(
                                'tpv_staff_id',
                                $tpv_staff['id']
                            )->where(
                                'event',
                                'Logged In'
                            )->where(
                                'created_at',
                                '>=',
                                Carbon::now('America/Chicago')->startOfDay()
                            )->where(
                                'created_at',
                                '<=',
                                Carbon::now('America/Chicago')->endOfDay()
                            )->orderBy(
                                'created_at',
                                'ASC'
                            )->first();
                        }
                    );

                    $logged_in_at = '';
                    if ($logged_in_time) {
                        $logged_in_at = Carbon::parse($logged_in_time['created_at']);
                    }

                    $workers[] = [
                        'worker_id' => $worker->sid,
                        'name' => $tpv_staff['name'],
                        'location' => $call_center,
                        'status' => $worker->activityName,
                        'status_changed_at' => $carbon_time->tz('America/Chicago'),
                        'logged_in_at' => $logged_in_at,
                    ];
                }
            }
        }

        try {
            $newJ = new JsonDocument();
            $newJ->document_type = 'not-ready-agent-stats';
            $newJ->document = $workers;
            $newJ->save();
        } catch (\Exception $e) {
            $this->info('Unable to write to the database.');
        }
    }
}
