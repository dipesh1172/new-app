<?php

namespace App\Http\Controllers;

use Twilio\TwiML\VoiceResponse as Twiml;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\RestException;
use Symfony\Component\Console\Output\BufferedOutput;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;
use App\Models\WebhookLog;
use App\Models\UserHireflowActivity;
use App\Models\User;
use App\Models\Upload;
use App\Models\TpvStaff;
use App\Models\Survey;
use App\Models\State;
use App\Models\ScriptAnswer;
use App\Models\Script;
use App\Models\JsonDocument;
use App\Models\Interaction;
use App\Models\GpsDistance;
use App\Models\GpsCoord;
use App\Models\Eztpv;
use App\Models\EventProduct;
use App\Models\EventCallback;
use App\Models\Event;
use App\Models\EmailAddress;
use App\Models\DigitalSubmission;
use App\Models\Brand;
use App\Mail\GenericEmail;
use App\Mail\ConfigChange;

class WebhookController extends Controller
{
    var $client;
    public function __construct()
    {
        $this->middleware(
            function ($request, $next) {
                return $this->cors($request, $next);
            }
        );
    }

    public function sayItsOk()
    {
        return response('It is Okay My Dude', 200);
    }

    private function cors($request, $next)
    {
        $x = $next($request);
        if (is_object($x)) {
            return $x->withHeaders(
                [
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                    'Access-Control-Allow-Headers' => '*',
                ]
            );
        }
        return response($x ? 'true' : 'false', 400);
    }

    private function generic_auth(Request $request)
    {
        $isBypassed = $request->input('bypassauth');
        if ('yes' === $isBypassed && 'production' !== config('app.env', 'production')) {
            return null;
        }
        $token = $request->input('token');
        if (null === $token) {
            abort(400, 'No Authorization Token');
        }
        if ('U' == $token[0]) {
            $user = User::where('api_token', $token)->first();
        } else {
            if ('X' == $token[0]) {
                $eventId = $request->input('event');
                if (null === $eventId) {
                    abort(400, 'Missing Authorization Information');
                }
                $event = Event::find($eventId);
                if (null !== $event) {
                    $confCode = $event->confirmation_code;
                    $shouldBe = 'X' . hash_hmac('sha256', $eventId, $confCode);
                    if (!hash_equals($shouldBe, $token)) {
                        abort(401, 'Authorization Invalid - hashes do not match');
                    }
                    $user = User::leftJoin(
                        'brand_users',
                        'brand_users.user_id',
                        'users.id'
                    )->where(
                        'brand_users.id',
                        $event->sales_agent_id
                    )->first();
                } else {
                    abort(401, 'Authorization Invalid - invalid event');
                }
            } else {
                $user = TpvStaff::where('api_token', $token)->first();
            }
        }
        if (null === $user) {
            abort(401, 'Authorization Invalid - invalid user');
        }
        // maybe should also check the user has permission here
        return $user;
    }

    private function verifyMailgunSignature($data)
    {
        $signingKey = config('services.mailgun.webhook_key');
        if ($signingKey) {
            $token = $data['token'];
            $timestamp = $data['timestamp'];
            $signature = $data['signature'];
            // check if the timestamp is fresh
            if (abs(time() - $timestamp) > 15) {
                return false;
            }

            // returns true if signature is valid
            return hash_equals(hash_hmac('sha256', $timestamp . $token, $signingKey), $signature);
        }
        return true;
    }


    public function generic_hook(Request $request)
    {
        $beVerbose = null !== $request->input('verbose');
        if ($beVerbose) {
            Log::debug(print_r($request->all(), true));
        }
        $command = $request->input('command');
        $authUser = null;
        $noAuthCommands = [
            'contract:select',
            'recording:status',
            'conference:twiml',
            'update:interaction',
            'email:status',
            'transcription:update',
            'calculate:distance-cust',
            'address:gps-locate',
            'address:gps-locate-suggested',
            'zip:lookup',
            'select:contract',
            'stats:generate',
            'schedule:survey',
            'sqs:queue:contract',
            'run:vms'
        ];
        if (config('app.env', 'production') !== 'production') {
            $noAuthCommands[] = 'salespitch:record';
            $noAuthCommands[] = 'hydrate:vars';
        }

        if (!in_array($command, $noAuthCommands)) {
            $authUser = $this->generic_auth($request);
        }

        switch ($command) {
            case 'stats:generate':
                $cCode = trim($request->input('confirmation'));
                if (!empty($cCode)) {
                    Artisan::queue('stats:product', [
                        '--confirmationCode' => $cCode,
                    ]);
                    return response('', 200);
                }
                abort(401, 'Missing Confirmation code');
                break;

            case 'zip:lookup':
                $zip = $request->input('zip');
                $zl = strlen($zip);
                if ($zl < 5 || $zl > 6) {
                    abort(400, 'Invalid Zip/Postal code');
                }
                $ret = Artisan::call('zip:lookup', [
                    '--zip' => $zip,
                ]);
                if ($ret == 1) {
                    return response('', 200);
                } else {
                    return response($ret . '', 401);
                }
                break;

            case 'calculate:distance-cust':
                $type = intval($request->input('dtype'));
                if (!is_int($type)) {
                    abort(400, 'Invalid dtype');
                }

                $custLocationId = $request->input('loc');
                $custLocation = GpsCoord::find($custLocationId);
                if (!empty($custLocation)) {

                    $servAddrLoc = null;
                    $event_id = null;
                    $ev = null;
                    switch ($custLocation->ref_type_id) {
                        default:
                            info('[calculate:distance-cust] Invalid ref type id for distance calculation');
                            break;
                        case 1: // ref type is eztpv
                            $ev = Event::where('eztpv_id', $custLocation->type_id)->first();
                            $event_id = $ev->id;
                            break;
                        case 2:
                            $event_id = $custLocation->type_id;
                            break;
                        case 4: // ref type is digital submission
                            $ev = null;
                            $ds = DigitalSubmission::find($custLocation->type_id);
                            $event_id = $ds->event_id;
                            break;
                    }
                    if ($event_id != null) {
                        if ($ev == null) {
                            $ev = Event::find($event_id);
                        }
                        if ($type === 4) {
                            // have to find the point_a different here since we're looking for the sales agent location not a service address
                            $servAddrLoc = GpsCoord::where('type_id', $event_id)
                                ->where('gps_coord_type_id', 2)
                                ->where('ref_type_id', 3)
                                ->first();
                            if (empty($servAddrLoc) && $ev->eztpv_id != null) {
                                $servAddrLoc = GpsCoord::where('type_id', $ev->eztpv_id)
                                    ->where('gps_coord_type_id', 2)
                                    ->where('ref_type_id', 1)
                                    ->first();
                            }
                        }

                        if (empty($servAddrLoc) && !empty($ev->products)) {
                            try {
                                $servAddrLoc = $ev->products[0]->serviceAddress->address->gps_coordinates;
                            } catch (\Exception $e) {
                                info('[calculate:distance-cust] Error trying to get product service address for event: ' . $event_id, [$e]);
                            }
                        } else {
                            info('[calculate:distance-cust] servAddrLoc is not empty or products is empty');
                        }
                    } else {
                        info('[calculate:distance-cust] event_id is null');
                    }
                    if ($servAddrLoc != null) {
                        $dist = new GpsDistance();
                        $dist->created_at = now('America/Chicago');
                        $dist->updated_at = now('America/Chicago');
                        $dist->gps_point_a = $servAddrLoc->id;
                        $dist->gps_point_b = $custLocationId;
                        $dist->distance_type_id = $type;
                        $dist->ref_type_id = 3;
                        $dist->type_id = $event_id;
                        $dist->distance = CalculateDistanceFromCoords($servAddrLoc, $custLocation);
                        $dist->save();
                    } else {
                        info('[calculate:distance-cust] serverAddrLoc is null');
                    }
                } else {
                    info('[calculate:distance-cust] unable to determine customer location');
                }
                return response()->json(['message' => 'submitted']);

            case 'address:gps-locate':
                $address = $request->input('address');
                Artisan::queue('gps:lookup', ['--addressid' => $address]);
                return response('', 200);

            case 'address:gps-locate-suggested':
                $address = $request->input('address');
                Artisan::queue('gps:lookup',['--addressid' => $address, '--use-suggested' => true]);
                return response('', 200);

            case 'ivr:callback':
                Log::debug(print_r($request->all(), true));
                $to = CleanPhoneNumber(request()->input('to'));
                if ($to === null) {
                    abort(400, 'Invalid Phone Number');
                }
                $from = CleanPhoneNumber(request()->input('from'));
                if ($from === null) {
                    $from = config('services.twilio.default_number');
                }
                $ivrUrl = request()->input('url');
                $url = str_replace(':/', '://', str_replace('//', '/', config('app.urls.mgmt'))) . $ivrUrl;
                Artisan::call('twilio:call-ivr', [
                    '--to' => $to,
                    '--from' => $from,
                    '--url' => $url,
                ]);
                return response()->json(['error' => false]);

            case 'salespitch:record':
                $to = $request->input('to');
                $ref_id = $request->input('ref_id');
                $brand = $request->input('brand');
                $salesAgent = $request->input('agent');
                $lang = $request->input('lang');
                if ($lang === null) {
                    $lang = 'en';
                }
                $transcribe = $request->input('transcribe');
                if ($transcribe === null) {
                    $transcribe = false;
                }
                $continue = false;
                if ($request->has('continue')) {
                    if ($request->input('continue') == 1) {
                        $continue = true;
                    }
                }

                if ($to && $ref_id) {
                    $twilio = new TwilioClient(config('services.twilio.account'), config('services.twilio.auth_token'));
                    try {
                        $call = $twilio->calls->create(
                            $to, // to
                            config('services.twilio.default_number'),
                            [
                                'url' => str_replace(
                                    ':/',
                                    '://',
                                    str_replace(
                                        '//',
                                        '/',
                                        config('app.urls.mgmt') . '/api/twilio/sales-pitch/start?ref_id=' . $ref_id . '&lang=' . $lang . ($transcribe ? '&transcribe=1' : '') . ($continue ? '&continue=1' : '') . '&brand=' . $brand . '&agent=' . $salesAgent
                                    )
                                ),
                            ]
                        );
                        return response()->json(['error' => false, 'call' => $call]);
                    } catch (RestException $e) {
                        info('Exception while trying to start sales pitch capture', [$e]);
                        return response()->json(['error' => $e->getMessage()]);
                    }
                }
                return response()->json(['error' => 'Invalid Parameters']);

            case 'select:contract':
                $brand_id = $request->input('brand_id');
                $state_id = intval($request->input('state_id'), 10);
                $market_id = intval($request->input('market_id'), 10);
                $channel_id = intval($request->input('channel_id'), 10);
                $language_id = intval($request->input('language_id'), 10);
                $rate_id = $request->input('rate_id');
                $commodity = $request->input('commodity');
                $document_type_id = intval($request->input('document_type_id'), 10);
                $api_submission = $request->input('api_submission') === 'yes' || $request->input('api_submission') === 'true' || $request->input('api_submission') == 1;

                $contract = \App\Http\Controllers\ContractController::choose_contract($brand_id, $state_id, $channel_id, $market_id, $language_id, $rate_id, $commodity, $document_type_id, $api_submission);
                return response()->json(['contract' => $contract]);

            case 'contract:select': // old method? is this used?
                $output = new BufferedOutput();
                $params['--brand']  = $request->input('brand');
                switch ($request->input('lang')) {
                    case 2:
                        $params['--spanish'] = true;
                        break;
                    case 1:
                    default:
                        $params['--english'] = true;
                        break;
                }
                $params['--state'] = $request->input('state');
                switch ($request->input('channel')) {
                    default:
                    case 1:
                        $params['--channel-dtd'] = true;
                        break;
                    case 2:
                        $params['--channel-tm'] = true;
                        break;
                    case 3:

                        $params['--channel-retail'] = true;
                        break;
                }
                switch ($request->input('market')) {
                    case 2:
                        $params['--commercial'] = true;
                        break;
                    default:
                    case 1:
                        $params['--residential'] = true;
                        break;
                }
                switch ($request->input('commodity')) {
                    case 'dual':
                        $params['--dual-fuel'] = true;
                        break;
                    case 'gas':
                        $params['--gas'] = true;
                        break;
                    default:
                    case 'electric':
                        $params['--electric'] = true;
                        break;
                }
                $ret = Artisan::call('contract:select', $params, $output);

                return response($output->fetch());
                break;

            case 'slack:notify':
                $message = request()->input('message');
                $channel = request()->input('channel');
                $ret = Artisan::call('slack:notify', [
                    '--channel' => $channel,
                    'message' => $message,
                ]);
                if ($ret == 0) {
                    return response('Message Sent', 200);
                }
                return response('Message Not Sent', 400);
                break;

            case 'email:status':
                $data = request()->input();
                info('Email Status Update', [$data]);
                if (isset($data['signature'])) {
                    $valid = $this->verifyMailgunSignature($data['signature']);
                } else {
                    $valid = false;
                }
                if (!$valid) {
                    info('Invalid Mailgun signature');
                }
                if (isset($data['event-data'])) {
                    $data = $data['event-data'];
                }
                $mailStatus = 0;
                $mailAddress = null;
                $mailEvent = $data['event'];
                $mailError = null;
                switch ($mailEvent) {
                    case 'failed':
                        if (Arr::has($data, 'recipient')) {
                            $mailAddress = $data['recipient'];
                        }
                        if ($mailAddress == null) {
                            $mailAddress = Arr::get($data, 'message.headers.to');
                        }
                        if ($mailAddress) {
                            $mailStatus = 1;

                            $dMessage = Arr::get($data, 'delivery-status.message');
                            $dCode = Arr::get($data, 'delivery-status.code');

                            if ($dMessage !== null) {
                                $mailError = $mailAddress . ' (' . $dCode . ') ' . $dMessage;
                            } else {
                                $dMessage = Arr::get($data, 'delivery-status.description');
                                $mailError = $mailAddress . ' (' . $dCode . ') ' . $dMessage;
                            }
                        }
                        break;

                    default:
                        break;
                }

                $brand = "Unknown Brand";
                $cCode = "Unknown Conf#";

                // We have the email address, so search and join to events
                if($mailAddress) {
                    // Find the email address in email_addresses
                    // If not found, then no brand no confirmation can be provided

                    $result = DB::table('events')
                        ->join('brands', 'events.brand_id', 'brands.id')
                        ->join('email_address_lookup', 'events.id', 'email_address_lookup.type_id')
                        ->join('email_addresses', 'email_address_lookup.email_address_id', 'email_addresses.id')
                        ->where('email_addresses.email_address', $mailAddress)
                        ->where('email_address_lookup.email_address_type_id', 3)
                        ->select('brands.name as brand_name', 'events.confirmation_code');

                    $result = $result->first();

                    if ($result) {
                        // Accessing brand name and confirmation code from the result
                        $brand = $result->brand_name;
                        $cCode = $result->confirmation_code;
                    }
                }
                
                $pass = false;
                if ($mailAddress && $mailStatus > 0) {
                    $allowlistedDomainsRaw = runtime_setting('allowlist', 'email');

                    if (!empty($allowlistedDomainsRaw) && is_string($allowlistedDomainsRaw)) {
                        $allowedDomains = explode(',', $allowlistedDomainsRaw);
                        $allowedDomains = array_map(function ($item) {
                            return mb_strtolower(trim($item));
                        }, $allowedDomains);
                        $emailParts = explode('@', $mailAddress);
                        $mailDomain = mb_strtolower(trim($emailParts[1]));
                        if (in_array($mailDomain, $allowedDomains)) {
                            $pass = true;
                            $msg = '[' . $brand . '][' . $cCode . ']There was a failure to send to "' . $mailAddress . '": ```' . $mailError . '``` Address is on an allowed domain, will *not* be marked undeliverable.';
                            if($request->input('brand')) {

                                $msg .= "Brand: " . $request->input('brand');
                            }
                            SendTeamMessage('monitoring', $msg);
                        }
                    }
                    if (!$pass) {
                        $addrs = EmailAddress::where('email_address', $mailAddress)->withTrashed()->get();
                        if ($addrs && $addrs->count() > 0) {
                            foreach ($addrs as $addr) {
                                $addr->undeliverable = $mailStatus;
                                $addr->save();
                            }
                        }
                    }
                }
                if (!$pass && $mailError) {
                    if (config('app.env') === 'production') {
                        SendTeamMessage('monitoring', '[Mail Error][' . $brand . '][' . $cCode . '] ' . $mailError);
                    }
                    info('[MAIL-ERROR] ' . $mailError);
                }
                return response($valid ? '' : 'Invalid Signature', $valid ? 200 : 400);
                break;

            case 'recording:status':
                info('RecordingStatus', request()->input());
                $interaction_id = request()->input('interaction');
                $interaction = Interaction::find($interaction_id);
                $brand = request()->input('brand');
                $recordingUrl = request()->input('RecordingUrl');
                $recordingDuration = request()->input('RecordingDuration');
                if ($recordingDuration === null) {
                    $recordingDuration = 0;
                }
                $status = request()->input('RecordingStatus');
                if ($status == null) {
                    $status = 'completed';
                }
                $callSid = request()->input('CallSid');
                if (!isset($interaction_id) && isset($callSid)) {
                    $interaction = Interaction::where('session_call_id', $callSid)->first();
                    $interaction_id = $interaction->id;
                }
                if (!isset($brand) && isset($interaction_id)) {
                    if (!isset($interaction)) {
                        $interaction = Interaction::find($interaction_id);
                    }
                    if ($interaction) {
                        $event = Event::find($interaction->event_id);
                        if ($event) {
                            $brand = $event->brand_id;
                        }
                    }
                }
                if (isset($interaction) && isset($brand)) {
                    if ($interaction->event_result_id == 2 && $interaction->interaction_type_id == 20) {
                        $dispo = DB::table('dispositions')->where('reason', 'Pending')->where('brand_id', $brand)->first();
                        if ($interaction->disposition_id == $dispo->id) {
                            $adispo = DB::table('dispositions')->where('reason', 'Abandoned')->where('brand_id', $brand)->first();
                            if ($adispo) {
                                $interaction->disposition_id = $adispo->id;
                                $interaction->save();
                            }
                        }
                    }
                }
                if (isset($interaction_id) && isset($brand) && $recordingUrl !== null && $status === 'completed') {
                    Artisan::queue('fetch:recording:single', [
                        '--interaction' => $interaction_id,
                        '--url' => $recordingUrl,
                        '--brand' => $brand,
                        '--callid' => $callSid,
                        '--duration' => $recordingDuration,
                    ]);
                } else {
                    info('Missing info to associate recording', [request()->input()]);
                }

                return response('', 200);
                break;

            case 'hydrate:vars':
                $eventId = $request->input('event');
                $selectedProduct = $request->input('selected');
                $text = $request->input('text');
                $lang = $request->input('lang_id');
                $eventData = SupportController::gatherEventDetails($eventId);

                if (!is_array($selectedProduct)) {
                    if (isset($eventData['products']) && null !== $selectedProduct) {
                        foreach ($eventData['products'] as $p) {
                            if ($p['id'] === $selectedProduct) {
                                $eventData['selectedProduct'] = $p;
                                break;
                            }
                        }
                    }
                } else {
                    if (2 != count($selectedProduct)) {
                        return response()->json([
                            'error' => 'Invalid number of selected products',
                            'text' => $text,
                        ]);
                    }
                    $dualProduct = ['dualFuel' => true, 'electric' => null, 'gas' => null];
                    if (isset($eventData['products'])) {
                        foreach ($eventData['products'] as $p) {
                            foreach ($selectedProduct as $sp) {
                                if ($p['id'] === $sp) {
                                    if (1 == $p['event_type_id']) {
                                        $dualProduct['electric'] = $p;
                                        continue 2;
                                    }
                                    if (2 == $p['event_type_id']) {
                                        $dualProduct['gas'] = $p;
                                        continue 2;
                                    }
                                }
                            }
                        }
                        $eventData['selectedProduct'] = $dualProduct;
                    }
                }
                $varMap = SupportController::getVariableMap();

                if ($text != null) {
                    if (!is_array($text)) {
                        $ret = SupportController::hydrateVariables($text, $eventData, $lang, $varMap);
                    } else {
                        $ret = [];
                        foreach ($text as $inText) {
                            $ret[] = SupportController::hydrateVariables($inText, $eventData, $lang, $varMap);
                        }
                    }

                    return response()->json([
                        'error' => false,
                        'text' => $ret,
                    ]);
                }
                return response()->json([
                    'error' => 'Parameter `text` is required',
                ]);

            case 'alert:report':
                $event = $request->input('event');
                $alert = $request->input('alert');
                $data = $request->input('data');
                $cac = new ClientAlertController();
                $cac->altChecksEntry($event, $alert, $data);

                return response()->json(['message' => 'report submitted']);
                break;

            case 'soap:api':
                $wsdl = $request->input('wsdl');
                $method = $request->input('apiMethod');
                $data = $request->input('data');

                info(print_r($data, true));

                $prefix = $request->input('prefix');
                $refId = $request->input('refId');

                $cookies = [];
                if ($request->input('cookies')) {
                    $cookies = $request->input('cookies');
                }

                $soapV = 1;

                if ($request->has('soap_version')) {
                    if ($request->input('soap_version') == '1.2') {
                        $soapV = 2;
                    }
                }
                if ($prefix !== null) {
                    $response = SoapCall($wsdl, $method, $data, 'production' != config('app.env'), $soapV, [], ['prefix' => $prefix, 'ref' => $refId], $cookies);
                } else {
                    $response = SoapCall($wsdl, $method, $data, 'production' != config('app.env'), $soapV, [], [], $cookies);
                }
                info('Soap Response:', [json_encode($response, \JSON_PARTIAL_OUTPUT_ON_ERROR)]);
                unset($response['__client']);

                return response()->json($response);
                break;

            case 'soap:api:curl':
                $url = $request->input('url');
                $requestXml = $request->input('request');

                // Get request headers
                $requestHeaders = [];
                if($request->input('headers')) {
                    $requestHeaders = $request->input('headers');
                }

                info(print_r($requestXml, true));

                $prefix = $request->input('prefix');
                $refId = $request->input('refId');

                // Build headers
                $headers = array();
                $headers[] = "Content-Type: text/xml";

                if(count($requestHeaders) > 0) {
                    foreach($requestHeaders as $header) {
                        $headers[] = $header['name'] . ": " . $header['value'];
                    }
                }

                if ($prefix !== null) {
                    $response = curlHttpPost($url, $requestXml, $headers, ['prefix' => $prefix, 'ref' => $refId]);
                } else {
                    $response = curlHttpPost($url, $requestXml, $headers);
                }

                info('Soap Response:', [json_encode($response, \JSON_PARTIAL_OUTPUT_ON_ERROR)]);
                unset($response['__client']);

                return response()->json($response);
                break;

            case 'send:mail':
                $etype = $request->input('etype');
                $data = $request->input('data');
                if (!isset($data['content'])) {
                    $data['content'] = null;
                    $content = 'Email Content Not Set';
                } else {
                    $content = $data['content'];
                }
                $to = $request->input('to');
                $subject = $request->input('subject');
                switch ($etype) {
                    case 'config':
                        Mail::to($to)->send(new ConfigChange($data, $subject));
                        break;
                    case 'generic':
                        Mail::to($to)->send(new GenericEmail($subject, $content));
                        break;
                    default:
                        abort(401);
                }

                return response('', 200);

            case 'schedule:survey':
                $eventId = $request->input('event_id');
                $event = Event::find($eventId);
                if (!$event) {
                    return response()->json(
                        [
                            'error' => 'Invalid Event ID ' . $eventId
                        ]
                    );
                }

                // Create survey record
                $survey = new Survey();

                // In Dev/Staging, schedule the survey for today.
                if (config('app.env') !== 'production') {
                    $survey->created_at = Carbon::now('America/Chicago');
                } else {
                    $survey->created_at = Carbon::tomorrow('America/Chicago');
                }

                $survey->event_id = $event->id;
                $survey->brand_id = $event->brand_id;
                $survey->language_id = $event->language_id;
                $survey->script_id = $request->input('survey_script_id');
                $survey->custom_data = $request->input('custom_data');
                $survey->save();

                // Create link_tracking interaction
                $interaction = new Interaction();
                $interaction->created_at = Carbon::now('America/Chicago');
                $interaction->event_id = $event->id;
                $interaction->interaction_time = null;
                $interaction->interaction_type_id = 22;
                $interaction->notes = [
                    'source' => 'Post TPV Survey Scheduled',
                    'survey_script_id' => $request->input('survey_script_id'),
                    'survey_id' => $survey->id
                ];
                $interaction->save();

                return response()->json(
                    [
                        'error' => null,
                        'message' => 'Survey scheduled successfully.',
                    ]
                );

            case 'rate:import':
                $uploadId = $request->input('upload');
                $upload = Upload::find($uploadId);
                $upload->processing = 1;
                $upload->save();
                $contents = Storage::disk('s3')->get($upload->filename);
                $fname = tempnam('/tmp', 'importpreview');
                file_put_contents($fname, $contents);
                $retg = Artisan::call('rate:import', ['--file' => $fname, '--brand' => $upload->brand_id]);
                $upload->processing = 0;
                $upload->processed_at = Carbon::now();
                $upload->save();
                try {
                    unlink($fname);
                } catch (Exception $e) {
                    info('Got an error while deleting the uploaded file: ' . $e->getMessage());
                }

                return response($retg, 200);

            case 'survey:import':
                $uploadId = $request->input('upload');
                $scriptId = $request->input('script');
                $brandId = $request->input('brand');
                $stateAbbrev = $request->input('state');
                if (!$uploadId || !$scriptId || !$brandId) {
                    Log::error('Error due to missing upload, script, or brand ID.');
                    abort(400);
                }

                $upload = Upload::find($uploadId);
                $script = Script::find($scriptId);
                $brand = Brand::find($brandId);
                $state = State::where(
                    'state_abbrev',
                    strtoupper($stateAbbrev)
                )->first();
                if (!$upload || !$script || !$brand || !$state) {
                    Log::error('Error due to missing upload, script, brand, or state.');
                    abort(410);
                }

                event(new \App\Events\SurveyReadyToImport(
                    $upload,
                    $scriptId,
                    $brand,
                    $stateAbbrev
                ));

                Log::debug('generic_hook is complete.');

                return response('', 200);
                break;

            case 'customerlist:import':
                $uploadId = $request->input('upload');
                $brandId = $request->input('brand');
                $listType = $request->input('type');
                $action = $request->input('action');
                if ('replace' !== $action) {
                    $action = 'update';
                }
                Artisan::queue('customerlist:import', [
                    '--type' => $listType,
                    '--brand' => $brandId,
                    '--upload' => $uploadId,
                    '--' . $action => true,
                ]);

                return response('', 200);
                break;

            case 'eztpv:previewContract':
                $eztpvId = $request->input('eztpv_id');
                if ($eztpvId) {
                    $testing = Eztpv::select(
                        'testing'
                    )->find($eztpvId);
                    $local = ($testing->testing === 0 && 'local' == config('app.env'))
                        ? true
                        : false;

                    Artisan::queue(
                        'eztpv:generateContracts',
                        [
                            '--preview' => true,
                            '--eztpv_id' => $eztpvId,
                            '--override-local' => $local,
                            '--unfinished' => true,
                        ]
                    );

                    for ($i = 0; $i < 120; ++$i) {
                        usleep(250000);

                        $contracts = JsonDocument::where(
                            'document_type',
                            'eztpv-preview'
                        )->where(
                            'ref_id',
                            $eztpvId
                        )->orderBy(
                            'created_at',
                            'desc'
                        )->first();
                        if ($contracts) {
                            return response(
                                $contracts->document,
                                200
                            );
                        }
                    }
                }

                return response(null, 200);

            case 'eztpv:previewContractProductless':
                $eztpvId = $request->input('eztpv_id');
                if ($eztpvId) {
                    $testing = Eztpv::select(
                        'testing'
                    )->find($eztpvId);
                    $local = ($testing->testing === 0 && 'local' == config('app.env'))
                        ? true
                        : false;

                    Artisan::queue(
                        'eztpv:generateContractsProductless',
                        [
                            '--preview' => true,
                            '--eztpv_id' => $eztpvId,
                            '--override-local' => $local,
                            '--unfinished' => true,
                        ]
                    );

                    for ($i = 0; $i < 120; ++$i) {
                        usleep(250000);

                        $contracts = JsonDocument::where(
                            'document_type',
                            'eztpv-preview'
                        )->where(
                            'ref_id',
                            $eztpvId
                        )->orderBy(
                            'created_at',
                            'desc'
                        )->first();
                        if ($contracts) {
                            return response(
                                $contracts->document,
                                200
                            );
                        }
                    }
                }

                return response(null, 200);

            case 'conference:start':
                $this->initTwilio();
                $to = request()->input('to');
                $from = request()->input('from');
                $confId = request()->input('conference');
                
                info("CONFERENCE: starting conference $to  $from $confId");
                try {
                    $call = $this->client->calls->create($to, $from, [
                        'url' => config('app.urls.mgmt') . '/api/hook?command=conference:twiml&conference=' . $confId,
                    ]);

                    return response()->json(['error' => false, 'conference' => $confId, 'call' => $call->sid]);
                } catch (\Exception $e) {
                    info('CONFERENCE: Error starting conference', [$e]);

                    return response()->json(['error' => $e->getMessage()]);
                }
                break;

            case 'conference:twiml':
                $confId = request()->input('conference');
                $shouldRecord = request()->input('record');
                $response = new Twiml();
                //$response->say('Warm Transfer Enabled', ['voice' => 'woman']);
                $dial = $response->dial('');
                if ('no' !== $shouldRecord) {
                    $dial->conference($confId, [
                        'startConferenceOnEnter' => true,
                        'endConferenceOnExit' => false,
                        'record' => 'record-from-start',
                    ]);
                } else {
                    $dial->conference($confId, [
                        'startConferenceOnEnter' => true,
                        'endConferenceOnExit' => true,
                        'record' => false,
                    ]);
                }

                return response($response, 200, ['Content-Type' => 'application/xml']);
                break;


            case 'conference:add':
                $this->initTwilio();
                $to = request()->input('to');
                $from = request()->input('from');
                $confId = request()->input('conference');
                info("CONFERENCE: Adding starting conference $to $from $confId");
                try {
                    
                    $participant = $this->client->conferences($confId)->participants->create($from, $to, [
                        'endConferenceOnExit' => false,
                        'earlyMedia' => true,
                    ]);

                    return response()->json(['error' => false, 'participant' => $participant->callSid]);
                } catch (\Exception $e) {
                    info('CONFERENCE: Error adding conference participant', [$e]);

                    return response()->json(['error' => $e->getMessage()]);
                }
                break;

            case 'conference:stop-record':
                $this->initTwilio();
                $confId = request()->input('conference');
                $interactionId = request()->input('interaction');
                $agent_call_id = request()->input('agent_call_id');

                $conference_id = $this->getConferenceSidByFriendlyName($confId);
                info("CONFERENCE:STOP-RECORD Friendly: ".$confId." Conference ID: ".$conference_id );
                
                if (strlen($conference_id) > 10){
                    $this->pauseConferenceRecording($conference_id);
                    $this->endOnExitConferenceParticipants($conference_id, $agent_call_id);
                } else {
                    info("CONFERENCE:STOP-RECORD No Conference ID Found" );
                }
                return response('', 200);
                
                break;
            case 'check:callstatus':
                $this->initTwilio();
                $callSid = request()->input('sid');

                try {
                    $call = $this->client->calls->read(['parentCallSid' => $callSid]);
                    info('callStatusCheck', [$call]);
                    if ($call !== null && count($call) > 0) {
                        return response()->json(['status' => $call[0]->status, 'error' => null]);
                    }
                    return response()->json(['status' => 'not-found', 'error' => null, 'calls' => $call]);
                } catch (\Exception $e) {
                    return response()->json(['error' => $e->getMessage()]);
                }

            case 'update:interaction':
                // TODO: make this update the interaction time after determining if the passed
                // in duration is the entire call or only the not recorded part
                // This will be called for twice (one for each participant left in the conference after the agent drops out)
                // params from https://www.twilio.com/docs/voice/api/call-resource#statuscallback
                $interactionId = request()->input('interaction');
                $callDuration = request()->input('CallDuration');
                info('Recieved after call update', request()->input());

                return response('', 200);

            case 'conference:update':
                $this->initTwilio();
                $confId = request()->input('conference');
                $callSid = request()->input('participant');

                try {
                    $this->client->conferences($this->getConferenceSidByFriendlyName($confId))->participants($callSid)->update(['endConferenceOnExit' => true]);

                    return response()->json(['error' => false]);
                } catch (\Exception $e) {
                    info('CONFERENCE:conference-update Error updating conference participant', [$e]);

                    return response()->json(['error' => $e->getMessage()]);
                }
                break;

            case 'transcription:update':
                $tSid = request()->input('TranscriptionSid');
                $tText = request()->input('TranscriptionText');
                $tStatus = request()->input('TranscriptionStatus');
                $tRecUrl = request()->input('RecordingUrl');

                $answer = ScriptAnswer::where('answer', $tRecUrl)->first();
                if ($answer) {
                    $dta = $answer->additional_data;
                    if (!is_array($dta)) {
                        $dta = json_decode($dta, true);
                        if (!is_array($dta)) {
                            $dta = [];
                        }
                    }
                    $dta['transcript_status'] = $tStatus;
                    $dta['transcript_text'] = $tText;
                    $dta['transcript_sid'] = $tSid;
                    $answer->additional_data = $dta;
                    $answer->save();
                }

                return response('', 200);
                break;
            case 'sqs:queue:contract':
                info("##### WebhookController::generic_hook - sqs:queue:contract - Queuing confirmation code in SQS");
                $eventData = json_decode(request()->input('eventData'));

                info("##### WebhookController::generic_hook - sqs:queue:contract - EventData:", [$eventData]);

                $result = API\ContractGeneratorApiController::queueContract($eventData);

                if($result->result != "success") {
                    info("##### WebhookController::generic_hook - sqs:queue:contract - Error:", [$result->message]);
                    abort(400, 'Error: ' . $result->message);
                }
                break;
            case 'run:vms':
                info("##### WebhookController::generic_hook - run:vms - Running VMS to update Genie Vendors, Offices, etc...");

                Artisan::queue(
                    'genie:sync',
                    [
                        '--dryrun' => false
                    ]
                );

                return response()->json(['result' => 'success', 'message' => 'VMS sync has been initiated. Update may take a few minutes.', 'data' => null]);
            default:
                abort(400, 'Bad or Invalid Command');
        }
    }
    private function initTwilio(){
        $this->client = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
    }

    private function getConferenceSidByFriendlyName($friendlyName) {
        try {
            // Retrieve conferences filtered by Friendly Name
            $conferences = $this->client->conferences->read([
                'friendlyName' => $friendlyName, // Filter by the Friendly Name
                'status' => 'in-progress' // Optionally filter by status (e.g., in-progress, completed)
            ]);

            // Check if any conferences match the Friendly Name
            if (!empty($conferences)) {
                // Assuming the Friendly Name is unique, return the first matching Conference SID
                $conferenceSid = $conferences[0]->sid;
                echo "Conference SID for Friendly Name '$friendlyName': " . $conferenceSid . "\n";
                return $conferenceSid;
            } else {
                echo "No conference found with Friendly Name: $friendlyName.\n";
                return null;
            }

        } catch (Exception $e) {
            echo "Error finding conference: " . $e->getMessage();
            return null;
        }
    }

    private function pauseConferenceRecording($conference_id){
        try {
            $recordings = $this->client->conferences($conference_id)->recordings->read();
            foreach($recordings as $rec){
                $this->client->conferences($conference_id)->recordings($rec->sid)->update('paused');
            }
        } catch (\Exception $e) {
            info('CONFERENCE:pauseConferenceRecording EXCEPTION Error converting Recording to stop recording', [$e]);
        }
    }

    private function endOnExitConferenceParticipants($conference_id, $agent_call_id="")  {
        $participants = $this->client->conferences($conference_id)->participants->read();

        info("CONFERENCE:onEndExitConferenceParticipants --> ".count($participants) );
        if (count($participants) > 1){
            foreach($participants as $part){
               info("CONFERENCE:onEndExitConferenceParticipants Checking {$part->callSid} vs {$agent_call_id}  ");
               if (strcmp($part->callSid,$agent_call_id) == 0 && count($participants) == 2)
               {
                    info("CONFERENCE:onEndExitConferenceParticipants Canceling Conference $conference_id because 1 of the member was the Agent  ");
                    $this->completeConference($conference_id); 
               }

               try { 
                    info("CONFERENCE:onEndExitConferenceParticipants Updating {$part->callSid}   ");
                    $this->client->conferences($conference_id)->participants($part->callSid)->update(['endConferenceOnExit' => 'true']);
               } catch( \Exception $e ) {
                 info("CONFERENCE:onEndExitConferenceParticipants EXCEPTION Error converting Participants {$part->callSid} -- {$agent_call_id}", [$e]);
                 if (count($participants) == 2){
                    $this->completeConference($conference_id); 
                 }
               }
            }                        
        } else {
            info("CONFERENCE:onEndExitConferenceParticipants Canceling Conference $conference_id  ");
            $this->completeConference($conference_id);
        }
    }

    private function completeConference($conference_id){
        try {
            $this->client->conferences($conference_id)->update([ 'status'=> 'completed']);
        } catch( \Exception $e ) {
            info("CONFERENCE:End Conference EXCEPTION Error Completing Conference", [$e]);
        }
    }


    public function eventCallback(Request $request)
    {
        if ($request->EventType) {
            if ('production' !== config('app.env')) {
                info($request->EventType);
                info(print_r($request->all(), true));
            }

            EventCallback::disableAuditing();

            $ec = new EventCallback();
            $ec->event_type = $request->EventType;
            $ec->task_created_date = ($request->Timestamp)
                ? $request->Timestamp
                : $request->TaskDateCreated;
            $ec->task_queue_name = $request->TaskQueueName;
            $ec->task_age = $request->TaskAge;
            $ec->sid = $request->Sid;
            $ec->task_assignment_status = $request->TaskAssignmentStatus;
            $ec->task_queue_sid = $request->TaskQueueSid;
            $ec->task_attributes = $request->TaskAttributes;
            $ec->worker_name = $request->WorkerName;
            $ec->worker_activity_name = $request->WorkerActivityName;
            $ec->worker_previous_activity_name = $request->WorkerPreviousActivityName;
            $ec->task_sid = $request->TaskSid;
            $ec->worker_time_in_previous_activity_ms = $request->WorkerTimeInPreviousActivityMs;
            $ec->reservation_sid = $request->ReservationSid;
            $ec->save();
        }
    }

    public function recordingComplete()
    {
        $input = request()->input();
        event(new \App\Events\Twilio\RecordingCompleted($input));
    }

    public function shieldscreening()
    {
        $input = file_get_contents('php://input');
        $body = str_replace('Status=', '', urldecode($input));
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $json = json_encode($xml);
        $array = json_decode($json, true);

        $whl = new WebhookLog();
        $whl->webhook_type = 1;
        $whl->body = $json;
        $whl->save();

        // info('Shield Screen Webhook');
        // info($array);

        $order_decision = null;
        $report = $array['BackgroundReportPackage'];
        if (
            isset($report)
            && isset($report['ScreeningStatus'])
            && isset($report['ScreeningStatus']['OrderDecision'])
        ) {
            $order_decision = $report['ScreeningStatus']['OrderDecision'];
        }

        if (
            isset($report) &&
            isset($report['Screenings']) &&
            isset($report['Screenings']['Screening']['ScreeningResults']['InternetWebAddress'])
        ) {
            if (
                isset($report['OrderId'])
                && strlen(trim($report['OrderId'])) > 0
            ) {
                $uha = UserHireflowActivity::where(
                    'screen_id',
                    $report['OrderId']
                )->first();
                if ($uha) {
                    $uha->screen_url = $report['Screenings']['Screening']['ScreeningResults']['InternetWebAddress'];
                    $uha->status = 1;
                    $uha->order_decision = $order_decision;
                    $uha->save();
                }
            }
        }
    }
}
