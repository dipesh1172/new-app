<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\JsonDocument;

class GenerateAsaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:asa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the ASA for the current day';

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
        $start_query_date = Carbon::now('America/Chicago')->hour(7)->minute(0)->second(0)->tz('UTC')->toDateTimeString();
        $end_query_date = Carbon::now('America/Chicago')->hour(23)->minute(30)->second(0)->tz('UTC')->toDateTimeString();
        $reservations = $this->getReservations($start_query_date, $end_query_date);

        $queues = [
            'dxc_English' => 0,
            'dxc_Spanish' => 0,
            'focus_English' => 0,
            'focus_Spanish' => 0
        ];

        $d_e = $d_s = $f_e = $f_s = 0;
        foreach ($reservations as $call) {
            $task_queue = strtolower($call->task_queue);
            if (!$this->isSurvey($task_queue)) {
                if ($this->isDXC($task_queue)) {
                    if ($this->isEnglish($task_queue)) {
                        $d_e++;
                        $queues['dxc_English'] += $call->task_age;
                    }
                    if ($this->isSpanish($task_queue)) {
                        $d_s++;
                        $queues['dxc_Spanish'] += $call->task_age;
                    }
                } else {
                    if ($this->isEnglish($task_queue)) {
                        $f_e++;
                        $queues['focus_English'] += $call->task_age;
                    }
                    if ($this->isSpanish($task_queue)) {
                        $f_s++;
                        $queues['focus_Spanish'] += $call->task_age;
                    }
                }
            }
        }

        $queues['dxc_English'] /= $d_e === 0 ? 1 : $d_e;
        $queues['dxc_Spanish'] /= $d_s === 0 ? 1 : $d_s;
        $queues['focus_English'] /= $f_e === 0 ? 1 : $f_e;
        $queues['focus_Spanish'] /= $f_s === 0 ? 1 : $f_s;

        DB::transaction(function () use ($queues) {
            JsonDocument::where('document_type', 'asa-calculation')->delete();
            $jd = new JsonDocument();
            $jd->document_type = 'asa-calculation';
            $jd->document = $queues;
            $jd->save();
            Cache::forget('asa-calculation-cmd');
        });
    }

    private function isDXC($task_queue)
    {
        return (strpos($task_queue, "z_") !== false || strpos($task_queue, "dxc") !== false);
    }

    private function isEnglish($task_queue)
    {
        return (strpos($task_queue, "english") !== false);
    }

    private function isSpanish($task_queue)
    {
        return (strpos($task_queue, "spanish") !== false);
    }

    private function isSurvey($task_queue)
    {
        return (strpos($task_queue, 'outbound call queue') !== false || strpos($task_queue, 'survey') !== false);
    }

    public function getReservations($started, $end)
    {
        return DB::select(DB::raw("SELECT ec.task_age, btq.task_queue FROM event_callback ec INNER JOIN brand_task_queues btq ON ec.task_queue_sid=btq.task_queue_sid WHERE ec.created_at >= '" . $started . "' AND ec.created_at <= '" . $end . "' AND ec.event_type = 'reservation.accepted' ORDER BY ec.task_created_date;"));
    }
}
