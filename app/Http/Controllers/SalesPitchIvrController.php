<?php

namespace App\Http\Controllers;

use Twilio\TwiML\VoiceResponse as Twiml;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\SalesPitch;
use App\Models\Interaction;

class SalesPitchIvrController extends Controller
{
    public function startSalesPitch_inbound(Request $request)
    {
        info('Inbound Start Sales Pitch', $request->input());

        $response = new Twiml();
        $response->pause(['length' => 2]);
        $prompts = [
            'Hello, thank you for calling TPV dot coms Sales Pitch Capture Service',
            'For English, press 1',
            'Para español, presione el número 3',
        ];
        $response->say($prompts[0], [
            'voice' => 'woman',
            'language' => 'en',
        ]);

        $gather = $response->gather(
            [
                'input' => 'dtmf',
                'action' => str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/set-lang')),
                'numDigits' => 1,
                'timeout' => 7,
            ]
        );

        $gather->say($prompts[1], [
            'voice' => 'woman',
            'language' => 'en',
        ]);

        $gather->say($prompts[2], [
            'voice' => 'woman',
            'language' => 'es',
        ]);

        $response->redirect(str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/call-start')));

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function getLanguageAndPromptForRefId(Request $request)
    {
        info('Inbound Sales Pitch - get lang', $request->input());
        $rPromptCount = 0;
        $inputLang = $request->input('Digits');
        $lang = 'en';
        $skipLang = false;
        if ($request->has('rprompt')) {
            $rPromptCount = intval($request->input('rprompt'));
        }
        if ($request->has('lang')) {
            $skipLang = true;
            $lang = $request->input('lang');
        }
        $prompts = [
            'en' => [
                'Thank You!',
                'Please enter your 9 digit sales pitch reference i.d.',
                'No Input Detected',
                'Goodbye!'
            ],
            'es' => [
                'Gracias!',
                'Ingrese su I.D. de referencia de argumento de venta de 9 dígitos.',
                'Ninguna entrada detectada',
                'Adiós'
            ],
        ];

        $response = new Twiml();
        if ($rPromptCount > 3) {
            $response->say($prompts[$lang][2], [
                'voice' => 'woman',
                'language' => $lang
            ]);

            $response->pause(['length' => 1]);

            $response->say($prompts[$lang][3], [
                'voice' => 'woman',
                'language' => $lang
            ]);

            $response->hangup();
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }

        if (!$skipLang) {
            switch ($inputLang) {
                case 1:
                    $lang = 'en';
                    break;
                case 2:
                    $lang = 'es';
                    break;
                default:
                    $response->redirect(str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/call-start')));
                    return response($response, 200, ['Content-Type' => 'application/xml']);
            }

            $response->pause(['length' => 1]);
            $response->say($prompts[$lang][0], [
                'voice' => 'woman',
                'language' => $lang,
            ]);
        }
        $response->pause(['length' => 1]);

        $gather = $response->gather(
            [
                'input' => 'dtmf',
                'action' => str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/set-ref-id?noConf=1&lang=' . $lang)),
                'numDigits' => 9,
                'timeout' => 10,
            ]
        );

        $gather->say($prompts[$lang][1], [
            'voice' => 'woman',
            'language' => $lang,
        ]);

        $response->say($prompts[$lang][2], [
            'voice' => 'woman',
            'language' => $lang
        ]);

        $response->pause(['length' => 2]);

        $rPromptCount += 1;

        $response->redirect(str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/set-lang?rprompt=' . $rPromptCount . '&lang=' . $lang)));


        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function validateRefId(Request $request)
    {
        info('Inbound Sales Pitch Validate RefId', $request->input());
        $inputRefId = $request->input('Digits');
        $noConf = 0;
        if ($request->has('noConf')) {
            $noConf = intval($request->input('noConf'));
        }
        $lang = $request->input('lang');
        $prompts = [
            'en' => [
                'Invalid Sales Pitch Reference I.D.',
                'When the sales pitch is complete press the star key to finish recording.',
            ],
            'es' => [
                'I.D. de referencia de tono de venta no válido',
                'Cuando el argumento de venta esté completo, presione la tecla de asterisco para finalizar la grabación.',
            ],
        ];
        $response = new Twiml();

        if (!empty($inputRefId)) {
            $sp = SalesPitch::where('ref_id', $inputRefId)->first();
            if (empty($sp)) {
                // fail
                $response->say($prompts[$lang][0], [
                    'voice' => 'woman',
                    'language' => $lang,
                ]);
                $response->pause(['length' => 1]);
                $response->redirect(str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/set-lang?lang=' . $lang)));
                return response($response, 200, ['Content-Type' => 'application/xml']);
            }

            // PASS
            $agent = $sp->sales_agent_id;
            $brand = $sp->brand_id;

            $response->pause(['length' => 1]);
            $response->say($prompts[$lang][1], [
                'voice' => 'woman',
                'language' => $lang,
            ]);
            $response->redirect(str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/continue')) . '?noConf=' . $noConf . '&inbound=1&lang=' . $lang . '&agent=' . $agent . '&brand=' . $brand . '&transcribe=1&ref_id=' . $inputRefId);
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }

        // fail
        $response->say($prompts[$lang][0], [
            'voice' => 'woman',
            'language' => $lang,
        ]);
        $response->pause(['length' => 1]);
        $response->redirect(str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/set-lang?lang=' . $lang)));
        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function startSalesPitch_outbound(Request $request)
    {
        $noConf = 0;
        if ($request->has('noConf')) {
            $noConf = intval($request->input('noConf'));
        }
        info('In IvrController@getVoicePrint', request()->all());
        $prompts = [
            'en' => [
                'Hello, this is TPV dot com.',
                'This call is to record the sales pitch.',
                'When the sales pitch is complete press the star key to finish recording.',
            ],
            'es' => [
                'Hola, este es TPV dot com.',
                'Esta llamada es para registrar el argumento de venta.',
                'Cuando el argumento de venta esté completo, presione la tecla de asterisco para finalizar la grabación.',
            ],
            'continue' => [
                'en' => [
                    'Hello, this is TPV dot com.',
                    'The sales pitch recording call was disconnected before it was complete.',
                    'This call will record the remainder of the sales pitch.',
                    'When the sales pitch is complete press the star key to finish recording.',
                ],
                'es' => [
                    'Hola, este es TPV dot com.',
                    'La llamada de grabación del argumento de venta se desconectó antes de que se completara.',
                    'Esta llamada registrará el resto del argumento de venta.',
                    'Cuando el argumento de venta esté completo, presione la tecla de asterisco para finalizar la grabación.'
                ],
            ],
            'gather' => [
                'en' => ['Press any button to begin recording.'],
                'es' => ['Presione cualquier botón para comenzar a grabar.'],
            ],
        ];
        $isContinue = false;
        if ($request->has('continue')) {
            if ($request->input('continue') == 1) {
                $isContinue = true;
            }
        }
        $agent = $request->input('agent');
        $brand = $request->input('brand');
        $ref_id = $request->input('ref_id');
        if ($ref_id === null) {
            abort(400);
        }
        $transcribe = false;
        if ($request->has('transcribe')) {
            $transcribe = $request->input('transcribe') == 1 ? true : false;
        }

        $lang = 'en';
        if ($request->has('lang')) {
            $lang = $request->input('lang');
        }

        $response = new Twiml();
        $response->pause(['length' => 2]); // give them time to be listening
        $toRead = $isContinue ? $prompts['continue'][$lang] : $prompts[$lang];
        for ($i = 0, $len = count($toRead); $i < $len; $i += 1) {
            $response->say($toRead[$i], [ // introduce ourselves
                'voice' => 'woman',
                'language' => $lang,
            ]);
            //$response->pause(['length' => 1]);
        }

        $gather = $response->gather(
            [
                'input' => 'dtmf',
                'action' => str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/continue?noConf=' . $noConf . '&ref_id=' . $ref_id . '&lang=' . $lang . ($transcribe ? '&transcribe=1' : '') . '&brand=' . $brand . '&agent=' . $agent)),
                'numDigits' => 1,
                'timeout' => 7,
            ]
        );

        for ($i = 0, $len = count($prompts['gather'][$lang]); $i < $len; $i += 1) {
            $gather->say($prompts['gather'][$lang][$i], [ // introduce ourselves
                'voice' => 'woman',
                'language' => $lang,
            ]);
            //$gather->pause(['length' => 1]);
        }

        $response->redirect(str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/start?noConf=' . $noConf . '&ref_id=' . $ref_id . '&lang=' . $lang . ($transcribe ? '&transcribe=1' : '') . '&brand=' . $brand . '&agent=' . $agent)));

        return response($response)->header('Content-Type', 'application/xml');
    }

    public function processSalesPitch(Request $request)
    {
        $noConf = 0;
        if ($request->has('noConf')) {
            $noConf = intval($request->input('noConf'));
        }
        $inbound = $request->input('inbound');
        $callSid = $request->input('CallSid');
        $agent = $request->input('agent');
        $lang = 'en';
        if ($request->has('lang')) {
            $lang = $request->input('lang');
        }
        $brand = $request->input('brand');
        $ref_id = $request->input('ref_id');
        $transcribe = false;
        if ($request->has('transcribe')) {
            $transcribe = $request->input('transcribe') == 1 ? true : false;
        }

        //create DB records
        $interaction = new Interaction();
        $interaction->created_at = Carbon::now('America/Chicago');
        $interaction->updated_at = Carbon::now('America/Chicago');
        $interaction->interaction_type_id = 21;
        $interaction->session_call_id = $callSid;
        $interaction->session_id = $ref_id;
        $interaction->notes = $request->input();
        $interaction->save();

        $sp = new SalesPitch();
        $sp->created_at = Carbon::now('America/Chicago');
        $sp->updated_at = Carbon::now('America/Chicago');
        $sp->ref_id = $ref_id;
        $sp->lang = $lang;
        $sp->interaction_id = $interaction->id;
        $sp->sales_agent_id = $agent;
        $sp->brand_id = $brand;
        $sp->save();

        $response = new Twiml();
        $response->record([
            'method' => 'POST',
            'action' => str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/complete?noConf=' . $noConf . '&interaction=' . $interaction->id . '&lang=' . $lang . '&ref_id=' . $ref_id . ($transcribe ? '&transcribe=1' : '') . '&brand=' . $brand . '&agent=' . $agent . '&inbound=' . $inbound)),
            'timeout' => 0,
            'transcribe' => $transcribe,
            'trim' => 'do-not-trim',
            'playBeep' => true,
            'finishOnKey' => '*',
            'maxLength' => 60 * 60, // 1 hour
            'recordingStatusCallback' => str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/hook?command=recording:status&interaction=' . $interaction->id . '&brand=' . $brand)),
            'recordingStatusCallbackMethod' => 'POST',
            'recordingStatusCallbackEvent' => 'completed',
        ]);

        return response($response)->header('Content-Type', 'application/xml');
    }

    public function completeSalesPitch(Request $request)
    {
        $noConf = 0;
        if ($request->has('noConf')) {
            $noConf = intval($request->input('noConf'));
        }
        //info('Complete Sales Pitch', $request->input());
        $interaction_id = $request->input('interaction');
        $agent = $request->input('agent');
        $brand = $request->input('brand');
        $url = $request->input('RecordingUrl');
        $duration = $request->input('RecordingDuration');
        $repeating = $request->input('repeating');
        $thanks = [
            'en' => [
                'Thank you. Your sales pitch is complete. You may now hang up. Goodbye.',
                'Thank you. Your sales pitch is complete. Your confirmation number is $$, you may now hang up.',
            ],
            'es' => [
                'Gracias. Su argumento de venta está completo. Ahora puede colgar. Adiós.',
                'Gracias. Su argumento de venta está completo. Su número de confirmación es $$, ahora puede colgar.'
            ],
        ];
        $lang = 'en';
        if ($request->has('lang')) {
            $lang = $request->input('lang');
        }
        $digits = $request->input('Digits');
        $ref_id = $request->input('ref_id');
        $transcribe = false;
        if ($request->has('transcribe')) {
            $transcribe = $request->input('transcribe') == 1 ? true : false;
        }
        $isComplete = false;
        $isInbound = false;
        if ($noConf != 1) {
            $isInbound = true;
        }

        if ($digits !== 'hangup' && $digits !== null) {
            $isComplete = true;
            info('Sales Pitch Completed', [$digits]);
        } else {
            info('Sales Pitch NOT Completed', [$digits]);
        }

        if (empty($repeating)) {
            $interaction = Interaction::find($interaction_id);
            $interaction->interaction_time = $duration > 0 ? $duration / 60 : 0;
            $interaction->notes = $request->input();
            $interaction->event_result_id = 2; // this is how we will tell this portion is not in progress
            $interaction->save();

            // fetch recording
            $recordingUrl = $request->input('RecordingUrl');
            $recordingDuration = $request->input('RecordingDuration');
            if ($recordingDuration === null) {
                $recordingDuration = 0;
            }
            $callSid = $request->input('CallSid');
            if ($recordingUrl !== null) {
                Artisan::queue('fetch:recording:single', [
                    '--interaction' => $interaction_id,
                    '--url' => $recordingUrl,
                    '--brand' => $brand,
                    '--callid' => $callSid,
                    '--duration' => $recordingDuration > 0 ? $recordingDuration / 60 : 0,
                ]);
            }
        }
        // end fetch recording

        if ($isComplete) {
            $response = new Twiml();
            if ($isInbound) {
                $response->say($thanks[$lang][0], [
                    'voice' => 'woman',
                    'language' => $lang,
                ]);

                $response->hangup();
            } else {
                $sp = SalesPitch::where('ref_id', $ref_id)->where('brand_id', $brand)->where('interaction_id', $interaction_id)->first();
                if (!empty($sp->confirmation_code)) {
                    $conf = $sp->confirmation_code;
                } else {
                    $conf = $sp->generateConfirmationCode();
                    $sp->save();
                }
                $cArray = str_split($conf, 1);
                $conf = '';
                foreach ($cArray as $cChar) {
                    $conf .= $cChar . ', ';
                }

                $msg = str_replace('$$', $conf, $thanks[$lang][1]);
                $response->say($msg, [
                    'voice' => 'woman',
                    'language' => $lang,
                ]);
                $response->pause(['length' => 2]);
                if (empty($repeating)) {
                    $repeating = 1;
                } else {
                    $repeating += 1;
                }
                if ($repeating < 4) {
                    $response->redirect(str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/complete?Digits=1&interaction=' . $interaction_id . '&lang=' . $lang . '&ref_id=' . $ref_id . ($transcribe ? '&transcribe=1' : '') . '&brand=' . $brand . '&agent=' . $agent . '&repeating=' . $repeating)));
                } else {
                    $response->hangup();
                }
            }

            return response($response)->header('Content-Type', 'application/xml');
        }

        // Start callback
        $url = str_replace(':/', '://', str_replace('//', '/', config('app.url') . '/api/twilio/sales-pitch/start?ref_id=' . $ref_id . '&lang=' . $lang . ($transcribe ? '&transcribe=1' : '') . '&brand=' . $brand . '&agent=' . $agent));
        if (!$isInbound) {
            Artisan::call('twilio:call-ivr', [
                '--incomplete' => true,
                '--to' => $request->input('To'),
                '--from' => $request->input('From'),
                '--url' => $url
            ]);
        } else {
            Artisan::call('twilio:call-ivr', [
                '--incomplete' => true,
                '--from' => $request->input('To'), // To and From are swapped here
                '--to' => $request->input('From'),
                '--url' => $url
            ]);
        }

        $response = new Twiml();
        $response->say('Call is not complete', [
            'voice' => 'woman',
            'language' => 'en',
        ]);
        $response->hangup();

        return response($response)->header('Content-Type', 'application/xml');
    }
}
