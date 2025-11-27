<?php

namespace App\Console\Commands;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use App\Models\Interaction;
use App\Models\DxcCall;

class GenerateDxcCalls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dxc:generateCalls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull calls data from Twilio and insert the DXC ones on the DB';

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
        $start_date = Carbon::now('America/Chicago')->subMinutes(5);
        $end_date = Carbon::now('America/Chicago');

        $twilio = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );

        try {
            //Final statuses Failed, Canceled, No-answer, Busy, Completed
            //Getting all calls from Twilio on a time range
            $calls_completed = $twilio->calls
                ->read(
                    array(
                        'endTimeBefore' => $end_date,
                        'endTimeAfter' => $start_date,
                        'status' => 'completed'
                    ),
                    20
                );
            //We need the number of canceled calls to calculate service level on the Inbound Call Volume Report    
            $calls_canceled = $twilio->calls
                ->read(
                    array(
                        'endTimeBefore' => $end_date,
                        'endTimeAfter' => $start_date,
                        'status' => 'canceled'
                    ),
                    20
                );

            $calls = array_merge($calls_canceled, $calls_completed);

            if (count($calls) > 0) {
                $twilio_sid = [];

                foreach ($calls as $call) {
                    $twilio_sid[] = $call->sid;
                }
                //Getting all interactions.session_call_id
                $focus_sid = Interaction::select(
                    'session_call_id'
                )->whereBetween(
                    'created_at',
                    [$start_date, $end_date]
                )->whereNotNull('session_call_id')
                    ->get()->pluck('session_call_id')->toArray();

                //If calls.sid are not in the interactions table then those calls belongs to DXC
                $dxc_sid = array_diff($twilio_sid, $focus_sid);

                if (count($dxc_sid) > 0) {
                    foreach ($calls as $call) {
                        if (in_array($call->sid, $dxc_sid)) {
                            //Dealing with repeated call_sid (pulling same call twice) => 8.00 - 8.05, 8.05 - 8.10
                            if ($call->endTime->format('Y-m-d H:i:s') === $start_date->format('Y-m-d H:i:s')) {
                                if (DxcCall::find($call->sid)) {
                                    continue;
                                }
                            }
                            //Storing the calls on the DB
                            $dxc_call = new DxcCall;
                            //Converting DateTimeObject to string
                            $dxc_call->endTime = $call->endTime->format('Y-m-d H:i:s');
                            $dxc_call->startTime = $call->startTime->format('Y-m-d H:i:s');
                            $dxc_call->dateUpdated = $call->dateUpdated->format('Y-m-d H:i:s');
                            $dxc_call->dateCreated = $call->dateCreated->format('Y-m-d H:i:s');
                            $dxc_call->sid = $call->sid;
                            $dxc_call->direction = $call->direction;
                            $dxc_call->duration = $call->duration;
                            $dxc_call->fromFormatted = $call->fromFormatted;
                            $dxc_call->toFormatted = $call->toFormatted;
                            $dxc_call->callerName = $call->callerName;
                            $dxc_call->answeredBy = $call->answeredBy;
                            $dxc_call->status = $call->status;
                            $dxc_call->forwardedFrom = $call->forwardedFrom;
                            $dxc_call->parentCallSid = $call->parentCallSid;
                            $dxc_call->save();
                        }
                    }
                } else {
                    Log::info('There are no DXCCalls for the period ' . $start_date . ' - ' . $end_date);
                }
            } else {
                Log::info('There are no calls from Twilio for the period ' . $start_date . ' - ' . $end_date);
            }
        } catch (\Twilio\Exceptions\RestException $e) {
            Log::error('There has been an error with Twilio request for calls logs. Error: ' . $e);
        }
    }
}
