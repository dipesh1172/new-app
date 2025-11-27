<?php

namespace App\Console\Commands;

use App\Models\Survey;
use App\Models\Event;
use Illuminate\Console\Command;

/*
Survey Clear out job
     If a survey has a surveys.last_call and surveys.deleted_at IS NULL
         - Check if it has had 3+ calls and mark the surveys.deleted_at = NOW()
         - Check if the survey has an interaction that was a "Good Sale"
             - mark the surveys.deleted_at = NOW()
         - Check if there was a "Refused Survey"
             - mark the surveys.deleted_at = NOW()
*/
class FinalizeSurveys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey:finalize {--dryrun : only list potential changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes surveys and finalizes them where necessary.';

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
        $dryRun = $this->option('dryrun');
        $toProcess = Survey::whereNotNull('last_call')->get();
        if ($dryRun) {
            $this->info('Found '.$toProcess->count().' surveys within criteria, only finalizations are listed after this.');
        }
        $toProcess->each(
            function ($item) use ($dryRun) {
                $event = Event::where('survey_id', $item->id)
                    ->with('interactions')
                    ->with('interactions.disposition')
                    ->first();
                if ($event === null) {
                    // the event wasn't found for this? weird
                    return;
                }
                $interactions = $event->interactions;
                if ($interactions === null || $interactions->count() === 0) {
                    // there weren't any calls yet, also weird
                    return;
                }
                if ($interactions->count() > 2) {
                    if ($dryRun) {
                        $this->info('Would have removed survey '.$item->refcode.' for having 3+ calls.');
                    } else {
                        $item->delete();
                    }

                    return;
                }

                $goodSale = $interactions->where('event_result_id', 1)->first();
                if ($goodSale !== null) {
                    if ($dryRun) {
                        $this->info('Would have removed survey '.$item->refcode.' for having been completed.');
                    } else {
                        $item->delete();
                    }

                    return;
                }

                $noSales = $interactions->where('event_result_id', 2);
                $noSales->each(
                    function ($nsi) use ($item, $dryRun) {
                        if (
                            $nsi->disposition !== null &&
                            (
                                strtolower(trim($nsi->disposition->reason)) === 'refused survey' ||
                                (
                                    $nsi->disposition->fraud_indicator == 1 ||
                                    $nsi->disposition->fraud_indicator == true
                                )
                            )
                        ) {
                            if ($dryRun) {
                                $this->info('Would have removed survey '.$item->refcode.' for having been refused.');
                            } else {
                                $item->delete();
                            }

                            return false;
                        }
                    }
                );
            }
        );
    }
}
