<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\Interaction;
use App\Models\EventFlag;

class QAFlagging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qa:flagging';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Looks for events/interactions that need flagged.';

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
        $interactions = Interaction::where(
            'interactions.event_result_id',
            2
        )->leftJoin('event_flags', 'event_flags.interaction_id', '=', 'interactions.id')
            ->whereNull('event_flags.id')
            ->whereNull(
                'interactions.disposition_id'
            )->where(
                'interactions.created_at',
                '<',
                Carbon::now('America/Chicago')->subMinutes(30)->toDateTimeString()
            )->get();

        foreach ($interactions as $interaction) {
            $flag = EventFlag::where(
                'interaction_id',
                $interaction->id
            )->where(
                'flag_reason_id',
                '00000000000000000000000000000000'
            )->withTrashed() // flags are soft deleted when resolved 
                ->first();
            if (empty($flag)) {
                echo "Adding interaction (" . $interaction->id . ")\n";
                $flag = new EventFlag();
                $flag->event_id = $interaction->event_id;
                $flag->flag_reason_id = '00000000000000000000000000000000';
                $flag->interaction_id = $interaction->id;
                $flag->notes = "Flagged by automated background job.";
                $flag->save();
            }
        }
    }
}
