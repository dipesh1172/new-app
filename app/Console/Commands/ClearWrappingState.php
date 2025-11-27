<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Twilio\Rest\Client as TwilioClient;

use Illuminate\Console\Command;

class ClearWrappingState extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:wrapping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears agents in a wrapping state';

    private $_client;
    private $_workspace_id;
    private $_workspace;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->_client = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $this->_workspace_id = config('services.twilio.workspace');
        $this->_workspace = $this->_client->taskrouter
            ->workspaces($this->_workspace_id);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tasks = $this->_client->taskrouter
            ->v1->workspaces($this->_workspace_id)
            ->tasks
            ->read();

        foreach ($tasks as $record) {
            $dateCurrent = new Carbon();
            $dateUpdated = new Carbon($record->dateUpdated->format('Y-m-d H:i:s'));
            $dateUpdatedAddTime = $dateUpdated->addMinutes(1);

            if ($dateCurrent->gt($dateUpdatedAddTime)) {
                if ($record->assignmentStatus == 'wrapping') {
                    $task = $this->_client->taskrouter->v1
                        ->workspaces($this->_workspace_id)
                        ->tasks($record->sid)
                        ->update(
                            array(
                                "assignmentStatus" => "completed",
                                "reason" => "waiting too long - complete hammer"
                            )
                        );
                }
            }
        }
    }
}
