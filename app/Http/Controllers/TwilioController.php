<?php

namespace App\Http\Controllers;

use Twilio\TwiML\VoiceResponse as Twiml;
use Twilio\Rest\Client;
use Twilio\Jwt\ClientToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;
use Aws\Sdk;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\Exception\DynamoDbException;
use App\Models\ZipCode;
use App\Models\TpvStaff;
use App\Models\TextMessage;
use App\Models\State;
use App\Models\PhoneNumber;
use App\Models\PhoneNumberLookup;
use App\Models\PendingStatusChange;
use App\Models\JsonDocument;
use App\Models\Dnis;
use App\Models\CallCenter;
use App\Models\BrandHour;
use App\Models\BrandConfig;
use App\Models\Brand;
use App\Models\AgentStatus;
use App\Models\TextMessagesIncoming;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TwilioController extends Controller
{
    private $_client;
    private $_workspace_id;
    private $_workspace;

    private $highVolumeMessage = 'We appreciate your patience but we are experiencing high call volume at this time.';

    public function __construct()
    {
        $this->middleware(
            function ($request, $next) {
                return $this->cors($request, $next);
            }
        );
        $this->_client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $this->_workspace_id = config('services.twilio.workspace');
        $this->_workspace = $this->_client->taskrouter
            ->workspaces($this->_workspace_id);
    }

    private function cors($request, $next)
    {
        return $next($request)->withHeaders(
            [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
            ]
        );
    }

    public function taskrouterWebhook()
    {
        if (config('services.aws.dynamo.enabled') !== false) {
            $options = [
                'region' => config('services.aws.region'),
                'version' => 'latest',
            ];
            if (config('services.aws.dynamo.endpoint') !== null) {
                $options['endpoint'] = config('services.aws.dynamo.endpoint');
            }
            $sdk = new Sdk($options);
            $dynamo = $sdk->createDynamoDb();
            $marshaler = new Marshaler();
            $tableName = 'TwilioRawData';
            $item = $marshaler->marshalJson(json_encode(request()->all()));
            $params = [
                'TableName' => $tableName,
                'Item' => $item,
            ];

            try {
                $result = $dynamo->putItem($params);
            } catch (DynamoDbException $e) {
                info('Could not insert record:', [$e]);
            }
        }

        return response('', 204)->header('ContentType', 'application/json');
    }

    private function rejectCall()
    {
        $response = new Twiml();
        $response->reject(['reason' => 'rejected']);

        return response($response)->header('Content-Type', 'application/xml');
    }

    private function rejectCallWithComment($comment)
    {
        $response = new Twiml();
        $response->say($comment);
        $response->reject(['reason' => 'rejected']);

        return response($response)->header('Content-Type', 'application/xml');
    }

    public function changeCallToConferenceTwiml($callSid)
    {
        $newTwiml = new Twiml();
        $newTwiml->dial()->conference($callSid, [
            'startConferenceOnEnter' => true,
            'endConferenceOnExit' => true,
            'beep' => true,
            'muted' => false,
            'record' => 'do-not-record',
        ]);

        return response($newTwiml)->header('Content-Type', 'application/xml');
    }

    public function addParticipantToConference(Request $request)
    {
        try {
            $this->_client->conferences($request->conference)->participants->create($request->from ?: config('services.twilio.default_number'), $request->to);
            return response()->json(['error' => false]);
        } catch (Exception $e) {
            return response()->json(['lastRequest1' => $this->_client->getHttpClient()->lastRequest, 'error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function changeCallToConference(Request $request, $callSid)
    {
        try {
            $options = [
                'method' => 'POST',
                'url' => (config('app.urls.mgmt')) . '/api/twilio/changeToConferenceTwiml/' . $callSid,
            ];

            $call = $this->_client->calls($request->in)->fetch();
            if ($call->status !== 'in-progress') {
                throw new Exception('The call ' . $request->in . ' is not in-progress, it is ' . $call->status);
            }

            // place customer in our new conference
            $this->_client->calls($callSid)->update($options);

            usleep(5000);

            // connect agent to same
            $conference = $this->_client->conferences->read(['friendlyName' => $callSid]);
            //if (count($conference) == 1) {
            $this->_client->conferences($conference[0]->sid)->participants->create(config('services.twilio.default_number'), $request->client);
            return response()->json(['error' => false, 'conference' => $conference[0]->sid]);
            //}
            throw new Exception('Could not create conference');
        } catch (Exception $e) {
            return response()->json(['lastRequest2' => $this->_client->getHttpClient()->lastRequest, 'error' => true, 'message' => $e->getMessage()]);
        }
    }

    private function highVolume(): bool
    {
        $test = json_decode(json_encode(DB::table('runtime_settings')->where('name', 'high_volume')->where('namespace', 'system')->first()), true);
        if ($test) {
            if (!is_numeric($test['value'])) {
                $this->highVolumeMessage = $test['value'];
                return true;
            }
            if ($test['value'] != 0) {
                return true;
            }
        }
        return false;
    }


    public function ivr()
    {
        $isHighVolume = $this->highVolume();
        $type = request()->input('type');
        if (null == $type) {
            $type = 'ivr';
        }
        switch ($type) {
            case 'ivr':
                $from = request()->input('From');
                $to = request()->input('To');

                if ($to == config('services.twilio.default_number')) {
                    return $this->unmonitored();
                }
                if (!starts_with($from, '+')) {
                    $from = '+' . $from;
                }
                if (!starts_with($to, '+')) {
                    $to = '+' . $to;
                }
                $tophone = Cache::remember(
                    'tophone_' . $to,
                    300,
                    function () use ($to) {
                        return Dnis::select(
                            'dnis.brand_id',
                            'dnis.states',
                            'scripts.channel_id'
                        )
                            ->join('scripts', 'dnis.id', 'scripts.dnis_id')
                            ->where('dnis.dnis', $to)
                            ->first();
                    }
                );
                if (null == $tophone) {
                    return $this->rejectCall();
                }
                $config_answers = Cache::remember(
                    'brand_config_' . $tophone->brand_id,
                    300,
                    function () use ($tophone) {
                        $configs = BrandConfig::where(
                            'brand_id',
                            $tophone->brand_id
                        )->first();
                        if (!is_null($configs)) {
                            return json_decode($configs->rules);
                        } else {
                            return new StdClass();
                        }
                    }
                );
                $agent = null;
                $whoareyou = PhoneNumberLookup::select(
                    'users.id',
                    'users.first_name',
                    'brand_users.state_id',
                    'brand_users.channel_id'
                )
                    ->join(
                        'phone_numbers',
                        'phone_number_lookup.phone_number_id',
                        'phone_numbers.id'
                    )
                    ->join('users', 'phone_number_lookup.type_id', 'users.id')
                    ->join('brand_users', 'users.id', 'brand_users.user_id')
                    ->where('phone_numbers.phone_number', $from)
                    ->where('phone_number_lookup.phone_number_type_id', 1)
                    ->where('brand_users.works_for_id', $tophone->brand_id)
                    ->whereNull('brand_users.deleted_at')
                    ->whereNull('users.deleted_at');
                $uses = $whoareyou->count();
                if (
                    isset($config_answers->ivr_known_numbers)
                    && true == $config_answers->ivr_known_numbers
                    && 0 == $uses
                ) {
                    return $this->rejectCall();
                }
                if (1 == $uses) {
                    $whoareyou = $whoareyou->first();
                    $agent = $whoareyou->id;
                }
                if ($uses > 1) {
                    $whoareyou = $whoareyou->get();
                }
                $ivr_company = Cache::remember(
                    'ivr_company_' . $tophone->brand_id,
                    300,
                    function () use ($tophone) {
                        return Brand::select('name')
                            ->where('id', $tophone->brand_id)
                            ->first();
                    }
                );
                $response = new Twiml();
                // if ($uses == 1) {
                //     $response->say('Hello, ' . $whoareyou->first_name . '.', ['voice' => 'woman']);
                // } else {
                //     $response->say('Hello!', ['voice' => 'woman']);
                // }
                // $response->say('We show you calling for ' . $ivr_company->name . '.', ['voice' => 'woman']);
                $state_id = null;
                // echo "DNIS STATES: ".$tophone->states."\n";
                if (strlen(trim($tophone->states)) > 0) {
                    $state_explode = explode(',', $tophone->states);
                    $states = Cache::remember(
                        'dnis_states_' . $tophone->brand_id . '_' . md5($tophone->states),
                        300,
                        function () use ($state_explode) {
                            return State::select('states.name')
                                ->whereIn('states.id', $state_explode)
                                ->get()
                                ->toArray();
                        }
                    );
                }
                if (array_key_exists('Digits', $_POST)) {
                    Log::debug('Received: ' . $_POST['Digits']);
                    $state_id = $state_explode[$_POST['Digits'] - 1];
                } elseif (isset($whoareyou) && isset($whoareyou->state_id) && $whoareyou->state_id) {
                    $response
                        ->say(
                            'Thank you for calling T P V dot com.',
                            [
                                'voice' => 'woman',
                            ]
                        );
                    if ($isHighVolume) {
                        $response->say($this->highVolumeMessage, ['voice' => 'woman']);
                    }
                    $state_id = $whoareyou->state_id;
                } else {
                    $response->say(
                        'Thank you for calling T P V dot com.',
                        [
                            'voice' => 'woman',
                        ]
                    );
                    if ($isHighVolume) {
                        $response->say($this->highVolumeMessage, ['voice' => 'woman']);
                    }
                    if (isset($state_explode) && 1 == count($state_explode)) {
                        $state_id = $state_explode[0];
                    } elseif (isset($states) && count($states) > 0) {
                        $gather = $response->gather(
                            [
                                'input' => 'dtmf speech',
                                'numDigits' => 1,
                            ]
                        );
                        for ($i = 0; $i < count($states); ++$i) {
                            $gather->say(
                                'For ' . $states[$i]['name'] . ' press ' . ($i + 1),
                                [
                                    'voice' => 'woman',
                                ]
                            );
                        }
                    }
                }
                // Log::debug("STATE_ID: ".$state_id);
                // Log::debug(print_r($state_id, true));
                if ($state_id) {
                    $tz = Cache::remember(
                        'zips_' . $state_id,
                        1800,
                        function () use ($state_id) {
                            return ZipCode::select('timezone')
                                ->leftJoin('states', 'zips.state', 'states.state_abbrev')
                                ->where('states.id', $state_id)
                                ->first();
                        }
                    );
                    $hours = Cache::remember(
                        'brand_hour_' . $tophone->brand_id . '_' . $state_id,
                        300,
                        function () use ($tophone, $state_id) {
                            return BrandHour::where('brand_id', $tophone->brand_id)
                                ->where('state_id', $state_id)
                                ->first();
                        }
                    );
                    $now = Carbon::now($tz->timezone);
                    $dayofweek = $now->copy()->formatLocalized('%A');
                    $month_day = $now->copy()->formatLocalized('%m-%d');
                    if ($hours) {
                        $hours_array = $hours->toArray();
                        if (isset($hours_array['data'])) {
                            $hour_data = json_decode($hours_array['data'], true);
                        } else {
                            $hour_data = null;
                        }
                    } else {
                        $hour_data = null;
                    }
                }
                $open = (isset($hour_data) && isset($hour_data[$dayofweek]) && isset($hour_data[$dayofweek]['open']) && 'Closed' != $hour_data[$dayofweek]['open']) ? $now->copy()->hour(explode(':', $hour_data[$dayofweek]['open'])[0])->minute(00) : null;
                $close = (isset($hour_data) && isset($hour_data[$dayofweek]) && isset($hour_data[$dayofweek]['close']) && 'Closed' != $hour_data[$dayofweek]['close']) ? $now->copy()->hour(explode(':', $hour_data[$dayofweek]['close'])[0])->minute(00) : null;
                // Check if today is an exception (usually means a holiday)
                if (isset($hour_data['Exceptions']) && isset($hour_data['Exceptions']['Closed'])) {
                    for ($i = 0; $i < count($hour_data['Exceptions']['Closed']); ++$i) {
                        if ($month_day == $hour_data['Exceptions']['Closed'][$i]) {
                            return $this->rejectCallWithComment('Sorry!  We are currently closed.');
                        }
                    }
                }
                $continue = ($open && $close) ? $now->between($open, $close) : false;
                // echo "MONTH-DAY: ".$month_day."\n";
                // echo "DAY OF WEEK: ".$dayofweek."\n";
                // echo "OPEN: ".$open."\n";
                // echo "CLOSE: ".$close."\n";
                // echo "CONTINUE: ".$continue."\n";
                if ((!isset($hours) || null == $hours) || $continue) {
                    $response->say(
                        'Please hold while I bring an agent on
                    the line for your verification.',
                        [
                            'voice' => 'woman',
                        ]
                    );
                    $channel_id = (isset($tophone->channel_id)
                        && isset($tophone->channel_id))
                        ?
                        $tophone->channel_id
                        : 1;
                    $response
                        ->enqueue(null, ['workflowSid' => config('services.twilio.workflow')])
                        ->task(
                            json_encode(
                                [
                                    'brand_id' => $tophone->brand_id,
                                    'client' => $ivr_company->name,
                                    'agent' => $agent,
                                    'state_id' => $state_id,
                                    'channel_id' => $channel_id,
                                ]
                            ),
                            array('priority' => 1)
                        );

                    return response($response)->header(
                        'Content-Type',
                        'application/xml'
                    );
                } else {
                    return $this->rejectCallWithComment(
                        'Sorry!  We are currently closed.'
                    );
                }
                break;
            case 'make-outgoing':
                $to = request()->input('to');
                $from = request()->input('from');
                $response = new Twiml();
                $response->dial($to, ['callerId' => $from, 'record' => true]);

                return response($response)->header('Content-Type', 'application/xml');
                break;
        }
    }

    public function get_activities()
    {
        $activities = Cache::remember(
            'activities',
            7200,
            function () {
                $activities = $this->_workspace->activities->read();
                $results = [];
                foreach ($activities as $activity) {
                    $a = ['id' => $activity->sid, 'activity' => $activity->friendlyName];
                    $results[] = $a;
                }

                return $results;
            }
        );

        return $activities;
    }

    public function unmonitored()
    {
        $script = 'Thank you for calling TPV dot com Powered by Data Exchange. If you’ve missed a call from us, it will be helpful to know that we reached out to you on behalf of a business you have recently interacted with. If you’re unfamiliar with us, then you can disregard our call. If you require assistance, please reach out to that business using the contact information they provided. This number is not a monitored number.';
        $response = new Twiml();
        $response->say($script, [
            'voice' => 'woman'
        ]);
        $response->hangUp();
        return response($response, 200);
    }

    public function update_worker(Request $request)
    {
        info('Got Request to change Agent Status', [$request->input()]);
        $worker_id = $request->get('worker_id');
        $activity_sid = $request->get('activity_sid');
        $on_call = $request->get('on_call');

        if ($on_call) {
            try {
                $psc = new PendingStatusChange;
                $psc->tpv_id = $worker_id;
                $psc->new_status_id = $activity_sid;
                $psc->completed = false;
                $psc->save();
            } catch (Exception $e) {
                info("Error while saving PendingStatusChange to database.");
            }

            return "PendingStatusChange";
        }

        return $this->update_worker_api($worker_id, $activity_sid);
    }

    public function update_worker_api($tpv_staff_id = null, $activity_sid = null)
    {
        if ($tpv_staff_id && $activity_sid) {
            $activities = $this->get_activities();
            $activity = null;
            foreach ($activities as $actToCheck) {
                if ($actToCheck['id'] == $activity_sid) {
                    $activity = $actToCheck['activity'];
                }
            }
            if (!empty($activity)) {
                $as = new AgentStatus();
                $as->tpv_staff_id = $tpv_staff_id;
                $as->created_at = now('America/Chicago');
                $as->event = 'Activity updated to ' . $activity . ' by ' . optional(Auth::user())->first_name . ' ' . optional(Auth::user())->last_name;
                $as->language_id = 1;
                $as->save();
            }
            info('Updating tpv ' . $tpv_staff_id . ' status to ' . $activity_sid . ' by ' . Auth::id());
            $worker = $this->_workspace
                ->workers($tpv_staff_id)
                ->update(
                    array(
                        'activitySid' => $activity_sid,
                    )
                );

            return response()->json($worker->toArray());
        }
    }

    public function get_workers_raw()
    {
        return $this->_workspace->workers->read();
    }

    public function get_workers()
    {
        $workers = Cache::remember('twilio-workers', 300, function () {
            $get_workers = $this->get_workers_raw();
            $out = [];
            foreach ($get_workers as $record) {
                $worker = $this->_workspace->workers($record->sid)->fetch();
                $staff = Cache::remember(
                    'staff_worker_' . $record->sid,
                    900,
                    function () use ($worker) {
                        return TpvStaff::select(
                            'first_name',
                            'last_name',
                            'call_center_id',
                            'language_id'
                        )
                            ->where('username', $worker->friendlyName)
                            ->first();
                    }
                );
                if ($staff) {
                    $row = [
                        'id' => $worker->friendlyName,
                        'name' => $staff->first_name . ' ' . $staff->last_name,
                        'status' => $worker->activitySid,
                        'worker_id' => $record->sid,
                    ];
                    $out[] = $row;
                }
            }
            return $out;
        });

        return $workers;
    }

    public function calls()
    {
        $calls = Cache::remember(
            'twilio_calls',
            30,
            function () {
                $array = array();
                foreach ($this->_client->calls->read(array('status' => 'in-progress')) as $call) {
                    $array[] = array('date' => $call->dateCreated, 'sid' => $call->sid, 'direction' => $call->direction, 'duration' => $call->duration, 'from' => $call->from, 'fromFormatted' => $call->fromFormatted, 'to' => $call->to, 'toFormatted' => $call->toFormatted, 'parentCallSid' => $call->parentCallSid);
                }

                return $array;
            }
        );

        return $calls;
    }

    public function callsFilter($category, $start, $end)
    {
        $records = Cache::remember(
            'twilio_calls',
            30,
            function () use ($category, $start, $end) {
                $records = $this->client->usage->records->read(
                    array(
                        'category' => $category,
                        'startDate' => $start,
                        'endDate' => $end,
                    )
                );

                return $records;
            }
        );

        return $records;
    }

    private function get_service_levels()
    {
        $lastIntervalStart = null;
        $lastIntervalEnd = null;
        $rightNow = Carbon::now();
        if ($rightNow->minute >= 30) {
            $lastIntervalStart = $rightNow->copy();
            $lastIntervalStart->minute = 0;
            $lastIntervalStart->second = 0;
        } else {
            $lastIntervalStart = $rightNow->copy();
            $lastIntervalStart->minute = 30;
            $lastIntervalStart->second = 0;
            $lastIntervalStart->addHours(-1);
        }
        $lastIntervalEnd = $lastIntervalStart->copy()
            ->addMinutes(30)->addSeconds(-1);
        $t = $this->_workspace
            ->events
            ->read(
                [
                    'EventType' => 'reservation.created',
                    'StartDate' => $lastIntervalStart->format(\DateTime::ISO8601),
                    'EndDate' => $lastIntervalEnd->format(\DateTime::ISO8601),
                ]
            );
        $t2 = $this->_workspace
            ->events
            ->read(
                [
                    'EventType' => 'reservation.accepted',
                    'StartDate' => $lastIntervalStart->format(\DateTime::ISO8601),
                    'EndDate' => $lastIntervalEnd->format(\DateTime::ISO8601),
                ]
            );

        $out = [];
        $total = count($t) + count($t2);
        $t = array_merge($t, $t2);
        foreach ($t as $event) {
            try {
                $x = $this->_workspace->events($event->sid)->fetch()->toArray();
                if (str_contains($x['eventType'], ['reservation.created', 'reservation.accepted'])) {
                    switch ($x['eventType']) {
                        case 'reservation.created':
                            $queue = $x['eventData']['task_queue_name'];
                            if (!isset($out[$queue])) {
                                $out[$queue] = [];
                            }
                            if (!isset($out[$queue][$x['eventData']['reservation_sid']])) {
                                $out[$queue][$x['eventData']['reservation_sid']] = ['start' => (Carbon::instance($x['eventDate']))->format(\DateTime::ISO8601)];
                            } else {
                                $out[$queue][$x['eventData']['reservation_sid']]['start'] = (Carbon::instance($x['eventDate']))->format(\DateTime::ISO8601);
                            }

                            break;
                        case 'reservation.accepted':
                            $queue = $x['eventData']['task_queue_name'];
                            if (!isset($out[$queue])) {
                                $out[$queue] = [];
                            }
                            if (!isset($out[$queue][$x['eventData']['reservation_sid']])) {
                                $out[$queue][$x['eventData']['reservation_sid']] = ['end' => (Carbon::instance($x['eventDate']))->format(\DateTime::ISO8601)];
                            } else {
                                $out[$queue][$x['eventData']['reservation_sid']]['end'] = (Carbon::instance($x['eventDate']))->format(\DateTime::ISO8601);
                            }
                            break;
                    }
                }
            } catch (\Twilio\Exceptions\RestException $e) {
                info($e->getCode() . ' : ' . $e->getMessage());
            }
        }
        $out2 = [];
        foreach ($out as $queue => $tasks) {
            $temp = [];
            foreach ($tasks as $reservation => $info) {
                if (isset($info['start']) && isset($info['end'])) {
                    $start = Carbon::parse($info['start']);
                    $end = Carbon::parse($info['end']);
                    $temp[] = $end->diffInSeconds($start);
                }
            }
            $out2[$queue] = [];
            if (count($temp) > 0) {
                for ($i = 10; $i < 65; $i += 5) {
                    $out2[$queue][$i . ''] = round(floatval($this->count_values_less_than($temp, $i) / count($temp)) * 100, 2);
                }
            }
        }

        return $out2;
    }

    private function count_values_less_than(array $array, int $value): int
    {
        $ret = 0;
        $len = count($array);
        for ($i = 0; $i < $len; ++$i) {
            if ($array[$i] < $value) {
                ++$ret;
            }
        }

        return $ret;
    }

    public function get_tasks()
    {
        $tasks = $this->_workspace->tasks->read();

        return response()->json($tasks);
    }

    public function getTaskQueues()
    {
        return Cache::remember(
            'twilio-task-queues',
            900,
            function () {
                $taskQueues = $this->_workspace->taskQueues->read();
                $out = [];
                foreach ($taskQueues as $queue) {
                    $out[] = [
                        'FriendlyName' => $queue->friendlyName,
                        'Sid' => $queue->sid,
                    ];
                }

                return $out;
            }
        );
    }

    private function getQueueStats($queueSid)
    {
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $stats = $this->_workspace
            ->taskQueues($queueSid)->statistics()
            ->fetch(['startDate' => $today]);
        $rstats = $stats->realtime;
        $cstats = $stats->cumulative;
        $out = [];
        $out['*LongestTaskWaitingAge'] = $rstats[snake_case('longestTaskWaitingAge')];
        $out['*TotalTasks'] = $rstats[snake_case('totalTasks')];
        $out['!Tasks'] = count($this->_workspace->tasks->read(['taskQueueSid' => $queueSid, 'assignmentStatus' => 'pending']));
        $out['*TotalEligibleWorkers'] = $rstats[snake_case('totalEligibleWorkers')];
        $out['*TotalAvailableWorkers'] = $rstats[snake_case('totalAvailableWorkers')];
        $out['WaitDurationUntilAccepted'] = $cstats[snake_case('waitDurationUntilAccepted')];
        $out['*ActivityStatistics'] = collect($rstats[snake_case('activityStatistics')])->map(
            function ($item) {
                return [$item['friendly_name'] => $item['workers']];
            }
        );

        return $out;
    }

    public function getWorkspaceStats()
    {
        $today = Carbon::today('America/Chicago')->toIso8601String();
        $out = [];
        $stats = $this->_workspace->statistics()->fetch(['startDate' => $today]);
        $cstats = $stats->cumulative;
        $rstats = $stats->realtime;
        $out['AvgTaskAcceptanceTime'] = $cstats[snake_case('avgTaskAcceptanceTime')];
        $out['ReservationsAccepted'] = $cstats[snake_case('reservationsAccepted')];
        $out['ReservationsRejected'] = $cstats[snake_case('reservationsRejected')];
        $out['ReservationsTimedOut'] = $cstats[snake_case('reservationsTimedOut')];
        $out['ReservationsCanceled'] = $cstats[snake_case('reservationsCanceled')];
        $out['ReservationsRescinded'] = $cstats[snake_case('reservationsRescinded')];
        $out['TasksCreated'] = $cstats[snake_case('tasksCreated')];
        $out['TasksCanceled'] = $cstats[snake_case('tasksCanceled')];
        $out['TasksCompleted'] = $cstats[snake_case('tasksCompleted')];
        $out['TasksDeleted'] = $cstats[snake_case('tasksDeleted')];
        $out['TasksMoved'] = $cstats[snake_case('tasksMoved')];
        $out['TasksTimedOutInWorkflow'] = $cstats[snake_case('tasksTimedOutInWorkflow')];
        $out['WaitDurationUntilAccepted'] = $cstats[snake_case('waitDurationUntilAccepted')];
        $out['*ActivityStatistics'] = collect($rstats[snake_case('activityStatistics')])->map(
            function ($item) {
                return [$item['friendly_name'] => $item['workers']];
            }
        );

        return $out;
    }

    private function get_stats_array()
    {
        $latest = JsonDocument::where('document_type', 'workspace-stats')->orderBy(
            'created_at',
            'DESC'
        )->first();
        if (null != $latest) {
            $age = Carbon::now('America/Chicago')
                ->diffInRealSeconds($latest->created_at);
        } else {
            $age = 10;
        }
        if ($age > 0) {
            $start = microtime(true);
            $out = $this->getWorkspaceStats();
            //dd($out);
            $out['queues'] = collect($this->getTaskQueues())->map(
                function ($item) {
                    return [
                        $item['FriendlyName'] => $this->getQueueStats($item['Sid']),
                    ];
                }
            )->toArray();
            $svcLevels = Cache::remember(
                'service-levels',
                60,
                function () {
                    return $this->get_service_levels();
                }
            );

            foreach ($svcLevels as $queue => $stats) {
                for ($i = 0, $len = count($out['queues']); $i < $len; ++$i) {
                    $q = array_keys($out['queues'][$i])[0];
                    if ($q === $queue) {
                        $out['queues'][$i][$queue]['svcLevel'] = $stats;
                    }
                }
            }

            $end = number_format(microtime(true) - $start, 3);
            $out['AsOf'] = Carbon::now()->toIso8601String();
            $out['ProcessingTime'] = $end;
            $newJ = new JsonDocument();
            $newJ->document_type = 'workspace-stats';
            $newJ->document = $out;
            $newJ->save();
            $howMany = JsonDocument::where(
                'document_type',
                'workspace-stats'
            )->count();
            // info("howMany is " . $howMany);
            if ($howMany > 10) {
                JsonDocument::where('document_type', 'workspace-stats')
                    ->orderBy('created_at', 'ASC')->first()->delete();
            }
            $out['isNew'] = true;

            return $out;
        } else {
            $out = $latest->document;
            $out['isNew'] = false;

            return $out;
        }
    }

    public function get_stats()
    {
        return $this->getStatsRaw("stats-job");
    }

    public function get_stats_new()
    {
        return $this->getStatsRaw("stats-job-2");
    }

    private function getStatsRaw($stats_job_type)
    {
        $stats = JsonDocument::where(
            'document_type',
            $stats_job_type
        )->orderBy(
            'created_at',
            'desc'
        )->first();

        if (!$stats) {
            return response()->json();
        } else {
            return response()->json($stats);
        }
    }
    public function callcenter()
    {
        return view(
            'dashboard/callcenter',
            []
        );
    }

    public function liveagent()
    {
        $callCenters = CallCenter::all();
        return view(
            'dashboard/liveagent',
            [
                'callCenters' => $callCenters
            ]
        );
    }

    public function notready()
    {
        return view(
            'dashboard/notready',
            []
        );
    }

    public function queues()
    {
        return view(
            'dashboard/queues',
            []
        );
    }

    public function television()
    {
        return view(
            'dashboard/television',
            []
        );
    }

    public function alerts()
    {
        return view(
            'dashboard/alerts',
            []
        );
    }

    public function surveys()
    {
        return view(
            'dashboard/surveys',
            []
        );
    }
    public function debug_info(Request $request)
    {
        $this->_client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $this->_workspace_id = config('services.twilio.workspace');
        $this->_workspace = $this->_client->taskrouter->workspaces($this->_workspace_id);
        $tasks = $this->_workspace->tasks->read(array('assignmentStatus' => 'pending'));
        $task_queues = $this->_workspace->taskQueues->read();
        $calls = $this->_client->calls->read(array('status' => 'in-progress'));
        $activities = $this->_workspace->activities->read();
        $workers = $this->_workspace->workers->read(["activityName" => "TPV In Progress"]);
        $start_date = Carbon::now('America/Chicago')->toIso8601String();
        $end_date = Carbon::now('America/Chicago')->addMinutes(30)->toIso8601String();
        $timefilter = [
            'startDate' => $start_date,
            'endDate' => $end_date,
            'splitByWaitTime' => '20',
        ];
        $stats = $this->_workspace->cumulativeStatistics()->fetch($timefilter);

        return view(
            'twilio.debug',
            [
                'stats' => collect($stats),
                'workers' => collect($workers),
                'task_queues' => collect($task_queues),
                'calls' => collect($calls),
                'activities' => collect($activities),
                'tasks' => collect($tasks),
            ]
        );
    }

    public function test_calls()
    {
        return view(
            'support.test_calls',
            []
        );
    }

    public function getToken()
    {
        $identity = 'Test_Caller';
        $capability = new ClientToken(config('services.twilio.account'), config('services.twilio.auth_token'));
        $capability->allowClientOutgoing(config('services.twilio.app'));
        $capability->allowClientIncoming($identity);
        $token = $capability->generateToken();

        return response()->json(array(
            'identity' => $identity,
            'token' => $token,
        ));
    }

    /**
     * Handle incoming SMS messages from Twilio
     */
    public function incomingSms(Request $r)
    {
        try {
            $from = $r->input('From');
            $to   = $r->input('To');

            // Translate To number to a brand name
            $brand_id = $this->getBrandFromIncomingText($to);

            // Look up 'from' phone number for its ID.
            // If it doens't exist, create the phone number entry.
            $fromPhone = PhoneNumber::where('phone_number', $from)->whereNull('extension')->first();
            if ($fromPhone === null) {
                $fromPhone = new PhoneNumber();
                $fromPhone->phone_number = $from;
                $fromPhone->save();
            }

            // Look up 'to' phone number for its ID.
            // If it doens't exist, create the phone number entry.
            $toPhone = PhoneNumber::where('phone_number', $to)->whereNull('extension')->first();
            if ($toPhone === null) {
                $toPhone = new PhoneNumber();
                $toPhone->phone_number = $to;
                $toPhone->save();
            }

            // Log the incoming message
            $tmi = new TextMessagesIncoming();

            $tmi->message_sid   = $r->input('SmsMessageSid');
            $tmi->brand_id      = $brand_id;
            $tmi->from_phone_id = $fromPhone->id;
            $tmi->to_phone_id   = $toPhone->id;
            $tmi->content       = $r->input('Body');

            $tmi->save();
            
        } catch (\Exception $e) {
            info('TwilioController::incomingSms() Exception: ' . $e->getMessage());

            $jd = new JsonDocument();

            $jd->ref_id = ($r && $r->input('SmsMessageSid') !== null) ? $r->input('SmsMessageSid') : Carbon::now("America/Chicago")->format("Y-m-d H:i:s");
            $jd->document_type = 'incoming-sms-error';
            $jd->document = [
                'DateTime' => Carbon::now("America/Chicago")->format("Y-m-d H:i:s"),
                'Request' => $r->all(),
                'Error' => $e->getMessage()
            ];

            $jd->save();
        }

        return;
    }

    /**
     * Return a brand ID based on the $toPhoneNumber
     */
    public function getBrandFromIncomingText($toPhoneNumber)
    {
        switch ($toPhoneNumber) {
            case '+18555032758': // Stage
            case '+18886164601': // Prod
                return '77c6df91-8384-45a5-8a17-3d6c67ed78bf'; // IDT Energy
            case '+18554913148': // Stage
            case '+18886164504': // Prod
                return '0e80edba-dd3f-4761-9b67-3d4a15914adb'; // Residents Energy
            default:
                return null;
        }
    }

    public function updateSmsStatus()
    {
        $sid = request()->input('MessageSid');
        $status = request()->input('MessageStatus');
        info('SMS Update', request()->input());
        $tm = TextMessage::onWriteConnection()->where('message_sid', $sid)->first();
        if ($tm) {
            $tm->status = $status;
            $tm->save();
        } else {
            abort(400);
        }
        return response()->json(['error' => false]);
    }

    public function getCallActiveStatus($worker_id)
    {
        $worker = $this->_workspace->workers($worker_id)->fetch();
        switch ($worker->activityName) {
            case "TPV In Progress":
            case "After Call Work":
                return 'true';

            default:
                return 'false';
        }
    }

    public function updateStatusIfPending($worker_id)
    {
        try {
            $psc = PendingStatusChange::where('tpv_id', '=', $worker_id)->firstOrFail();
        } catch (Exception $e) {
            info("PendingStatusChange record doesn't exist.");
            return 'false';
        }

        if ($psc) {
            try {
                $tpv_id = $psc->tpv_id;
                $new_status_id = $psc->new_status_id;
                $psc->completed = true;
                $this->update_worker_api($tpv_id, $new_status_id);
            } catch (Exception $e) {
                return 'false';
            }
            return 'true';
        }
        return 'true';
    }
}
