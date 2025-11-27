<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
use Carbon\Carbon;
use App\Models\ProviderIntegration;
use App\Models\Interaction;
use App\Models\Brand;

class TitanLiveEnrollment extends Command
{
    // Titan is now CleanSky Energy

    private $logger;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'titan:live:enrollments {--nopost} {--debug} {--limit=} {--confirmation_code=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Titan Live Enrollments (at the interaction level instead of event product)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->logger = app('logger.enrollment');
    }

    private function log($msg, $data = [])
    {
        echo $msg;
        $this->logger->info("[TitanLiveEnroll] " . $msg, $data);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->log("Starting..");
        $brand = Brand::where(
            'name',
            'CleanSky Energy'
        )->first();
        if (!$brand) {
            $this->log("Cannot find the brand in the brands table");
            echo "Cannot find the brand in the brands table.\n";
            exit();
        }

        $env_id = (config('app.env') === 'production') ? 1 : 2;
        $pi = ProviderIntegration::where(
            'brand_id',
            $brand->id
        )->where(
            'env_id',
            $env_id
        )->first();
        if (!$pi) {
            $this->log("Unable to find provider integration information");
            echo "Unable to find provider integration information.\n";
            exit();
        }

        $limit = ($this->option('limit')) ? $this->option('limit') : 20;
        $interactions = Interaction::select(
            'interactions.id',
            'interactions.event_id',
            'interactions.created_at',
            'interactions.event_result_id',
            'interactions.tpv_staff_id',
            'interactions.interaction_type_id',
            'interactions.interaction_time',
            'interactions.notes',
            'interactions.disposition_id',
            'events.confirmation_code'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->where(
            'events.brand_id',
            $brand->id
        );

        if ($this->option('confirmation_code')) {
            $this->log(" => By conf#: " . $this->option('confirmation_code'));
            $interactions = $interactions->where(
                'events.confirmation_code',
                $this->option('confirmation_code')
            );
        } else {
            $interactions = $interactions->whereNull(
                'interactions.enrolled'
            );
        }

        $interactions = $interactions->whereNotNull(
            'interactions.event_result_id'
        )->whereNotIn(
            'interactions.interaction_type_id',
            [
                22
            ]
        )->orderBy(
            'interactions.created_at',
            'asc'
        )->whereIn(
            'interactions.event_result_id',
            [
                1,
                2,
            ]
        )->with(
            'disposition'
        )
        /* Approach to filter out (pending non-`api_submission` interactions)
        // Not in use
        // ->where(function($query) {
        //     $query->whereHas('disposition', function($q) {
        //         $q->whereNull('reason');
        //         $q->orWhere('reason', '!=', 'Pending');
        //     });
        //     $query->orWhere('interactions.interaction_type_id', 11);
        // })
        */
        // Read interactions that are created within 5 days
        ->where(
            'interactions.created_at',
            '>=',
            Carbon::now()->subDays(5)
        )
        ->limit($limit)->get();

        if ($this->option('debug')) {
            print_r($interactions->toArray());
        }

        if (!$interactions || $interactions->count() === 0) {
            $this->log("No interactions");
            echo "No interactions were found.\n";
            exit();
        }

        if ($pi) {
            $url = $pi->hostname;
            $timestamp = time();
            $signature = hash_hmac(
                'sha256',
                'token=' . $pi->username . '&timestamp=' . $timestamp,
                $pi->password
            );
            $auth_endpoint = '/auth/login?token=' . $pi->username
                . '&timestamp=' . $timestamp . '&signature=' . $signature;
            $client = new \GuzzleHttp\Client(['verify' => false]);

            $res = $client->post($url . $auth_endpoint, [
                'verify' => false,
                'debug' => $this->option('debug'),
                'headers' => [
                    'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                ]
            ]);

            if ($res->getStatusCode() == 200) {
                $authBody = $res->getBody();
                $authPayload = json_decode($authBody, true);

                // print_r($authPayload);
                if (isset($authPayload['token'])) {
                    foreach ($interactions as $interaction) {
                        $this->log("Processing interaction: #" . $interaction->id . "\tCreated At:" . $interaction->created_at . "\tConf#:" . ($interaction->event ? $interaction->event->confirmation_code : 'invalid'));

                        echo "Processing " . $interaction->confirmation_code . "\n";
                        // print_r($interaction->toArray());

                        $reason = (isset($interaction->disposition->reason))
                            ? $interaction->disposition->reason
                            : null;
                        $result = ($interaction->event_result_id === 1)
                            ? 'VERIFIED'
                            : 'FAILED';

                        if ($this->option('debug')) {
                            $this->info('reason is ' . $reason);
                            $this->info('result is ' . $result);
                        }

                        $this->log("  => Reason: {$reason}\tResult: {$result}");

                        // If not api_submission interaction type, do not process if result is no sale - pending
                        if ($interaction->interaction_type_id != 11 && $reason == 'Pending') {
                            $this->log("  => Interaction is pending. Skipping record... Type: " . $interaction->interaction_type_id);
                            $this->info("Interaction is pending. Skipping record...");
                            continue;
                        }

                        switch ($reason) {
                            case 'Pending':
                                $reason = 'PENDING';
                                $result = 'PENDING';
                                break;
                            case 'Could Not Contact Customer - Busy, Answering Machine, Etc.':
                                $result = 'COULDNT_CONTACT';
                                break;
                        }

                        $record = [];
                        $record['result'] = $result;
                        $record['reasons'] = [
                            [
                                'code' => 1,
                                'reason' => $reason,
                            ]
                        ];

                        if ($this->option('debug')) {
                            echo json_encode($record) . "\n";
                        }

                        $verify_endpoint = '/tpv/verification/' . $interaction->confirmation_code;
                        try {
                            $verify = $client->put($url . $verify_endpoint, [
                                'verify' => false,
                                'debug' => $this->option('debug'),
                                'headers' => [
                                    'User-Agent' => 'DXCLiveEnrollment/' . config('app.version', 'debug'),
                                    'Authorization' => $authPayload['token'],
                                    'Content-Type' => 'application/json',
                                ],
                                'body' => json_encode($record),
                            ]);

                            $verifyBody = $verify->getBody();
                            $verifyPayload = json_decode($verifyBody, true);

                            print_r($verifyPayload);
                            echo $verify->getStatusCode() . "\n";

                            if ($verify->getStatusCode() == 400) {
                                echo "--- Not found.  Skipping.\n";

                                $interaction->enrolled = 'Verification not found';
                                $interaction->save();
                            }

                            if ($verify->getStatusCode() == 200) {
                                if (isset($verifyPayload['status']) && $verifyPayload['status'] === 'OK') {
                                    echo "--- Successfully sent.\n";

                                    if (isset($verifyPayload['enrollment_id'])) {
                                        $interaction->enrolled = $verifyPayload['enrollment_id'];
                                    } else {
                                        $interaction->enrolled = Carbon::now();
                                    }

                                    $interaction->save();
                                } else {
                                    print_r($verifyPayload);
                                }
                            }

                            $this->log("  => ** Enroll Result: {$interaction->enrolled}");
                        } catch (Exception $e) {
                            echo "--- Not found.  Skipping.\n";

                            $interaction->enrolled = 'Exception Occurred.';
                            $interaction->save();

                            $this->log("  => !! Enroll Exception: " . $e->getMessage());

                            if ($this->option('debug')) {
                                echo $e . "\n";
                            }
                        }
                    }
                }
            }
        }

        $this->log("End");
    }
}
