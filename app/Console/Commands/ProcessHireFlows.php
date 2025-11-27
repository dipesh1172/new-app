<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\BrandUser;
use App\Models\ScriptAnswer;
use App\Models\UserHireflowActivity;

class ProcessHireFlows extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:hireflow {--hireId=} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for and updated users after hireflow process.';

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
        $newUsers = BrandUser::where(
            'status',
            0
        )->whereNotNull(
            'hireflow_id'
        );

        $newUsers = ($this->option('hireId'))
            ? $newUsers->where('hire_id', $this->option('hireId'))->get()
            : $newUsers->get();

        // get hireflow items and current user status for each item
        // if any are incomplete, continue
        // if all are complete and call is "good sale", activate

        foreach ($newUsers as $user) {
            $uha = UserHireflowActivity::select(
                'user_hireflow_activities.id',
                'hireflow_items.activate',
                'hireflow_items.hireflow_type_id',
                'user_hireflow_activities.pdf_path',
                'user_hireflow_activities.screen_url',
                'user_hireflow_activities.status',
                'stats_product.result',
                'stats_product.interaction_id'
            )->join(
                'hireflow_items',
                'user_hireflow_activities.hireflow_item_id',
                'hireflow_items.id'
            )->join(
                'stats_product',
                'user_hireflow_activities.brand_user_id',
                'stats_product.sales_agent_id'
            )->where(
                'user_hireflow_activities.brand_user_id',
                $user->id
            );

            $uha_count = $uha->count();
            $activities = $uha->get();
            $counter = 0;
            $non_activate_count = 0;

            if (count($activities) > 0 && $uha_count > 0) {
                foreach ($activities as $item) {
                    if ($item->result == 'Sale') {
                        // set user_hireflow_activities status for call item on good sale
                        $saveUha = UserHireflowActivity::find($item->id);
                        $saveUha->status = 1;
                        $saveUha->save();
                    }

                    if ($item->activate == 1) {
                        switch ($item->hireflow_type_id) {
                            case 1:
                                // Background Check
                                //  Status = 1 if disclosure is complete
                                //  If screen_url is not complete, background check isn't complete
                                if (isset($item->pdf_path)
                                    && isset($item->screen_url)
                                    && strlen(trim($item->screen_url)) > 0
                                    && $item->status == 1
                                ) {
                                    $counter++;
                                }
                                break;

                            case 2:
                                // Document
                                if (isset($item->pdf_path)
                                    && $item->status == 1
                                ) {
                                    $counter++;
                                }
                                break;

                            case 3:
                                // Phone call

                                // call result requirement
                                $result_check = false;
                                if ($item->result == 'Sale') {
                                    $result_check = true;
                                }

                                // call answers requirement
                                $answer_check = false;
                                $sa = ScriptAnswer::select('answer_type')
                                    ->join('scripts', 'scripts.id', 'script_answers.script_id')
                                    ->where('scripts.script_type_id', 2)
                                    ->where('script_answers.interaction_id', $item->interaction_id)
                                    ->where('script_answers.answer_type', 'No')
                                    ->get();
                                if (!$sa || count($sa) == 0)
                                {
                                    $answer_check = true;
                                }

                                // final evaluation
                                if ($answer_check && $result_check) {
                                    $counter++;
                                    // sanitize
                                    unset($answer_check, $result_check);
                                }
                                break;
                        }
                    } else {
                        $non_activate_count++;
                    }
                }
            }

            if ($uha_count > 0
                && $uha_count == ($counter + $non_activate_count)
                && !$this->option('debug')
            ) {
                $user->status = 1;
                $user->hire_id = null;
                $user->save();
            } else {
                $this->info('UHA Count: ' . $uha_count . ' -- Counter = ' . $counter . ' -- Non-Activate Count: ' . $non_activate_count);
            }
        }
    }
}
