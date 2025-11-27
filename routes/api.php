<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(
    [
        'prefix' => 'mailgun',
        'middleware' => [
            'mailgun.webhook',
            'throttle:10000,1'
        ]
    ],
    function () {
        Route::post('incoming', 'MailgunController@store');
    }
);

Route::group(
    [
        'middleware' => [
            'throttle:30000,1'
        ]
    ],
    function () {
        // try as hard as we can to not limit the ivr
        Route::get('twilio/ivr', 'TwilioController@ivr');
        Route::post('twilio/ivr', 'TwilioController@ivr');

        Route::post('twilio/sales-pitch/call-start', 'SalesPitchIvrController@startSalesPitch_inbound');
        Route::post('twilio/sales-pitch/set-lang', 'SalesPitchIvrController@getLanguageAndPromptForRefId');
        Route::post('twilio/sales-pitch/set-ref-id', 'SalesPitchIvrController@validateRefId');

        Route::post('twilio/sales-pitch/start', 'SalesPitchIvrController@startSalesPitch_outbound');
        Route::post('twilio/sales-pitch/continue', 'SalesPitchIvrController@processSalesPitch');
        Route::post('twilio/sales-pitch/complete', 'SalesPitchIvrController@completeSalesPitch');

        Route::post('twilio/voiceimprint', 'IvrController@getVoicePrint');
        Route::post('twilio/voiceimprint/start', 'IvrController@startVoicePrintAfterHumanDetect');
        Route::post('twilio/voiceimprint/complete', 'IvrController@processVoicePrint');

        Route::post('twilio/sms/status-update', 'TwilioController@updateSmsStatus');
        Route::post('twilio/sms/incoming-message', 'TwilioController@incomingSms')->middleware('twilio.requestValiator');
    }
);

Route::group(
    [
        'middleware' => [
            'throttle:1000,1', // default routes are throttled to 60 requests per client per minute
        ],
    ],
    function () {
        // default throttle rules apply to these routes

        // Amazon
        Route::post('amazon/sns', 'AmazonSnsProcessor@process_request');

        Route::post('webhook/shieldscreening', 'WebhookController@shieldscreening');
        Route::any('webhook/recordings', 'WebhookController@recordingCompleted');
        Route::post('webhook/eventCallback', 'WebhookController@eventCallback');

        // Alerts
        Route::post(
            'alerts/check-existing-sales-agent-name',
            'ClientAlertController@checkExistingSalesAgentName'
        );

        // Twilio
        Route::post('twilio/events', 'TwilioController@taskrouterWebhook');
        Route::get('twilio/calls', 'TwilioController@calls')->name('twilio.calls');

        Route::options('twilio/changeToConference/{callSid}', 'ClientAlertController@sayItsOk');
        Route::post('twilio/changeToConference/{callSid}', 'TwilioController@changeCallToConference');
        Route::options('twilio/changeToConferenceTwiml/{callSid}', 'ClientAlertController@sayItsOk');
        Route::post('twilio/changeToConferenceTwiml/{callSid}', 'TwilioController@changeCallToConferenceTwiml');
        Route::options('twilio/addParticipantToConference', 'ClientAlertController@sayItsOk');
        Route::post('twilio/addParticipantToConference', 'TwilioController@addParticipantToConference');

        Route::get('twilio/update_worker', 'TwilioController@update_worker')->middleware(['auth']);
        Route::get('twilio/activities', 'TwilioController@get_activities')->name('twilio.get_activities');
        Route::get('twilio/queues', 'TwilioController@get_queues')->name('twilio.get_queues');
        Route::get('twilio/tasks', 'TwilioController@get_tasks')->name('twilio.get_tasks');
        Route::get('twilio/workers', 'TwilioController@get_workers')->name('twilio.get_workers');
        Route::get('twilio/workspace/stats', 'TwilioController@get_workspace_stats')->name('twilio.get_workspace_stats');
        Route::get('twilio/workspace/stats/cumulative', 'TwilioController@get_workspace_cumulative_stats')->name('twilio.get_workspace_cumulative_stats');
        Route::get('twilio/workspace/stats/realtime', 'TwilioController@get_workspace_realtime_stats')->name('twilio.get_workspace_realtime_stats');
        Route::get('callcenter/stats', 'TwilioController@get_stats')->name('callcenter.stats');

        Route::post('update-agent-status', 'TwilioController@update_worker')->name('agent.update_status');

        Route::post('update-agent-skills', 'WorkAtHomeController@update_worker_skills')->name('agent.update_skills');
        Route::get('get-call-active-status/{worker_id}', 'WorkAtHomeController@getCallActiveStatus')->name('agent.get_call_active_status');
        Route::post('update-pending-status', 'WorkAtHomeController@updateStatusIfPending')->name('agent.update_pending_status')->middleware(['auth']);
        Route::get('token', 'TwilioController@getToken')->name('twilio.get_token');

        Route::middleware('auth:api')
            ->get(
                '/user',
                'HomeController@user_get'
            );

        Route::options('hook', 'WebhookController@sayItsOk');
        Route::get('hook', 'WebhookController@generic_hook');
        Route::post('hook', 'WebhookController@generic_hook');

        Route::get('/live-agent', 'WorkAtHomeController@get_live_agents')->name('live_agent.dashboard');
        Route::get('/not-ready', 'WorkAtHomeController@get_not_ready_agents')->name('not_ready.dashboard');
        Route::get('/status-list', 'WorkAtHomeController@statuses_select_options')->name('statuses.list');
        Route::get('/skill-list', 'WorkAtHomeController@skills_select_options')->name('skills.list');
    }
); // end default throttling group

Route::post(
    'supervisor/verify/action',
    'HomeController@supervisor_verify'
)->middleware(['throttle:10,5']);

// Rate-Level brands
Route::group(
    [
        'prefix' => 'rate_level_brands',
        'middleware' => [
            'throttle:100,1'
        ]
    ],
    function () {
        Route::post('/load', 'BrandController@load_rate_level_brands')->name('rate_level_brands.load');
        Route::post('/save', 'BrandController@store_rate_level_brands')->name('rate_level_brands.save');
    }
);

// IVR
Route::group(
    [
        'prefix' => 'ivr',
        'middleware' => [
            'throttle:30000,1',
        ],
    ],
    function () {
        Route::any(
            '',
            [
                'as' => 'home',
                'uses' => 'IvrController@ivr',
            ]
        );
        Route::any(
            '/states',
            [
                'as' => 'states',
                'uses' => 'IvrController@ivrStates',
            ]
        );
        Route::any(
            '/states-inbound-customer-only',
            [
                'as' => 'states-inbound-customer-only',
                'uses' => 'IvrController@ivrStatesInboundCustomerOnly',
            ]
        );
        Route::any(
            '/languages',
            [
                'as' => 'languages',
                'uses' => 'IvrController@ivrLanguages',
            ]
        );
        Route::any(
            '/languages-inbound',
            [
                'as' => 'languages-inbound',
                'uses' => 'IvrController@ivrLanguagesInbound',
            ]
        );
        Route::any(
            '/languages-inbound-customer-only',
            [
                'as' => 'languages-inbound-customer-only',
                'uses' => 'IvrController@ivrLanguagesInboundCustomerOnly',
            ]
        );
        Route::any(
            '/complete',
            [
                'as' => 'complete',
                'uses' => 'IvrController@completeIVR',
            ]
        );
        Route::any(
            '/get-state',
            [
                'as' => 'get-state',
                'uses' => 'IvrController@getState',
            ]
        );
        Route::any(
            '/get-state-inbound-customer-only',
            [
                'as' => 'get-state-inbound-customer-only',
                'uses' => 'IvrController@getStateInboundCustomerOnly',
            ]
        );
        Route::any(
            '/get-language',
            [
                'as' => 'get-language',
                'uses' => 'IvrController@getLanguage',
            ]
        );
        Route::any(
            '/get-language-inbound',
            [
                'as' => 'get-language-inbound',
                'uses' => 'IvrController@getLanguageInbound',
            ]
        );
        Route::any(
            '/get-language-inbound-customer-only',
            [
                'as' => 'get-language-inbound-customer-only',
                'uses' => 'IvrController@getLanguageInboundCustomerOnly',
            ]
        );
        Route::any(
            '/get-voiceprint',
            [
                'as' => 'get-voiceprint',
                'uses' => 'IvrController@getVoicePrint',
            ]
        );

        Route::post('/process-voiceprint', 'IvrController@processVoicePrint');
        
        Route::group(['prefix' => 'sq'], function() {
            // ex: <domain>/api/ivr/sq/<resource>

            Route::any('',                                    'SingleQueueIvrController@ivr')->name('sq-home');
            Route::any('/states',                             'SingleQueueIvrController@ivrStates')->name('sq-states');
            Route::any('/states-inbound-customer-only',       'SingleQueueIvrController@ivrStatesInboundCustomerOnly')->name('sq-states-inbound-customer-only');
            Route::any('/languages',                          'SingleQueueIvrController@ivrLanguages')->name('sq-languages');
            Route::any('/languages-inbound',                  'SingleQueueIvrController@ivrLanguagesInbound')->name('sq-languages-inbound');
            Route::any('/languages-inbound-customer-only',    'SingleQueueIvrController@ivrLanguagesInboundCustomerOnly')->name('sq-languages-inbound-customer-only');
            Route::any('/complete',                           'SingleQueueIvrController@completeIVR')->name('sq-complete');
            Route::any('/get-state',                          'SingleQueueIvrController@getState')->name('sq-get-state');
            Route::any('/get-state-inbound-customer-only',    'SingleQueueIvrController@getStateInboundCustomerOnly')->name('sq-get-state-inbound-customer-only');
            Route::any('/get-language',                       'SingleQueueIvrController@getLanguage')->name('sq-get-language');
            Route::any('/get-language-inbound',               'SingleQueueIvrController@getLanguageInbound')->name('sq-get-language-inbound');
            Route::any('/get-language-inbound-customer-only', 'SingleQueueIvrController@getLanguageInboundCustomerOnly')->name('sq-get-language-inbound-customer-only');
            Route::any('/get-voiceprint',         'SingleQueueIvrController@getVoicePrint')->name('sq-get-voiceprint');
            Route::any('/handle-motion-callback', 'SingleQueueIvrController@handleMotionCallback')->name('sq-handle-motion-callback');
            Route::any('/queue-call',             'SingleQueueIvrController@queueCall')->name('sq-queue-call');
            Route::post('/inbound-call-status-change',    'SingleQueueIvrController@inboundCallStatusChange')->name('sq-inbound-call-status-change');
        });

        Route::group(['prefix' => 'script'], function () {
            Route::post('/get-language', 'IvrScriptController@getLanguage')->name('start-ivr-get-lang');
            Route::post('/gather-conf-code', 'IvrScriptController@getConfCode')->name('start-ivr-script-get-code');
            Route::post('/verify-conf-code', 'IvrScriptController@verifyConfCode')->name('start-ivr-script-verify-code');
            Route::post('/start-interaction', 'IvrScriptController@startInteraction')->name('start-ivr-interaction');
            Route::post('/do-question', 'IvrScriptController@doQuestion')->name('ivr-script-question');
            Route::post('/response', 'IvrScriptController@handleQuestionResponse')->name('ivr-script-response');
            Route::post('/finish', 'IvrScriptController@finishInteraction')->name('ivr-script-finish');
        });
    }
);

// Contracts
Route::group(
    [
        'prefix' => 'contracts',
        'middleware' => [
            'throttle:3000,1'
        ]
    ],
    function() {
        Route::post('/generate', 'API\ContractGeneratorApiController@generateContract')->name('contracts-api-generate');
    } 
);

// DXC Proxy
Route::group(
    [
        'prefix' => 'dxc/proxy',
        'middleware' => [
            'throttle:30000,1',
        ]
    ],
    function () {
        Route::get('/getCallCenterStats', 'DXCProxyController@getCallCenterStats')->name('proxy-get-call-center-stats');
        Route::get('/getDowStats', 'DXCProxyController@getDowStats')->name('proxy-get-dow-stats');
        Route::get('/getActiveTpvAgents', 'DXCProxyController@getActiveTpvAgents')->name('proxy-get-active-tpv-agents');
        Route::get('/getTotalCalls', 'DXCProxyController@getTotalCalls')->name('proxy-get-total-calls');
        Route::get('/getPayrollHours', 'DXCProxyController@getPayrollHours')->name('proxy-get-payroll-hours');
        Route::get('/getAvgHandleTime', 'DXCProxyController@getAvgHandleTime')->name('proxy-avg-handle-time');
        Route::get('/getProductiveOccupancy', 'DXCProxyController@getProductiveOccupancy')->name('proxy-get-productive-occupancy');
        Route::get('/getRevenuePerHour', 'DXCProxyController@getRevenuePerHour')->name('proxy-get-revenue-per-hour');
        Route::get('/getAvgCallsByDow', 'DXCProxyController@getAvgCallsByDow')->name('proxy-get-avg-calls-by-dow');
        Route::get('/getAvgTpvAgentsByDow', 'DXCProxyController@getAvgTpvAgentsByDow')->name('proxy-get-avg-tpv-agents-by-dow');
        Route::get('/getAvgCallsByHalfHour', 'DXCProxyController@getAvgCallsByHalfHour')->name('proxy-get-avg-calls-by-half-hour');
        Route::get('/getTpvAgentStats', 'DXCProxyController@getTpvAgentStats')->name('proxy-get-tpv-agent-stats');
    }
);

Route::post('spark/recordings/download', 'API\SparkRecordingApiController@download')->name('download-spark-recording');
Route::get('spark/ping', 'API\SparkRecordingApiController@ping')->name('spark-ping');
Route::post('solomon/recordings', 'API\SolomonRecordingApiController@getRecords')->name('download-solomon-recording');
Route::get('solomon/ping', 'API\SolomonRecordingApiController@ping')->name('solomon-ping');
Route::post('nrg/recordings', 'API\NrgRecordingApiController@getRecords')->name('get-nrg-recordings');
Route::get('nrg/ping', 'API\NrgRecordingApiController@ping')->name('nrg-ping');
