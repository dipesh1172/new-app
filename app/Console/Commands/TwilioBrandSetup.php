<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\BrandTaskQueue;
use App\Models\TelephonyWorkflowConfig;
use Twilio\Rest\Client;
use Illuminate\Console\Command;

class TwilioBrandSetup extends Command
{
    private $client;
    private $workspace_id;
    private $workspace;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:brand:setup {--brand=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup Task Queues and Workflow in Twilio';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );

        $this->workspace_id = config('services.twilio.workspace');
        $this->workspace = $this->client->taskrouter->workspaces($this->workspace_id);

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->option('brand')) {
            echo "Syntax: php artisan twilio:brand:setup --brand=<brand id>\n";
            exit();
        }

        $brand = Brand::find($this->option('brand'));
        if ($brand) {
            echo 'Setting up '.$brand->name."\n";

            $taskQueues = $this->workspace->taskQueues->read();
            $exists = false;

            foreach ($taskQueues as $record) {
                if ($record->friendlyName === $brand->name.' - English'
                    || $record->friendlyName === $brand->name.' - Spanish'
                ) {
                    $exists = true;
                }
            }

            if ($exists) {
                echo ' -- Task queues are already configured in Twilio for '.$brand->name."\n";
                exit();
            }

            $exists_english = BrandTaskQueue::where(
                'task_queue',
                $brand->name.' - English'
            )->exists();
            if ($exists_english) {
                echo ' -- English Task Queue is already configured in brand_task_queues for '.$brand->name."\n";
                exit();
            }

            $exists_spanish = BrandTaskQueue::where(
                'task_queue',
                $brand->name.' - Spanish'
            )->exists();
            if ($exists_spanish) {
                echo ' -- Spanish Task Queue is already configured in brand_task_queues for '.$brand->name."\n";
                exit();
            }

            $activities = $this->workspace->activities->read();
            $reserved = null;
            $tpv_in_progress = null;

            foreach ($activities as $record) {
                switch ($record->friendlyName) {
                    case 'Reserved':
                        $reserved = $record->sid;
                        break;
                    case 'TPV In Progress':
                        $tpv_in_progress = $record->sid;
                        break;
                }
            }

            echo " -- Adding " . $brand->name . " - English\n";

            $english_config = [];
            $taskqueue_english = $this->workspace->taskQueues->create(
                $brand->name.' - English',
                [
                    'assignmentActivitySid' => $tpv_in_progress,
                    'reservationActivitySid' => $reserved,
                    'targetWorkers' => 'skills HAS "'.$brand->name.' - English"',
                ]
            );

            $btq = new BrandTaskQueue();
            $btq->brand_id = $brand->id;
            $btq->task_queue = $brand->name.' - English';
            $btq->task_queue_sid = $taskqueue_english->sid;
            $btq->save();

            $english_config = [
                'targets' => [
                    [
                        'queue' => $taskqueue_english->sid,
                    ]
                ],
                'expression' => "brand_id == '" . $brand->id . "' AND selected_language == 'English'",
                'filter_friendly_name' => $brand->name . ' - English',
            ];

            echo " -- Adding " . $brand->name . " - Spanish\n";

            $taskqueue_spanish = $this->workspace->taskQueues->create(
                $brand->name.' - Spanish',
                [
                    'assignmentActivitySid' => $tpv_in_progress,
                    'reservationActivitySid' => $reserved,
                    'targetWorkers' => 'skills HAS "'.$brand->name.' - Spanish"',
                ]
            );

            $btq = new BrandTaskQueue();
            $btq->brand_id = $brand->id;
            $btq->task_queue = $brand->name.' - Spanish';
            $btq->task_queue_sid = $taskqueue_spanish->sid;
            $btq->save();

            $spanish_config = [
                'targets' => [
                    [
                        'queue' => $taskqueue_spanish->sid,
                    ]
                ],
                'expression' => "brand_id == '" . $brand->id . "' AND selected_language == 'Spanish'",
                'filter_friendly_name' => $brand->name . ' - Spanish',
            ];

            echo " -- Adding Workflow\n";

            $workflows = $this->workspace->workflows->read();
            $workflow_id = null;
            $config = null;
            foreach ($workflows as $record) {
                if (null === $config) {
                    $workflow_id = $record->sid;
                    $config = json_decode($record->configuration, true);
                }

                $twc = new TelephonyWorkflowConfig();
                $twc->config = $record->configuration;
                $twc->save();
            }

            $config['task_routing']['filters'][] = $english_config;
            $config['task_routing']['filters'][] = $spanish_config;

            // print_r($config);

            $workflow = $this->workspace->workflows($workflow_id)->update(
                [
                    'configuration' => json_encode($config)
                ]
            );
        } else {
            echo 'Brand ID '.$this->option('brand')." does not exist.\n";
            exit();
        }
    }
}
