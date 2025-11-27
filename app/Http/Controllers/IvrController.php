<?php

namespace App\Http\Controllers;

use Twilio\TwiML\VoiceResponse as Twiml;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\ZipCode;
use App\Models\Vendor;
use App\Models\State;

use App\Models\PhoneNumberLookup;
use App\Models\Interaction;
use App\Models\Event;
use App\Models\Dnis;
use App\Models\BrandUserOffice;
use App\Models\BrandUser;
use App\Models\BrandStateLanguage;
use App\Models\BrandState;
use App\Models\BrandHour;
use App\Models\BrandConfig;
use App\Models\Brand;
use App\Models\Office;

/**
 * IvrController handles IVR communication.
 */
class IvrController extends Controller
{
    private $_client;
    private $_workspace_id;
    private $_workspace;
    private $_nostate;
    private $_welcome;

    private const VISTA_BRAND_IDS = [
        'old_stage' => '1d6da685-c586-4c2f-857e-d609d6ce2d6f',
        'prod' => '47b32b8c-3df2-425c-8bfb-024f8173b5fb'
    ];

    private $highVolumeMessage = 'We appreciate your patience but we are experiencing high call volume at this time.';

    /**
     * IvrController constructor.
     */
    public function __construct()
    {
        $this->_client = new Client(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
        $this->_workspace_id = config('services.twilio.workspace');
        $this->_workspace = $this->_client->taskrouter
            ->workspaces($this->_workspace_id);

        $this->_nostate = 'This Phone Number is not assigned a state.  Goodbye.';
        $this->_welcome = 'Thank you for calling T P V dot com';
    }

    /**
     * Reject Call function.
     */
    public function rejectCall()
    {
        $response = new Twiml();
        $response->reject(['reason' => 'rejected']);

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Reject Call with Comment function.
     *
     * @param string $comment - what should be said
     */
    public function rejectCallWithComment($comment)
    {
        $response = new Twiml();
        $response->say($comment, ['voice' => 'woman']);
        $response->reject(['reason' => 'rejected']);

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Get States by brand_id and states.
     *
     * @param string $brand_id brand identifier
     * @param string $states   comma delimited list of states
     */
    private function getStates($brand_id, $states)
    {
        $state_explode = explode(',', $states);

        return Cache::remember(
            'dnis_states_' . md5($states),
            300,
            function () use ($state_explode) {
                return State::select('states.name')
                    ->whereIn('states.id', $state_explode)
                    ->get()
                    ->toArray();
            }
        );
    }

    /**
     * ToPhone - looks up the incoming phone number to verify that
     * its attached to a script.
     *
     * @param string $to - number being called into
     *
     * @return object $tophone
     */
    private function toPhone($to)
    {
        return Cache::remember(
            'tophone_' . $to,
            300,
            function () use ($to) {
                return Dnis::select(
                    'dnis.brand_id',
                    'dnis.states',
                    'dnis.platform',
                    'dnis.skill_name',
                    'dnis.channel_id AS dnis_channel_id',
                    'dnis.market_id AS dnis_market_id',
                    'dnis.config',
                    'scripts.channel_id',
                    'script_types.script_type',
                    'scripts.exit_reason',
                    'scripts.id as script_id'
                )->join(
                    'scripts',
                    'dnis.id',
                    'scripts.dnis_id'
                )->leftJoin(
                    'script_types',
                    'scripts.script_type_id',
                    'script_types.id'
                )->where(
                    'dnis.dnis',
                    $to
                )->whereNull(
                    'scripts.deleted_at'
                )->first();
            }
        );
    }

    /**
     * selectScript - Looks up the a script associated with a phone number and state
     *
     * @param mixed $data - request/attributes data collected so far
     *
     * @return object $tophone
     */
    private function selectScript($data)
    {
        if(strpos(request()->input('To'), 'sip:') !== false) {
            $to = $this->parseSipAddress(request()->input('To'));
        } else {
            $to = CleanPhoneNumber(request()->input('To'));   
        }  

        $state_id = $data->input('state_id');

        return Cache::remember(
            'tophone_' . $to . '_' . $state_id,
            300,
            function () use ($to, $state_id) {
                return Dnis::select(
                    'scripts.id',
                    'scripts.title',
                    'scripts.channel_id',
                    'scripts.state_id'
                )->join(
                    'scripts',
                    'dnis.id',
                    'scripts.dnis_id'
                )->leftJoin(
                    'script_types',
                    'scripts.script_type_id',
                    'script_types.id'
                )->where(
                    'dnis.dnis',
                    $to
                )->where(
                    'scripts.state_id',
                    $state_id
                )->whereNull(
                    'scripts.deleted_at'
                )->first();
            }
        );
    }

    /**
     * Config Answers business rules from the brand.
     *
     * @return object $config_answers
     */
    private function configAnswers($tophone)
    {
        return Cache::remember(
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
                    return new \StdClass();
                }
            }
        );
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

    /**
     * Lookup Rep - Returns information about the sales agent.
     *
     * @param string $from           - the number the agent called from
     * @param object $config_answers - the business rules
     */
    private function lookupRep($from, $tophone, $config_answers)
    {
        $whoareyou = PhoneNumberLookup::select(
            'users.id',
            'users.first_name',
            'brand_users.state_id',
            'brand_users.channel_id'
        )->join(
            'phone_numbers',
            'phone_number_lookup.phone_number_id',
            'phone_numbers.id'
        )->join(
            'users',
            'phone_number_lookup.type_id',
            'users.id'
        )->join(
            'brand_users',
            'users.id',
            'brand_users.user_id'
        )->where(
            'phone_numbers.phone_number',
            $from
        )->where(
            'phone_number_lookup.phone_number_type_id',
            1
        )->where(
            'brand_users.works_for_id',
            $tophone->brand_id
        )->whereNull(
            'brand_users.deleted_at'
        )->whereNull(
            'users.deleted_at'
        );

        $uses = $whoareyou->count();

        if (
            isset($config_answers->ivr_known_numbers)
            && true == $config_answers->ivr_known_numbers
            && 0 == $uses
        ) {
            return $this->rejectCall();
        }

        Log::debug('uses is ' . $uses);

        if (1 == $uses) {
            $whoareyou = $whoareyou->first();
        }

        if ($uses > 1) {
            $whoareyou = $whoareyou->get();
        }

        if (0 == $uses) {
            return null;
        }

        return $whoareyou;
    }

    /**
     * LookupCompany - Looks up the company by brand_id.
     *
     * @return object containing company information
     */
    public function lookupCompany($tophone)
    {
        $ivr_company = Cache::remember(
            'ivr_company_' . $tophone->brand_id,
            300,
            function () use ($tophone) {
                return Brand::select('name')
                    ->where('id', $tophone->brand_id)
                    ->first();
            }
        );

        return $ivr_company;
    }

    /**
     * IVR States step.
     */
    public function ivrStates(Request $request)
    {
        //Log::debug(print_r($request->all(), true));

        $states = $this->getStates(
            $request->get('brand_id'),
            $request->get('states')
        );

        $response = new Twiml();

        Log::debug('States count is: ' . count($states));

        switch (count($states)) {
            case 0:
                $response->say(
                    $this->_nostate,
                    ['voice' => 'woman']
                );

                $response->hangup();

                return response($response, 200, ['Content-Type' => 'application/xml']);
            case 1:
                $state_explode = explode(',', $request->get('states'));
                $state_id = $state_explode[0];

                $hoo = $this->checkHoursOfOperation(
                    $state_id,
                    $request->get('brand_id')
                );

                if ((!isset($hoo['hours']) || null == $hoo['hours'])
                    || $hoo['continue']
                ) {
                    $response->redirect(
                        route(
                            'languages',
                            [
                                'method' => 'POST',
                                'states' => $request->get('states'),
                                'brand_id' => $request->get('brand_id'),
                                'ivr_company_name' => $request->get('ivr_company_name'),
                                'agent_id' => $request->get('agent_id'),
                                'channel_id' => $request->get('channel_id'),
                                'state_id' => $state_id,
                                'type' => $request->get('type'),
                                'platform' => $request->get('platform'),
                                'skill_name' => $request->get('skill_name'),
                                'dnis_channel_id' => $request->get('dnis_channel_id'),
                                'dnis_market_id' => $request->get('dnis_market_id'),
                            ],
                            false
                        )
                    );
                } else {
                    return $this->rejectCallWithComment(
                        'Sorry!  We are currently closed.'
                    );
                }

                return response($response, 200, ['Content-Type' => 'application/xml']);
            default:
                $numDigits = 1;
                if (count($states) > 9) {
                    $numDigits = 2;
                }

                $gather = $response->gather(
                    [
                        'input' => 'dtmf',
                        'action' => route(
                            'get-state',
                            [
                                'method' => 'POST',
                                'states' => $request->get('states'),
                                'brand_id' => $request->get('brand_id'),
                                'ivr_company_name' => $request->get('ivr_company_name'),
                                'agent_id' => $request->get('agent_id'),
                                'channel_id' => $request->get('channel_id'),
                                'type' => $request->get('type'),
                                'platform' => $request->get('platform'),
                                'skill_name' => $request->get('skill_name'),
                                'dnis_channel_id' => $request->get('dnis_channel_id'),
                                'dnis_market_id' => $request->get('dnis_market_id'),
                            ],
                            false
                        ),
                        'numDigits' => $numDigits,
                    ]
                );

                for ($i = 0; $i < count($states); ++$i) {
                    $digit = ($numDigits == 2)
                        ? sprintf('%02d', ($i + 1))
                        : ($i + 1);

                    $gather->say(
                        'For ' . $states[$i]['name'] . ' press ' . $digit,
                        [
                            'voice' => 'woman',
                        ]
                    );
                }

                $response->redirect(route(
                    'states',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'brand_id' => $request->get('brand_id'),
                        'ivr_company_name' => $request->get('ivr_company_name'),
                        'agent_id' => $request->get('agent_id'),
                        'channel_id' => $request->get('channel_id'),
                        'type' => $request->get('type'),
                        'platform' => $request->get('platform'),
                        'skill_name' => $request->get('skill_name'),
                        'dnis_channel_id' => $request->get('dnis_channel_id'),
                        'dnis_market_id' => $request->get('dnis_market_id'),
                    ],
                    false
                ));

                return response($response, 200, ['Content-Type' => 'application/xml']);
        }
    }

    /**
     * IVR States step.
     */
    public function ivrStatesInboundCustomerOnly(Request $request)
    {
        //Log::debug(print_r($request->all(), true));

        $states = $this->getStates(
            $request->get('brand_id'),
            $request->get('states')
        );

        $response = new Twiml();

        Log::debug('States count is: ' . count($states));

        switch (count($states)) {
            case 0:
                $response->say(
                    $this->_nostate,
                    ['voice' => 'woman']
                );

                $response->hangup();

                return response($response, 200, ['Content-Type' => 'application/xml']);
            case 1:
                $state_explode = explode(',', $request->get('states'));
                $state_id = $state_explode[0];

                $hoo = $this->checkHoursOfOperation(
                    $state_id,
                    $request->get('brand_id')
                );

                if ((!isset($hoo['hours']) || null == $hoo['hours'])
                    || $hoo['continue']
                ) {
                    $response->redirect(
                        route(
                            'languages-inbound-customer-only',
                            [
                                'method' => 'POST',
                                'states' => $request->get('states'),
                                'type' => $request->get('type'),
                                'brand_id' => $request->get('brand_id'),
                                'script_id' => $request->get('script_id'),
                                'event_id' => $request->get('event_id'), // when null this will prompt Agents to lookup the customer's info
                                'skill_name' => $request->get('skill_name'),
                                'platform' => $request->get('platform'),
                                'client' => $request->get('client'),
                                'state_id' => $state_id
                            ],
                            false
                        )
                    );
                } else {
                    return $this->rejectCallWithComment(
                        'Sorry!  We are currently closed.'
                    );
                }

                return response($response, 200, ['Content-Type' => 'application/xml']);
            default:
                $numDigits = 1;
                if (count($states) > 9) {
                    $numDigits = 2;
                }

                $gather = $response->gather(
                    [
                        'input' => 'dtmf',
                        'action' => route(
                            'get-state-inbound-customer-only',
                            [
                                'method' => 'POST',
                                'states' => $request->get('states'),
                                'type' => $request->get('type'),
                                'brand_id' => $request->get('brand_id'),
                                'script_id' => $request->get('script_id'),
                                'event_id' => $request->get('event_id'),
                                'skill_name' => $request->get('skill_name'),
                                'platform' => $request->get('platform'),
                                'client' => $request->get('client')
                            ],
                            false
                        ),
                        'numDigits' => $numDigits,
                    ]
                );

                for ($i = 0; $i < count($states); ++$i) {
                    $digit = ($numDigits == 2)
                        ? sprintf('%02d', ($i + 1))
                        : ($i + 1);

                    $gather->say(
                        'For ' . $states[$i]['name'] . ' press ' . $digit,
                        [
                            'voice' => 'woman',
                        ]
                    );
                }

                $response->redirect(route(
                    'states-inbound-customer-only',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'type' => $request->get('type'),                        
                        'brand_id' => $request->get('brand_id'),
                        'script_id' => $request->get('script_id'),
                        'event_id' => $request->get('event_id'),
                        'skill_name' => $request->get('skill_name'),
                        'platform' => $request->get('platform'),
                        'client' => $request->get('client')
                    ],
                    false
                ));

                return response($response, 200, ['Content-Type' => 'application/xml']);
        }
    }

    /**
     * Gets state from state dtmf.
     *
     * @param Request $request digit parameter
     */
    public function getState(Request $request)
    {
        $state_explode = explode(',', $request->get('states'));
        $state_selected = intval(ltrim($request->input('Digits'), '0'), 10);
        $response = new Twiml();
        if ($state_selected > 0) {
            if (isset($state_explode[$state_selected - 1])) {
                $state_id = $state_explode[$state_selected - 1];

                Log::debug('State ID is ' . $state_id);

                $hoo = $this->checkHoursOfOperation(
                    $state_id,
                    $request->get('brand_id')
                );

                if ((!isset($hoo['hours']) || null == $hoo['hours'])
                    || $hoo['continue']
                ) {
                    $response->redirect(
                        route(
                            'languages',
                            [
                                'method' => 'POST',
                                'states' => $request->get('states'),
                                'brand_id' => $request->get('brand_id'),
                                'ivr_company_name' => $request
                                    ->get('ivr_company_name'),
                                'agent_id' => $request->get('agent_id'),
                                'channel_id' => $request->get('channel_id'),
                                'state_id' => $state_id,
                                'type' => $request->get('type'),
                                'platform' => $request->get('platform'),
                                'skill_name' => $request->get('skill_name'),
                                'dnis_channel_id' => $request->get('dnis_channel_id'),
                                'dnis_market_id' => $request->get('dnis_market_id'),
                            ],
                            false
                        )
                    );
                } else {
                    return $this->rejectCallWithComment(
                        'Sorry!  We are currently closed.'
                    );
                }
            } else {
                $response->say('Sorry!  That state selection does not exist.', ['voice' => 'woman']);
                $response->redirect(
                    route(
                        'states',
                        [
                            'method' => 'POST',
                            'states' => $request->get('states'),
                            'brand_id' => $request->get('brand_id'),
                            'ivr_company_name' => $request
                                ->get('ivr_company_name'),
                            'agent_id' => $request->get('agent_id'),
                            'channel_id' => $request->get('channel_id'),
                            'type' => $request->get('type'),
                            'platform' => $request->get('platform'),
                            'skill_name' => $request->get('skill_name'),
                            'dnis_channel_id' => $request->get('dnis_channel_id'),
                            'dnis_market_id' => $request->get('dnis_market_id'),
                        ],
                        false
                    )
                );
            }
        } else {
            $response->say('You did not select a state.', ['voice' => 'woman']);
            $response->redirect(
                route(
                    'states',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'brand_id' => $request->get('brand_id'),
                        'ivr_company_name' => $request
                            ->get('ivr_company_name'),
                        'agent_id' => $request->get('agent_id'),
                        'channel_id' => $request->get('channel_id'),
                        'type' => $request->get('type'),
                        'platform' => $request->get('platform'),
                        'skill_name' => $request->get('skill_name'),
                        'dnis_channel_id' => $request->get('dnis_channel_id'),
                        'dnis_market_id' => $request->get('dnis_market_id'),
                    ],
                    false
                )
            );
        }

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Gets state from state dtmf.
     *
     * @param Request $request digit parameter
     */
    public function getStateInboundCustomerOnly(Request $request)
    {
        $state_explode = explode(',', $request->get('states'));
        $state_selected = intval(ltrim($request->input('Digits'), '0'), 10);
        $response = new Twiml();
        if ($state_selected > 0) {
            if (isset($state_explode[$state_selected - 1])) {
                $state_id = $state_explode[$state_selected - 1];

                Log::debug('State ID is ' . $state_id);

                $hoo = $this->checkHoursOfOperation(
                    $state_id,
                    $request->get('brand_id')
                );

                if ((!isset($hoo['hours']) || null == $hoo['hours'])
                    || $hoo['continue']
                ) {
                    $response->redirect(
                        route(
                            'languages-inbound-customer-only',
                            [
                                'method' => 'POST',
                                'states' => $request->get('states'),
                                'type' => $request->get('type'),
                                'brand_id' => $request->get('brand_id'),
                                'script_id' => $request->get('script_id'),
                                'state_id' => $state_id,
                                'event_id' => $request->get('event_id'),
                                'skill_name' => $request->get('skill_name'),
                                'platform' => $request->get('platform'),
                                'client' => $request->get('client')
                            ],
                            false
                        )
                    );

                } else {
                    return $this->rejectCallWithComment(
                        'Sorry!  We are currently closed.'
                    );
                }
            } else {
                $response->say('Sorry!  That state selection does not exist.', ['voice' => 'woman']);
                $response->redirect(
                    route(
                        'states-inbound-customer-only',
                        [
                            'method' => 'POST',
                            'states' => $request->get('states'),
                            'type' => $request->get('type'),
                            'brand_id' => $request->get('brand_id'),
                            'script_id' => $request->get('script_id'),
                            'event_id' => $request->get('event_id'),
                            'skill_name' => $request->get('skill_name'),
                            'platform' => $request->get('platform'),
                            'client' => $request->get('client')
                        ],
                        false
                    )
                );
            }
        } else {
            $response->say('You did not select a state.', ['voice' => 'woman']);
            $response->redirect(
                route(
                    'states-inbound-customer-only',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'type' => $request->get('type'),
                        'brand_id' => $request->get('brand_id'),
                        'script_id' => $request->get('script_id'),
                        'event_id' => $request->get('event_id'),
                        'skill_name' => $request->get('skill_name'),
                        'platform' => $request->get('platform'),
                        'client' => $request->get('client')
                    ],
                    false
                )
            );
        }

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Do Language IVR.
     */
    public function ivrLanguages(Request $request)
    {
        $languages = $this->getLanguages(
            $request->get('brand_id'),
            $request->get('state_id')
        );

        info('Languages: ' . json_encode($languages));

        $response = new Twiml();

        if (
            isset($languages)
            && isset($languages[0])
            && isset($languages[0]->id)
        ) {
            $language_id = $languages[0]->id;
        } else {
            $language_id = 1;
        }

        info('ivrLanguages Language ID set to: ' . $language_id);

        switch (count($languages)) {
            case 0:
            case 1:
                $response->redirect(
                    route(
                        'complete',
                        [
                            'method' => 'POST',
                            'states' => $request->get('states'),
                            'brand_id' => $request->get('brand_id'),
                            'state_id' => $request->get('state_id'),
                            'ivr_company_name' => $request->get('ivr_company_name'),
                            'agent_id' => $request->get('agent_id'),
                            'channel_id' => $request->get('channel_id'),
                            'language_id' => $language_id,
                            'type' => $request->get('type'),
                            'platform' => $request->get('platform'),
                            'skill_name' => $request->get('skill_name'),
                            'dnis_channel_id' => $request->get('dnis_channel_id'),
                            'dnis_market_id' => $request->get('dnis_market_id'),
                        ],
                        false
                    )
                );

                return response($response, 200, ['Content-Type' => 'application/xml']);
            default:
                $gather = $response->gather(
                    [
                        'input' => 'dtmf',
                        'action' => route(
                            'get-language',
                            [
                                'method' => 'POST',
                                'states' => $request->get('states'),
                                'brand_id' => $request->get('brand_id'),
                                'state_id' => $request->get('state_id'),
                                'ivr_company_name' => $request->get('ivr_company_name'),
                                'agent_id' => $request->get('agent_id'),
                                'channel_id' => $request->get('channel_id'),
                                'type' => $request->get('type'),
                                'platform' => $request->get('platform'),
                                'skill_name' => $request->get('skill_name'),
                                'dnis_channel_id' => $request->get('dnis_channel_id'),
                                'dnis_market_id' => $request->get('dnis_market_id'),
                            ],
                            false
                        ),
                        'numDigits' => 1,
                    ]
                );

                for ($i = 0; $i < count($languages); ++$i) {
                    $gather->say(
                        'For ' . $languages[$i]['language'] . ' press ' . ($i + 1),
                        [
                            'voice' => 'woman',
                        ]
                    );
                }

                $response->redirect(route(
                    'languages',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'brand_id' => $request->get('brand_id'),
                        'state_id' => $request->get('state_id'),
                        'ivr_company_name' => $request->get('ivr_company_name'),
                        'agent_id' => $request->get('agent_id'),
                        'channel_id' => $request->get('channel_id'),
                        'type' => $request->get('type'),
                        'platform' => $request->get('platform'),
                        'skill_name' => $request->get('skill_name'),
                        'dnis_channel_id' => $request->get('dnis_channel_id'),
                        'dnis_market_id' => $request->get('dnis_market_id'),
                    ],
                    false
                ));

                return response($response, 200, ['Content-Type' => 'application/xml']);
        }
    }

    /**
     * Do Language IVR.
     */
    public function ivrLanguagesInboundCustomerOnly(Request $request)
    {
        $languages = $this->getLanguages(
            $request->get('brand_id'),
            $request->get('state_id')
        );

        info('Languages: ' . json_encode($languages));

        $response = new Twiml();

        if (
            isset($languages)
            && isset($languages[0])
            && isset($languages[0]->id)
        ) {
            $language_id = $languages[0]->id;
        } else {
            $language_id = 1;
        }

        info('ivrLanguages Language ID set to: ' . $language_id);

        // Determine script ID here
        // At this point a state has been selected and we can determine
        // the correct script
        $script = $this->selectScript($request);

        switch (count($languages)) {
            case 0:
            case 1:
                $response->redirect(
                    route(
                        'complete',
                        [
                            'method' => 'POST',
                            'states' => $request->get('states'),
                            'type' => $request->get('type'),
                            'brand_id' => $request->get('brand_id'),
                            'script_id' => $script->id,
                            'event_id' => $request->get('event_id'), // when null this will prompt Agents to lookup the customer's info                            
                            'skill_name' => $request->get('skill_name'),
                            'platform' => $request->get('platform'),
                            'client' => $request->get('client'),
                            'state_id' => $request->get('state_id'),
                            'language_id' => $language_id,
                        ],
                        false
                    )
                );

                return response($response, 200, ['Content-Type' => 'application/xml']);
            default:
                $gather = $response->gather(
                    [
                        'input' => 'dtmf',
                        'action' => route(
                            'get-language-inbound-customer-only',
                            [
                                'method' => 'POST',
                                'states' => $request->get('states'),
                                'type' => $request->get('type'),
                                'brand_id' => $request->get('brand_id'),
                                'script_id' => $script->id,
                                'event_id' => $request->get('event_id'), // when null this will prompt Agents to lookup the customer's info                            
                                'skill_name' => $request->get('skill_name'),
                                'platform' => $request->get('platform'),
                                'client' => $request->get('client'),
                                'state_id' => $request->get('state_id')
                            ],
                            false
                        ),
                        'numDigits' => 1,
                    ]
                );

                for ($i = 0; $i < count($languages); ++$i) {
                    $gather->say(
                        'For ' . $languages[$i]['language'] . ' press ' . ($i + 1),
                        [
                            'voice' => 'woman',
                        ]
                    );
                }

                $response->redirect(route(
                    'languages-inbound-customer-only',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'type' => $request->get('type'),
                        'brand_id' => $request->get('brand_id'),
                        'script_id' => $script->id,
                        'event_id' => $request->get('event_id'), // when null this will prompt Agents to lookup the customer's info                            
                        'skill_name' => $request->get('skill_name'),
                        'platform' => $request->get('platform'),
                        'client' => $request->get('client'),
                        'state_id' => $request->get('state_id')
                    ],
                    false
                ));

                return response($response, 200, ['Content-Type' => 'application/xml']);
        }
    }

    /**
     * Do Language IVR for Inbound Only.
     */
    public function ivrLanguagesInbound(Request $request)
    {
        $languages = [['id' => 1, 'language' => 'English' ], ['id' => 2, 'language' => 'Spanish' ] ];

//        $languages = $this->getLanguages(
//            $request->get('brand_id'),
//            $request->get('state_id')
//        );

        info('Languages: ' . json_encode($languages));

        $response = new Twiml();

        if (
            isset($languages)
            && isset($languages[0])
            && isset($languages[0]->id)
        ) {
            $language_id = $languages[0]->id;
        } else {
            $language_id = 1;
        }

        info('ivrLanguagesInbound Language ID set to: ' . $language_id);

        switch (count($languages)) {
            case 0:
            case 1:
                $response->redirect(
                    route(
                        'complete',
                        [
                            'method' => 'POST',
                            'type' => 'inbound_customer_only',
                            'brand_id' => $request->get('brand_id'),
                            'script_id' => $request->get('script_id'),
                            'event_id' =>  $request->get('event_id'),
                            'skill_name' => $request->get('skill_name'),
                            'platform' => $request->get('platform'),
                            'client' => $request->get('client'),
                            'language_id' => $language_id,
                        ],
                        false
                    )
                );

                return response($response, 200, ['Content-Type' => 'application/xml']);
            default:
                $gather = $response->gather(
                    [
                        'input' => 'dtmf',
                        'action' => route(
                            'get-language-inbound',
                            [
                                'method' => 'POST',
                                'type' => 'inbound_customer_only',
                                'brand_id' => $request->get('brand_id'),
                                'script_id' => $request->get('script_id'),
                                'event_id' =>  $request->get('event_id'),
                                'skill_name' => $request->get('skill_name'),
                                'platform' => $request->get('platform'),
                                'client' => $request->get('client'),

                            ],
                            false
                        ),
                        'numDigits' => 1,
                    ]
                );

                for ($i = 0; $i < count($languages); ++$i) {
                    $gather->say(
                        'For ' . $languages[$i]['language'] . ' press ' . ($i + 1),
                        [
                            'voice' => 'woman',
                        ]
                    );
                }

                $response->redirect(route(
                    'languages-inbound',
                    [
                        'method' => 'POST',
                        'type' => 'inbound_customer_only',
                        'brand_id' => $request->get('brand_id'),
                        'script_id' => $request->get('script_id'),
                        'event_id' =>  $request->get('event_id'),
                        'skill_name' => $request->get('skill_name'),
                        'platform' => $request->get('platform'),
                        'client' => $request->get('client'),
                    ],
                    false
                ));

                return response($response, 200, ['Content-Type' => 'application/xml']);
        }
    }

    /**
     * Gets language from language dtmf.
     *
     * @param Request $request digit parameter
     */
    public function getLanguage(Request $request)
    {
        $language_id = intval($request->input('Digits'), 10);

        $response = new Twiml();

        Log::debug('getLanguage Language ID is ' . $language_id);

        if ($language_id == null || !is_numeric($language_id) || !in_array($language_id, [1, 2])) {
            $response->say('I\'m sorry, That is an invalid language selection.', ['voice' => 'woman']);
            $response->redirect(
                route(
                    'languages',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'brand_id' => $request->get('brand_id'),
                        'ivr_company_name' => $request
                            ->get('ivr_company_name'),
                        'agent_id' => $request->get('agent_id'),
                        'channel_id' => $request->get('channel_id'),
                        'state_id' => $request->get('state_id'),
                        'type' => $request->get('type'),
                        'platform' => $request->get('platform'),
                        'skill_name' => $request->get('skill_name'),
                        'dnis_channel_id' => $request->get('dnis_channel_id'),
                        'dnis_market_id' => $request->get('dnis_market_id'),
                    ],
                    false
                )
            );
        } else {
            $response->redirect(
                route(
                    'complete',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'brand_id' => $request->get('brand_id'),
                        'state_id' => $request->get('state_id'),
                        'ivr_company_name' => $request->get('ivr_company_name'),
                        'agent_id' => $request->get('agent_id'),
                        'channel_id' => $request->get('channel_id'),
                        'language_id' => $language_id,
                        'type' => $request->get('type'),
                        'platform' => $request->get('platform'),
                        'skill_name' => $request->get('skill_name'),
                        'dnis_channel_id' => $request->get('dnis_channel_id'),
                        'dnis_market_id' => $request->get('dnis_market_id'),
                    ],
                    false
                )
            );
        }

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

/**
     * Gets language from language dtmf.
     *
     * @param Request $request digit parameter
     */
    public function getLanguageInboundCustomerOnly(Request $request)
    {
        $language_id = intval($request->input('Digits'), 10);

        $response = new Twiml();

        Log::debug('getLanguage Language ID is ' . $language_id);

        if ($language_id == null || !is_numeric($language_id) || !in_array($language_id, [1, 2])) {
            $response->say('I\'m sorry, That is an invalid language selection.', ['voice' => 'woman']);
            $response->redirect(
                route(
                    'languages-inbound-customer-only',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'type' => $request->get('type'),
                        'brand_id' => $request->get('brand_id'),
                        'script_id' => $request->get('script_id'),
                        'event_id' => $request->get('event_id'), // when null this will prompt Agents to lookup the customer's info
                        'skill_name' => $request->get('skill_name'),
                        'platform' => $request->get('platform'),
                        'client' => $request->get('client'),
                        'state_id' => $request->get('state_id')
                    ],
                    false
                )
            );
        } else {
            $response->redirect(
                route(
                    'complete',
                    [
                        'method' => 'POST',
                        'states' => $request->get('states'),
                        'type' => $request->get('type'),
                        'brand_id' => $request->get('brand_id'),
                        'script_id' => $request->get('script_id'),
                        'event_id' => $request->get('event_id'), // when null this will prompt Agents to lookup the customer's info
                        'skill_name' => $request->get('skill_name'),
                        'platform' => $request->get('platform'),
                        'client' => $request->get('client'),
                        'state_id' => $request->get('state_id'),
                        'language_id' => $language_id
                    ],
                    false
                )
            );
        }

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Gets language from language dtmf for Inbound Only.
     *
     * @param Request $request digit parameter
     */
    public function getLanguageInbound(Request $request)
    {
        $language_id = intval($request->input('Digits'), 10);

        $response = new Twiml();

        Log::debug('getLanguageInbound Language ID is ' . $language_id);

        if ($language_id == null || !is_numeric($language_id) || !in_array($language_id, [1, 2])) {
            $response->say('I\'m sorry, That is an invalid language selection.', ['voice' => 'woman']);
            $response->redirect(
                route(
                    'languages-inbound',
                    [
                        'method' => 'POST',
                        'type' => 'inbound_customer_only',
                        'brand_id' => $request->get('brand_id'),
                        'script_id' => $request->get('script_id'),
                        'event_id' =>  $request->get('event_id'),
                        'skill_name' => $request->get('skill_name'),
                        'platform' => $request->get('platform'),
                        'client' => $request->get('client'),
                    ],
                    false
                )
            );
        } else {
            $response->redirect(
                route(
                    'complete',
                    [
                        'method' => 'POST',
                        'type' => 'inbound_customer_only',
                        'brand_id' => $request->get('brand_id'),
                        'script_id' => $request->get('script_id'),
                        'event_id' =>  $request->get('event_id'),
                        'skill_name' => $request->get('skill_name'),
                        'platform' => $request->get('platform'),
                        'client' => $request->get('client'),
                        'language_id' => $language_id,

                    ],
                    false
                )
            );
        }

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function getLanguages($brand_id, $state_id)
    {
        $bsa = BrandState::select('id')
            ->where('brand_id', $brand_id)
            ->where('state_id', $state_id)
            ->first();

        if ($bsa) {
            return BrandStateLanguage::select(
                'languages.id',
                'languages.language'
            )->leftJoin(
                'languages',
                'brand_state_languages.language_id',
                'languages.id'
            )->where(
                'brand_state_id',
                $bsa->id
            )->orderBy(
                'brand_state_languages.language_id'
            )->get()->toArray();
        } else {
            return [];
        }
    }

    public function checkHoursOfOperation($state_id, $brand_id)
    {
        $open_override = DB::table('runtime_settings')
            ->select('value')
            ->where('namespace', 'system')
            ->where('name', 'open_at_override')
            ->first();

        $close_override = DB::table('runtime_settings')
            ->select('value')
            ->where('namespace', 'system')
            ->where('name', 'close_at_override')
            ->first();

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
            'brand_hour_' . $brand_id . '_' . $state_id,
            300,
            function () use ($brand_id, $state_id) {
                return BrandHour::where('brand_id', $brand_id)
                    ->where('state_id', $state_id)
                    ->first();
            }
        );

        $now = Carbon::now('America/Chicago');
        $dayofweek = $now->copy()->formatLocalized('%A');
        $month_day = $now->copy()->formatLocalized('%m-%d');

        if ($hours) {
            $hours_array = $hours->toArray();
            if (isset($hours_array['data'])) {
                $hour_data = $hours_array['data'];
            } else {
                $hour_data = null;
            }
        } else {
            $hour_data = null;
        }

        $open = (isset($hour_data)
            && isset($hour_data[$dayofweek])
            && isset($hour_data[$dayofweek]['open'])
            && 'Closed' != $hour_data[$dayofweek]['open'])
            ? $now->copy()->hour(explode(':', $hour_data[$dayofweek]['open'])[0])->minute(0)->second(0)
            : null;

        if ($open_override !== null && $open_override->value !== '') {
            if ($open !== null) {
                $arr = explode(':', $open_override->value);
                if (count($arr) == 2) {
                    info('using open override');
                    $open = $now->copy()->hour($arr[0])->minute($arr[1])->second(0);
                }
            }
        }

        $close = (isset($hour_data)
            && isset($hour_data[$dayofweek])
            && isset($hour_data[$dayofweek]['close'])
            && 'Closed' != $hour_data[$dayofweek]['close'])
            ? $now->copy()->hour(explode(':', $hour_data[$dayofweek]['close'])[0])->minute(0)->second(0)
            : null;

        if ($close_override !== null && $close_override->value !== '') {
            if ($close !== null) {
                $arr = explode(':', $close_override->value);
                if (count($arr) == 2) {
                    info('using close override');
                    $close = $now->copy()->hour($arr[0])->minute($arr[1])->second(0);
                }
            }
        }

        // Check if today is an exception (usually means a holiday)
        if (
            isset($hour_data['Exceptions'])
            && isset($hour_data['Exceptions']['Closed'])
        ) {
            for ($i = 0; $i < count($hour_data['Exceptions']['Closed']); ++$i) {
                if ($month_day == $hour_data['Exceptions']['Closed'][$i]) {
                    return $this->rejectCallWithComment(
                        'Sorry!  We are currently closed.'
                    );
                }
            }
        }

        $continue = ($open !== null && $close !== null) ? $now->between($open, $close) : false;
        info('Hours of Operation Check for (' . $brand_id . ') in state (' . $state_id . ')', [
            'continue' => $continue ? 'true' : 'false',
            'NOW' => $now->toIso8601String(),
            'OPEN' => $open !== null ? $open->toIso8601String() : 'null',
            'CLOSE' => $close !== null ? $close->toIso8601String() : 'null',
        ]);

        return ['hours' => $hours, 'continue' => $continue];
    }

    public function completeIVR(Request $request)
    {
        $response = new Twiml();

        // Log::debug(print_r($request->all(), true));

        switch ($request->get('language_id')) {
            case 2:
                $language = 'Spanish';
                break;
            default:
                $language = 'English';
                break;
        }

        $JsonData = [
            'brand_id' => $request->get('brand_id'),
            'client' => $request->get('ivr_company_name'),
            'agent' => ($request->get('agent_id')) ?
                $request->get('agent_id') : null,
            'state_id' => $request->get('state_id'),
            'selected_language' => $language,
            'channel_id' => ($request->get('channel_id'))
                ? $request->get('channel_id') : 1,
            'type' => $request->get('type'),
            'platform' => $request->get('platform'),
            'skill_name' => $request->get('skill_name'),
            'dnis_channel_id' => $request->get('dnis_channel_id'),
            'dnis_market_id' => $request->get('dnis_market_id'),
            'event_id' => $request->get('event_id'),
            'script_id' => $request->get('script_id'),
        ];

        if($request->get('type') === "inbound_customer_only") {
            $JsonData['lang_id'] = $request->get('language_id');
        }

        $response
            ->enqueue(null, ['workflowSid' => config('services.twilio.workflow')])
            ->task(
                json_encode(
                    $JsonData
                )
            );

        info("JSON DATA: ", $JsonData);

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function processVoicePrint()
    {
        info('In IvrController@processVoicePrint', request()->all());
        $thanks = [
            'en' => 'Thank you. Your verification is complete. You may now hang up. Goodbye.',
            'es' => 'Gracias. Su verificación está completa. Ahora puede colgar. Adiós.',
        ];
        $lang = 'en';
        // recv notification of recording status
        $confirmation = request()->input('confirmation');
        $callSid = request()->input('CallSid');
        $parentSid = request()->input('ParentCallSid');
        $from = request()->input('From');
        $to = request()->input('To');
        $callStatus = request()->input('CallStatus');
        $url = request()->input('RecordingUrl');
        $duration = request()->input('RecordingDuration');
        $digits = request()->input('Digits'); // should be '#', if the recording was ended by the customer hanging up this will be 'hangup'

        $event = Event::where('confirmation_code', $confirmation)->first();
        switch ($event->language_id) {
            case 2:
                $lang = 'es';
                break;
        }

        $interaction = new Interaction();
        $interaction->created_at = Carbon::now('America/Chicago');
        $interaction->event_id = $event->id;
        $interaction->interaction_type_id = 8; // voice_imprint
        $interaction->interaction_time = $duration > 0 ? $duration / 60 : 0;
        $interaction->session_id = $callSid;
        $interaction->session_call_id = $callSid;
        $interaction->notes = request()->all();
        $interaction->save();

        if ($digits !== 'hangup') {
            $response = new Twiml();
            $response->say($thanks[$lang], [
                'voice' => 'woman',
                'language' => $lang,
            ]);
            $response->hangup();

            return response($response, 200, ['Content-Type' => 'application/xml']);
        } else {
            return response('', 200);
        }
    }



    public function getVoicePrint()
    {
        // Answers the voice print call and prompts the user to press a key to begin
        info('In IvrController@getVoicePrint', request()->all());
        $prompts = [
            'en' => [
                'Hello, this is TPV dot com.',
                'This short call is needed for us to capture your voice for enrollment verification. Please press the number one key to begin.',
            ],
            'es' => [
                'Hola, este es TPV dot com.',
                'Se necesita esta llamada corta para nosotros para capturar su voz para la verificación de la inscripción. Por favor, pulse la tecla número uno para comenzar.',
            ],
        ];
        $cnt = request()->input('rcount');
        if ($cnt == null) {
            $cnt = 0;
        }

        $lang = 'en';
        $confirmation = request()->input('confirmation');
        $event = Event::where('confirmation_code', $confirmation)->first();
        switch ($event->language_id) {
            case 2:
                $lang = 'es';
                break;
        }
        $response = new Twiml();
        if ($cnt > 2) {
            $response->hangup();
        } else {
            if ($cnt == 0) {
                $response->pause(['length' => 3]); // give them time to be listening
            } else {
                $response->pause(['length' => 1]);
            }
            $response->say($prompts[$lang][0], [ // introduce ourselves
                'voice' => 'woman',
                'language' => $lang,
            ]);

            $response->pause(['length' => 2]);
            $gather = $response->gather(
                [
                    'numDigits' => 1,
                    'action' => str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/voiceimprint/start?confirmation=' . $confirmation)),
                ]
            );
            $gather->say($prompts[$lang][1], [ //  directions
                'voice' => 'woman',
                'language' => $lang,
            ]);
            $cnt += 1;
            $response->redirect(
                str_replace(':/', '://', str_replace('//', '/', config('app.url')) . '/api/twilio/voiceimprint?') . http_build_query(
                    [
                        'confirmation' => $confirmation,
                        'rcount' => $cnt
                    ]
                )
            );
            $response->record([
                'method' => 'POST',
                'action' => str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/voiceimprint/complete?confirmation=' . $confirmation)),
                'timeout' => 0,
                'transcribe' => false,
                'trim' => 'do-not-trim',
                'playBeep' => true,
                // 'finishOnKey' => '#',//default is any key
                'maxLength' => 60 * 5, // five minutes
            ]);
        }

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function startVoicePrintAfterHumanDetect()
    {
        $prompts = [
            'en' => [
                'After the tone, please clearly state your full name and service address. When you are finished, please hang up to complete your verification.',
            ],
            'es' => [
                'Después del tono, indique claramente su nombre completo y dirección de servicio. Cuando haya terminado, cuelgue para completar su verificación.',
            ],
        ];
        $lang = 'en';
        $confirmation = request()->input('confirmation');
        $event = Event::where('confirmation_code', $confirmation)->first();
        switch ($event->language_id) {
            case 2:
                $lang = 'es';
                break;
        }
        $response = new Twiml();

        $response->pause(['length' => 1]);
        $response->say($prompts[$lang][0], [ // voiceprint directions
            'voice' => 'woman',
            'language' => $lang,
        ]);
        $response->pause(['length' => 2]);
        $response->record([
            'method' => 'POST',
            'action' => str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/voiceimprint/complete?confirmation=' . $confirmation)),
            'timeout' => 0,
            'transcribe' => false,
            'trim' => 'do-not-trim',
            'playBeep' => true,
            // 'finishOnKey' => '#',//default is any key
            'maxLength' => 60 * 5, // five minutes
        ]);

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Parses the account name from a SIP address.
     * If the $address is not actually a SIP address, returns $address with no changes.
     */
    private function parseSipAddress($address)
    {
        // SIP address format: sip:<account>@<domain>
        // Extract the string between 'sip:' and '@'
        if(strpos($address, 'sip:') !== false && strpos($address, '@') !== false) {
            $arr = explode('sip:', $address);
            if(isset($arr[1])) {
                $arr = explode('@', $arr[1]);
                return $arr [0];
            }
        }

        return $address; // No changes
    }

    /**
     * Main IVR entry point.
     */
    public function ivr()
    {
        $isHighVolume = $this->highVolume();
        $type = request()->input('type');
        if (null == $type) {
            $type = 'ivr';
        }

        switch ($type) {
            case 'ivr':
                if(strpos(request()->input('From'), 'sip:') !== false) { // Type-safe check as sip will be at index 0
                    $from = $this->parseSipAddress(request()->input('From'));
                } else {
                    $from = CleanPhoneNumber(request()->input('From'));
                }

                if(strpos(request()->input('To'), 'sip:') !== false) {
                    $to = $this->parseSipAddress(request()->input('To'));
                } else {
                    $to = CleanPhoneNumber(request()->input('To'));   
                }                

                $tophone = $this->toPhone($to);
                if (null == $tophone) {
                    return $this->rejectCallWithComment('This third party verification line is no longer active. Please contact your supervisor for further assistance.');
                }
                info('tophone is', [$tophone]);
                if ($tophone->script_type === 'IVR') {
                    return $this->ivrScriptStart();
                }

                $ivr_company = $this->lookupCompany($tophone);

                if (isset($tophone->config) && strlen(trim($tophone->config)) > 0) {
                    if (is_string($tophone->config)) {
                        $tophone->config = json_decode($tophone->config, true);
                    }

                    if (!empty($tophone->config)) {
                        $configuredType = empty($tophone->config['type']) ? 'flyer' : $tophone->config['type'];

                        $finishInboundOnly = function ($event_id, $tophone, $ivr_company) {
                            $configuredType2 = empty($tophone->config['type']) ? 'flyer' : $tophone->config['type'];

                            $results = [
                                'method' => 'POST',
                                'type' => 'inbound_customer_only',
                                'brand_id' => $tophone['brand_id'],
                                'script_id' => $tophone['script_id'],
                                'event_id' => $event_id, // when null this will prompt Agents to lookup the customer's info
                                'skill_name' => $tophone['skill_name'],
                                'platform' => $tophone['platform'],
                                'client' => $ivr_company->name,
                            ];

                            // Default next route for inbound customer-only calls. No other steps needed. Proceed to 'complete' step.
                            $nextRoute = 'complete';

                            // If inbound (not flyer) and configured, send to state/language selection
                            if(
                                strtolower($configuredType2) == 'inbound' 
                                && isset($tophone->config['stateLanguageMenu']) 
                                && $tophone->config['stateLanguageMenu'] == true 
                            ) {
                                $results['states'] = $tophone['states'];

                                $nextRoute = 'states-inbound-customer-only';
                            } else {
                                // For vista, route to custom language selection first.
                                if ( in_array( $tophone['brand_id'] , self::VISTA_BRAND_IDS) ) {
                                    $nextRoute = 'languages-inbound';
                                }
                            }

                            $response = new Twiml();
                            $response->redirect(
                                route(
                                    $nextRoute,
                                    $results,
                                    false
                                )
                            );

                            info('Sending Inbound only call', $results);

                            return response($response, 200, ['Content-Type' => 'application/xml']);
                        };

                        switch ($configuredType) {
                            case 'inbound':
                                return $finishInboundOnly(null, $tophone, $ivr_company);
                                break;

                            case 'flyer':
                            default:
                                // this behavior is expected for the Rushmore flyer dnis
                                $vendor = Vendor::where(
                                    'brand_id',
                                    $tophone['brand_id']
                                )->where(
                                    'vendors.vendor_label',
                                    $tophone->config['vendor']
                                )->first();
                                if (!$vendor) {
                                    return $this->rejectCallWithComment('This number does not have a valid vendor configuration.');
                                }

                                $user = BrandUser::where(
                                    'brand_users.works_for_id',
                                    $tophone['brand_id']
                                )->where(
                                    'brand_users.employee_of_id',
                                    $vendor->vendor_id
                                )->where(
                                    'brand_users.tsr_id',
                                    $tophone->config['sales_agent']
                                )->first();
                                if (!$user) {
                                    return $this->rejectCallWithComment('This number does not have a valid sales agent configuration.');
                                }

                                // $office = BrandUserOffice::select(
                                //     'offices.name',
                                //     'brand_user_offices.office_id'
                                // )->leftJoin(
                                //     'offices',
                                //     'brand_user_offices.office_id',
                                //     'offices.id'
                                // )->where(
                                //     'brand_user_offices.brand_user_id',
                                //     $user->id
                                // )->first();

                                $office = Office::select(
                                    'offices.name',
                                    'brand_user_offices.office_id'
                                )->join(
                                    'brand_user_offices', 
                                    'offices.id', 
                                    'brand_user_offices.office_id'
                                )->where(
                                    'offices.name', 
                                    $tophone->config['office']
                                )->where(
                                    'brand_user_offices.brand_user_id', 
                                    $user->id
                                )->first();
                                

                                if (!$office) {
                                    return $this->rejectCallWithComment('This number does not have a valid office configuration.');
                                }

                                $event = new Event();
                                $event->brand_id = $tophone['brand_id'];
                                $event->channel_id = ($tophone->channel_id)
                                    ? $tophone->channel_id : 1;
                                $event->vendor_id = $vendor->vendor_id;
                                $event->office_id = $office->office_id;
                                $event->script_id = $tophone['script_id'];
                                $event->language_id = 1;
                                $event->generateConfirmationCode();
                                $event->sales_agent_id = $user->id;
                                $event->save();
                                if (!$event) {
                                    return $this->rejectCallWithComment('This number errored during event creation.');
                                }
                                return $finishInboundOnly($event->id, $tophone, $ivr_company);
                                break;
                        }
                    }
                }


                $config_answers = $this->configAnswers($tophone);
                $whoareyou = $this->lookupRep($from, $tophone, $config_answers);
                $agent_id = (isset($whoareyou) && isset($whoareyou->id))
                    ? $whoareyou->id : null;

                $response = new Twiml();
                $response->say(
                    $this->_welcome,
                    ['voice' => 'woman']
                );

                if ($isHighVolume) {
                    $response->say($this->highVolumeMessage, ['voice' => 'woman']);
                }

                if ($ivr_company !== null) {
                    $response->redirect(
                        route(
                            'states',
                            [
                                'method' => 'POST',
                                'states' => $tophone['states'],
                                'brand_id' => $tophone['brand_id'],
                                'ivr_company_name' => $ivr_company->name,
                                'agent_id' => $agent_id,
                                'channel_id' => ($tophone->channel_id)
                                    ? $tophone->channel_id : 1, // defaults to DTD,
                                'type' => ($tophone->script_type)
                                    ? strtolower($tophone->script_type) : 'energy',
                                'platform' => ($tophone->platform)
                                    ? $tophone->platform : 'focus',
                                'skill_name' => ($tophone->skill_name)
                                    ? $tophone->skill_name : null,
                                'dnis_channel_id' => ($tophone->dnis_channel_id)
                                    ? $tophone->dnis_channel_id : null,
                                'dnis_market_id' => ($tophone->dnis_market_id)
                                    ? $tophone->dnis_market_id : null,
                            ],
                            false
                        )
                    );
                } else {
                    $response->say('This number is not configured correctly, please contact Client Services.', ['voice' => 'woman']);
                }

                return response($response, 200, ['Content-Type' => 'application/xml']);

            case 'make-outgoing':
                $to = request()->input('to');
                $from = request()->input('from');

                $response = new Twiml();
                $response->dial($to, ['callerId' => $from, 'record' => true]);

                return response($response, 200, ['Content-Type' => 'application/xml']);
        }
    }

    public function ivrScriptStart()
    {
        $response = new Twiml();
        $response->redirect(route('start-ivr-get-lang'));
        return response($response, 200, ['Content-Type' => 'application/xml']);
    }
}
