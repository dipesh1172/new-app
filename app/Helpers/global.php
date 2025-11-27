<?php

use Twilio\Rest\Client;
use Postmark\PostmarkClient;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use GuzzleHttp\Client as HttpClient;
use App\Models\TextMessage;
use App\Models\ShortUri;
use App\Models\PhoneNumberLookup;
use App\Models\PhoneNumber;
use App\Models\JsonDocument;
use App\Models\GpsCoord;
use App\Models\EmailAddressLookup;
use App\Models\EmailAddress;
use App\Models\Dnis;
use App\Models\Address;

/**
 * Creates a short uri
 *
 * @param string $destination - where the short url will redirect to
 * @param int $type - the type id of the message: 1 - temporary, 2 - long lived, 3 - permanent
 *
 * @return string the shortened uri
 */
function CreateShortURI(string $destination, int $type = 1): string
{
    $existingDestination = ShortUri::where('destination_uri', $destination)->where('type_id', $type)->first();
    if ($existingDestination) {
        return config('app.urls.clients') . '/l/' . $existingDestination->key;
    }

    $potentialKey = hash('joaat', $destination);
    $existing = ShortUri::where('key', $potentialKey)->first();
    if ($existing) {
        while ($existing != null) {
            $potentialKey = hash('joaat', $destination . bin2hex(openssl_random_pseudo_bytes(10)));
            $existing = ShortUri::where('key', $potentialKey)->first();
        }
    }
    $short = new ShortUri();
    $short->key = $potentialKey;
    $short->destination_uri = $destination;
    $short->type_id = $type;
    $short->save();

    return config('app.urls.clients') . '/l/' . $potentialKey;
}

function runtime_setting($key, $namespace = null)
{
    $setting = Cache::remember(
        'runtime_setting_' . (empty($namespace) ? '*::' : $namespace . '::') . $key,
        7200,
        function () use ($key, $namespace) {
            $q = DB::table('runtime_settings')
                ->select('value')
                ->where(
                    'name',
                    $key
                );
            if (!empty($namespace)) {
                $q = $q->where('namespace', $namespace);
            }
            return optional(
                $q->first()
            )->value;
        }
    );

    return $setting;
}

/**
 * @method CalculateDistanceFromGPS
 */
function CalculateDistanceFromGPS(float $lat1, float $lon1, float  $lat2, float $lon2): float
{
    if (($lat1 == $lat2) && ($lon1 == $lon2)) {
        return floatval(0);
    }

    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    //$dist = acos($dist);
    //Avoiding problems with NaN when $dist is a little bigger than 1
    $dist = acos(min(max($dist, -1.0), 1.0));
    $dist = rad2deg($dist);
    $feets = $dist * 60 * 1.1515 * 5280;
    return round($feets);
}

function CalculateDistanceFromCoords(GpsCoord $point_a, GpsCoord $point_b): float
{
    $a_loc = explode(',', $point_a->coords);
    if (count($a_loc) == 2) {
        $lat1 = floatval($a_loc[0]);
        $lon1 = floatval($a_loc[1]);

        $b_loc = explode(',', $point_b->coords);
        if (count($b_loc) == 2) {
            $lat2 = floatval($b_loc[0]);
            $lon2 = floatval($b_loc[1]);

            return CalculateDistanceFromGPS($lat1, $lon1, $lat2, $lon2);
        }
    }
    info('Invalid coordinates', ['point_a' => $point_a, 'point_b' => $point_b]);
    return floatval(0);
}

/**
 * @method CleanPhoneNumber
 * Ensures a phone number is properly formatted in e.164 format
 *
 * @param string $phone - the raw phone number
 *
 * @return string|null
 */
function CleanPhoneNumber($phone)
{
    if (!is_string($phone)) {
        $phone = '' . $phone;
    }
    if ($phone == null || $phone == '') {
        return null;
    }
    $cleanPhone = preg_replace('/[^\d]/', '', $phone);
    if (strlen($cleanPhone) == 10) {
        return '+1' . $cleanPhone;
    }
    if (strlen($cleanPhone) == 11) {
        return '+' . $cleanPhone;
    }
    if (strlen($cleanPhone) == 12) {
        return $cleanPhone;
    }

    return null;
}

/**
 * @method FormatPhoneNumber
 * Formats a E.164 phone number for human friendly display i.e. takes +12345678900 to (234) 567-8900
 *
 * @param string $phone - the phone number to format
 *
 * @return string|null
 */
function FormatPhoneNumber(string $phone)
{
    if ($phone === null || strlen($phone) === 0) {
        return null;
    }
    return preg_replace('/^\\+?[1]?\\(?([0-9]{3})\\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/', '($1) $2-$3', $phone);
}

/**
 * @method SendErrorEmail
 * Sends an email about an error
 *
 * @param string $subject - Email subject
 * @param string $message - Error Message
 */
function SendErrorEmail(string $subject, string $message)
{
    $errorEmail = runtime_setting('error_email');
    if ($errorEmail === null) {
        $errorEmail = 'engineering@tpv.com';
    }
    if (!filter_var($errorEmail, \FILTER_VALIDATE_EMAIL)) {
        $msg = 'Invalid error_email setting: ' . $errorEmail . "\n" . $message;
        $errorEmail = 'engineering@tpv.com';
    } else {
        $msg = $message;
    }

    SimpleSendEmail($errorEmail, 'no-reply@tpvhub.com', $subject, $msg);
}

/**
 * @method SimpleSendEmail
 * Sends a generic email
 *
 * @param string $to - email address to send email to
 * @param string $from - email address to send email from
 * @param string $subject - email subject line
 * @param string $message - email message
 * @param string $template (optional) - the template to use for the email, default is 'generic' (leave off the emails. part)
 */
function SimpleSendEmail(string $to, string $from, string $subject, string $message, string $template = 'generic')
{
    if (!filter_var($to, \FILTER_VALIDATE_EMAIL)) {
        throw new \Exception('$to is not a valid email address');
    }
    if (!filter_var($from, \FILTER_VALIDATE_EMAIL)) {
        throw new \Exception('$from is not a valid email address');
    }
    if (empty($subject)) {
        throw new \Exception('$subject cannot be empty');
    }
    if (empty($message)) {
        throw new \Exception('$message cannot be empty');
    }

    $mailData = [];

    switch ($template) {
        default:
            throw new \RuntimeException('Unrecognized email template: ' . $template);

        case 'generic':
            $mailData = ['subject' => $subject, 'content' => $message];
            break;
    }

    try {
        // Mailgun (primary)
        Mail::send(
            'emails.' . $template,
            $mailData,
            function ($msg) use ($to, $from, $subject) {
                $msg->subject($subject);
                $msg->from($from);
                $msg->to($to);
            }
        );
    } catch (\Exception $e) {
        info('SimpleSendEmail Exception: ' . $e);

        // Secondary Email (postmark)
        _SimpleSendEmail_via_Postmark(
            $to,
            $from,
            $subject,
            $mailData,
            $template
        );
    }
}

function _SimpleSendEmail_via_Postmark(string $to, string $from, string $subject, $mailData, $template)
{
    $client = new PostmarkClient(config('services.postmark.secret'));
    $data = view(
        'emails.' . $template
    )->with($mailData)->render();

    $client->sendEmail(
        $from,
        $to,
        $subject,
        $data
    );
}

function _SimpleSendEmail_via_Mailersend(string $to, string $from, string $subject, string $message)
{
    $key = config('services.mailersend.secret');
    if (!empty($key)) {
        try {
            $hclient = new HttpClient();
            $msg = [
                'from' => [
                    'email' => $from
                ],
                'to' => [
                    [
                        'email' => $to,
                    ],
                ],
                'subject' => $subject,
                'text' => strip_tags($message),
                'html' => $message,
            ];
            $hresponse = $hclient->post('https://api.mailersend.com/v1/email', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Authorization' => 'Bearer ' . $key,
                ],
                'body' => $msg,
            ]);

            if ($hresponse->getStatusCode() === 200) {
                return true;
            } else {
                info('Mailersend Error: ' . $hresponse->getStatusCode() . ' ' . $hresponse->getReasonPhrase() . ' (body) ' . ((string) $hresponse->getBody()));
            }
        } catch (\Exception $e) {
            info('Mailersend Error: ' . $e->getCode() . ' ' . $e->getMessage(), [$e]);
        }
    }
    return false;
}

/**
 * @method SendSMS
 *
 * @param string $to
 * @param string $from
 * @param string $message
 * @param string (optional) $sender_id
 * @param string (optional) $brand_id
 * @param int (optional) $message_type_id
 *
 * @return mixed
 */
function SendSMS(string $to, string $from, string $message, $sender_id = null, $brand_id = null, $message_type_id = 1)
{
    return __Twilio_doSendSms(CleanPhoneNumber($to), CleanPhoneNumber($from), $message, null, $sender_id, $brand_id, $message_type_id);
}

/**
 * @method SendMMS
 *
 * @param string $to
 * @param string $from
 * @param string $message
 * @param string $media_url
 * @param string (optional) $sender_id
 * @param string (optional) $brand_id
 * @param int (optional) $message_type_id
 *
 * @return mixed
 */
function SendMMS(string $to, string $from, string $message, string $media_url = null, $sender_id = null, $brand_id = null, $message_type_id = 1)
{
    return __Twilio_doSendSms(CleanPhoneNumber($to), CleanPhoneNumber($from), $message, $media_url, $sender_id, $brand_id, $message_type_id);
}

/**
 * @method SendMultipleSMS
 *
 * @param array  $to
 * @param string $from
 * @param string $message
 *
 * @return mixed
 */
function SendMultipleSMS(array $to, string $from, string $message, $sender_id = null, $brand_id = null, $type_id = 1)
{
    $ret = [];
    foreach ($to as $dest) {
        try {
            $ret[] = __Twilio_doSendSms($dest, $from, $message, null, $sender_id, $brand_id, $type_id);
        } catch (Exception $e) {
            $ret[] = 'ERROR: ' . $e->getMessage();
        }
    }

    return $ret;
}

function __Twilio_doSendSms($to, $from, $message, $mediaUrl, $sender_id, $brand_id, $message_type_id)
{
    static $client = null;
    if ($client == null) {
        $client = new Client(config('services.twilio.account'), config('services.twilio.auth_token'));
    }
    $toPhone = PhoneNumber::where('phone_number', $to)->whereNull('extension')->first();
    if ($toPhone === null) {
        $toPhone = new PhoneNumber();
        $toPhone->phone_number = $to;
        $toPhone->save();
    }
    $overrideFrom = CleanPhoneNumber(runtime_setting('override_sms_number', 'system'));
    if (!empty($overrideFrom)) {
        $from = $overrideFrom;
    }
    $fromDnis = Dnis::where('dnis', $from)->first();

    $tm = new TextMessage();
    $tm->content = $message;
    $tm->media_uri = $mediaUrl;
    $tm->to_phone_id = $toPhone->id;
    $tm->text_message_type_id = $message_type_id;
    if ($fromDnis !== null) {
        $tm->from_dnis_id = $fromDnis->id;
    }

    if ($brand_id == null) {
        /* THIS LINE IS THE ONLY DIFFERENCE FROM MGMT */
        // $tm->brand_id = session('current_brand')->id;
    } else {
        $tm->brand_id = $brand_id;
    }
    if ($sender_id == null) {
        $tm->sender_id = Auth::id();
    } else {
        $tm->sender_id = $sender_id;
    }
    $msg = null;

    try {
        $out = [
            'body' => $message,
            'from' => $from,
            'statusCallback' => config('app.urls.mgmt') . '/api/twilio/sms/status-update',
        ];

        if ($mediaUrl !== null) {
            $out['mediaUrl'] = $mediaUrl;
        }

        if (!empty(config('services.twilio.messaging_sid'))) {
            $out['messagingServiceSid'] = config('services.twilio.messaging_sid');
        }

        info(print_r($out, true));

        $msg = $client->messages->create($to, $out);

        $tm->message_sid = $msg->sid;
        $tm->save();

        return $msg->sid;
    } catch (Exception $e) {
        if ($msg !== null) {
            $tm->message_sid = $msg->sid;
        }
        $tm->status = 'failed';
        $tm->save();

        return 'SMSERROR: ' . $e->getMessage();
    }
}

/**
 * @method SoapCall
 *
 * @param wsdl
 * @param methodName
 * @param inputData
 * @param debug
 * @param soapVersion
 * @param contextOptions
 * @param storageOptions - prefix will be used to build the document_type and is required to store in db, ref is not required but will be the ref_id if not given is auto generated
 * @param cookies - array of cookies to pass to the remote server.
 *
 * @return array contains response and other requested options
 */
function SoapCall(string $wsdl, string $methodName, array $inputData, bool $debug = false, int $soapVersion = 1, array $contextOptions = [], array $storageOptions = [], array $cookies = []): array
{
    $context = array_merge([
        'http' => [
            'ignore_errors' => true,
            'user_agent' => 'TPV.com Focus Platform SOAP/1.0',
        ],
        'ssl' => [
            // 'ciphers' => 'RC4-SHA',
            'verify_peer' => false,
            'verify_peer_name' => false,
            // 'crypto_method' => \STREAM_CRYPTO_METHOD_TLS_CLIENT,
            'allow_self_signed' => true,
        ],
    ], $contextOptions);

    ini_set('default_socket_timeout', 5000);
    libxml_disable_entity_loader(false);

    $client = new \SoapClient(
        trim($wsdl),
        [
            'user_agent' => 'TPV.com Focus Platform SOAP/1.0',
            'soap_version' => $soapVersion == 1 ? \SOAP_1_1 : \SOAP_1_2,
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 180,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create($context),
            'keep_alive' => false,
            // 'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
        ]
    );

    // Set cookies, if any.
    foreach ($cookies as $cookie) {
        info("Setting Cookie: ", [$cookie]);
        $client->__setCookie($cookie['name'], $cookie['value']);
    }

    $ret = [];
    try {
        $ret['response'] = $client->$methodName($inputData);
        $ret['headers'] = $client->__getLastResponseHeaders();
    } catch (\SoapFault $e) {
        $ret['response'] = null;
        $ret['error'] = $e->getMessage();
    } finally {
        if (isset($ret['response']) && $ret['response'] == null) {
            $ret['response'] = $client->__getLastResponse();
        }
        if (!isset($ret['error'])) {
            $ret['error'] = false;
        }
    }
    if ($debug) {
        $ret['__request'] = $client->__getLastRequest();
        $ret['__client'] = $client;
    }

    if (isset($storageOptions['prefix']) && $storageOptions['prefix'] !== null) {
        $prefix = $storageOptions['prefix'];
        if (strlen($prefix) > 32) {
            $prefix = substr($prefix, 0, 32);
        }
        if (isset($storageOptions['ref']) && $storageOptions['ref'] !== null) {
            $ref = $storageOptions['ref'];
        } else {
            $ref = uniqid('auto-');
        }
        if (strlen($ref) > 36) {
            $ref = substr($ref, 0, 36);
        }

        $jdReq = new JsonDocument();
        $jdReq->ref_id = $ref;
        $jdReq->document_type = $prefix . ' REQ';
        $jdReq->document = [
            'url' => $wsdl,
            'headers' => $client->__getLastRequestHeaders(),
            'request' => $client->__getLastRequest()
        ];
        $jdReq->save();

        $jdRes = new JsonDocument();
        $jdRes->ref_id = $ref;
        $jdRes->document_type = $prefix . ' RES';
        $jdRes->document = [
            'url' => $wsdl,
            'headers' => $client->__getLastResponseHeaders(),
            'response' => $client->__getLastResponse()
        ];
        $jdRes->save();

        $ret['ref_id'] = $ref;
    }

    return $ret;
}

/**
 * curlHttpPost
 *
 * Makes an HTTP Post call using Curl. 
 * 
 * @param url            - The API endpoint to post to.
 * @param request        - The request XML string.
 * @param headers        - An array of request headers, if any.
 * @param storageOptions - JSONDocument storage options. Array with named indexes expected.
 *                         'prefix' index will be used to build the document_type. It is required; omitting it will
 *                         will cause the request/response to not be logged in the DB.
 *                         'ref' index is not required. It will be the JSONDocument ref_id. If omitted, the ref_id will be auto-generated.
 *
 * @return array contains response and other requested options
 */
function curlHttpPost(string $url, string $request, array $headers = [], array $storageOptions = []): array
{    
    ini_set('default_socket_timeout', 5000);

    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);  
    curl_setopt($curl, CURLOPT_HEADER, true); // return response headers

    // Set headers
    if(count($headers) > 0) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }

    // Add request data
    curl_setopt($curl, CURLOPT_POSTFIELDS, $request);

    $ret = [];
    try {
        // Make the API request
        $response = curl_exec($curl); // This will return both the response headers and body

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        $ret['response'] = substr($response, $headerSize);
        $ret['headers'] = substr($response, 0, $headerSize);

        curl_close($curl);    

    } catch (\Exception $e) {

        $ret['response'] = null;
        $ret['headers'] = null;
        $ret['error'] = $e->getMessage();
    } finally {
        if(!isset($ret['error'])) {
            $ret['error'] = false;
        }
    }

    if (isset($storageOptions['prefix']) && $storageOptions['prefix'] !== null) {
        $prefix = $storageOptions['prefix'];
        if (strlen($prefix) > 32) {
            $prefix = substr($prefix, 0, 32);
        }
        if (isset($storageOptions['ref']) && $storageOptions['ref'] !== null) {
            $ref = $storageOptions['ref'];
        } else {
            $ref = uniqid('auto-');
        }
        if (strlen($ref) > 36) {
            $ref = substr($ref, 0, 36);
        }

        $jdReq = new JsonDocument();
        $jdReq->ref_id = $ref;
        $jdReq->document_type = $prefix . ' REQ';
        $jdReq->document = [
            'url' => $url,
            'headers' => $headers,
            'request' => $request
        ];
        $jdReq->save();

        $jdRes = new JsonDocument();
        $jdRes->ref_id = $ref;
        $jdRes->document_type = $prefix . ' RES';
        $jdRes->document = [
            'url' => $url,
            'headers' => $ret['headers'],
            'response' => $ret['response']
        ];
        $jdRes->save();

        $ret['ref_id'] = $ref;
    }

    return $ret;
}

/**
 * Generate Phone Number Records.
 *
 * @param string $phone_number - phone number
 * @param object $event        - event object
 */
function generatePhoneNumberRecords($phone_number, $id_type, $type_id)
{
    $phone_number = CleanPhoneNumber($phone_number);
    $ret = false;

    $p = PhoneNumber::where('phone_number', $phone_number)->withTrashed()->first();
    if (!$p) {
        $p = new PhoneNumber();
        $p->phone_number = $phone_number;
        $p->save();
        $ret = true;
    } else {
        if ($p->trashed()) {
            $p->restore();
        }
    }

    $count = PhoneNumberLookup::where(
        'phone_number_type_id',
        $id_type
    )->where(
        'phone_number_id',
        $p->id
    )->count();

    $pl = PhoneNumberLookup::where(
        'phone_number_type_id',
        $id_type
    )->where(
        'type_id',
        $type_id
    )->where(
        'phone_number_id',
        $p->id
    )->withTrashed()->first();
    if (!$pl) {
        $pl = new PhoneNumberLookup();
        $pl->phone_number_type_id = $id_type;
        $pl->type_id = $type_id;
        $pl->phone_number_id = $p->id;
        $pl->save();
    } else {
        if ($pl->trashed()) {
            $pl->restore();
        }
    }

    return [
        'new_record' => $ret,
        'new_lookup' => $count == 0,
        'lookup_count' => $count,
        'lookup' => $pl->id,
    ];
}

/**
 * Add Address.
 *
 * @param array $address - address array
 *
 * @return string
 */
function addAddress($address)
{
    $exists = Address::select(
        'id'
    )->where(
        'line_1',
        mb_strtoupper($address['line_1'])
    );

    if (isset($address['line_2'])) {
        $exists = $exists->where(
            'line_2',
            mb_strtoupper($address['line_2'])
        );
    } else {
        $exists = $exists->whereNull('line_2');
    }

    if (isset($address['line_3'])) {
        $exists = $exists->where(
            'line_3',
            mb_strtoupper($address['line_3'])
        );
    } else {
        $exists = $exists->whereNull('line_3');
    }

    if (!isset($address['country_id'])) {
        $address['country_id'] = 1;
    }

    $exists = $exists->where(
        'city',
        mb_strtoupper($address['city'])
    )->where(
        'state_province',
        mb_strtoupper($address['state_province'])
    )->where(
        'zip',
        mb_strtoupper($address['zip'])
    )->where(
        'country_id',
        $address['country_id']
    )->first();
    if ($exists) {
        return $exists->id;
    } else {
        $add = new Address();
        $add->line_1 = mb_strtoupper($address['line_1']);

        if (isset($address['line_2'])) {
            $add->line_2 = mb_strtoupper($address['line_2']);
        }

        if (isset($address['line_3'])) {
            $add->line_3 = mb_strtoupper($address['line_3']);
        }

        $add->city = mb_strtoupper($address['city']);
        $add->state_province = mb_strtoupper($address['state_province']);
        $add->zip = mb_strtoupper($address['zip']);
        $add->country_id = mb_strtoupper($address['country_id']);
        $add->save();

        return $add->id;
    }
}

/**
 * Retrieve the brand ID from DB, using the brand name as the lookup
 */
function getBrandId(string $name) {

    $query = '
        SELECT
            id
        FROM brands
        WHERE
            name = :name
            AND client_id IS NOT NULL
            AND deleted_at IS NULL
    ';

    $bindings = [
        'name' => $name
    ];

    $result = DB::select(DB::raw($query), $bindings);

    // Return ID from first record
    if(count($result) > 0) {
        return $result[0]->id;
    }
    
    return null;
}

/**
 * Generate Email Address Records.
 *
 * @param string $email_address - email address
 * @param object $event         - event object
 */
function generateEmailAddressRecords($email_address, $id_type, $type_id)
{
    $email_address = mb_strtolower($email_address);
    $e = EmailAddress::where('email_address', $email_address)->withTrashed()->first();
    $ret = false;
    if (!$e) {
        $e = new EmailAddress();
        $e->email_address = $email_address;
        $e->save();
        $ret = true;
    } else {
        if ($e->trashed()) {
            $e->restore();
        }
    }

    $el = EmailAddressLookup::where(
        'email_address_type_id',
        $id_type
    )->where(
        'type_id',
        $type_id
    )->where(
        'email_address_id',
        $e->id
    )->withTrashed()->first();
    if (!$el) {
        $el = new EmailAddressLookup();
        $el->email_address_type_id = $id_type;
        $el->type_id = $type_id;
        $el->email_address_id = $e->id;
        $el->save();
    } else {
        if ($el->trashed()) {
            $el->restore();
        }
    }

    return ['new_record' => $ret, 'lookup' => $el->id];
}

/**
 * @method getHydrationVariableMap
 *
 * @return array Variable Map for use with variable hydration function
 */
function getHydrationVariableMap()
{
    return \App\Http\Controllers\SupportController::getVariableMap();
}

/**
 * Retrieve utilities list from DB
 * 
 * @param string $brandId - The brand ID to retrieve the utilities for
 * @return array - The utility list. An array of simple objects
 */
function getUtilities(string $brandId) {

    $query = '
        SELECT
            utilities.name AS utility_name,
            brand_utilities.utility_label,
            states.state_abbrev AS state,
            utility_supported_fuels.utility_fuel_type_id,
            utilities.id AS utility_id,
            utility_supported_fuels.id AS usf_id,
            brand_utility_supported_fuels.id AS busf_id,
            utilities.ldc_code AS utility_ldc_code,
            brand_utility_supported_fuels.ldc_code AS brand_ldc_code
        FROM utilities
        LEFT JOIN utility_supported_fuels ON utilities.id = utility_supported_fuels.utility_id AND utility_supported_fuels.deleted_at IS NULL
        LEFT JOIN brand_utilities ON utilities.id = brand_utilities.utility_id AND brand_utilities.deleted_at IS NULL
        LEFT JOIN brand_utility_supported_fuels ON utility_supported_fuels.id = brand_utility_supported_fuels.utility_supported_fuel_id 
                  AND brand_utility_supported_fuels.brand_id = brand_utilities.brand_id AND brand_utility_supported_fuels.deleted_at IS NULL
        LEFT JOIN states ON utilities.state_id = states.id
        WHERE
            brand_utilities.brand_id = :brand_id
            AND utilities.deleted_at IS NULL
    ';

    $bindings = [
        'brand_id' => $brandId
    ];

    $utils = DB::select(DB::raw($query), $bindings);

    return $utils;
}

/**
 * @method getSqlQueryString
 * 
 * Returns the raw SQL string from an Eloquent or query build object.
 * 
 * @param mixed The query contructed in query builder or eloquent.
 * @return string Query string with bindings tokens replaced with their values
 */
function getSqlQueryString($query) {
    $str = str_replace(array('?'), array('\'%s\''), $query->toSql());
    $str = vsprintf($str, $query->getBindings());

    $str = str_replace('`', '', $str); // Removes ` character from table and field names in SQL string
    return $str;
}

/**
 * @method gatherEventDetails
 * @param string $eventId - the id of the event to gather
 * @return array|null caches and returns the event data suitable for hydration
 */
function gatherEventDetails(string $eventId)
{
    return Cache::remember('event-' . $eventId, 30, function () use ($eventId) {
        return \App\Http\Controllers\SupportController::gatherEventDetails($eventId);
    });
}


/**
 * @method hydrateVariables
 * @param string $inputText
 * @param string|array $eventIdOrData - either the event id as a string or event data
 * @param string|array $selectedProduct - either the selected product id as a string or product data
 * @param int $langId - 1 for english, 2 for spanish, optional (defaults to english)
 * @param array $varMap - for reusing var map instance, optional
 * @return string|null hydrated string or null on error
 */
function hydrateVariables(string $inputText, $eventIdOrData, $selectedProduct = null, $langId = 1, $varMap = null)
{
    if (is_string($eventIdOrData)) {
        $eventData = Cache::remember('event-' . $eventIdOrData, 30, function () use ($eventIdOrData) {
            return \App\Http\Controllers\SupportController::gatherEventDetails($eventIdOrData);
        });
    } else {
        $eventData = $eventIdOrData;
    }
    if ($selectedProduct !== null) {
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
                throw new \Exception('Invalid number of selected products');
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
    }
    if ($varMap === null) {
        $varMap = \App\Http\Controllers\SupportController::getVariableMap();
    }

    return \App\Http\Controllers\SupportController::hydrateVariables($inputText, $eventData, $langId, $varMap);
}

/**
 * Send a message to the specified slack channel
 */
function SendTeamMessage(string $channel, string $message)
{
    $notification_service = strtolower(config('services.notifications', 'both'));
    $notification_service = empty($notification_service) ? 'both' : $notification_service;
    $return = false;

    $message = sprintf('[%s] %s', config('app.env'), $message);

    if ($notification_service === 'both' || $notification_service === 'mattermost') {
        $return = $return || Artisan::call('mattermost:notify', [
            '--channel' => $channel,
            'message' => $message,
        ]) == 0;
    }
    if ($notification_service === 'both' || $notification_service === 'slack') {
        $return = $return || Artisan::call('slack:notify', [
            '--channel' => $channel,
            'message' => $message,
        ]) == 0;
    }


    return $return;
}

/**
 * Default settings object/array we'll be using to work with Curl.
 */
function curlGetDefaultFtpSettings()
{
    return [
        "delivery_method" => "", // Should be left blank unless FTP w/SSL explicit mode is required
        "host" => "",
        "username" => "",
        "password" => "",
        "port" => 21,
        "root" => "/",
        "passive" => true,
        "ssl" => false,
        "timeout" => 30
    ];
}

/**
 * Replaces values in one array using values from a second array. Only the keys in the first array are looked at.
 * 
 * Used to allow use to have start with the full set of array keys from the default settings and use a small array
 * to populate (overwrite) just the keys we want.
 */
function curlMergeFtpSettings($settings, $customVals)
{
    foreach (array_keys($settings) as $key) {
        if (isset($customVals[$key]) && $customVals[$key]) {
            $settings[$key] = $customVals[$key];
        }
    }

    return $settings;
}

/**
 * FTP upload using Curl. At this time, we can only upload one file at a time.
 */
function curlFtpUpload($file, $ftpSettings)
{
    // Validate params
    if (!$file) {
        return '$file arg is empty';
    }

    if (!isset($ftpSettings) || !$ftpSettings) {
        return '$ftpSettings arg is missing or null';
    }

    // Does local file exist?
    if (!file_exists($file)) {
        return 'Unable to locate local file: ' . $file;
    }

    // Get file info
    $fileInfo = pathinfo($file);

    // Populate settings
    // Start with default settings, then override with user values
    $settings = curlGetDefaultFtpSettings();
    $settings = curlMergeFtpSettings($settings, $ftpSettings);

    // Validate settings
    if (!$settings['host']) {
        return "Missing setting: host";
    }

    // Root path must begin and end in '/'. If root is empty, only one '/' is required.
    if (substr(trim($settings['root']), -1) != "/") {
        $settings['root'] = trim($settings['root']) . "/";
    }

    if (substr(trim($settings['root']), 0, 1) != "/") {
        $settings['root'] = "/" . trim($settings['root']);
    }


    // Only FTP the file if we can open it. File handle is provided to CURL.
    if ($fp = fopen($file, 'r')) {

        // Determine protocol
        if($settings['delivery_method'] == 'ftpes') {
            $protocol = "ftp://";
        } else {
            if ($settings['ssl']) {
                $protocol = "ftps://";
            } else {
                $protocol = "ftp://";
            }
        }

        // Build URL
        $ftp_server = $protocol . $settings['host'] . ":" . $settings['port'] . $settings['root'] . $fileInfo['basename'];

        // exit();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $ftp_server);
        curl_setopt($ch, CURLOPT_USERPWD, $settings['username'] . ':' . $settings['password']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if($settings['delivery_method'] == 'ftpes') { // For FTP with SSL explicit mode
            curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
            curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
        } else {
            if ($settings['ssl']) { // For FTP with SSL implicit mode
                curl_setopt($ch, CURLOPT_USE_SSL, CURLFTPSSL_TRY);
                curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
            }
        }

        curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, true);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);

        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        fclose($fp);

        return (!$err ? "Success" : "Error");
    } else {
        return "Error opening the specified file. FTP cancelled.";
    }

    return "Unexpected error";
}

/**
 * Curl FTP Download
 * 
 * @file
 */
function curlFtpDownload($remoteFile, $localFile, $ftpSettings)
{
    // Validate params
    if (!$remoteFile) {
        return '$remoteFile arg is empty';
    }

    if (!$localFile) {
        return '$localFile arg is empty';
    }

    if (!isset($ftpSettings) || !$ftpSettings) {
        return '$ftpSettings arg is missing or null';
    }

    // Does local file exist?
    if (file_exists($localFile)) {
        return 'A local file with this name already exists: ' . $localFile;
    }

    // Populate settings
    // Start with default settings, then override with user values
    $settings = curlGetDefaultFtpSettings();
    $settings = curlMergeFtpSettings($settings, $ftpSettings);

    // Validate settings
    if (!$settings['host']) {
        return "Missing setting: host";
    }

    if (trim($settings['root']) === "") {
        $settings['root'] = "/";
    }

    if ($fp = fopen($localFile, 'w')) {

        // Determine protocol
        if ($settings['ssl']) {
            $protocol = "ftps://";
        } else {
            $protocol = "ftp://";
        }

        // Build URL
        $ftp_server = $protocol . $settings['host'] . ":" . $settings['port'] . $settings['root'] . $remoteFile;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $ftp_server);
        curl_setopt($ch, CURLOPT_USERPWD, $settings['username'] . ':' . $settings['password']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($settings['ssl']) {
            curl_setopt($ch, CURLOPT_USE_SSL, CURLFTPSSL_TRY);
            curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
        }
        curl_setopt($ch, CURLOPT_UPLOAD, 0);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        curl_exec($ch);

        if (curl_error($ch)) {
            curl_close($ch);
            $result = 'Error';
        } else {
            curl_close($ch);
            $result = 'Success';
        }

        fclose($fp);

        return $result;
    } else {
        return "Error opening the local file stream. FTP cancelled.";
    }

    return "Unexpected error";
}

/**
 * Convert text to a field-name-friendly format. [a-zA-Z0-9] are allowed. Everything else gets converted to an underscore.
 * 
 * ex1: 'field name 1' --> field_name_1
 * ex2: 'field-name:2' --> field_name_2
 * ex3: 'field-name:::3' --> field_name_3
 * 
 * @param string $text - The text to convert.
 * 
 * @return string
 */
function strToFieldName(string $text)
{
    if (!$text) {
        return "";
    }

    // Initial pass. Replace non-alphanumeric characters with underscores
    $t = preg_replace("/[^A-Za-z0-9]/", "_", $text);

    // Remove sequential underscores, leaving only one (ie ___ becomes _)
    while (substr_count($t, "__") > 0) {
        $t = str_replace("__", "_", $t);
    }

    // Finally, remove any trailing underscores
    if (substr($t, -1) === "_") {
        $t = substr($t, 0, strlen($t) - 1);
    }

    return $t;
}

function xls2Csv($inputFile, $resultFile = '')
{
    if (!file_exists($inputFile)) {
        return "Unable to locate file '" . $inputFile . "'";
    }

    if (empty($resultFile)) {
        $resultFile = $inputFile;
    }

    // Change result file's extension
    // $fileileInfo = pathinfo($resultFile);

    // $resultFile = $fileileInfo['dirname'] . '/' . $fileileInfo['filename'] . '.csv';

    $output = null;
    $returnVal = null;
    exec("ssconvert " . $inputFile . " " . $resultFile, $output, $returnVal);

    if ($returnVal != 0) {
        return 'Error converting file';
    }

    if (!file_exists($resultFile)) {
        return "Unable to locate converted file. Conversion failure? File: '" . $resultFile . "'";
    }
    return 'success';
}
