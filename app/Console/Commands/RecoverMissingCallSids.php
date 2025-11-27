<?php

namespace App\Console\Commands;

use Twilio\Rest\Client as TwilioClient;

use Illuminate\Support\Str;
use Illuminate\Console\Command;

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

use App\Models\Interaction;

class RecoverMissingCallSids extends Command
{
    private $_client;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:missing-call-sids
        {--dryrun}
        {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets the Interaction recordings from Twilio';

    protected $runId = '';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_client = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );

        $this->runId = Uuid::uuid4();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $jobStart = Carbon::now('America/Chicago')->format('Y-m-d H:i:s e');

        $this->info("[{$this->runId}][{$jobStart}] RecoverMissingCallSids :: Job Started");
        
        $beforeDate = Carbon::now()->subHour()->toISOString();

        // Get list of recordings from Twilio that is older than 
        $this->info("[{$this->runId}] Retrieving recordings list from Twilio...");

        $recordings = $this->_client->recordings->read(['dateCreatedBefore' => $beforeDate, 'status' => 'completed', '' ]);
        
        // Iterate over list to get Call_Sid of recording.
        $ctr = 0;
        $totalRecordings = count($recordings);

        $this->info("[{$this->runId}] Found {$totalRecordings} recording(s).");

        foreach($recordings as $rec) 
        {
            $ctr++;

            $this->info('');
            $this->info("[{$this->runId}] --------------------------------------------------");
            $this->info("[{$this->runId}] [ {$ctr} / {$totalRecordings} ]");
            $this->info("[{$this->runId}] Recording Sid: " . $rec->sid);
            $this->info("[{$this->runId}] Recording Call Sid: " . $rec->callSid);
            
            if( !($rec->status === 'completed') ) {
                $this->warn("[{$this->runId}] Recording does have not 'Completed' status. Skipping.");
                continue;
            }

            try {
                if ($rec->conferenceSid != null) {
                    // Get Friendly Name from Conference
                    // Use find Conference associated with Call Sid
                    $this->info("[{$this->runId}] Getting conference friendly name...");
                    $conference = $this->_client->conferences($rec->conferenceSid)->fetch();

                    $this->info("[{$this->runId}] Conference Friendly Name: " . $conference->friendlyName);

                    // Make sure this is a Twilio call. Interaction session ID will start with 'WT'.
                    if(Str::startsWith($conference->friendlyName, 'WT')) {
                        
                        // Locate interaction and fix session_call_id
                        $this->_updateRecording($conference->friendlyName, $rec->dateCreated, $rec->callSid);
                    } else {
                        $this->warn("[{$this->runId}] Conference ID does not start with 'WT'. Skipping");
                    }
                } else {
                    $this->warn("[{$this->runId}] Recording does not have a Conference Sid. Skipping");
                }
            }
            catch ( \Exception $e ){
                $this->error("[{$this->runId}] " . print_r($e->getMessage(), true));
            }
        }

        $jobEnd = Carbon::now('America/Chicago')->format('Y-m-d H:i:s e');

        $this->info("[{$this->runId}][{$jobEnd}] RecoverMissingCallSids :: Done.");
    }
    
    private function _updateRecording($friendlyName, $recordingDateTime, $callSid)
    {
        // Use Friendly name of conference to locate interaction on Production
        $this->info("[{$this->runId}] Searching for interactions with session ID: {$friendlyName}");

        $yesterday = Carbon::now('America/Chicago')->subDay()->startOfDay();

        $interactions = Interaction::select(
            'interactions.created_at',
            'interactions.id',
            'interactions.event_id',
            'interactions.notes',
            'interactions.session_call_id',
            'interactions.session_id',
            'brands.id AS brand_id',
            'interactions.id AS interaction_id',
            'interactions.interaction_time',
            'interactions.interaction_type_id',
            'events.confirmation_code'
        )->leftJoin(
            'events',
            'events.id',
            'interactions.event_id'
        )->leftJoin(
            'brands',
            'events.brand_id',
            'brands.id'
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )->whereNull(
            'interactions.session_call_id'
        )->where(
            'interactions.session_id',
            $friendlyName
        )->where(
            'interactions.created_at', 
            '>', 
            $yesterday->format('Y-m-d H:i:s')
        )->orderBy(
            'interactions.created_at', 'desc'
        );

        if($this->option('debug')) {
            $this->info("[{$this->runId}] QUERY:");
            $this->info("[{$this->runId}] " . $interactions->toSql());
            $this->info("[{$this->runId}] BINDINGS:");
            $this->info("[{$this->runId}] " . print_r($interactions->getBindings(), true));
        }

        $interactions = $interactions->get();

        $this->info("[{$this->runId}] Found " . count($interactions) . ' interaction(s)');

        // If more than one interaction, choose which one to update based on recording created date/time
        $recordingDateTime2 = Carbon::parse($recordingDateTime)->setTimezone('America/Chicago');

        $interactionCtr = 0;

        $this->info('');
        $this->info("[{$this->runId}] Processing Interactions");

        foreach($interactions as $interaction ) 
        {
            $interactionCtr++;

            $interactionCreatedAt = Carbon::parse($interaction->created_at->format('Y-m-d H:i:s'), 'America/Chicago'); // Created is in CST, but Carbon thinks it's in UTC

            $this->info('');
            $this->info("[{$this->runId}] **************************************************");
            $this->info("[{$this->runId}] Brand:  " . $interaction->brand_id);
            $this->info("[{$this->runId}] Conf #: " . $interaction->confirmation_code);
            $this->info("[{$this->runId}] Interaction: {$interaction->id}");
            $this->info("[{$this->runId}] Interaction Type: {$interaction->interaction_type_id}");            
            $this->info("[{$this->runId}] Interaction Date: " . $interactionCreatedAt->format('Y-m-d H:i:s'));
            $this->info("[{$this->runId}] Recording Date:   " . $recordingDateTime2->format('Y-m-d H:i:s'));

            // Skip interactions that are newer than the recording
            if($interactionCreatedAt->gt($recordingDateTime2)) {
                $this->warn("[{$this->runId}] Interaction is newer than recording. Skipping");
                continue;
            }
            
            if(!$this->option('dryrun')) {
                $this->info("[{$this->runId}] Updating interaction with Call Sid");

                // Use interaction ID to create Recording. 
                $interaction->session_call_id = $callSid;
                $interaction->save();

            } else {
                $this->info("[{$this->runId}] Dry-run. Interaction {$interaction->id} would have been updated.");
            }

            // At this point, we processed the interaction we need. No need to look at any other interactions. Break out of loop.
            break;
        }

        $this->info("[{$this->runId}] END: Processing Interactions");
    }
}
