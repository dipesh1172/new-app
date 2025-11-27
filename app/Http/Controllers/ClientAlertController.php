<?php

namespace App\Http\Controllers;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

use GuzzleHttp\Client as GuzzleClient;
use Exception;
use Carbon\CarbonImmutable;
use Carbon\Carbon;

use App\Models\UtilityAccountType;
use App\Models\Utility;
use App\Models\User;
use App\Models\StatsProduct;
use App\Models\State;
use App\Models\ServiceType;
use App\Models\ProviderIntegration;
use App\Models\PhoneNumberVoipLookup;
use App\Models\PhoneNumberLookup;
use App\Models\PhoneNumber;
use App\Models\JsonDocument;
use App\Models\Interaction;
use App\Models\EventProduct;
use App\Models\EventAlert;
use App\Models\Event;
use App\Models\Disposition;
use App\Models\ClientAlertCategory;
use App\Models\ClientAlert;
use App\Models\BrandUtilitySupportedFuel;
use App\Models\BrandUtility;
use App\Models\BrandUser;
use App\Models\BrandClientAlert;
use App\Models\Brand;
use App\Models\AddressLookup;
use App\Models\Address;
use App\Mail\EventAlertTripped;
use App\Mail\EventAlertTrippedStaging;
use App\Models\BrandHour;

class ClientAlertController extends Controller
{
    private $_client;

    public function __construct()
    {
        $this->middleware(
            function ($request, $next) {
                return $this->cors($request, $next);
            }
        );

        $this->_client = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
    }

    public static function routes()
    {
        Route::post('alerts/check', 'ClientAlertController@doCheckForAlerts');
        Route::options('alerts/check', 'ClientAlertController@sayItsOk');
    }

    public function sayItsOk()
    {
        return response('It is Okay My Dude', 200);
    }

    private function cors($request, $next)
    {
        return $next($request)->withHeaders(
            [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
            ]
        );
    }

    public function standardizeProductData($indata)
    {
        info('In standardizeProductData');
        if (!is_array($indata)) {
            $outdata = json_decode($indata, true);
        } else {
            $outdata = $indata;
        }

        $product = null;

        if (isset($outdata['product']) && null !== $outdata['product']) {
            $product = json_decode(json_encode($outdata['product']), true);

            if (!empty($product['bill_fname']) && empty($product['bill_first_name'])) {
                $product['bill_first_name'] = $product['bill_fname'];
                unset($product['bill_fname']);
            }
            if (!empty($product['bill_mname']) && empty($product['bill_middle_name'])) {
                $product['bill_middle_name'] = $product['bill_mname'];
                unset($product['bill_mname']);
            }
            if (!empty($product['bill_lname']) && empty($product['bill_last_name'])) {
                $product['bill_last_name'] = $product['bill_lname'];
                unset($product['bill_lname']);
            }
            if (!isset($product['bill_middle_name'])) {
                $product['bill_middle_name'] = null;
            }

            if (!isset($product['selection']) || !is_array($product['selection'])) {
                if (!isset($product['utility_supported_fuel'])) {
                    return $outdata;
                }
                $util_id = $product['utility_supported_fuel']['utility']['id'];
                $product['selection'] = [$util_id => [[], []]];

                if (isset($product['utility_supported_fuel']['fuel_type'])) {
                    $product['selection'][$util_id][$product['event_type']['id'] - 1]['fuel_type']
                        = $product['utility_supported_fuel']['fuel_type'];
                } else {
                    $product['selection'][$util_id][$product['event_type']['id'] - 1]['fuel_type']
                        = $product['utility_supported_fuel']['utility_fuel_type_id'];
                }

                $product['selection'][$util_id][$product['event_type']['id'] - 1]['selected']
                    = $product['rate']['id'];

                $rawIdents = $product['identifiers'];
                $identifiers = [];
                foreach ($rawIdents as $ident) {
                    $copy = $ident;
                    if (!empty($copy['identifier']) && !isset($copy['ident'])) {
                        $copy['ident'] = $copy['identifier'];
                        unset($copy['identifier']);
                    }
                    $identifiers[] = $copy;
                }
                $product['selection'][$util_id][$product['event_type']['id'] - 1]['identifiers']
                    = $identifiers;
                unset($product['identifiers']);

                $product['selection'][$util_id][$product['event_type']['id'] - 1]['fuel_id']
                    = $product['utility_supported_fuel']['id'];

                if (isset($product['promotion'])) {
                    $product['selection'][$util_id][$product['event_type']['id'] - 1]['promotion_id'] = $product['promotion']['id'];
                    unset($product['promotion']);
                } else {
                    $product['selection'][$util_id][$product['event_type']['id'] - 1]['promotion_id'] = null;
                }
            }

            if (isset($product['service_address']) || isset($product['billing_address'])) {
                $product['addresses'] = [
                    'service' => isset($product['service_address']) ? $product['service_address'] : null,
                    'billing' => isset($product['billing_address']) ? $product['billing_address'] : null,
                ];

                unset($product['service_address']);
                unset($product['billing_address']);
            }

            if (isset($product['addresses']) && !isset($product['addresses']['billing'])) {
                $product['addresses']['billing'] = null;
            }

            if (isset($product['phone_number']) && empty($outdata['phone'])) {
                $outdata['phone'] = $product['phone_number'];
            }

            if ($product != null) {
                $outdata['product'] = $product;
            }
        }

        if ($product != null && empty($outdata['auth_name']['first_name']) && !empty($product['auth_first_name'])) {
            $outdata['auth_name'] = [
                'first_name' => $product['auth_first_name'],
                'middle_name' => $product['auth_middle_name'],
                'last_name' => $product['auth_last_name'],
            ];
        }

        if (!empty($outdata['phone'])) {
            $outdata['phone'] = CleanPhoneNumber($outdata['phone']);
        }

        return $outdata;
    }

    private function auth_request(Request $request)
    {
        $u = null;
        if (config('app.env') !== 'production' && $request->input('apitest') !== null) {
            return $u;
        }
        $api_token = $request->input('api_token');
        if (null == $api_token) {
            $api_token = $request->input('token');
        }
        if ($api_token) {
            if (is_string($api_token)) {
                $exists = false;
                if ('U' === substr($api_token, 0, 1)) {
                    $u = \App\Models\User::where('api_token', $api_token)->first();
                    if (null !== $u) {
                        $exists = true;
                    }
                } else {
                    $u = \App\Models\TpvStaff::where(
                        'api_token',
                        $api_token
                    )->first();
                    if (null !== $u) {
                        $exists = true;
                    }
                }
                if (!$exists) {
                    Log::info('Invalid API TOKEN: ' . $api_token);
                    $api_token = null;
                }
            } else {
                $api_token = null;
            }
        }

        if (null === $api_token) {
            abort(401);
        }

        return $u;
    }

    public function doCheckForAlerts(Request $request)
    {
        info('Raw Alert Data: ' . json_encode($request->all()));

        $request->validate(
            [
                'brand' => 'required|exists:brands,id',
                'type' => 'required|exists:client_alert_categories,name',
                'data' => 'required',
                //'token' => 'required|exists:tpv_staff,api_token',
            ]
        );

        // $this->auth_request($request);

        $brand_id = $request->input('brand');
        $event_type = ClientAlertCategory::where('name', $request->input('type'))->first()->id;
        try {
            $req_data = $this->standardizeProductData($request->input('data'));
            info('Standardized Data: ', $req_data);
        } catch (\Exception $e) {
            info('Got an error while standardizing data:', [$e]);

            return response()->json(['errors' => true, 'message' => [$e->getMessage()], 'disposition' => null, 'stop-call' => false]);
        }
        try {
            info('getting configured alerts for brand');
            $brand_alerts = $this->_getAlertsForBrand($brand_id, $event_type);
            info('got alerts');
        } catch (Exception $e) {
            return response()->json(['errors' => true, 'message' => [$e->getMessage()], 'disposition' => null, 'stop-call' => false]);
        }

        info(
            'In doCheckForAlerts: '
                . json_encode($brand_alerts->toArray())
        );

        $response = [];

        if ($brand_alerts->count() > 0) {
            switch ($event_type) {
                case 1: //'CALL-START':
                case 2: //'CUST-INFO-PROVIDED':
                case 3: //'ACCT-INFO-PROVIDED':
                case 4: //'DISPOSITIONED':
                    $this->ProcessEventForAlerts(
                        $brand_alerts,
                        $brand_id,
                        $event_type,
                        $req_data,
                        $response
                    );
                    break;

                default:
                    info(
                        'Unknown Alert Category,
                            ClientAlertController needs to be updated
                            to handle this request.',
                        [$request]
                    );
                    break;
            }
        }

        if (!empty($req_data['event'])) {
            info('Alert Response for ' . $req_data['event'], [$response]);
        } else {
            info('Alert Response (unknown event id)', [$response]);
        }

        return response()->json($response)
            ->withHeaders(
                [
                    'Access-Control-Allow-Origin' => config('app.urls.agents', '*'),
                    'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                    'Access-Control-Allow-Headers' => '*',
                ]
            );
    }

    /**
     * _channelsToArray converts the comma delimited channel string to int array.
     *
     * @param string $channels contains a comma delimited list of channels
     *
     * @return array of integers
     */
    private function _channelsToArray($channels)
    {
        $c = explode(',', $channels);
        $array = [];
        for ($i = 0; $i < count($c); ++$i) {
            switch ($c[$i]) {
                case 'DTD':
                    $array[] = 1;
                    break;
                case 'Retail':
                    $array[] = 3;
                    break;
                case 'TM':
                    $array[] = 2;
                    break;
            }
        }

        return $array;
    }

    public function ProcessEventForAlerts(
        Collection $brand_alerts,
        string $brand_id,
        string $event_type,
        array $data,
        array &$response,
        bool $createEntry = true
    ) {
        info('Alert Data', [$brand_alerts->toArray()]);
        foreach ($brand_alerts as $alert) {
            if ($event_type != $alert->category_id || $alert->enabled === false) {
                continue;
            }
            // info(print_r($data, true));
            $iresponse = [
                'errors' => false,
                'stop-call' => false,
                'disposition' => $alert->disposition,
                'message' => null,
                'conflicts' => [],
                'timing' => null,
            ];
            if (method_exists($this, $alert->function)) {
                info('Method exists: ' . $alert->function);

                try {
                    //$iresponse['disposition'] = $alert->disposition;
                    $iresponse['func'] = $alert->function;
                    $startTime = hrtime(true);
                    $ret = call_user_func_array(
                        [$this, $alert->function],
                        [
                            $alert->id,
                            $brand_id,
                            !is_int($alert->threshold) ? 0 : $alert->threshold,
                            $this->_channelsToArray($alert->channels),
                            ($alert->stop_call && null != $alert->stop_call)
                                ? true : false,
                            $data,
                            &$iresponse,
                            $alert->enabled,
                        ]
                    );
                    $iresponse['timing'] = (hrtime(true) - $startTime) / 1e+6; // ns to ms

                    if ($createEntry) {
                        if ($ret) {
                            $this->_createEventAlert(
                                $data['event'],
                                $alert,
                                $data,
                                $brand_id,
                                $iresponse['conflicts']
                            );
                        } else {
                            info(
                                '*** Did not _createEventAlert() for ' . $alert->function . ' for event ' . $data['event']
                            );
                        }
                    }

                    $response = array_merge_recursive($response, $iresponse);

                    if (false !== $iresponse['errors']) {
                        break;
                    }
                } catch (\Exception $e) {
                    info($e);
                    if ('Not Implemented' !== $e->getMessage()) {
                        $iresponse['errors'] = [$e->getMessage()];
                    }
                }
            } else {
                info(
                    'Check for Condition ' . $alert->function . ' does not exist',
                    $data
                );
            }
        }
    }

    private function _sendAlertEmail(EventAlert $alert, string $brand_id, string $vendor_id): void
    {
        // info('_sendAlertEmail ' . json_encode($alert));

        $list = BrandClientAlert::select(
            'brand_client_alerts.distribution_email',
            'brand_client_alerts.vendor_distribution'
        )->where(
            'brand_client_alerts.brand_id',
            $brand_id
        )->where(
            'brand_client_alerts.client_alert_id',
            $alert->client_alert_id
        )->where('brand_client_alerts.status', 1)->first();

        if ($list) {
            $email_list = [
                'client' => [],
                'vendor' => []
            ];

            $clientEmails = preg_replace("/\r|\n/", '', trim($list->distribution_email));
            if (strlen(trim($clientEmails)) > 0) {
                $email_list['client'] = array_filter(array_map('trim', explode(',', $clientEmails)));
            }

            if ($list->vendor_distribution) {
                try {
                    $vendorDistribution = json_decode($list->vendor_distribution, true);
                    if (isset($vendorDistribution[$vendor_id])) {
                        $vendorEmails = preg_replace("/\r|\n/", '', trim($vendorDistribution[$vendor_id]));
                        $email_list['vendor'] = array_filter(array_map('trim', explode(',', $vendorEmails)));
                    }
                } catch (Exception $e) {
                    Log::error('_sendAlertEmail@ClientAlertController: client alert id ' . $alert->client_alert_id .
                        ' - brand ' . $brand_id . ' - vendor ' . $vendor_id . ' - error ' . $e->getMessage());
                }
            }

            foreach ($email_list as $type => $emails) {
                foreach ($emails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        Log::info("Sending {$type} mail to ({$email}) " . json_encode($alert));

                        try {
                            if (config('app.env') != 'production') {
                                Mail::to($email)
                                    ->send(new EventAlertTrippedStaging($alert, $type));

                            } else {
                                Mail::to($email)
                                    ->send(new EventAlertTripped($alert, $type));
                            }
                        } catch (Exception $e) {
                            SendTeamMessage('monitoring', "Failed to send notification for Brand Alert ID: ($alert->id):\n```" . $e->getMessage() . "```");
                        }

                        if (!Str::endsWith($email, '@tpv.com') && !Str::endsWith($email, '@answernet.com')) {
                            info('Email is not a tpv.com email so create invoiceable for it');
                            BillingController::CreateInvoiceable(
                                $brand_id,
                                'Email::Send',
                                1,
                                $alert->id
                            );
                        }
                    }
                }
            }
        } else {
            info('No emails for brand alert');
        }
    }

    public function altChecksEntry($event_id, $alert_name, $data)
    {
        $event = Event::find($event_id);
        if (!$event) {
            Log::error('ClientAlertController@altChecksEntry: No event found for event id ' . strval($event_id));
            return;
        }

        $data = $data ?? [];

        $clientAlert = ClientAlert::select('client_alerts.id', 'client_alerts.function', 'brand_client_alerts.status as enabled')
            ->join('brand_client_alerts', 'client_alerts.id', '=', 'brand_client_alerts.client_alert_id')
            ->where('brand_client_alerts.brand_id', $event->brand_id)
            ->where('client_alerts.function', $alert_name)
            ->first();

        if (!$clientAlert) {
            Log::error('ClientAlertController@altChecksEntry: No client alert found for alert function ' . strval($alert_name) . ' - Event: ' . strval($event_id));
            return;
        }

        try {
            $this->_createEventAlert($event_id, $clientAlert, $data, $event->brand_id);
        } catch (\Exception $e) {
            Log:error('ClientAlertController@altChecksEntry: Error while creating event alert for: Event ' . strval($event_id) . ' - Alert: ' . strval($alert_name) . ' - Brand: ' . strval($event->brand_id));
        }
    }

    private function _createEventAlert(
        $event_id,
        $client_alert,
        $data,
        $brand_id = null,
        $conflicts = []
    ): EventAlert {
        info('In _createEventAlert for ' . $event_id);
        if ($client_alert == null) {
            info('client_alert is null');

            throw new \Exception('Client Alert cannot be empty');
        }

        info(
            '_createEventAlert (alert_id #' . $client_alert->id . ') '
                . $client_alert->function
                . ' -- '
                . json_encode($data)
        );

        if (!isset($data['when'])) {
            $data['when'] = Carbon::now();
        }

        $alertsFunctionCanBeSentTwice = array('CheckLinkAccessedSameDevice','CheckDuplicatedIPAddress', 'DigitalCompletedOnAgentDevice');

        if (null !== $event_id && !in_array($client_alert->function, $alertsFunctionCanBeSentTwice)) {
            info('event_id is null');
            $existing = EventAlert::where(
                'event_id',
                $event_id
            )->where(
                'client_alert_id',
                $client_alert->id
            )->first();
        } else {
            $existing = null;
        }

        // Retrieve the event record using the event_id
        $event = Event::find($event_id);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        // Extract the vendor_id from the event record
        $vendor_id = $event->vendor_id;
        info('Vendor ID for event is ' . $vendor_id);

        if (null === $existing) {
            info(
                '_createEventAlert :: new event ('
                    . $client_alert->function
                    . ') - starting email sending.'
            );

            $ret = new EventAlert();
            $ret->event_id = $event_id;
            $ret->client_alert_id = $client_alert->id;
            $ret->data = ['conflicts' => $conflicts, 'brand_id' => $brand_id, 'data' => [$data]];
            $ret->save();

            info($client_alert->function . ' - alert is ' . $client_alert->enabled);
            if ($client_alert->enabled) {
                $this->_sendAlertEmail($ret, $brand_id, $vendor_id);
            }

            return $ret;
        } else {
            info('_createEventAlert :: old event');

            $now = CarbonImmutable::now();
            $then = $existing->updated_at;
            $diffInDays = abs($now->diffInDays($then));
            $o = $existing->data;
            $inDataHash = hash('sha256', json_encode($data));
            $add = $diffInDays < 1 ? false : true;

            if (is_array($o) && count($o['data']) > 0) {
                info('Determining if we need to add our data to alert');
                foreach ($o['data'] as $dataItem) {
                    $copy = $dataItem;
                    unset($copy['when']);
                    $hashy = hash('sha256', json_encode($copy));
                    if (hash_equals($inDataHash, $hashy)) {
                        info('We do not need to add this data, it already exists');
                        $add = false;
                        break;
                    }
                }
            }

            if ($add) {
                info('Adding this data to the alert');
                $o['conflicts'] = $conflicts;
                $o['data'][] = $data;
                $existing->data = $o;
                $existing->save();

                info($client_alert->function . ' - (add) alert is ' . $client_alert->enabled);
                if ($client_alert->enabled) {
                    info('This alert is enabled so "send" the email');
                    $this->_sendAlertEmail($existing, $brand_id, $vendor_id);
                }
            }

            return $existing;
        }
    }

    public function GetAlertsForBrand($brand_id, $category_id)
    {
        return $this->_getAlertsForBrand($brand_id, $category_id);
    }

    /**
     * Get Alerts by Brand.
     *
     * @param string $brand_id
     *
     * @return Collection
     */
    private function _getAlertsForBrand($brand_id, $category_id)
    {
        $only = request()->input('onlyFunc');
        // info('getting all alerts');
        $alerts = $this->_getAllAlerts($brand_id, $category_id);
        // info('getting brand enabled alerts');
        $brand_alerts = BrandClientAlert::select(
            'brand_client_alerts.client_alert_id',
            'brand_client_alerts.channels',
            'brand_client_alerts.threshold',
            'brand_client_alerts.stop_call',
            'brand_client_alerts.disposition_id',
            'client_alerts.function',
            'client_alerts.category_id'
        )
            ->with('disposition')
            ->leftJoin(
                'client_alerts',
                'brand_client_alerts.client_alert_id',
                'client_alerts.id'
            )->where(
                'brand_client_alerts.brand_id',
                $brand_id
            )->where(
                'client_alerts.category_id',
                $category_id
            )->where(
                function ($query) {
                    $query->where(
                        'status',
                        1
                    )->orWhere(
                        'status',
                        true
                    );
                }
            );

        if ($only !== null && config('app.env') !== 'production') {
            $brand_alerts->where('client_alerts.function', $only);
        }

        $brand_alerts = $brand_alerts->orderBy(
            'client_alerts.sort',
            'asc'
        )->get();
        // info('mapping results');
        $alerts = $alerts->map(
            function ($item) use ($brand_alerts) {
                $item->enabled = false;
                $item->disposition = null;
                foreach ($brand_alerts as $ba) {
                    if ($ba->client_alert_id === $item->id) {
                        $item->enabled = true;
                        $item->threshold = $ba->threshold;
                        $item->channels = $ba->channels;
                        $item->function = $ba->function;
                        if (!empty($ba->disposition_id)) {
                            info('assigning disposition id ' . $ba->disposition_id . ' to alert ' . $item->id);
                            info('brand alert', [$ba]);
                            $item->disposition = $ba->disposition->reason;
                        } else {
                            info('no disposition for alert ' . $item->id);
                            $item->disposition = null;
                        }
                        $item->stop_call = ($ba->stop_call) ? true : false;
                    }
                }

                return $item;
            }
        )->filter(function ($item) {
            return $item->enabled;
        });
        // info('mapping done');

        return $alerts;
    }

    private function _getAllAlerts($brand_id, $category_id)
    {
        // info('In _getAllAlerts');
        return ClientAlert::select(
            'client_alerts.id',
            'client_alerts.title',
            'client_alerts.channels',
            'client_alerts.description',
            'client_alerts.threshold',
            'client_alerts.function',
            'client_alerts.category_id',
            DB::raw('client_alert_categories.name as category'),
            'client_alerts.brand_id'
        )->join(
            'client_alert_categories',
            'client_alert_categories.id',
            'client_alerts.category_id'
        )->where('client_alerts.category_id', '=', $category_id)
            ->where(function ($query) use ($brand_id) {
                $query->where('brand_id', $brand_id)
                    ->orWhereNull('brand_id');
            })
            ->orderBy('sort', 'ASC')
            ->get()
            ->map(
                function ($item) {
                    $item->disposition = null;
                    $item->enabled = false;
                    $item->stop_call = false;
                    $item->type = strtoupper(str_slug($item->title, '_'));

                    return $item;
                }
            );
    }

    /* Section: alert checks */

    /*
     * The $data parameter depends on the alert category as to what data it receives.
     *
     * CALL-START: Alerts in this category can be detected at the
     *      beginning of the call.
     * {
            event: this.$store.state.callInfo.eventId,
            agent: this.$store.state.callInfo.agent.id,
            calledFrom: this.$store.state.callInfo.calledFrom,
            calledInto: this.$store.state.callInfo.calledInto,
            interaction: this.$store.state.callInfo.interaction_id
        }
     *
     * CUST-INFO-PROVIDED: Alerts in this category can be detected
     *      once the agent has provided customer contact information.
     * {
            auth_name: {first_name, middle_name, last_name},
            phone: this.phone,
            email: this.email,
            event: this.$store.state.callInfo.eventId,
            agent: this.$store.state.callInfo.agent.id,
            calledFrom: this.$store.state.callInfo.calledFrom,
            calledInto: this.$store.state.callInfo.calledInto,
            interaction: this.$store.state.callInfo.interaction_id,
            channel: this.$store.state.callInfo.event.channel_id,
        }
     *
     * ACCT-INFO-PROVIDED: Alerts in this category can be detected when
     *      the agent provides account information.
     * {
            event: this.$store.state.callInfo.eventId,
            agent: this.$store.state.callInfo.agent.id,
            calledFrom: this.$store.state.callInfo.calledFrom,
            calledInto: this.$store.state.callInfo.calledInto,
            interaction: this.$store.state.callInfo.interaction_id,
            product: this.curProduct
        }
     *
     * DISPOSITIONED: Alerts in this category can be detected at the end of the call.
     * {
            event: this.$store.state.callInfo.eventId,
            agent: this.$store.state.callInfo.agent.id,
            calledFrom: this.$store.state.callInfo.calledFrom,
            calledInto: this.$store.state.callInfo.calledInto,
            interaction: this.$store.state.callInfo.interaction_id,
            callReview: {
                reason: this.$store.state.callInfo.callReviewReason,
                notes: this.$store.state.callInfo.callReviewNotes
            },
            result: this.result, 1 = good sale, 2 = closed, 3 = no sale
            disposition: this.$store.state.callInfo.disposition_id,
            callTime: {
                total: 0,
                current: 0,
                current_interaction: 0,
                countUp: true,
            }
        }
     *
     *
     * Check functions do not "return" a value,
     *      they modify the passed $response array.
     * The array is simple:
     * $response = [
            'errors' => bool(false)|Array,
            'stop-call' => bool(false),
            'disposition' => null|String,
            'message' => null|String,
        ];

     * The behaviour of the Agent portal based on the response value is as follows:
     * 0. If errors != false then any code set to run on success WILL NOT RUN,
     *      a message should also be specified.
     * 1. if message != null and disposition == null and stop-call == false
     *      then the message will be displayed but the TPV will not be stopped.
     * 2. If stop-call == true then the message will be displayed and the call
     *      will end after acknowledgement
     * 3. if disposition != null then the message will be displayed and if
     *      accepted the call will be dispositioned with the disposition otherwise
     *      call will continue
    */

    /**
     * If the account number has been previously used (Good Sale) in the last $threshold days.
     * 
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkAccountPreviouslyEnrolled(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkAccountPreviouslyEnrolled()');
        Log::debug(json_encode($data));

        // Don't run alert if it's disabled or not configured for the current TPV's channel.
        if(!$alertEnabled || !in_array($data['channel'], $channels)) {
            return false;
        }

        if (isset($data['product']['selection'])) {
            foreach ($data['product']['selection'] as $selection) {
                if (isset($selection)) {
                    if (is_array($selection) && isset($selection[0])) {
                        for ($i = 0; $i < count($selection); ++$i) {
                            if (isset($selection[$i]['fuel_type'])) {
                                $product_type = $selection[$i]['fuel_type'];
                                foreach ($selection[$i]['identifiers'] as $selection_identifier) {
                                    if (isset($selection_identifier[0])) {
                                        for ($x = 0; $x < count($selection_identifier); ++$x) {
                                            $identifier = $selection_identifier[$x]['ident'];

                                            $query = "
                                                SELECT event_id AS id, confirmation_code, btn AS phone_number
                                                FROM stats_product sp
                                                WHERE
                                                    sp.account_number1 = '$identifier'
                                            ";

                                            if ($threshold > 0) {
                                                $query .= "
                                                    AND sp.created_at >= (CURDATE() - INTERVAL $threshold DAY) 
                                                ";
                                            }

                                            $query .= "
                                                    AND sp.brand_id = '$brand_id'
                                                    AND sp.result = 'Sale'
                                                    AND sp.commodity_id = $product_type
                                                GROUP BY sp.btn, sp.event_id
                                            ";

                                            $results = DB::select(
                                                DB::raw(
                                                    $query
                                                )
                                            );
                                        }
                                    } else {
                                        if (isset($selection_identifier['ident'])) {
                                            $identifier = $selection_identifier['ident'];

                                            $query = "
                                                SELECT event_id AS id, confirmation_code, btn AS phone_number
                                                FROM stats_product sp
                                                WHERE
                                                    sp.account_number1 = '$identifier'
                                            ";

                                            if ($threshold > 0) {
                                                $query .= "
                                                    AND sp.created_at >= (CURDATE() - INTERVAL $threshold DAY) 
                                                ";
                                            }

                                            $query .= "
                                                    AND sp.brand_id = '$brand_id'
                                                    AND sp.result = 'Sale'
                                                    AND sp.commodity_id = $product_type
                                                GROUP BY sp.btn, sp.event_id
                                            ";

                                            $results = DB::select(
                                                DB::raw(
                                                    $query
                                                )
                                            );

                                            if (count($results) > 0) {
                                                $response['message'] = 'acct_prev_enrolled';
                                                $response['extra'] = [
                                                    'identifier' => $identifier,
                                                ];
                                                $response['conflicts'] = collect($results)->pluck('confirmation_code')->map(function ($item) {
                                                    return 'Confirmation Code: ' . $item;
                                                })->toArray();

                                                if ($stop_call) {
                                                    Log::debug(
                                                        'In checkAccountPreviouslyEnrolled() - stopping call'
                                                    );

                                                    $response['stop-call'] = true;
                                                }

                                                return true;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $keys = array_keys($selection);
                        if (count($keys) === 1) {
                            $selection = $selection[$keys[0]];
                        }
                        $product_type = $selection['fuel_type'];
                        foreach ($selection['identifiers'] as $selection_identifier) {
                            if (isset($selection_identifier['ident'])) {
                                $identifier = $selection_identifier['ident'];

                                $query = "
                                    SELECT events.id AS event_id, events.confirmation_code, phone_numbers.phone_number
                                    FROM event_product_identifiers
                                    LEFT JOIN event_product ON (event_product_identifiers.event_product_id = event_product.id)
                                    LEFT JOIN events ON (event_product.event_id = events.id) 
                                    LEFT JOIN interactions ON (interactions.event_id = events.id)
                                    LEFT JOIN phone_number_lookup ON (phone_number_lookup.type_id = events.id AND phone_number_lookup.phone_number_type_id = 3)
                                    LEFT JOIN phone_numbers ON (phone_number_lookup.phone_number_id = phone_numbers.id)
                                    WHERE
                                        event_product_identifiers.identifier = '$identifier'
                                        AND event_product_identifiers.deleted_at IS NULL
                                ";

                                if ($threshold > 0) {
                                    $query .= "
                                        AND event_product_identifiers.created_at >= (CURDATE() - INTERVAL $threshold DAY)
                                    ";
                                }

                                $query .= "
                                        AND events.brand_id = '$brand_id'
                                        AND interactions.deleted_at IS NULL
                                        AND interactions.event_result_id = 1
                                        AND event_product.event_type_id = $product_type
                                    GROUP BY phone_numbers.phone_number, events.id
                                ";

                                $results = DB::select(
                                    DB::raw(
                                        $query
                                    )
                                );

                                if (count($results) > 0) {
                                    $response['message'] = 'acct_prev_enrolled';
                                    $response['extra'] = [
                                        'identifier' => $identifier,
                                    ];
                                    $response['conflicts'] = collect($results)->pluck('confirmation_code')->map(function ($item) {
                                        return 'Confirmation Code: ' . $item;
                                    })->toArray();

                                    if ($stop_call) {
                                        Log::debug(
                                            'In checkAccountPreviouslyEnrolled() - stopping call'
                                        );

                                        $response['stop-call'] = true;
                                    }

                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * More than one customer was TPVed using the same BTN in the last $threshold days
     * 
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkCallbackNumberPreviouslyUsed(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkCallbackNumberPreviouslyUsed()');
        Log::debug(json_encode($data));

        /*
        select * from interactions as t1
        join events as t3 on t3.id = t1.event_id
        join phone_number_lookup as t2 on t2.type_id = t3.id
        where t2.phone_number_id = 'f8a33e27-afe8-4585-9f7b-b88d852be89b'
        and t1.event_result_id = 1
        and t3.brand_id = '2C958990-AF67-485B-BBCF-488F1E5E2DD3'
        and t1.updated_at > '2018-06-15 00:00:00'
        */

        if (isset($data['phone'])) {
            $phone = CleanPhoneNumber($data['phone']);
            $phone_number = DB::table('phone_numbers')
                ->select('id', 'phone_number')
                ->whereNull('deleted_at')
                ->where('phone_number', $phone)
                ->get()
                ->first();
            if (null != $phone_number) {
                $prevDate = now()->startOfDay()
                    ->subDays($threshold > 0 ? $threshold : 60);

                $records = DB::table('interactions')
                    ->select('events.confirmation_code')
                    ->join('events', 'events.id', 'interactions.event_id')
                    ->join('phone_number_lookup', 'phone_number_lookup.type_id', 'events.id')
                    ->where('phone_number_lookup.phone_number_id', $phone_number->id)
                    ->where('interactions.event_result_id', 1)
                    ->where('events.brand_id', $brand_id)
                    ->whereIn('events.channel_id', $channels)
                    ->where('interactions.updated_at', '>', $prevDate->toDateTimeString())
                    ->get();

                if ($records->count() > 1) {
                    $response['message'] = 'btn_reuse';

                    if ($stop_call) {
                        Log::debug(
                            'In checkCallbackNumberPreviouslyUsed() - stopping call'
                        );

                        $response['stop-call'] = true;
                    }
                    $response['conflicts'] = $records->pluck('confirmation_code')->map(function ($item) {
                        return 'Confirmation Code: ' . $item;
                    })->toArray();

                    return true;
                }

                return false;
            }
        }

        return false;
    }

    /**
    If the customer’s BTN is actually a sales rep's phone number
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkBtnMatchesSalesRepPhoneNumber(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkBtnMatchesSalesRepPhoneNumber()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkBtnMatchesSalesRepPhoneNumber. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkBtnMatchesSalesRepPhoneNumber. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        /*
        select * from phone_number_lookup
        where phone_number_id = '74FB8377-7D82-4006-BCA9-3AD8128199AA'
        and phone_number_type_id in (1,3) group by phone_number_type_id
        */
        if (isset($data['phone']) && strlen(trim($data['phone'])) > 0) {
            $phone = CleanPhoneNumber($data['phone']);
            $phone_number = DB::table('phone_numbers')
                ->select('id', 'phone_number')
                ->whereNull('deleted_at')
                ->where('phone_number', $phone)
                ->get()
                ->first();
            if (null != $phone_number) {
                $records = DB::table(
                    'phone_number_lookup'
                )->select(
                    'phone_number_type_id',
                    'brand_users.tsr_id'
                )->leftJoin(
                    'users',
                    'users.id',
                    'phone_number_lookup.type_id'
                )->leftJoin(
                    'brand_users',
                    'brand_users.user_id',
                    'users.id'
                )->where(
                    'brand_users.works_for_id',
                    $brand_id
                )->where(
                    'phone_number_id',
                    $phone_number->id
                )->where(
                    'phone_number_type_id',
                    1
                )->whereNull(
                    'phone_number_lookup.deleted_at'
                )->whereNull(
                    'brand_users.deleted_at'
                )->whereNull(
                    'users.deleted_at'
                )->groupBy(
                    'phone_number_type_id'
                )->get();
                if ($records->count() > 0) {
                    $response['message'] = 'btn_is_sales_agent';
                    // $response['disposition'] = 'BTN Matches Sales Rep Phone Number';

                    if ($stop_call) {
                        Log::debug(
                            'In checkBtnMatchesSalesRepPhoneNumber() - stopping call'
                        );

                        $response['stop-call'] = true;
                    }
                    $response['conflicts'] = $records->pluck('tsr_id')->map(function ($item) {
                        return 'TSR ID ' . $item;
                    })->toArray();

                    return true;
                }
            }
        }

        return false;
    }

    /**
     *      * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkDualFuelOnlyRatesInUseWithSingleFuelEnrollment(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkDualFuelOnlyRatesInUseWithSingleFuelEnrollment()');
        if (isset($data['enrollmentType']) && 'single' === $data['enrollmentType']) {
            if (
                isset($data['product'])
                && isset($data['product']['rate'])
                && isset($data['product']['rate']['dual_only'])
            ) {
                if (1 === $data['product']['rate']['dual_only']) {
                    $response['message'] = 'dual_only_rate';
                    $response['conflicts'] = $data['product']['rate']['program_code'];

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The sales agent provided an address that is associated with a Good Sale in our database.
     * 
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkExistingServiceAddress(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkExistingServiceAddress()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkExistingServiceAddress. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkExistingServiceAddress. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        if (isset($data['product'])) {
            if (!empty($data['product']['addresses']['service']['line_1'])) {
                $address = trim(
                    mb_strtoupper($data['product']['addresses']['service']['line_1'])
                );
            } else {
                return false;
            }

            if (!empty($data['product']['addresses']['service']['line_2'])) {
                $address2 = trim(
                    mb_strtoupper($data['product']['addresses']['service']['line_2'])
                );
            } else {
                $address2 = null;
            }

            $city = trim(
                mb_strtoupper($data['product']['addresses']['service']['city'])
            );

            $state = trim(
                mb_strtoupper(
                    $data['product']['addresses']['service']['state_province']
                )
            );

            $zip = trim(
                mb_strtoupper($data['product']['addresses']['service']['zip'])
            );

            $addr = Address::where(
                'line_1',
                $address
            );

            if (!empty($address2)) {
                $addr = $addr->where(
                    'line_2',
                    $address2
                );
            } else {
                $addr = $addr->whereNull(
                    'line_2'
                );
            }

            $addr = $addr->where(
                'city',
                $city
            )->where(
                'state_province',
                $state
            )->where(
                'zip',
                $zip
            )->first();

            if (!empty($addr)) {
                $events = AddressLookup::select(
                    'events.id as event_id',
                    'events.confirmation_code'
                )->leftJoin(
                    'event_product',
                    'event_product.id',
                    'address_lookup.type_id'
                )->leftJoin(
                    'events',
                    'events.id',
                    'event_product.event_id'
                )->where(
                    'events.brand_id',
                    $brand_id
                )->whereNull(
                    'events.deleted_at'
                )->where(
                    'address_lookup.address_id',
                    $addr->id
                );

                if (!empty($data['event_id'])) {
                    $events = $events->where('events.id', '<>', $data['event_id']);
                }

                if ($threshold > 0) {
                    $events = $events->whereRaw(
                        'events.created_at >= (CURDATE() - INTERVAL ' . $threshold . ' DAY)'
                    );
                }

                $events = $events->groupBy(
                    'events.id'
                )->get()->filter(function ($item) {
                    $i = Interaction::where('event_id', $item->event_id)->where('event_result_id', 1)->count();

                    return $i > 0;
                });

                $list = [];

                if ($events) {
                    $events = $events->toArray();
                    if (empty($data['event_id']) && count($events) === 1) {
                        // if we can't compare the event ids and there's only one address entry it can't be in conflict
                        return false;
                    }
                    for ($i = 0, $len = count($events); $i < $len; ++$i) {
                        if (!empty($events[$i]['event_id'])) {
                            if (!empty($data['event_id']) && $events[$i]['event_id'] === $data['event_id']) {
                                continue;
                            }
                            $list[] = $events[$i]['event_id'];
                        }
                    }

                    info(print_r($list, true));

                    if (!empty($list)) {
                        $response['message']
                            = 'svc_addr_reuse';

                        if ($stop_call) {
                            Log::debug(
                                'In checkExistingServiceAddress() - stopping call'
                            );

                            $response['stop-call'] = true;
                        }
                        $response['conflicts'] = collect($events)->pluck('confirmation_code')->map(function ($item) {
                            return 'Confirmation Code: ' . $item;
                        })->toArray();

                        return true;
                    }
                }
            }
        }

        return false;
    }

    /*
    if this BTN was previously used for a good sale where in the previous call,
    the authorizing customer name was different than in the current TPV.
         * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
    */
    private function checkBtnPreviouslyUsedForMultipleCustomers(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkBtnPreviouslyUsedForMultipleCustomers()');

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkBtnPreviouslyUsedForMultipleCustomers. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkBtnPreviouslyUsedForMultipleCustomers. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        if (isset($data['phone']) && strlen(trim($data['phone'])) > 0) {
            if (isset($data['auth_name']['first'])) {
                $first_name = $data['auth_name']['first'];
            } elseif (isset($data['auth_name']['first_name'])) {
                $first_name = $data['auth_name']['first_name'];
            } elseif (isset($data['product']['auth_fname'])) {
                $first_name = $data['product']['auth_fname'];
            } else {
                $first_name = null;
            }

            if (isset($data['auth_name']['last'])) {
                $last_name = $data['auth_name']['last'];
            } elseif (isset($data['auth_name']['last_name'])) {
                $last_name = $data['auth_name']['last_name'];
            } elseif (isset($data['product']['auth_fname'])) {
                $last_name = $data['product']['auth_lname'];
            } else {
                $last_name = null;
            }

            $name = mb_strtolower($first_name . ' ' . $last_name);
            $phone = CleanPhoneNumber($data['phone']);

            $hasAltGoodsales = collect(DB::select(
                'select confirmation_code FROM (
                    select confirmation_code, CONCAT(LCASE(auth_first_name), " ", LCASE(auth_last_name)) as auth_name
                    FROM stats_product
                    WHERE
                        btn = ?
                        AND brand_id = ?
                        AND `result` = "Sale"
                ) as i
                WHERE auth_name <> ?
                LIMIT 10',
                [
                    $phone, $brand_id, $name
                ]
            ));

            if ($hasAltGoodsales->count() > 0) {
                Log::debug(
                    'In checkBtnPreviouslyUsedForMultipleCustomers() - alert triggered'
                );
                $response['message'] = 'cust_prev_enrolled';

                if ($stop_call) {
                    Log::debug(
                        'In checkBtnPreviouslyUsedForMultipleCustomers() - stopping call'
                    );

                    $response['stop-call'] = true;
                }
                $response['conflicts'] = $hasAltGoodsales->pluck('confirmation_code')->map(function ($item) {
                    return 'Confirmation Code: ' . $item;
                })->toArray();

                return true;
            }
        }

        return false;
    }

    /*
    if the BTN and Authorizing Name were used in more than three previous good sales.
         * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
    */
    private function checkBtnAndAuthorizingNamePreviouslyGoodSaled(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkBtnAndAuthorizingNamePreviouslyGoodSaled()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkBtnAndAuthorizingNamePreviouslyGoodSaled. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkBtnAndAuthorizingNamePreviouslyGoodSaled. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        if (isset($data['phone']) && strlen(trim($data['phone'])) > 0) {
            if (isset($data['auth_name']['first'])) {
                $first_name = $data['auth_name']['first'];
            } elseif (isset($data['product']['auth_fname'])) {
                $first_name = $data['product']['auth_fname'];
            } elseif (isset($data['auth_name']['first_name'])) {
                $first_name = $data['auth_name']['first_name'];
            } else {
                $first_name = null;
            }

            if (isset($data['auth_name']['last'])) {
                $last_name = $data['auth_name']['last'];
            } elseif (isset($data['product']['auth_fname'])) {
                $last_name = $data['product']['auth_lname'];
            } elseif (isset($data['auth_name']['last_name'])) {
                $last_name = $data['auth_name']['last_name'];
            } else {
                $last_name = null;
            }

            if ($first_name !== null && $last_name !== null) {
                $first_name = mb_strtolower($first_name);
                $last_name = mb_strtolower($last_name);

                $phone = CleanPhoneNumber($data['phone']);
                $events = StatsProduct::select(['confirmation_code', 'event_id'])
                    ->where('result', 'Sale')
                    ->where('brand_id', $brand_id)
                    ->where('btn', $phone)
                    ->whereNotNull('auth_first_name')
                    ->whereNotNull('auth_last_name')
                    ->whereRaw('LCASE(auth_first_name) <> ?', [$first_name])
                    ->whereRaw('LCASE(auth_last_name) <> ?', [$last_name]);

                if ($threshold > 0) {
                    $events = $events->whereRaw(
                        'event_created_at >= (CURDATE() - INTERVAL ' . $threshold . ' DAY)'
                    );
                }

                $events = $events->groupBy('event_id')
                    ->get();

                if ($events->count() > 0) {
                    $response['message']
                        = 'existing_acct';

                    if ($stop_call) {
                        Log::debug(
                            'In checkBtnAndAuthorizingNamePreviouslyGoodSaled() - stopping call'
                        );

                        $response['stop-call'] = true;
                    }
                    $response['conflicts'] = $events->pluck('confirmation_code')->map(function ($item) {
                        return 'Confirmation Code: ' . $item;
                    })->toArray();

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Lookup VOIP phone number.
     *
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkTemporaryOrVoipPhoneUsedBySalesAgent(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkTemporaryOrVoipPhoneUsedBySalesAgent()');
        Log::debug(json_encode($data));

        if (isset($data['channel']) && isset($data['phone']) && strlen(trim($data['phone'])) > 0) {
            $phone = CleanPhoneNumber($data['phone']);

            $lookup = null;
            if (isset($phone) && isset($brand_id) && $alertEnabled && in_array($data['channel'], $channels)) {
                // Only do this lookup if the alert is enabled
                $lookup = $this->_doVoipLookup($phone, $brand_id, false, $data);
            }

            if (!empty($lookup)) {
                if ('non-fixed' == $lookup->phone_number_subtype) {
                    $response['message']
                        = 'voip';

                    if ($stop_call) {
                        Log::debug(
                            'In checkTemporaryOrVoipPhoneUsedBySalesAgent() - stopping call'
                        );

                        $response['stop-call'] = true;
                    }

                    return true;
                } else {
                    info('checkTemporaryOrVoipPhoneUsedBySalesAgent - line is not voip', [$lookup]);
                }
            } else {
                info('checkTemporaryOrVoipPhoneUsedBySalesAgent - lookup not found');
            }
        } else {
            info('Did not run checkTemporaryOrVoipPhoneUsedBySalesAgent', [
                'channel' => isset($data['channel']),
                'phone' => isset($data['phone']),
                'phone_strlen' => isset($data['phone']) ? strlen(trim($data['phone'])) : 'not set'
            ]);
        }

        return false;
    }

    /**
     * Checks for a valid voip lookup and returns it or performs a new one.
     *
     * @param $phone - the phone number to check
     * @param $brand_id = the current brand
     * @param $phoneIsID - should the phone param be treated as a phone number or an id
     *
     * @return PhoneNumberVoipLookup|false
     */
    private function _doVoipLookup(string $phone, string $brand_id, bool $phoneIsID = false, $data = [])
    {
        $event_id = null;

        // CUST-INFO-PROVIDED category
        if (isset($data['event']) && !empty($data['event'])) {
            $event_id = $data['event'];
        } else {
            // DISPOSITIONED category
            if (isset($data['data']['event']) && !empty($data['data']['event'])) {
                $event_id = $data['data']['event'];
            }
        }

        $rphone = CleanPhoneNumber($phone);
        if ($phoneIsID) {
            info('Starting VOIP Lookup for phone number ID: ' . $phone);
            $pn = PhoneNumber::find($phone);
        } else {
            info('Starting VOIP Lookup for phone number: ' . $rphone);
            $pn = PhoneNumber::where(
                'phone_number',
                $rphone
            )->first();
        }

        //$rpvToken = config('services.rpv.token');

        if ($pn && $pn->id) {
            info('Checking for cached lookup for this number', [$phone]);
            $existing = PhoneNumberVoipLookup::where('phone_number_id', $pn->id)->first();
            if ($existing) {
                info('Existing record found for ' . $phone);
                BillingController::CreateInvoiceable(
                    $brand_id,
                    'Twilio::Carrier',
                    1,
                    $existing->id,
                    $event_id
                );
                info('Invoiceable created for existing record', [$phone]);
                return $existing;
            }
        }


        // do lookup with Twilio

        info('Doing lookup with Twilio', [$phone]);
        $phone_number = $this->_client->lookups->v1
            ->phoneNumbers($rphone)
            ->fetch(
                [
                    'addOns' => ['whitepages_pro_phone_intel'],
                    'type' => ['carrier', 'caller-name'],
                ]
            );

        if ($pn) {
            $pnvl = new PhoneNumberVoipLookup();
            $pnvl->phone_number_id = $pn->id;
            $pnvl->carrier = (isset($phone_number->carrier['name']))
                ? $phone_number->carrier['name']
                : null;
            $pnvl->phone_number_type = (isset($phone_number->carrier['type']))
                ? $phone_number->carrier['type']
                : null;

            $lineSubType = isset($phone_number->addOns['results']['whitepages_pro_phone_intel']['result']['line_type']) ? $phone_number->addOns['results']['whitepages_pro_phone_intel']['result']['line_type'] : null;
            if ($lineSubType == 'FixedVOIP') {
                $lineSubType = 'fixed';
            }
            if ($lineSubType == 'NonFixedVOIP') {
                $lineSubType = 'non-fixed';
            }
            $pnvl->phone_number_subtype = $lineSubType;

            $pnvl->phone_number_lookup_name = (isset($phone_number->callerName['caller_name']))
                ? $phone_number->callerName['caller_name']
                : null;
            $pnvl->response = json_encode($phone_number->toArray());
            $pnvl->save();

            BillingController::CreateInvoiceable(
                $brand_id,
                'Twilio::Carrier',
                1,
                $pnvl->id,
                $event_id
            );

            info('Lookup created (Twilio) and invoiceable written', [$phone]);

            return $pnvl;
        }


        return false;
    }

    /*
    if the TPV was No Saled for a defined list of fraud related reasons  i.e.
     * Agent Acted as Customer
     * Misrepresentation of Utility
     * Language Barrier
     * Not Authorized Decision Maker
     * Sales Rep Did Not Leave Premises
     *      * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
    */
    private function checkNoSaleAlert(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkNoSaleAlert()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkNoSaleAlert. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkNoSaleAlert. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        // Build the query but don't run it yet, in the event of brand specific logic
        $fraudDispositions = DB::table('dispositions')->select('id')
            ->where('brand_id', $brand_id)
            ->where('fraud_indicator', true)
            ->get()
            ->pluck('id')
            ->toArray();

        if (isset($data['event'])) {
            $frauds = DB::table('interactions')
                ->select('events.confirmation_code')
                ->join('events', 'interactions.event_id', 'events.id')
                ->where('interactions.event_id', $data['event'])
                ->where('interactions.event_result_id', 2)
                ->whereIn('events.channel_id', $channels)
                ->whereIn('interactions.disposition_id', $fraudDispositions);
        }

        // Log::debug('TOTAL FRAUDS: '.print_r($frauds, true));

        if (isset($data['event']) && $frauds->count() > 0) {
            if ($stop_call) {
                Log::debug(
                    'In checkNoSaleAlert() - stopping call'
                );

                $response['stop-call'] = true;
            }
            $response['conflicts'] = $frauds->pluck('confirmation_code')->map(function ($item) {
                return 'Confirmation Code: ' . $item;
            })->toArray();

            return true;
        }

        return false;
    }

    /**
     * If at least $threshold Good Sales exist today for the specified Sales Agent.
     * 
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkTooManySalesAlert(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkTooManySalesAlert()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkTooManySalesAlert. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkTooManySalesAlert. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        if (!isset($data['agent'])) {
            // No agent data, skipping alert...
            return false;
        }

        /*
        select count(*) from events as t1
        join interactions as t2 on t2.event_id = t1.id
        where sales_agent_id = '95B2B2B4-D9AC-4518-93D2-0E2B57DEC0D0'
        and t2.created_at >= '2018-06-29 04:00'
        and t2.event_result_id = 1
        */
        DB::enableQueryLog();
        $interactions = DB::table('events')
            ->select('events.confirmation_code')
            ->join('interactions', 'interactions.event_id', 'events.id')
            ->where('events.sales_agent_id', $data['agent'])
            ->where('interactions.event_result_id', 1)
            ->whereIn('events.channel_id', $channels)
            ->whereBetween(
                'interactions.created_at',
                [
                    Carbon::now('America/Chicago')->subMinutes(15),
                    Carbon::now('America/Chicago'),
                ]
            )->where('events.id', '<>', $data['event'])
            ->groupBy('events.confirmation_code')
            ->get();

        $log = \DB::getQueryLog()[0];
        $query = vsprintf(str_replace('?', '`%s`', $log['query']), $log['bindings']);
        info('checkTooManySalesAlert QUERY is: ' . $query);
        DB::disableQueryLog();

        if ($interactions->count() >= ($threshold > 0 ? $threshold : 2)) {
            $response['message'] = 'sales_limit';

            if ($stop_call) {
                Log::debug(
                    'In checkTooManySalesAlert() - stopping call'
                );

                $response['stop-call'] = true;
            }
            $response['conflicts'] = $interactions->pluck('confirmation_code')->map(function ($item) {
                return 'Confirmation Code: ' . $item;
            })->toArray();

            return true;
        }

        return false;
    }

    /*
    Sale made after 9 PM customer time
         * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
    */
    private function checkSalesRepSellingAfterLocalCurfew(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkSalesRepSellingAfterLocalCurfew()');
        Log::debug(json_encode($data));

        $ev = DB::table('events')
            ->whereIn('events.channel_id', $channels)
            ->where('id', $data['event'])
            ->get()
            ->first();
        if ($ev) {
            if (isset($ev->products) && count($ev->products) > 0) {
                $products = $ev->products;
                $addresses = $products[0]->addresses;
                if (count($addresses) > 0) {
                    try {
                        $state = State::where(
                            'state_abbrev',
                            $addresses[0]->address->state_province
                        )->first();
                        $state_id = $state->id;
                        $now = Carbon::now();
                        $today = $now->format('l');
                        $config = json_decode(
                            BrandHour::where(
                                'brand_id',
                                $brand_id
                            )->where(
                                'state_id',
                                $state_id
                            )->first()->data,
                            true
                        );

                        if ('Closed' === $config[$today]['closed']) {
                            $response['message'] = 'after_curfew';

                            if ($stop_call) {
                                Log::debug(
                                    'In checkSalesRepSellingAfterLocalCurfew() - stopping call'
                                );

                                $response['stop-call'] = true;
                            }

                            return true;
                        }

                        $ithreshold = intval((explode(':', $config[$today]['closed'])[0]));
                        $targetTime = ($ithreshold > 0 ? $ithreshold : 21);
                        $saleHour = (new Carbon($ev->created_at))->hour;
                        if ($saleHour >= $targetTime) {
                            $response['message'] = 'after_curfew';

                            if ($stop_call) {
                                Log::debug(
                                    'In checkSalesRepSellingAfterLocalCurfew() - stopping call'
                                );

                                $response['stop-call'] = true;
                            }

                            return true;
                        }
                    } catch (\Exception $e) {
                        Log::info('Error during curfew alert processing', $e);

                        return false;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check Account Number has been Good Sale.
     *
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkAccountNumberGoodSale(
        int    $alert_id,
        string $brand_id,
        int    $threshold,
        array  $channels,
        bool   $stop_call,
        array  $data,
        array  &$response,
        bool   $alertEnabled
    ): bool
    {

        Log::debug('In checkAccountNumberGoodSale()' . json_encode($data));

        // If channel value is missing, don't run alert
        if (!isset($data['channel'])) {
            info('Did not run checkAccountNumberGoodSale. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if (!in_array($data['channel'], $channels)) {
            info('Did not run checkAccountNumberGoodSale. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        if (!isset($data['product']['selection'])) { // No data to check, so return false to prevent this alert from triggering
            return false;
        }

        foreach ($data['product']['selection'] as $selection) {
            if (!isset($selection)) { // No data to check, skip this selection
                continue;
            }

            if (is_array($selection) && isset($selection[0])) {
                for ($i = 0; $i < count($selection); ++$i) {

                    if (!isset($selection[$i]['fuel_type'])) { // No fuel type set, skip this selection
                        continue;
                    }

                    $product_type = $selection[$i]['fuel_type'];
                    foreach ($selection[$i]['identifiers'] as $selection_identifier) {
                        if (isset($selection_identifier[0])) {
                            for ($x = 0; $x < count($selection_identifier); ++$x) {
                                $identifier = $selection_identifier[$x]['ident'];

                                $query = "
                                    SELECT event_id AS id, confirmation_code, btn AS phone_number
                                    FROM stats_product sp
                                    WHERE
                                        sp.account_number1 = :account_number1
                                ";

                                if ($threshold > 0) {
                                    $query .= "
                                        AND sp.created_at >= (CURDATE() - INTERVAL :threshold DAY) 
                                    ";
                                }

                                $query .= "
                                        AND sp.brand_id = :brand_id
                                        AND sp.result = :result
                                        AND sp.commodity_id = :product_type
                                    GROUP BY sp.btn, sp.event_id
                                ";

                                $parameters = [
                                    'account_number1' => $identifier,
                                    'brand_id' => $brand_id,
                                    'result' => 'Sale',
                                    'product_type' => $product_type,
                                ];

                                if ($threshold > 0) {
                                    $parameters['threshold'] = $threshold;
                                }

                                $results = DB::select(DB::raw($query), $parameters);
                            }
                        } else if (isset($selection_identifier['ident'])) {
                            $identifier = $selection_identifier['ident'];

                            $query = "
                                SELECT event_id AS id, confirmation_code, btn AS phone_number
                                FROM stats_product sp
                                WHERE
                                    sp.account_number1 = :account_number1
                            ";

                            if ($threshold > 0) {
                                $query .= "
                                    AND sp.created_at >= (CURDATE() - INTERVAL :threshold DAY)
                                ";
                            }

                            $query .= "
                                    AND sp.brand_id = :brand_id
                                    AND sp.result = :result
                                    AND sp.commodity_id = :product_type
                                GROUP BY sp.btn, sp.event_id
                            ";

                            $parameters = [
                                'account_number1' => $identifier,
                                'brand_id' => $brand_id,
                                'result' => 'Sale',
                                'product_type' => $product_type,
                            ];

                            if ($threshold > 0) {
                                $parameters['threshold'] = $threshold;
                            }

                            $results = DB::select(DB::raw($query), $parameters);

                            if (count($results) > 2) { // Alert should trigger only when we have at least 3 previous good sales
                                $response['message'] = 'account_number_good_sale';
                                $response['extra'] = [
                                    'identifier' => $identifier,
                                ];
                                $response['conflicts'] = collect($results)->pluck('confirmation_code')->map(function ($item) {
                                    return 'Confirmation Code: ' . $item;
                                })->toArray();

                                if ($stop_call) {
                                    Log::debug(
                                        'In checkAccountNumberGoodSale() - stopping call'
                                    );

                                    $response['stop-call'] = true;
                                }

                                return true;
                            }
                        }
                    }
                }
            } else {
                $keys = array_keys($selection);
                if (count($keys) === 1) {
                    $selection = $selection[$keys[0]];
                }

                $product_type = $selection['fuel_type'];
                foreach ($selection['identifiers'] as $selection_identifier) {
                    if (!isset($selection_identifier['ident'])) { // Identifier not set; skip record.
                        continue;
                    }

                    $identifier = $selection_identifier['ident'];

                    $query = "
                        SELECT
                            events.id AS event_id,
                            events.confirmation_code,
                            phone_numbers.phone_number
                        FROM event_product_identifiers
                        LEFT JOIN event_product ON (event_product_identifiers.event_product_id= event_product.id)
                        LEFT JOIN events ON (event_product.event_id = events.id)
                        LEFT JOIN interactions ON (interactions.event_id = events.id)
                        LEFT JOIN phone_number_lookup ON (phone_number_lookup.type_id = events.id AND phone_number_lookup.phone_number_type_id = 3)
                        LEFT JOIN phone_numbers ON (phone_number_lookup.phone_number_id = phone_numbers.id)
                        WHERE
                            event_product_identifiers.identifier = :identifier
                            AND event_product_identifiers.deleted_at IS NULL
                    ";

                    if ($threshold > 0) {
                        $query .= "
                            AND event_product_identifiers.created_at >= (CURDATE() - INTERVAL :threshold DAY)
                        ";
                    }

                    $query .= "
                            AND events.brand_id = :brand_id
                            AND interactions.deleted_at IS NULL
                            AND interactions.event_result_id = 1
                            AND event_product.event_type_id = :product_type
                        GROUP BY phone_numbers.phone_number, events.id
                    ";

                    $parameters = [
                        'account_number1' => $identifier,
                        'brand_id' => $brand_id,
                        'result' => 'Sale',
                        'product_type' => $product_type,
                    ];

                    if ($threshold > 0) {
                        $parameters['threshold'] = $threshold;
                    }

                    $results = DB::select(DB::raw($query), $parameters);

                    if (count($results) > 2) { // Alert should trigger only when we have at least 3 previous good sales
                        $response['message'] = 'account_number_good_sale';
                        $response['extra'] = [
                            'identifier' => $identifier,
                        ];
                        $response['conflicts'] = collect($results)->pluck('confirmation_code')->map(function ($item) {
                            return 'Confirmation Code: ' . $item;
                        })->toArray();

                        if ($stop_call) {
                            Log::debug(
                                'In checkAccountNumberGoodSale() - stopping call'
                            );

                            $response['stop-call'] = true;
                        }

                        return true;
                    }
                }
            }
        }


        // $conflicts = $results->pluck('confirmation_code')->map(function ($item) {
        //     return 'Confirmation Code: ' . $item;
        // })->toArray();

        // if (count($conflicts) > 2) {
        //     $response['message'] = 'account_number_good_sale';
        //     $response['extra'] = null;
        //     $response['conflicts'] = $conflicts;

        //     if ($stop_call) {
        //         Log::debug(
        //             'In checkAccountNumberGoodSale() - stopping call'
        //         );

        //         $response['stop-call'] = true;
        //     }

        //     return true;
        // }

        return false;
    }

    /**
     * Check Account Number against recent No Sale dispositions.
     *
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkAccountNumberNoSaleDispositions(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkAccountNumberNoSaleDispositions()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkAccountNumberNoSaleDispositions. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkAccountNumberNoSaleDispositions. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        $fraudDispositions = Disposition::select(
            'id'
        )->where(
            'brand_id',
            $brand_id
        )->where(
            'fraud_indicator',
            true
        )->get()->pluck('id')->toArray();

        //info(print_r($data, true));

        if (isset($data['product']['selection'])) {
            $idents = [];
            foreach ($data['product']['selection'] as $selection) {
                if (isset($selection)) {
                    if (is_array($selection) && isset($selection[0])) {
                        for ($i = 0; $i < count($selection); ++$i) {
                            if (isset($selection[$i]['fuel_type'])) {
                                if (isset($selection[$i]['identifiers'])) {
                                    foreach ($selection[$i]['identifiers'] as $selection_identifier) {
                                        if (isset($selection_identifier['identifier'])) {
                                            $idents[] = $selection_identifier['identifier'];
                                        }

                                        if (isset($selection_identifier['ident'])) {
                                            $idents[] = $selection_identifier['ident'];
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        if (isset($selection['identifiers'])) {
                            foreach ($selection['identifiers'] as $selection_identifier) {
                                if (isset($selection_identifier['identifier'])) {
                                    $idents[] = $selection_identifier['identifier'];
                                }

                                if (isset($selection_identifier['ident'])) {
                                    $idents[] = $selection_identifier['ident'];
                                }
                            }
                        }
                    }
                }
            }

            // info(print_r($idents, true));

            if (!empty($idents)) {
                $events = EventProduct::select(
                    'events.id AS event_id',
                    'events.confirmation_code',
                    'interactions.id as interaction_id',
                    'event_product_identifiers.identifier'
                )->leftJoin(
                    'events',
                    'event_product.event_id',
                    'events.id'
                )->leftJoin(
                    'interactions',
                    'events.id',
                    'interactions.event_id'
                )->leftJoin(
                    'event_product_identifiers',
                    'event_product_identifiers.event_product_id',
                    'event_product.id'
                )->whereNull(
                    'events.deleted_at'
                )->whereNull(
                    'event_product.deleted_at'
                )->where(
                    'events.brand_id',
                    $brand_id
                )->where(
                    'interactions.event_result_id',
                    2
                )->whereIn(
                    'event_product_identifiers.identifier',
                    $idents
                )->whereIn(
                    'interactions.disposition_id',
                    $fraudDispositions
                )->whereDate(
                    'events.created_at',
                    Carbon::now()->format('Y-m-d')
                )->groupBy('interactions.id')->get();

                if (count($events) > 2) {
                    $response['message'] = 'account_number_no_sale';
                    $response['extra'] = null;
                    $response['conflicts'] = $events->pluck('confirmation_code')->map(function ($item) {
                        return 'Confirmation Code: ' . $item;
                    })->toArray();

                    if ($stop_call) {
                        Log::debug(
                            'In checkAccountNumberNoSaleDispositions() - stopping call'
                        );

                        $response['stop-call'] = true;
                    }

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check BTN against recent No Sale dispositions.
     *
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkBtnNoSaleDispositions(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkBtnNoSaleDispositions()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkBtnNoSaleDispositions. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkBtnNoSaleDispositions. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        // Get the list of dispositions flagged as fraud indicators.
        $fraudDispositions = Disposition::select(
            'id'
        )->where(
            'brand_id',
            $brand_id
        )->where(
            'fraud_indicator',
            true
        )->get()->pluck('id')->toArray();

        // Retrieve the BTN from phone numbers table
        $btn = null;
        if (isset($data['phone']) && strlen(trim($data['phone'])) > 0) {
            $btn = CleanPhoneNumber($data['phone']);
        } else {
            //info('looking for btn based on event id');
            if (isset($data['event'])) {
                $phone = PhoneNumberLookup::select(
                    'phone_numbers.phone_number'
                )->leftJoin(
                    'phone_numbers',
                    'phone_number_lookup.phone_number_id',
                    'phone_numbers.id'
                )->where(
                    'phone_number_lookup.phone_number_type_id',
                    3
                )->where(
                    'phone_number_lookup.type_id',
                    $data['event']
                )->whereNull(
                    'phone_numbers.deleted_at'
                )->first();
                if ($phone) {
                    //info('found phone number for event', [$phone->phone_number]);
                    $btn = $phone->phone_number;
                }
            }
        }

        // info('btn is now', [$btn]);

        if (null !== $btn && is_string($btn) && strlen(trim($btn)) > 0) {
            $events = Event::select(
                'events.id AS event_id',
                'events.confirmation_code',
                'interactions.id as interaction_id'
            )->leftJoin(
                'interactions',
                'events.id',
                'interactions.event_id'
            )->leftJoin(
                'phone_number_lookup',
                function ($join) {
                    $join->on(
                        'events.id',
                        'phone_number_lookup.type_id'
                    )->where(
                        'phone_number_lookup.phone_number_type_id',
                        3
                    );
                }
            )->leftJoin(
                'phone_numbers',
                'phone_number_lookup.phone_number_id',
                'phone_numbers.id'
            )->whereNull(
                'events.deleted_at'
            )->where(
                'events.brand_id',
                $brand_id
            )->where(
                'interactions.event_result_id',
                2
            )->whereNull(
                'phone_numbers.deleted_at'
            )->where(
                'phone_numbers.phone_number',
                $btn
            )->whereIn(
                'interactions.disposition_id',
                $fraudDispositions
            );

            if ($threshold > 0) {
                //info('threshold defined as '.$threshold);
                $events = $events->whereRaw(
                    'events.created_at >= (CURDATE() - INTERVAL '
                        . $threshold . ' DAY)'
                );
            }

            $events = $events->get();

            //info('got results of', [$events]);

            if (count($events) > 2) {
                $response['message'] = 'btn_no_sale_dispositions';
                $response['extra'] = null;
                $response['conflicts'] = $events->pluck('confirmation_code')->map(function ($item) {
                    return 'Confirmation Code: ' . $item;
                })->toArray();

                if ($stop_call) {
                    Log::debug(
                        'In checkBtnNoSaleDispositions() - stopping call'
                    );

                    $response['stop-call'] = true;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * If an authorizing name and BTN appear in a previous good saled TPV, but with a different account number.
     * 
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkCustomerTpvedMultipleTimes(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkCustomerTpvedMultipleTimes()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkCustomerTpvedMultipleTimes. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkCustomerTpvedMultipleTimes. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        if (isset($data['auth_name']['first'])) {
            $first_name = $data['auth_name']['first'];
        } elseif (isset($data['auth_name']['first_name'])) {
            $first_name = $data['auth_name']['first_name'];
        } elseif (isset($data['product']['auth_fname'])) {
            $first_name = $data['product']['auth_fname'];
        } else {
            $first_name = null;
        }

        if (isset($data['auth_name']['last'])) {
            $last_name = $data['auth_name']['last'];
        } elseif (isset($data['auth_name']['last_name'])) {
            $last_name = $data['auth_name']['last_name'];
        } elseif (isset($data['product']['auth_lname'])) {
            $last_name = $data['product']['auth_lname'];
        } else {
            $last_name = null;
        }

        if (
            isset($data['product']['selection'])
            && strlen(trim($first_name)) > 0
            && strlen(trim($last_name)) > 0
        ) {
            foreach ($data['product']['selection'] as $selection) {
                if (isset($selection)) {
                    if (is_array($selection) && isset($selection[0])) {
                        info('selection is an array with at least one element');
                        for ($i = 0; $i < count($selection); ++$i) {
                            if (isset($selection[$i]['fuel_type'])) {
                                $product_type = $selection[$i]['fuel_type'];
                                foreach ($selection[$i]['identifiers'] as $selection_identifier) {
                                    if (isset($selection_identifier['ident'])) {
                                        $identifier = $selection_identifier['ident'];
                                    }
                                }

                                if (isset($identifier) && strlen(trim($identifier)) > 0) {
                                    $phone = CleanPhoneNumber($data['phone']);

                                    $events = EventProduct::select(
                                        'events.id AS event_id',
                                        'events.confirmation_code',
                                        'interactions.id as interaction_id',
                                        'event_product_identifiers.identifier'
                                    )->leftJoin(
                                        'events',
                                        'event_product.event_id',
                                        'events.id'
                                    )->leftJoin(
                                        'interactions',
                                        'events.id',
                                        'interactions.event_id'
                                    )->leftJoin(
                                        'phone_number_lookup',
                                        function ($join) {
                                            $join->on(
                                                'events.id',
                                                'phone_number_lookup.type_id'
                                            )->where(
                                                'phone_number_lookup.phone_number_type_id',
                                                3
                                            );
                                        }
                                    )->leftJoin(
                                        'phone_numbers',
                                        'phone_number_lookup.phone_number_id',
                                        'phone_numbers.id'
                                    )->leftJoin(
                                        'event_product_identifiers',
                                        'event_product_identifiers.event_product_id',
                                        'event_product.id'
                                    )->whereNull(
                                        'events.deleted_at'
                                    )->whereNull(
                                        'phone_numbers.deleted_at'
                                    )->whereNull(
                                        'phone_number_lookup.deleted_at'
                                    )->whereNull(
                                        'event_product.deleted_at'
                                    )->where(
                                        function ($query) use ($data) {
                                            if (isset($data['event'])) {
                                                $query->where(
                                                    'events.id',
                                                    '!=',
                                                    $data['event']
                                                );
                                            }
                                        }
                                    )->where(
                                        'events.brand_id',
                                        $brand_id
                                    )->where(
                                        'phone_numbers.phone_number',
                                        $phone
                                    )->where(
                                        'interactions.event_result_id',
                                        1
                                    )->whereNotNull(
                                        'event_product.auth_first_name'
                                    )->whereNotNull(
                                        'event_product.auth_last_name'
                                    )->where(
                                        'event_product.auth_first_name',
                                        $first_name
                                    )->where(
                                        'event_product.auth_last_name',
                                        $last_name
                                    )->where(
                                        'event_product_identifiers.identifier',
                                        '!=',
                                        $identifier
                                    );

                                    if ($threshold !== null && $threshold > 0) {
                                        $events = $events->whereRaw(
                                            'events.created_at >= (CURDATE() - INTERVAL '
                                                . $threshold . ' DAY)'
                                        );
                                    }

                                    $events = $events->get();

                                    if (count($events) > 0) {
                                        $response['message'] = 'multi_tpv';
                                        $response['extra'] = ['identifier' => $identifier];
                                        $response['conflicts'] = $events->pluck('confirmation_code')->map(function ($item) {
                                            return 'Confirmation Code: ' . $item;
                                        })->toArray();

                                        if ($stop_call) {
                                            Log::debug(
                                                'In checkCustomerTpvedMultipleTimes() - stopping call'
                                            );

                                            $response['stop-call'] = $stop_call;
                                        }

                                        return true;
                                    }
                                }
                            }
                        }
                    } else {
                        info('alternat selection path');
                        $keys = array_keys($selection);
                        if (count($keys) === 1) {
                            $selection = $selection[$keys[0]];
                        }
                        $product_type = $selection['fuel_type'];
                        foreach ($selection['identifiers'] as $selection_identifier) {
                            if (isset($selection_identifier['ident'])) {
                                $identifier = $selection_identifier['ident'];
                            }
                        }

                        if (isset($identifier)) {
                            info('ident is set');
                            $phone = CleanPhoneNumber($data['phone']);

                            $events = EventProduct::select(
                                'events.id AS event_id',
                                'events.confirmation_code',
                                'interactions.id as interaction_id',
                                'event_product_identifiers.identifier'
                            )->leftJoin(
                                'events',
                                'event_product.event_id',
                                'events.id'
                            )->leftJoin(
                                'interactions',
                                'events.id',
                                'interactions.event_id'
                            )->leftJoin(
                                'phone_number_lookup',
                                function ($join) {
                                    $join->on(
                                        'events.id',
                                        'phone_number_lookup.type_id'
                                    )->where(
                                        'phone_number_lookup.phone_number_type_id',
                                        3
                                    );
                                }
                            )->leftJoin(
                                'phone_numbers',
                                'phone_number_lookup.phone_number_id',
                                'phone_numbers.id'
                            )->leftJoin(
                                'event_product_identifiers',
                                'event_product_identifiers.event_product_id',
                                'event_product.id'
                            )->whereNull(
                                'events.deleted_at'
                            )->whereNull(
                                'phone_numbers.deleted_at'
                            )->whereNull(
                                'phone_number_lookup.deleted_at'
                            )->whereNull(
                                'event_product.deleted_at'
                            )->where(
                                'events.id',
                                '!=',
                                $data['event']
                            )->where(
                                'events.brand_id',
                                $brand_id
                            )->where(
                                'phone_numbers.phone_number',
                                $phone
                            )->where(
                                'interactions.event_result_id',
                                1
                            )->whereNotNull(
                                'event_product.auth_first_name'
                            )->whereNotNull(
                                'event_product.auth_last_name'
                            )->where(
                                'event_product.auth_first_name',
                                $first_name
                            )->where(
                                'event_product.auth_last_name',
                                $last_name
                            )->where(
                                'event_product_identifiers.identifier',
                                '!=',
                                $identifier
                            );

                            if ($threshold > 0) {
                                $events = $events->whereRaw(
                                    'events.created_at >= (CURDATE() - INTERVAL '
                                        . $threshold . ' DAY)'
                                );
                            }

                            $events = $events->get();

                            if (count($events) > 0) {
                                $response['message'] = 'multi_tpv';
                                $response['extra'] = ['identifier' => $identifier];
                                $response['conflicts'] = $events->pluck('confirmation_code')->map(function ($item) {
                                    return 'Confirmation Code: ' . $item;
                                })->toArray();

                                if ($stop_call) {
                                    Log::debug(
                                        'In checkCustomerTpvedMultipleTimes() - stopping call'
                                    );

                                    $response['stop-call'] = $stop_call;
                                }

                                return true;
                            }
                        } else {
                            info('identifier is not set');
                        }
                    }
                } else {
                    info('selection is not set');
                }
            }
        } else {
            info('malformed product data');
        }

        return false;
    }

    /**
     * If the customer's BTN was used in a no saled TPV.
     * 
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkBtnUsedInPreviousNoSales(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkBtnUsedInPreviousNoSales()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkBtnUsedInPreviousNoSales. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }
        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkBtnUsedInPreviousNoSales. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        if (isset($data['phone'])) {
            $phone = CleanPhoneNumber($data['phone']);

            $events = DB::table('events as e')
                ->join('phone_number_lookup as pnl', 'e.id', '=', 'pnl.type_id')
                ->join('phone_numbers as pn', 'pnl.phone_number_id', '=', 'pn.id')
                ->where('e.brand_id', $brand_id)
                ->where('pn.phone_number', $phone)
                ->where('pnl.phone_number_type_id', 3)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('interactions as i')
                        ->whereRaw('i.event_id = e.id')
                        ->where('i.event_result_id', 1);
                })
                ->select('e.confirmation_code', 'e.id');

            if ($threshold > 0) {
                $events = $events->whereRaw(
                    'e.created_at >= (CURDATE() - INTERVAL '
                        . $threshold . ' DAY)'
                );
            }

            $events = $events->whereNull('e.deleted_at')
                ->get();

            // Filter events that are not the same as the current event
            $events = $events->filter(function ($event) use ($data) {
                return $event->id !== $data['event'];
            });

            if ($events->count() > 0) {
                $response['message']
                    = 'btn_no_sale';
                $response['conflicts'] = $events->pluck('confirmation_code')->map(function ($item) {
                    return 'Confirmation Code: ' . $item;
                })->toArray();

                if ($stop_call) {
                    Log::debug(
                        'In checkBtnUsedInPreviousNoSales() - stopping call'
                    );

                    $response['stop-call'] = true;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * If the customer’s BTN was used in a no saled TPV.
     * 
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    private function checkEmailUsedInPreviousGoodSaleDiffNames(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkEmailUsedInPreviousGoodSaleDiffNames()');
        Log::debug(json_encode($data));

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkEmailUsedInPreviousGoodSaleDiffNames. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkEmailUsedInPreviousGoodSaleDiffNames. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        if (!empty($data['email'])) {
            if (isset($data['auth_name']['first'])) {
                $first_name = $data['auth_name']['first'];
            } elseif (isset($data['auth_name']['first_name'])) {
                $first_name = $data['auth_name']['first_name'];
            } elseif (isset($data['product']['auth_fname'])) {
                $first_name = $data['product']['auth_fname'];
            } else {
                $first_name = null;
            }

            if (isset($data['auth_name']['last'])) {
                $last_name = $data['auth_name']['last'];
            } elseif (isset($data['auth_name']['last_name'])) {
                $last_name = $data['auth_name']['last_name'];
            } elseif (isset($data['product']['auth_fname'])) {
                $last_name = $data['product']['auth_lname'];
            } else {
                $last_name = null;
            }

            $name = mb_strtolower($first_name . ' ' . $last_name);

            $hasAltGoodsales = DB::table('email_addresses')
                ->select('events.confirmation_code', 'event_product.auth_first_name', 'event_product.auth_last_name')
                ->join(
                    'email_address_lookup',
                    'email_address_lookup.email_address_id',
                    'email_addresses.id'
                )
                ->join('events', 'events.id', 'email_address_lookup.type_id')
                ->join('interactions', 'interactions.event_id', 'events.id')
                ->join('event_product', 'event_product.event_id', 'events.id')
                ->where('email_addresses.email_address', $data['email'])
                ->where('events.brand_id', $brand_id)
                ->whereIn('events.channel_id', $channels)
                /*->where(
                    'event_product.auth_first_name',
                    '<>',
                    $first_name
                )
                ->where(
                    'event_product.auth_last_name',
                    '<>',
                    $last_name
                )*/
                ->where('interactions.event_result_id', 1)
                ->get()
                ->filter(function ($item) use ($name) {
                    $name2 = mb_strtolower($item->auth_first_name . ' ' . $item->auth_last_name);

                    return $name !== $name2;
                });

            if ($hasAltGoodsales->count() > 0) {
                $response['message'] = 'cust_email_prev_enrolled';
                $response['conflicts'] = $hasAltGoodsales->pluck('confirmation_code')->map(function ($item) {
                    return 'Confirmation Code: ' . $item;
                })->toArray();
                if ($stop_call) {
                    Log::debug(
                        'In checkEmailUsedInPreviousGoodSaleDiffNames() - stopping call'
                    );

                    $response['stop-call'] = true;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Sends an SMS to the Agent after a event is disposition and includes no sale info if needed.
     *
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    public function checkAgentSmsWithDisposition(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In checkAgentSmsWithDisposition()');

        // If channel value is missing, don't run alert
        if(!isset($data['channel'])) {
            info('Did not run checkAgentSmsWithDisposition. Missing channel property in $data');

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if channel is configure for this alert
        if(!in_array($data['channel'], $channels)) {
            info('Did not run checkAgentSmsWithDisposition. Channel is not configured for alert.', [
                'channel' => $data['channel'],
                'configure_channels' => $channels
            ]);

            return false; // Return false to prevent TPV from being flagged
        }

        // Check if SMS alert is enabled
        $sendSMS = DB::table('brand_client_alerts')
            ->where('brand_id', $brand_id)
            ->where('client_alert_id', $alert_id)
            ->where('status', 1)
            ->first();

        if (null !== $sendSMS) {
            // this client has enabled agent sms alerts
            Log::debug('SMS enabled');
            Log::debug(json_encode($data));
            $msgText = null;
            $confCode = DB::table('events')->select('confirmation_code')
                ->whereIn('events.channel_id', $channels)
                ->where('id', $data['event'])
                ->first();

            if ($confCode) {
                $confCode = $confCode->confirmation_code;
                if (isset($data['result'])) {
                    switch ($data['result']) {
                        case 1: //good sale
                            $msgText = 'Verification ' . $confCode . ' is complete. Reply STOP to unsubscribe.';
                            break;
                        case 2: //no sale
                            $dispo = DB::table('dispositions')->select('reason')
                                ->where('id', $data['disposition'])->first();
                            $reason = 'Invalid Disposition: ' . $data['disposition'];
                            if ($dispo) {
                                $reason = $dispo->reason;
                            }
                            $msgText = 'Verification ' . $confCode . ' rejected: '
                                . $reason . '. Reply STOP to unsubscribe.';
                            break;
                    }
                }

                if (null !== $msgText) {
                    try {
                        if (isset($data['calledFrom'])) {
                            $ret = SendSMS($data['calledFrom'], $data['calledInto'], $msgText, null, $brand_id, 2);
                            if (strpos($ret, 'ERROR') !== false) {
                                Log::error('Could not send SMS notification. ' . $ret);
                            } else {
                                BillingController::CreateInvoiceable(
                                    $brand_id,
                                    'Twilio::SMS',
                                    1,
                                    $confCode
                                );
                                Log::debug(
                                    'SMS Sent to ' . $data['calledFrom'] . ' from '
                                        . $data['calledInto'] . ' :: ' . $msgText
                                );
                            }
                        }
                    } catch (\Exception $e) {
                        Log::debug('SMS Not Sent');
                        Log::error($e);
                    }
                } else {
                    Log::debug('SMS Not Sent');
                }
            }
        } else {
            Log::debug('SMS disabled');
        }

        return false;
    }

    /**
     * Get a list of all good sales for a specific agent on a specific day
     * Lookup all interactions that were call_inbound AND have a notes field with an ANI (calledFrom).
     *
     * Count the number of voip numbers used for the good sales ^
     * - If >= 3, alert
     *
     * @param int    $alert_id
     * @param string $brand_id
     * @param int    $threshold
     * @param array  $channels
     * @param bool   $stop_call
     * @param array  $data
     * @param array  $response
     * @param bool   $alertEnabled
     *
     * @return bool
     */
    public function checkHasMultipleVoipUsagesToday(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        info('In checkHasMultipleVoipUsagesToday');
        $phones = [];
        if (
            isset($data['result'])
            && $data['result'] == 1
        ) {
            // Check current number first
            if ($alertEnabled && !empty($data['calledFrom'])) {
                $lookup = $this->_doVoipLookup($data['calledFrom'], $brand_id, false, $data);
                if (!empty($lookup) && 'non-fixed' == $lookup->phone_number_subtype) {
                    $ev = Event::find($data['event']);
                    if ($ev) {
                        $phones[] = $ev->confirmation_code;
                    }
                }
            }

            // Lookup other good sales for this agent today
            $today = new Carbon('today', 'America/Chicago');
            $sps = StatsProduct::where(
                'stats_product.sales_agent_id',
                $data['agent']
            )->whereDate(
                'stats_product.event_created_at',
                $today
            )->where(
                'stats_product.result',
                'Sale'
            )->groupBy(
                'stats_product.event_id'
            )->pluck('event_id');
            if ($sps) {
                info('found stats product');
                // We only care about incoming call interactions for this check
                $interactions = Interaction::leftJoin(
                    'events',
                    'interactions.event_id',
                    'events.id'
                )->whereIn(
                    'interactions.event_id',
                    $sps->toArray()
                )->where(
                    'interactions.interaction_type_id',
                    1
                )->get();
                if ($interactions) {
                    info('found interactions');

                    foreach ($interactions as $interaction) {
                        if (isset($interaction->notes) && is_string($interaction->notes)) {
                            info('interaction has notes');
                            $interaction->notes = json_decode(
                                stripslashes(
                                    rtrim(
                                        ltrim(
                                            $interaction->notes,
                                            '"'
                                        ),
                                        '"'
                                    )
                                ),
                                true
                            );
                        }

                        // info(print_r($interaction->toArray(), true));

                        $lookup = null;

                        if ($alertEnabled && isset($interaction->notes['ani'])) {
                            // only do this lookup if the alert is enabled
                            info('doing voip lookup');
                            $lookup = $this->_doVoipLookup(
                                $interaction->notes['ani'],
                                $interaction->brand_id,
                                false,
                                $data
                            );
                        } else {
                            info('not doing voip lookup');
                        }

                        $isVoip = false;
                        if (!empty($lookup) && 'non-fixed' == $lookup->phone_number_subtype) {
                            $isVoip = true;
                        }

                        if ($isVoip) {
                            $phones[] = $interaction->confirmation_code;
                        }
                    }

                    info('checkHasMultipleVoipUsagesToday');
                    // info(print_r($phones, true));

                    $phones = array_unique($phones);

                    // info(print_r($phones, true));

                    if (count($phones) >= 2) {
                        $response['conflicts'] = $phones;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    /* End Section: alert checks */


    // Omar Rodriguez ticket 2024-02-12-92477
    private function is_valid_for_genie_api($selection_identifier,$utility,$selection):bool 
    {
        if (!empty($selection_identifier['utility_account_number_type_id']) && $selection_identifier['utility_account_number_type_id'] == '1'
            && (!empty($selection_identifier['ident']) || !empty($selection_identifier['identifier']))) {
                return true;
        }
        if ($utility->utility_label === "PGW" && isset($selection['fuel_type']) && $selection['fuel_type'] == 2
            && isset($selection['identifiers']) && count($selection['identifiers']) == 2) {
                return true;
        }

        return false;
    }
    /* Section: Brand Specific Alert Checks */

    private function genie_customer_eligibility_check(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In genie_customer_eligibility_check', [$data]);
        $servicetype = ServiceType::where('name', 'Genie Customer Eligibility Check API')->first();
        $brand = Brand::find($brand_id);
        $integration = ProviderIntegration::where('brand_id', $brand_id)
            ->where('provider_integration_type_id', 2)
            ->where('service_type_id', $servicetype->id)
            ->where('env_id', config('app.env') === 'production' ? 1 : 2)
            ->first();

        $http = new GuzzleClient([
            'base_uri' => $integration->hostname,
        ]);

        $bearer = Cache::remember('genie-retail-bearer', 86398, function () use ($integration, $http) {
            $response = $http->request('GET', 'token', [
                'form_params' => [
                    'username' => $integration->username,
                    'password' => $integration->password,
                    'grant_type' => 'password',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $jres = json_decode($response->getBody()->getContents(), true);
                if (isset($jres['access_token'])) {
                    return $jres['access_token'];
                }
            } else {
                info('Unable to get Genie bearer token', [
                    'status' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'body' => $response->getBody()->getContents(),
                ]);
            }

            return false;
        });

        if ($bearer === false) {
            info('genie_eligibility_check: did not receive bearer token from genie api');
            return false;
        }

        $opts = [
            'headers' => [
                'Content-type' => 'application/json',
                'Authorization' => 'Bearer ' . $bearer,
            ],
        ];

        $buId = $data['agent'];
        $bu = BrandUser::find($buId);
        $agentId = null;
        if ($bu !== null) {
            $agentId = $bu->tsr_id;
        } else {
            $agentId = request()->agentId;
        }

        // $validAccountIdentifierTypes = Cache::remember(
        //     'primary-utility-account-types',
        //     60, // 1 minute
        //     function () {
        //         return UtilityAccountType::where('utility_account_number_type_id', 1)
        //             ->select('id')
        //             ->get()
        //             ->pluck('id')
        //             ->toArray();
        //     }
        // );

        $phone = $data['phone'];
        if ($agentId !== null && !empty($data['product']['selection'])) {
            info('continuing genie_customer_eligibility_check');
            $event = Event::find($data['event']);
            if ($event !== null) {
                if (!in_array($event->channel_id, $channels)) {
                    info('genie_customer_eligibility_check - channel check failed');
                    return false;
                }
            }
            info('starting genie_customer_eligibility_check api check');
            foreach ($data['product']['selection'] as $utilityId => $udata) {
                $utility = BrandUtility::where('utility_id', $utilityId)->where('brand_id', $brand_id)->first();

                if ($utility !== null && $utility->utility_label !== null) {
                    info('genie_customer_eligibility_checkgot utility');
                    $kntForLoop = 0;
                    $saveIdentifier = "";
                    foreach ($udata as $selection) {
                        if (is_array($selection) && !empty($selection['fuel_type'])) {
                            info('genie_customer_eligibility_check fuel type set', $selection);
                            foreach ($selection['identifiers'] as $selection_identifier) {

                                // Omar Rodriguez ticket 2024-02-12-92477
                                if ($this->is_valid_for_genie_api($selection_identifier,$utility,$selection)) {

                                    try {
                                        $kntForLoop++;
                                        if (!empty($selection_identifier['ident'])) {
                                            $identifier = $selection_identifier['ident'];
                                        } else {
                                            $identifier = $selection_identifier['identifier'];
                                        }
                                        if ($utility->utility_label === "WMECO" || $utility->utility_label === "NSTAR") {
                                            $identifier = substr($identifier,0,11);  // first 11 digits
                                        }
                                        if ($utility->utility_label === "PGW" && $selection['fuel_type'] == 2 && count($selection['identifiers']) == 2) { // special logic for api lookup
                                            if ($kntForLoop == 1) {
                                                $saveIdentifier = $identifier . '-';
                                                continue;
                                            }
                                            $callData = [
                                                'Commodity' => $selection['fuel_type'] == 1 ? 'E' : 'G',
                                                'AccountNumber' => ($saveIdentifier . $identifier),
                                                'UtilityCode' => $utility->utility_label,
                                            ];
                                        } else {
                                                $callData = [
                                                'Commodity' => $selection['fuel_type'] == 1 ? 'E' : 'G',
                                                'AccountNumber' => $identifier,
                                                'UtilityCode' => $utility->utility_label,
                                            ];
                                        }
                                        $jd = new JsonDocument();
                                        $jd->document_type = 'Genie Eligibility Check';
                                        $jd->ref_id = $data['event'];
                                        $jd->document = ['request' => $callData];
                                        $jd->save();
                                        $callData['RequestId'] = $jd->id;

                                        if (function_exists('hrtime')) {
                                            $start = hrtime(true);
                                        } else {
                                            $start = microtime(true);
                                        }
                                        $buildURI = 'TpvCompany/Enrollment/AccountValidate/' . $callData['AccountNumber'] . '/' . $callData['UtilityCode'] . '/' . $callData['Commodity'];
                                        $hresponse = $http->request('GET', $buildURI, $opts);
                                        if (function_exists('hrtime')) {
                                            $end = hrtime(true);
                                        } else {
                                            $end = microtime(true);
                                        }
                                        if ($hresponse->getStatusCode() === 200) {
                                            $res = json_decode($hresponse->getBody(), true);

                                            $jd->document = [
                                                'request' => $callData,
                                                'response' => $res,
                                                'response-time' => ($end - $start),
                                            ];
                                            $jd->save();
                                            if ($res['CanEnroll'] === false) {
                                                $response['extra'] = [
                                                    'msg' => $res['Description'],
                                                    'fuel' => ($selection['fuel_type'] == 1)
                                                        ? 'Electric'
                                                        : 'Gas',
                                                    'cx_service_number' => ($brand && $brand->service_number)
                                                        ? $brand->service_number
                                                        : null,
                                                ];
                                                $response['message'] = 'genie_active_api_fail';
                                                $response['conflicts'] = [$res['Details']];
                                                $response['request_id'] = $jd->id;
                                                if ($stop_call) {
                                                    Log::debug(
                                                        'In genie_customer_eligibility_check() - stopping call'
                                                    );

                                                    $response['stop-call'] = true;
                                                }

                                                return true;
                                            }
                                        } else {
                                            $jd->document = [
                                                'request' => $callData,
                                                'response' => $hresponse->getBody(),
                                                'response-code' => $hresponse->getStatusCode(),
                                                'response-time' => ($end - $start),
                                            ];
                                            $jd->save();
                                            info('Error during Genie API: ' . $hresponse->getStatusCode(), [$hresponse->getBody()]);
                                        }
                                    } catch (\Exception $e) {
                                        info('genie_eligibility_check: Error during Genie API (exception)', ['message' => $e->getMessage(), 'code' => $e->getCode(), 'line' => $e->getLine(), 'file' => $e->getFile(), 'trace' => $e->getTraceAsString()]);
                                    }
                                } else {
                                    info('genie_eligibility_check: no ident set on selection identifier', [$selection_identifier]);
                                }
                            }
                        } else {
                            info('genie_eligibility_check: fuel type not set on selection', [$selection]);
                        }
                    }
                } else {
                    info('genie_eligibility_check: brand utility not found');
                }
            }
        }
        info('genie_customer_eligibility_check - did not process record');
        return false;
    }

    private function indraDncApi($callData, $jd_id, $channel_id, $http, $opts)
    {
        $callData['RequestId'] = $jd_id;
        $opts['json'] = $callData;
        info('Calling Indra API with', $opts);

        if ($channel_id === 2) {
            return $http->request('POST', 'validate', $opts);
        }

        return $http->request('POST', 'validate/dtd', $opts);
    }

    private function indra_active_dnc_api_check(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In indra_active_dnc_api_check()', [$data]);
        Log::debug(print_r(request()->all(), true));

        $source = mb_strtoupper(request()->input('source'));

        if ($source === 'EZTPV' || request()->input('mode') === 'check') {
            info('source is eztpv, running', $data);
            $servicetype = ServiceType::where('name', 'Indra Active/DNC API')->first();

            $integration = ProviderIntegration::where('brand_id', $brand_id)
                ->where('provider_integration_type_id', 2)
                ->where('service_type_id', $servicetype->id)
                ->where('env_id', config('app.env') === 'production' ? 1 : 2)
                ->first();

            $http = new GuzzleClient([
                'base_uri' => $integration->hostname,
            ]);
            info('base-uri => ' . $integration->hostname);
            $opts = [
                'headers' => [
                    'Content-type' => 'application/json',
                    'api-key' => $integration->password,
                ]
            ];

            $buId = $data['agent'];
            $bu = BrandUser::find($buId);
            $agentId = null;
            if ($bu !== null) {
                $agentId = $bu->tsr_id;
            } else {
                $agentId = request()->agentId;
            }

            if (
                $opts['headers']['api-key'] !== null
                && $agentId !== null
                && isset($data['product']['selection'])
            ) {
                info('starting indra api check');
                $event = Event::find($data['event']);
                if ($event !== null) {
                    info('event found');
                    if (!in_array($event->channel_id, $channels)) {
                        info('event channel is not in configured channel list');
                        return false;
                    }
                }

                foreach ($data['product']['selection'] as $utilityId => $udata_array) {
                    foreach ($udata_array as $udata) {
                        if (empty($udata)) {
                            continue;
                        }
                        $utility = Utility::find($utilityId);
                        if (!$utility) {
                            Log::debug('Unable to find a utility for ' . $utilityId);
                            return false;
                        }

                        info('Utility is ' . $utility->name);

                        $sendMeter = [
                            // 'Philadelphia Gas Works',
                            // 'Western Mass Electric Company',
                        ];

                        if (in_array($utility->name, $sendMeter)) {
                            $account_number = null;
                            $account_number2 = null;
                            $external_id = null;

                            foreach ($udata['identifiers'] as $selection_identifier) {
                                if (!isset($external_id)) {
                                    $busf = BrandUtilitySupportedFuel::where(
                                        'utility_supported_fuel_id',
                                        $udata['fuel_id']
                                    )->where(
                                        'utility_id',
                                        $utility->id
                                    )->where(
                                        'brand_id',
                                        $brand_id
                                    )->first();
                                    if ($busf && $busf->external_id) {
                                        $external_id = $busf->external_id;
                                    }
                                }

                                switch ($selection_identifier['utility_account_type']['utility_account_number_type_id']) {
                                    case 3:
                                        // Do nothing for Name Key
                                        break;
                                    case 2:
                                        $account_number2 = (isset($selection_identifier['identifier']))
                                            ? $selection_identifier['identifier']
                                            : $selection_identifier['ident'];
                                        break;
                                    default:
                                        $account_number = (isset($selection_identifier['identifier']))
                                            ? $selection_identifier['identifier']
                                            : $selection_identifier['ident'];
                                        break;
                                }
                            }

                            try {
                                $start = microtime(true);

                                $callData = [
                                    'AgentId' => $agentId,
                                    'PhoneNumber' => str_replace('+1', '', CleanPhoneNumber($data['phone'])),
                                    'AccountNumber' => $account_number,
                                    'MeterNumber' => $account_number2,
                                    'TerritoryCode' => $busf->external_id,
                                ];

                                Log::debug(print_r($callData, true));

                                $jd = new JsonDocument();
                                $jd->document_type = 'Indra Active API';
                                $jd->ref_id = $data['event'];
                                $jd->document = $callData;
                                $jd->save();

                                $hresponse = $this->indraDncApi(
                                    $callData,
                                    $jd->id,
                                    $event->channel_id,
                                    $http,
                                    $opts
                                );
                                $end = microtime(true);

                                Log::debug(
                                    'Indra Active API Response in ' . (($end - $start) / 60) . ' sec(s)'
                                );

                                if ($hresponse->getStatusCode() === 200) {
                                    $res = json_decode($hresponse->getBody(), true);
                                    info('Indra API Response', $res);

                                    $opts['Body'] = $callData;

                                    $jd->document = [
                                        'request' => $opts,
                                        'response' => $res,
                                        'response-time' => ($end - $start),
                                    ];
                                    $jd->save();
                                    if ($res['Status'] === 'Fail') {
                                        $response['extra'] = ['msg' => $res['Detail']];
                                        $response['message'] = 'indra_active_api_fail';
                                        $response['conflicts'] = [$res['Detail']];
                                        $response['request_id'] = $jd->id;
                                        if ($stop_call) {
                                            Log::debug(
                                                'In indra_active_dnc_api_check() - stopping call'
                                            );

                                            $response['stop-call'] = true;
                                        }

                                        return true;
                                    }
                                } else {
                                    info(
                                        'Error during Indra API: ' . $hresponse->getStatusCode(),
                                        [
                                            $hresponse->getBody()
                                        ]
                                    );
                                }
                            } catch (\Exception $e) {
                                info(
                                    'Error during Indra API (exception)',
                                    [
                                        'message' => $e->getMessage(),
                                        'code' => $e->getCode(),
                                        'line' => $e->getLine(),
                                        'file' => $e->getFile(),
                                        'trace' => $e->getTraceAsString()
                                    ]
                                );
                            }
                        } else {

                            $identifiers = $udata['identifiers']; // Store original array, in case we're not dealing with PGW.

                            // For PGW, Indra wants us to pass <account number>-<service point id>.
                            // In Focus, these are stored as two separate items in an array.
                            // Create a new identifiers array with only one object. The account identifier object will be used for this.
                            // We'll then locate the service point ID object, and append it's identifier to the account number.
                            // We're expecting to only ever have the account number and service point id in the original array. If something changes
                            // for PGW, the code here will likely need to be updated.
                            if(strtolower($utility->name) == 'philadelphia gas works') {
                                $newIdentiers = [];

                                // Find the account number identifier. This will be the one object in the new
                                // identifiers array.
                                foreach($identifiers as $ident) {
                                    if(strtolower($ident['utility_account_type']['account_type']) == 'account number') {
                                        $newIdentiers[] = $ident;
                                    }
                                }

                                // Now, find the service point id. We'll append the identifier property from this object
                                // the the identifier of the account number object
                                foreach($identifiers as $ident) {
                                    if(strtolower($ident['utility_account_type']['account_type']) == 'service point id') {
                                        if(isset($newIdentiers[0]['identifier'])) {
                                            $newIdentiers[0]['identifier'] .= '-' . $ident['identifier'];
                                        } else {
                                            $newIdentiers[0]['ident'] .= '-' . $ident['ident'];
                                        }
                                    }
                                }

                                $identifiers = $newIdentiers;
                            }

                            foreach ($identifiers as $selection_identifier) {
                                $busf = BrandUtilitySupportedFuel::where(
                                    'utility_supported_fuel_id',
                                    $udata['fuel_id']
                                )->where(
                                    'utility_id',
                                    $utility->id
                                )->where(
                                    'brand_id',
                                    $brand_id
                                )->first();
                                if ($busf && $busf->external_id) {
                                    info('got utility with external_id = ' . $busf->external_id);
                                    Log::debug(print_r($selection_identifier, true));

                                    $identifier = (isset($selection_identifier['identifier']))
                                        ? $selection_identifier['identifier']
                                        : $selection_identifier['ident'];

                                    if (strlen(trim($identifier)) > 0) {
                                        Log::debug('using first identifier = ' . $identifier);

                                        try {
                                            $start = microtime(true);

                                            $callData = [
                                                'AgentId' => $agentId,
                                                'PhoneNumber' => str_replace('+1', '', CleanPhoneNumber($data['phone'])),
                                                'AccountNumber' => $identifier,
                                                'TerritoryCode' => $busf->external_id,
                                            ];

                                            Log::debug(print_r($callData, true));

                                            $jd = new JsonDocument();
                                            $jd->document_type = 'Indra Active API';
                                            $jd->ref_id = $data['event'];
                                            $jd->document = $callData;
                                            $jd->save();

                                            $opts['Body'] = $callData;

                                            $hresponse = $this->indraDncApi(
                                                $callData,
                                                $jd->id,
                                                $event->channel_id,
                                                $http,
                                                $opts
                                            );
                                            $end = microtime(true);

                                            Log::debug(
                                                'Indra Active API Response in ' . (($end - $start) / 60) . ' sec(s)'
                                            );

                                            if ($hresponse->getStatusCode() === 200) {
                                                $res = json_decode($hresponse->getBody(), true);
                                                info('Indra API Response', $res);

                                                $jd->document = [
                                                    'request' => $opts,
                                                    'response' => $res,
                                                    'response-time' => ($end - $start),
                                                ];
                                                $jd->save();
                                                if ($res['Status'] === 'Fail') {
                                                    $response['extra'] = ['msg' => $res['Detail']];
                                                    $response['message'] = 'indra_active_api_fail';
                                                    $response['conflicts'] = [$res['Detail']];
                                                    $response['request_id'] = $jd->id;
                                                    if ($stop_call) {
                                                        Log::debug(
                                                            'In indra_active_dnc_api_check() - stopping call'
                                                        );

                                                        $response['stop-call'] = true;
                                                    }

                                                    return true;
                                                }
                                            } else {
                                                info(
                                                    'Error during Indra API: ' . $hresponse->getStatusCode(),
                                                    [
                                                        $hresponse->getBody()
                                                    ]
                                                );
                                            }
                                        } catch (\Exception $e) {
                                            info(
                                                'Error during Indra API (exception)',
                                                [
                                                    'message' => $e->getMessage(),
                                                    'code' => $e->getCode(),
                                                    'line' => $e->getLine(),
                                                    'file' => $e->getFile(),
                                                    'trace' => $e->getTraceAsString()
                                                ]
                                            );
                                        }
                                    } else {
                                        Log::debug('No selection identifier was found.');
                                    }
                                } else {
                                    info(
                                        'no external_id was set'
                                    );
                                }
                            }
                        }
                    }
                }
            } else {
                info('no api password and/or product selected', ['opts' => $opts, 'data' => $data]);
            }
        } else {
            Log::debug('In indra_active_dnc_api_check() but source was not EZTPV.');
        }

        return false;
    }

    private function clearview_active_customer_api_check(
        int $alert_id,
        string $brand_id,
        int $threshold,
        array $channels,
        bool $stop_call,
        array $data,
        array &$response,
        bool $alertEnabled
    ): bool {
        Log::debug('In clearview_active_customer_api_check()', [$data]);

        $http = new GuzzleClient(
            [
                'base_uri' => 'https://cvapi.tpvhub.com',
                'verify' => false,
            ]
        );

        $opts = [
            'headers' => [
                'Content-type' => 'application/json',
            ]
        ];

        if (isset($data['product']['selection'])) {
            info('starting clearview api check');
            $event = Event::find($data['event']);
            if ($event !== null) {
                info('event found');
                if (!in_array($event->channel_id, $channels)) {
                    info('event channel is not in configured channel list');
                    return false;
                }
            }

            foreach ($data['product']['selection'] as $utilityId => $udata) {
                $utility = Utility::find($utilityId);
                $brandUtility = BrandUtility::where(
                    'brand_id',
                    $brand_id
                )->where(
                    'utility_id',
                    $utility->id
                )->first();
                $sutil = null;
                if ($brandUtility) {
                    $sutil = $brandUtility->utility_external_id;
                }
                if ($sutil !== null) {
                    info('got utility');
                    foreach ($udata as $selection) {
                        if (is_array($selection) && isset($selection['fuel_type'])) {
                            info('fuel type set', $selection);
                            foreach ($selection['identifiers'] as $selection_identifier) {
                                $identifier = (isset($selection_identifier['identifier']))
                                    ? $selection_identifier['identifier']
                                    : $selection_identifier['ident'];

                                if (
                                    strlen(trim($identifier)) > 0
                                    && !in_array($selection_identifier['utility_account_type_id'], [9])
                                ) {
                                    info('using first identifier');
                                    try {
                                        $callData = [
                                            'account_number' => $identifier,
                                            'util' => $sutil,
                                        ];

                                        $opts['body'] = $callData;

                                        $jd = new JsonDocument();
                                        $jd->document_type = 'Clearview Active API';
                                        $jd->ref_id = $data['event'];
                                        $jd->document = $callData;
                                        $jd->save();

                                        $start = microtime(true);
                                        $hresponse = $http->request(
                                            'GET',
                                            '/api/customer?account_number='
                                                . $identifier
                                                . '&util=' . urlencode($sutil)
                                                . '&debug=1'
                                        );
                                        $end = microtime(true);

                                        if ($hresponse->getStatusCode() === 200) {
                                            $res = json_decode($hresponse->getBody(), true);
                                            info('Clearview API Response', $res);

                                            $jd->document = [
                                                'request' => $opts,
                                                'response' => $res,
                                                'response-time' => ($end - $start),
                                            ];
                                            $jd->save();

                                            if (isset($res['found']) && $res['found'] === true) {
                                                $message = 'This account (' . $identifier . ') has already been registered.';
                                                $json_response = json_encode($res);
                                                $response['extra'] = [
                                                    'msg' => $message,
                                                    'full' => $json_response,
                                                ];
                                                $response['message'] = $message;
                                                $response['conflicts'] = null;
                                                $response['request_id'] = $jd->id;
                                                if ($stop_call) {
                                                    Log::debug(
                                                        'In clearview_active_customer_api_check() - stopping call'
                                                    );

                                                    $response['stop-call'] = true;
                                                }

                                                return true;
                                            }
                                        } else {
                                            info(
                                                'Error during Clearview API: ' . $hresponse->getStatusCode(),
                                                [
                                                    $hresponse->getBody()
                                                ]
                                            );
                                        }
                                    } catch (\Exception $e) {
                                        info(
                                            'Error during Clearview API (exception)',
                                            [
                                                'message' => $e->getMessage(),
                                                'code' => $e->getCode(),
                                                'line' => $e->getLine(),
                                                'file' => $e->getFile(),
                                                'trace' => $e->getTraceAsString()
                                            ]
                                        );
                                    }
                                }
                            }
                        }
                    }
                } else {
                    info('utility does not have external id', [$sutil]);
                }
            }
        } else {
            info('no api password and/or product selected', ['opts' => $opts, 'data' => $data]);
        }

        return false;
    }

    /* End Section: Brand Specific Alert Checks */

    /**
     * Check for duplicate sales agent (first name & last name).
     */
    public function checkExistingSalesAgentName(Request $request)
    {
        Log::debug('In checkExistingSalesAgentName()', $request->all());

        // Check if alert is set up and active
        $list = BrandClientAlert::select(
            'brand_client_alerts.distribution_email',
            'brand_client_alerts.vendor_distribution'
        )->where('brand_id', $request->brand
        )->where('client_alert_id', 16
        )->where('status', 1
        )->first();

        if (!$list) {
            return response()->json(['status' => false, 'message' => 'No alert setup found.']);
        }

        $users = User::select(
            'users.first_name',
            'users.last_name',
            'brand_users.tsr_id',
            'brands.name AS brand_name',
            'offices.name as office_name',
            'brand_users.deleted_at AS brand_user_deleted',
            'brand_users.employee_of_id'
        )->join(
            'brand_users',
            'users.id',
            'brand_users.user_id'
        )->join(
            'brands',
            'brand_users.employee_of_id',
            'brands.id'
        )->join(
            'brand_user_offices',
            'brand_users.id',
            'brand_user_offices.brand_user_id'
        )->join(
            'offices',
            'brand_user_offices.office_id',
            'offices.id'
        )->where(
            'brand_users.works_for_id',
            $request->brand
        )->whereRaw(
            'UPPER(users.first_name) = ?',
            mb_strtoupper($request->first_name)
        )->whereRaw(
            'UPPER(users.last_name) = ?',
            mb_strtoupper($request->last_name)
        )->where(
            'users.id',
            '!=',
            $request->exclude
        )->whereNull(
            'brands.deleted_at'
        )->whereNull(
            'offices.deleted_at'
        )->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No sales agents found.']);
        }

        $clientBodyText = 'Found a Sales Agent(s) matching the same name as "' .
            $request->first_name . ' ' . $request->last_name . '"<br /><br />';
        $vendorBodyText = '';

        $vendorExistingUsers = 0;
        foreach ($users as $user) {
            $status = empty($user['brand_user_deleted']) ? 'Active' : 'Inactive';
            $userInfo = "Rep ID {$user['tsr_id']} ({$status}) at {$user['brand_name']} in office {$user['office_name']}<br />";
            $clientBodyText .= $userInfo;

            if ($user['employee_of_id'] == $request->vendor) {
                $vendorBodyText = $vendorBodyText ?: 'Found a Sales Agent(s) matching the same name as "' .
                    $request->first_name . ' ' . $request->last_name . '"<br /><br />';
                $vendorBodyText .= $userInfo;
                $vendorExistingUsers++;
            }
        }

        $emailList = ['client' => [], 'vendor' => []];

        $clientEmails = preg_replace("/\r|\n/", '', trim($list->distribution_email));
        if (strlen(trim($clientEmails)) > 0) {
            $emailList['client'] = array_filter(array_map('trim', explode(',', $clientEmails)));
        }

        if ($list->vendor_distribution) {
            $vendorDistribution = json_decode($list->vendor_distribution, true);
            if (isset($vendorDistribution[$request->vendor])) {
                $vendorEmails = preg_replace("/\r|\n/", '', trim($vendorDistribution[$request->vendor]));
                $emailList['vendor'] = array_filter(array_map('trim', explode(',', $vendorEmails)));
            }
        }

        $subject = 'TPV.com Alert - Existing Sales Agent Name';

        foreach ($emailList as $type => $emails) {
            if ($type == 'vendor' && $vendorExistingUsers == 0) {
                continue;
            }

            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Log::info("Sending {$type} mail to ({$email}) for alert Existing Sales Agent Name");

                    Mail::send(
                        ['html' => 'emails.simpleAlertEmail'],
                        [
                            'alert_name' => $subject,
                            'body_text' => $type == 'vendor' ? $vendorBodyText : $clientBodyText,
                        ],
                        function ($message) use ($email, $subject) {
                            $message->to($email)->subject($subject);
                        }
                    );

                    if (!Str::endsWith($email, '@tpv.com') && !Str::endsWith($email, '@answernet.com')) {
                        info('Email is not a tpv.com email so create invoiceable for it');
                        BillingController::CreateInvoiceable(
                            $request->brand,
                            'Email::Send',
                            1,
                            '',
                            null,
                            'Email sent to ' . $email . ' for alert Existing Sales Agent Name alert'
                        );
                    }
                }
            }
        }

        return response()->json(['status' => true, 'message' => 'Alerts sent successfully.']);
    }
}
