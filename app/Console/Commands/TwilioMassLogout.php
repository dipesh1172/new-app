<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class TwilioMassLogout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:logout {--onehour : Only logs the user out if they have been in the status for over 1 hour}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Places all Twilio users into the logged out status.';

    private $_client = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_client = (new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        ))->taskrouter->v1->workspaces(config('services.twilio.workspace'));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $activities = $this->_client->activities->read();
        $loggedOut = null;
        $workers = [];
        foreach ($activities as $record) {
            if ($record->friendlyName !== 'Logged Out') {
                $tworkers = $this->_client->workers->read(['activitySid' => $record->sid]);
                if (count($tworkers) > 0) {
                    $workers = array_merge($workers, $tworkers);
                }
            } else {
                $loggedOut = $record;
            }
        }

        if ($this->option('onehour')) {
            $this->info('Limiting updates to users that have been in-status for an hour or more');
            $now = Carbon::now('UTC');
            $hourAgo = $now->subHour();
            $workers = array_filter($workers, function ($worker) use ($hourAgo) {
                $rawStatusChangedTime = $worker->dateStatusChanged;
                $statusChangedTime = Carbon::parse($rawStatusChangedTime, 'UTC');
                return $hourAgo->greaterThan($statusChangedTime);
            });
        }

        if ($loggedOut !== null && count($workers) > 0) {
            $this->info('Logging out users...');
            $bar = $this->output->createProgressBar(count($workers));
            foreach ($workers as $worker) {
                try {
                    $this->_client->workers($worker->sid)->update(['activitySid' => $loggedOut->sid]);
                } catch (\Exception $e) {
                    Log::error('Got error: ' . $e->getMessage() . ' when trying to update worker: ' . $worker->sid);
                    $this->error('Unable to update worker: ' . $worker->sid . ' => ' . $e->getMessage());
                }
                $bar->advance();
            }
            $bar->finish();
        } else {
            if ($loggedOut === null) {
                $this->info('Did not find "Logged Out" status');
            } else {
                Log::notice('All users were already in logout status.');
                $this->info('All Users are logged out.');
            }
        }
    }
}
