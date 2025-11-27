<?php

namespace App\Http\Controllers;

use Twilio\TwiML\VoiceResponse as Twiml;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use App\Models\ScriptQuestions;
use App\Models\ScriptAnswer;
use App\Models\Interaction;
use App\Models\EventFlagReason;
use App\Models\EventFlag;
use App\Models\Event;
use App\Models\Dnis;
use App\Models\Disposition;
use App\Http\Controllers\SupportController;

class IvrScriptController extends Controller
{
    // English
    // Woman Voices
    // Polly.Joanna-Neural
    // Polly.Ivy-Neural
    // Polly.Kendra-Neural
    // Polly.Kimberly-Neural
    // Polly.Salli-Neural

    // Man Voices
    // Polly.Joey-Neural
    // Polly.Justin-Neural
    // Polly.Matthew-Neural

    // spanish
    // Woman Voices
    // Polly.Lupe-Neural
    // Polly.Penelope

    // Male Voices
    // Polly.Miguel

    private $voice =
    [
        'en' => 'Polly.Joanna-Neural',
        'es' => 'Polly.Lupe-Neural'
    ];

    private $voiceRate = '90%';
    private $slowerVoiceRate = '50%';
    private $voicePitchAdjust = '-0%';
    private $voiceVolumeAdjust = '-0dB';

    private $minimumAcceptPercentage = 75;
    private $minimumCorrectness = 95;


    public function __construct()
    {
        $minAccept = config('app.ivr_minimum_accept', null);
        if ($minAccept === null) {
            $minAccept = runtime_setting('ivr_minimum_accept');
            if ($minAccept !== null) {
                $minAccept = intval($minAccept);
            }
        }
        if ($minAccept !== null && is_int($minAccept) && $minAccept >= 0 && $minAccept <= 100) {
            $this->minimumAcceptPercentage = $minAccept;
        }

        $minCorrect = config('app.ivr_minimum_correct', null);
        if ($minCorrect === null) {
            $minCorrect = runtime_setting('ivr_minimum_correct');
            if ($minCorrect !== null) {
                $minCorrect = intval($minCorrect);
            }
        }
        if ($minCorrect !== null && is_int($minCorrect) && $minCorrect >= 0 && $minCorrect <= 100) {
            $this->minimumCorrectness = $minCorrect;
        }
    }

    private function getScriptInfo($to, $script_id = null)
    {
        if ($script_id !== null) {
            return Cache::remember(
                'ivrscript_' . $script_id,
                300,
                function () use ($script_id) {
                    $ret = Dnis::select(
                        'dnis.brand_id',
                        'scripts.exit_reason',
                        'scripts.id as script_id'
                    )->join(
                        'scripts',
                        'dnis.id',
                        'scripts.dnis_id'
                    )->where(
                        'scripts.id',
                        $script_id
                    )->where('scripts.script_type_id', 5)
                        ->whereNull('scripts.deleted_at')
                        ->first();
                    if ($ret) {
                        return $ret->toArray();
                    }
                    return null;
                }
            );
        }
        if ($to[0] !== '+') {
            $to = '+' . $to;
        }
        return Cache::remember(
            'ivrscript_tophone_' . $to,
            300,
            function () use ($to) {
                $ret = Dnis::select(
                    'dnis.brand_id',
                    'scripts.exit_reason',
                    'scripts.id as script_id'
                )->join(
                    'scripts',
                    'dnis.id',
                    'scripts.dnis_id'
                )->where(
                    'dnis.dnis',
                    $to
                )->where('scripts.script_type_id', 5)
                    ->whereNull('scripts.deleted_at')
                    ->first();
                if ($ret) {
                    return $ret->toArray();
                }
                return null;
            }
        );
    }

    private function getScriptQuestions($scriptId)
    {
        return Cache::remember('ivrscript_questions', 300, function () use ($scriptId) {
            return ScriptQuestions::select(
                'loop',
                'loop_type',
                'section_id',
                'subsection_id',
                'question_id',
                'question',
                'exit_reason',
                'id'
            )->where('script_id', $scriptId)
                ->orderBy('section_id')
                ->orderBy('subsection_id')
                ->orderBy('question_id')
                ->get()
                ->toArray();
        });
    }

    private function getEventDetails($confirmationCode, $brand = null)
    {
        return Cache::remember('ivrscript_' . $confirmationCode, 300, function () use ($confirmationCode, $brand) {
            return SupportController::gatherEventDetails('', $confirmationCode, $brand);
        });
    }

    public function getLanguage() // for now we are bypassing language selection and pulling it from the event
    {
        $script_id = request()->input('script_id');
        $scriptInfo = $this->getScriptInfo(request()->input('To'), $script_id);
        $response = new Twiml();
        if (request()->input('repeat') === null) {
            $this->speak(
                $response,
                'Thank you for calling TPV dot coms Easy Verification Line.',
                'en'
            );
        }
        $gather = $response->redirect(route('start-ivr-script-get-code', ['lang' => 1]), ['method' => 'POST']);
        //$this->speak($gather, 'For English, Press 1', 'en');
        //$this->speak($gather, 'Para español, presione el número 2', 'es');

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function getConfCode()
    {
        $script_id = request()->input('script_id');
        $scriptInfo = $this->getScriptInfo(request()->input('To'), $script_id);
        $lang = request()->input('lang') !== null ? request()->input('lang') : request()->input('Digits');
        if ($lang != 1 && $lang != 2) {
            $response = new Twiml();
            $this->speak($response, 'The entered value is not recognized.', 'en');
            $response->redirect(route('start-ivr-get-lang', ['repeat' => 1]));
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }
        $response = new Twiml();
        $gather = $response->gather(['action' => route('start-ivr-script-verify-code', ['lang' => $lang, 'script_id' => $script_id]), 'method' => 'POST', 'numDigits' => 11]);
        $msg = [
            1 => 'Please enter your 11 digit confirmation code.',
            2 => 'Ingrese su código de confirmación de 11 dígitos.'
        ];
        $this->speak($gather, $msg[$lang], $lang == 1 ? 'en' : 'es');

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function verifyConfCode()
    {
        $script_id = request()->input('script_id');
        $scriptInfo = $this->getScriptInfo(request()->input('To'), $script_id);
        $confCode = request()->input('Digits');
        $lang = request()->input('lang');
        if (strlen($confCode) !== 11) {
            $response = new Twiml();
            $msg = [
                1 => 'The entered value is not recognized.',
                2 => 'El valor introducido no se reconoce.',
            ];
            $this->speak($response, $msg[$lang], $lang == 1 ? 'en' : 'es');
            $response->redirect(route('start-ivr-script-get-code', ['lang' => $lang, 'script_id' => $script_id]));
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }
        $response = new Twiml();
        $raw_ev = Event::where('confirmation_code', $confCode)->first();
        if (!empty($raw_ev) && $raw_ev->script_id != null) {
            $script_id = $raw_ev->script_id;
            $lang = $raw_ev->language_id;
            if ($script_id != null) {
                $scriptInfo = $this->getScriptInfo(request()->input('To'), $script_id);
            }
        }

        $msg = [
            1 => 'You entered ' . $this->sayNumber($confCode),
            2 => 'Entraste en ' . $this->sayNumber($confCode)
        ];
        $this->speak($response, $msg[$lang], $lang == 1 ? 'en' : 'es');
        $gather = $response->gather(['action' => route('start-ivr-interaction', ['lang' => $lang, 'conf' => $confCode, 'script_id' => $script_id]), 'method' => 'POST', 'numDigits' => 1]);
        $msg = [
            1 => 'If correct, press 1. Press 9 to enter again.',
            2 => 'Si es correcto, presione 1. Presione 9 para ingresar nuevamente.'
        ];
        $this->speak($gather, $msg[$lang], $lang == 1 ? 'en' : 'es');
        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function startInteraction()
    {
        $doGreeting = request()->input('greet') == 1;
        $callSid = request()->input('CallSid');
        $script_id = request()->input('script_id');
        $scriptInfo = $this->getScriptInfo(request()->input('To'), $script_id);
        $confCode = request()->input('conf');
        $raw_ev = Event::where('confirmation_code', $confCode)->first();
        if (isset($raw_ev) && isset($script_id) && $raw_ev->script_id != $script_id) {
            $raw_ev->script_id = $script_id;
            $raw_ev->save();
        }
        $lang = request()->input('lang');
        $interaction = request()->input('interaction');
        $isCorrect = request()->input('Digits');
        if ($interaction === null && $isCorrect == 9) {
            $response = new Twiml();
            $response->redirect(route('start-ivr-script-get-code', ['lang' => $lang, 'script_id' => $script_id]));
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }

        $eventData = $this->getEventDetails($confCode, $scriptInfo['brand_id']); // ensure this is cached
        if ($eventData['event'] === null) {
            $response = new Twiml();
            $msg = [
                1 => 'The entered confirmation code was not recognized.',
                2 => 'El código de confirmación ingresado no fue reconocido.',
            ];
            $this->speak($response, $msg[$lang], $lang == 1 ? 'en' : 'es');
            $response->redirect(route('start-ivr-script-get-code', ['lang' => $lang, 'script_id' => $script_id]));
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }
        if ($interaction === null) {
            $itype = (DB::table('interaction_types')->where('name', 'ivr_script')->first());
            if ($itype !== null) {
                $itype = $itype->id;
            }
            $dispo = DB::table('dispositions')->where('reason', 'Pending')->where('brand_id', $eventData['event']['brand_id'])->first();
            if ($dispo !== null) {
                $dispo = $dispo->id;
            }

            $goodSaled = Interaction::where('event_id', $eventData['event']['id'])->where('event_result_id', 1)->first();
            $pendingDispo = Disposition::where('reason', 'Pending QA Audit')->where('brand_id', $eventData['event']['brand_id'])->first();
            if (isset($pendingDispo) && $goodSaled == null) {
                $goodSaled = Interaction::where('event_id', $eventData['event']['id'])
                    ->where('event_result_id', 2)
                    ->where('disposition_id', $pendingDispo->id)
                    ->first();
            }
            if (isset($goodSaled)) {
                return $this->reportGoodSale($eventData, $lang);
            }

            $i = new Interaction();
            $i->created_at = Carbon::now('America/Chicago');
            $i->event_source_id = 2;
            $i->event_id = $eventData['event']['id'];
            $i->interaction_type_id = $itype;
            $i->event_result_id = 2;
            $i->disposition_id = $dispo;
            $i->notes = request()->input();
            $i->session_id = $callSid;
            $i->session_call_id = $callSid;
            $i->save();
            $interaction = $i->id;
        }

        $questions = $this->getScriptQuestions($script_id); // ensure these are cached

        $response = new Twiml();
        if ($doGreeting) {
            $response->pause(['length' => 1]);
            $response->redirect(route('ivr-script-question', ['current_question' => '1.1.1', 'lang' => $lang, 'conf' => $confCode, 'interaction' => $interaction, 'script_id' => $script_id]));

            /*$greetings = [
                1 => 'Hello! This is TPV dot com calling to verify your enrollment with ',
                2 => '¡Hola! Este es TPV dot com llamando para verificar su inscripción con '
            ];
            $response->pause(['length' => 1]);
            $response->say($greetings[$lang] . $eventData['event']['brand']['name'], ['voice' => $this->voice, 'language' => $lang == 1 ? 'en' : 'es']);
            $response->pause(['length' => 1]);*/
        } else {
            $gather = $response->gather(['action' => route('ivr-script-question', ['current_question' => '1.1.1', 'lang' => $lang, 'conf' => $confCode, 'interaction' => $interaction, 'script_id' => $script_id]), 'method' => 'POST', 'numDigits' => 1]);
            $msg = [
                1 => 'Press any key to begin your verification',
                2 => 'Presione cualquier tecla para comenzar su verificación',
            ];
            $this->speak($gather, $msg[$lang], $lang == 1 ? 'en' : 'es');
            $response->redirect(route('start-ivr-interaction', ['lang' => $lang, 'conf' => $confCode, 'interaction' => $interaction, 'script_id' => $script_id])); // this is ran if no response
        }
        if ($callSid !== null) {
            $method = 'hack';
            switch ($method) {
                case 'proper': // This method doesn't seem to update the call
                    $twilio_client = new Client(
                        config('services.twilio.account'),
                        config('services.twilio.auth_token')
                    );
                    $twilio_client->calls($callSid)->update(
                        [
                            'record' => true,
                            'recordingStatusCallback' => config('app.urls.mgmt') . '/api/hook?command=recording:status&interaction=' . $interaction . '&brand=' . $eventData['event']['brand_id']
                        ]
                    );
                    break;

                case 'hack':
                    $out = shell_exec('/usr/bin/curl -XPOST https://api.twilio.com/2010-04-01/Accounts/' . config('services.twilio.account') . '/Calls/' . $callSid . '/Recordings.json --data-urlencode "RecordingStatusCallback=' . config('app.urls.mgmt') . '/api/hook?command=recording:status&brand=' . $eventData['event']['brand_id'] . '&interaction=' . $interaction . '" --data-urlencode "RecordingStatusCallbackEvent=in-progress completed absent" -u "' . config('services.twilio.account') . ':' . config('services.twilio.auth_token') . '"');
                    info('ShellOutput', [$out]);
                    break;
            }
        }

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function reportGoodSale(array $eventData, $lang)
    {
        $response = new Twiml();

        $script = [
            1 => [
                'This enrollment has already been completed. Please contact ' . $eventData['event']['brand']['name'] . ' at ',
                $this->sayNumber($eventData['event']['brand']['service_number']),
                ' with any questions regarding your enrollment.',
                'Thank you, goodbye!'
            ],
            2 => [
                'Esta inscripción ya se ha completado. Por favor contactar ' . $eventData['event']['brand']['name'] . ' a ',
                $this->sayNumber($eventData['event']['brand']['service_number']),
                'con cualquier pregunta relacionada con su inscripción.',
                '¡Gracias adiós!'
            ],
        ];

        $response->pause(['length' => 1]);
        $this->speak($response, $script[$lang][0], $lang == 1 ? 'en' : 'es');
        $this->speakSlower($response, $script[$lang][1], $lang == 1 ? 'en' : 'es');
        $this->speak($response, $script[$lang][2], $lang == 1 ? 'en' : 'es');
        $response->pause(['length' => 1]);
        $this->speak($response, $script[$lang][3], $lang == 1 ? 'en' : 'es');
        $response->hangup();

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function prepareSelectedProduct(array $eventData, $loopIndex, $loopType)
    {
        $countP = count($eventData['products']);
        if ($loopIndex === null || $loopIndex >= $countP || $loopIndex < 0) {
            return $eventData;
        }

        $eventData['selectedProduct'] = $eventData['products'][$loopIndex];

        return $eventData;
    }

    public function doQuestion()
    {
        info('IVR Question', [request()->input()]);
        $script_id = request()->input('script_id');
        $scriptInfo = $this->getScriptInfo(request()->input('To'), $script_id);
        $confCode = request()->input('conf');
        $lang = request()->input('lang');
        $interaction = request()->input('interaction');
        $interactionFlagged = request()->input('int_flag');

        $questions = $this->getScriptQuestions($script_id);
        $loopStart = request()->input('loop_start');
        $loopIndex = request()->input('loop_index');
        $loopType = request()->input('loop_type');
        $repeatInputs = request()->input('repeat_input');

        $eventData = $this->prepareSelectedProduct($this->getEventDetails($confCode), $loopIndex, $loopType);
        $currentQuestionId = request()->input('current_question');
        $currentQuestion = $this->getQuestionFromId($questions, $currentQuestionId);


        if ($currentQuestion === null) {
            $response = new Twiml();
            $msg = [
                1 => 'I\'m sorry but a script error has occured. Question ' . $currentQuestionId . ' was not found.',
                2 => 'Lo siento, pero ocurrió un error de script. no se encontró el ' . $currentQuestionId . ' de la pregunta.'
            ];
            $this->speak($response, $msg[$lang], $lang == 1 ? 'en' : 'es');
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }

        $varMap = SupportController::getVariableMap();
        if ($repeatInputs === null || $repeatInputs == false) {
            $response = $this->prepareQuestionText($currentQuestion, $lang, $eventData, $varMap);
        } else {
            $response = new Twiml();
        }
        $this->prepareQuestionInputDef($currentQuestion, $lang, $eventData, $varMap, $response, [
            'loop_index' => $loopIndex,
            'loop_start' => $loopStart,
            'loop_type' => $loopType,
            'current_question' => request()->input('current_question'),
            'lang' => $lang,
            'conf' => $confCode,
            'interaction' => $interaction,
            'script_id' => $script_id,
            'int_flag' => $interactionFlagged,
        ], request()->input('inputId'));

        $response->redirect(route('ivr-script-question', [
            'loop_index' => $loopIndex,
            'loop_type' => $loopType,
            'loop_start' => $loopStart,
            'current_question' => request()->input('current_question'),
            'lang' => $lang,
            'conf' => $confCode,
            'interaction' => $interaction,
            'script_id' => $script_id,
            'int_flag' => $interactionFlagged,
        ]));

        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    public function handleQuestionResponse()
    {
        info('IVR handle response', [request()->input()]);
        $script_id = request()->input('script_id');
        $scriptInfo = $this->getScriptInfo(request()->input('To'), $script_id);
        $confCode = request()->input('conf');
        $lang = request()->input('lang');
        $interaction = request()->input('interaction');
        $eventData = $this->getEventDetails($confCode);
        $questions = $this->getScriptQuestions($script_id);
        $loopStart = request()->input('loop_start');
        $loopIndex = request()->input('loop_index');
        $loopType = request()->input('loop_type');
        $currentQuestionId = request()->input('current_question');
        $currentQuestion = $this->getQuestionFromId($questions, $currentQuestionId);
        $speechResult = request()->input('SpeechResult');
        $interactionFlagged = request()->input('int_flag');
        if ($speechResult !== null) {
            $speechResult = trim(mb_strtolower($speechResult));
            $speechResult = preg_replace('/[^a-z ]/', '', $speechResult);
        }
        $speechConfidence = request()->input('Confidence');
        if ($speechConfidence !== null) {
            $speechConfidence = floatval($speechConfidence) * 100;
            info('Confidence ' . $speechResult . ' is correct is: ' . $speechConfidence . '% Raw:' . request()->input('Confidence'));
        }

        $action = null;
        $inputValue = null;

        $expectation = $currentQuestion['question']['expectation']['type'];
        switch ($expectation) {
            default:
            case 'number':
                $input = request()->input('Digits');
                foreach ($currentQuestion['question']['expectation']['actions'] as $actionToCheck) {
                    if ($speechResult !== null && $speechConfidence !== null) {
                        if (
                            isset($actionToCheck['CASE_' . ($lang == 1 ? 'english' : 'spanish')])
                        ) {
                            $toCheck = mb_strtolower(trim($actionToCheck['CASE_' . ($lang == 1 ? 'english' : 'spanish')]));
                            if ($speechConfidence >= $this->minimumAcceptPercentage && $speechConfidence < $this->minimumCorrectness) {
                                $interactionFlagged = 1;
                            }
                            if (
                                $speechConfidence >= $this->minimumAcceptPercentage
                                && $speechResult == $toCheck
                            ) {
                                if (isset($actionToCheck['value'])) {
                                    $inputValue = $actionToCheck['value'];
                                }
                                $action = $actionToCheck['action'];
                                break;
                            }
                        }
                    } else {
                        if ($input !== null && $actionToCheck['CASE'] == $input) {
                            if (isset($actionToCheck['value'])) {
                                $inputValue = $actionToCheck['value'];
                            }
                            $action = $actionToCheck['action'];
                            break;
                        }
                    }
                }
                break;

            case 'record':
                $recording = request()->input('RecordingUrl');
                $inputValue = $recording;
                $duration = request()->input('RecordingDuration');
                Artisan::queue('fetch:recording:single', [
                    '--interaction' => $interaction,
                    '--url' => $recording,
                    '--brand' => $eventData['event']['brand_id'],
                    '--duration' => $duration,
                    '--callid' => $currentQuestionId,
                ]);
                $action = $currentQuestion['question']['expectation']['actions'][0]['action'];
                if (request()->input('Digits') == 'hangup') {
                    return response(); // no need to respond if this was a hangup
                }
        }

        if ($inputValue !== null && trim($inputValue) !== '') {
            $sa = new ScriptAnswer();
            $sa->interaction_id = $interaction;
            $sa->event_id = $eventData['event']['id'];
            $sa->script_id = $eventData['event']['script_id'];
            $sa->question_id = $currentQuestion['id'];
            $sa->answer_type = 'IVR Response';
            $sa->answer = trim($inputValue);
            $sa->additional_data = [
                'product_index' => $loopIndex,
                'numeric_input' => request()->input('Digits'),
                'speech_input' => request()->input('SpeechResult'),
                'speech_confidence' => request()->input('Confidence'),
            ];
            $sa->save();
        }

        $actionName = null;

        if ($action !== null) {
            $keys = array_keys($action);
            $values = array_values($action);
            $actionName = $keys[0];
            $actionValue = $values[0];
        }

        if ($actionName === null) {
            $isRepeatLikely = false;
            $repeatKeywords = [
                'repeat',
                'repetir', // repeat in spanish
                "don't understand",
                'not understand',
                'no entiendo', // i don't understand in spanish
                'what does that mean',
                'significa eso',
                'what do you mean',
                'quieres decir'
            ];

            foreach ($repeatKeywords as $repeatKeyword) {
                if ($isRepeatLikely === false && !empty($speechResult)) {
                    $isRepeatLikely = strpos($repeatKeyword, $speechResult);
                }
            }

            if ($isRepeatLikely !== false) {
                info('IVR - detected repeat keyword in "' . $speechResult . '"');
                $actionName = 'REPEAT';
            }
        }

        $speechResultArray = [];
        $speechResultWordCount = 0;
        if (!empty($speechResult)) {
            $speechResultArray = explode(' ', $speechResult);
            $speechResultWordCount = count($speechResultArray);
        }

        if ($actionName === null || $actionName === 'REPEAT') {
            // input didn't match anything or we want to repeat
            $response = new Twiml();
            if ($actionName === null) {
                if ($speechResultWordCount < 2 && $speechResult !== null && $speechConfidence < $this->minimumAcceptPercentage) {
                    $msg = [
                        1 => "I'm sorry, I may be having trouble understanding you. If you have this call on speakerphone please turn it off and try again.",
                        2 => 'Lo siento, puede que tenga problemas para entenderte. Si tiene esta llamada en el altavoz, apáguela e intente nuevamente.'
                    ];
                } else {
                    $msg = [
                        1 => "I'm sorry, that response was not recognized.",
                        2 => 'Lo siento, esa respuesta no fue reconocida.'
                    ];
                }
            } else {
                $msg = [
                    1 => 'Okay, heres that again.',
                    2 => 'Bien, heres eso de nuevo.',
                ];
            }
            /*if ($speechConfidence !== null && config('app.env') !== 'production') {
                $response->say('Speech Confidence with result of: ' . $speechResult . ' was: ' . $speechConfidence, ['voice' => 'woman']);
            }*/
            $this->speak($response, $msg[$lang], $lang == 1 ? 'en' : 'es');
            $response->redirect(route('ivr-script-question', [
                'loop_index' => $loopIndex,
                'loop_type' => $loopType,
                'loop_start' => $loopStart,
                'current_question' => $currentQuestionId,
                'lang' => $lang,
                'conf' => $confCode,
                'interaction' => $interaction,
                'repeat_input' => $actionName === null,
                'script_id' => $script_id,
                'int_flag' => $interactionFlagged
            ]));
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }

        if ($actionName === 'END' || $actionName === 'COMPLETE') {
            $response = new Twiml();
            $response->redirect(route('ivr-script-finish', [
                'loop_index' => $loopIndex,
                'loop_type' => $loopType,
                'loop_start' => $loopStart,
                'current_question' => $currentQuestionId,
                'lang' => $lang,
                'conf' => $confCode,
                'interaction' => $interaction,
                'ftype' => $actionName,
                'disposition' => $actionValue,
                'script_id' => $script_id,
                'int_flag' => $interactionFlagged
            ]));
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }

        // if adding commands they should start here
        //
        //
        //
        //
        // end new commands

        if ($actionName !== 'GOTO') { // this is the only remaining command possible
            $response = new Twiml();
            $msg = [
                1 => 'A script error has occured on question ' . $currentQuestionId . ', invalid command.',
                2 => 'Se produjo un error de script en el ' . $currentQuestionId . ' de la pregunta, comando no válido.'
            ];
            $this->speak($response, $msg[$lang], $lang == 1 ? 'en' : 'es');
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }

        $dest = $actionValue;
        if ($dest[0] === '{') {
            // vscript IF statement
            $stmt = json_decode($dest, true);
            $varMap = SupportController::getVariableMap();
            $dest = $this->resolveIf($stmt, $this->prepareSelectedProduct($eventData, $loopIndex, $loopType), $varMap, $lang);
        }

        $response = new Twiml();

        /*if ($speechConfidence !== null && config('app.env') !== 'production') {
            $response->say('Speech Confidence with result of: ' . $speechResult . ' was: ' . $speechConfidence, ['voice' => 'woman']);
        }*/

        if ($loopType !== null) {
            if ($currentQuestion['loop'] == 3) { // end of loop
                $loopIndex += 1;
                if ($loopIndex < count($eventData['products'])) {
                    $dest = $loopStart;
                } else {
                    // done with loop
                    $loopStart = null;
                    $loopType = null;
                    $loopIndex = null;
                }
            }
        } else {
            if ($currentQuestion['loop'] == 1) {
                $loopStart = $currentQuestionId;
                $loopType = $currentQuestion['loop_type'] !== null ? $currentQuestion['loop_type'] : 1;
                $loopIndex = 0;
            }
        }


        $response->redirect(route('ivr-script-question', [
            'loop_index' => $loopIndex,
            'loop_type' => $loopType,
            'loop_start' => $loopStart,
            'current_question' => $dest,
            'lang' => $lang,
            'conf' => $confCode,
            'interaction' => $interaction,
            'inputId' => request()->input('inputId'),
            'script_id' => $script_id,
            'int_flag' => $interactionFlagged
        ]));
        return response($response, 200, ['Content-Type' => 'application/xml']);
    }

    private function resolveIf($stmt, $eventData, $varMap, $lang)
    {
        $if = $stmt['IF'];
        $then = $stmt['THEN'];
        $else = $stmt['ELSE'];
        $elseIfs = null;
        if (isset($stmt['ELSE-IF'])) {
            $elseIfs = $stmt['ELSE-IF'];
        }
        $resolve = function ($s) use ($eventData, $varMap, $lang) {
            try {
                return SupportController::resolveBooleanCondition($s, $eventData, $lang, $varMap);
            } catch (\Exception $e) {
                info('Error during ivr script', [$e]);
                return false;
            }
        };
        if ($resolve($if)) {
            return $then;
        } else {
            if ($elseIfs === null) {
                return $else;
            }
            foreach ($elseIfs as $elif) {
                if ($resolve($elif['CASE'])) {
                    return $elif['THEN'];
                }
            }
            return $else;
        }
    }

    public function finishInteraction()
    {
        $script_id = request()->input('script_id');
        $scriptInfo = $this->getScriptInfo(request()->input('To'), $script_id);
        $confCode = request()->input('conf');
        $lang = request()->input('lang');
        $interactionId = request()->input('interaction');
        $interaction = Interaction::find($interactionId);
        $eventData = $this->getEventDetails($confCode);
        $questions = $this->getScriptQuestions($script_id);
        $currentQuestionId = request()->input('current_question');
        $currentQuestion = $this->getQuestionFromId($questions, $currentQuestionId);
        $finishType = request()->input('ftype');
        $dispoName = request()->input('disposition');
        $isFinished = request()->input('finished') == 1 ? true : false;
        $repeatCount = request()->input('repeat_count');
        $interactionFlagged = request()->input('int_flag');
        if ($repeatCount === null) {
            $repeatCount = 0;
        } else {
            $repeatCount += 1;
        }
        $varMap = SupportController::getVariableMap();

        $notes = $interaction->notes;
        $notes['exit_question'] = $currentQuestionId;
        $interaction->notes = $notes;

        $dispo = Disposition::where('reason', $dispoName)->where('brand_id', $eventData['event']['brand_id'])->first();
        if (!$isFinished) {
            if ($interactionFlagged != null) {
                $efr = EventFlagReason::where('description', 'IVR Voice Response lacks confidence')->first();
                if ($efr) {
                    $ef = new EventFlag();
                    $ef->flag_reason_id = $efr->id;
                    $ef->event_id = $eventData['event']['id'];
                    $ef->notes = 'IVR Voice Response lacks confidence';
                    $ef->interaction_id = $interactionId;
                    $ef->save();
                } else {
                    info('Unable to find EventFlagReason to flag ivr call');
                }
            }
            if ($finishType === 'END') {
                $interaction->event_result_id = 2;

                if ($dispo !== null) {
                    $interaction->disposition_id = $dispo->id;
                } else {
                    $dispo = Disposition::where('reason', 'Customer Changed Their Mind')->where('brand_id', $eventData['event']['brand_id'])->first();
                    if ($dispo !== null) {
                        $interaction->disposition_id = $dispo->id;
                    } else {
                        $interaction->disposition_id = null;
                    }
                }
            } else {
                $pendingDispo = null;
                if ($interactionFlagged != null) {
                    $pendingDispo = Disposition::where('reason', 'Pending QA Audit')->where('brand_id', $eventData['event']['brand_id'])->first();
                }
                if ($pendingDispo == null) {
                    $interaction->event_result_id = 1;
                    $interaction->disposition_id = null;
                } else {
                    $interaction->event_result_id = 2;
                    $interaction->disposition_id = $pendingDispo->id;
                }
            }
            /*$interaction->save();
            $diffInSeconds = ($interaction->created_at->diffInSeconds($interaction->updated_at));
            if ($diffInSeconds > 18000) {
                $diffInSeconds -= 18000;
            }
            $interaction->interaction_time = $diffInSeconds / 60;*/
            $interaction->interaction_time = 0;
            $interaction->save();
        }

        $msg = null;

        if ($finishType === 'END') {
            // No Sale
            $scriptExit = $scriptInfo['exit_reason'];
            if ($scriptExit !== null) {
                if (is_array($scriptExit)) {
                    $scriptExit = trim($scriptExit[$lang == 1 ? 'english' : 'spanish']);
                    if ($scriptExit === '') {
                        $scriptExit = null;
                    }
                } else {
                    $scriptExit = null;
                }
            }
            $questionExit = $currentQuestion['exit_reason'];
            if ($questionExit !== null) {
                if (is_array($questionExit)) {
                    $questionExit = trim($questionExit[$lang == 1 ? 'english' : 'spanish']);
                    if ($questionExit === '') {
                        $questionExit = null;
                    }
                } else {
                    $questionExit = null;
                }
            }
            $defaultExit = $lang == 1 ? 'Thank you, we have marked your enrollment as incomplete. Goodbye.' : 'Gracias, hemos marcado su inscripción como incompleta. Adios.';
            $exitStatement = null;
            if ($questionExit !== null) {
                $exitStatement = $questionExit;
            } else {
                if ($scriptExit !== null) {
                    $exitStatement = $scriptExit;
                } else {
                    $exitStatement = $defaultExit;
                }
            }
            $msg = $exitStatement;

            $response = new Twiml();
            $this->speak($response, $this->hydrate($msg, $eventData, $lang, $varMap), $lang == 1 ? 'en' : 'es');

            return response($response, 200, ['Content-Type' => 'application/xml']);
        } else {
            //Good Sale
            $confCodeToRead = $this->sayNumber($eventData['event']['confirmation_code'], $lang);
            if ($lang == 1) {
                $msg = [
                    'Your enrollment is now complete. Your confirmation number is ',
                    $confCodeToRead,
                    ' If you have any questions about your enrollment please contact {{client.name}} at {{client.service_phone}}. Thank you! You may now hang up.',
                    'This message will now repeat.'
                ];
            } else {
                $msg = [
                    'Su inscripción ahora está completa. Su numero de confirmación es ',
                    $confCodeToRead,
                    ' Si tiene alguna pregunta sobre su inscripción, comuníquese con {{client.name}} al {{client.service_phone}}. ¡Gracias! Ahora puede colgar.',
                    'Este mensaje ahora se repetirá.'
                ];
            }

            $response = new Twiml();
            $this->speak($response, $this->hydrate($msg[0], $eventData, $lang, $varMap), $lang == 1 ? 'en' : 'es');
            $this->speakSlower($response, $this->hydrate($msg[1], $eventData, $lang, $varMap), $lang == 1 ? 'en' : 'es');
            $this->speak($response, $this->hydrate($msg[2], $eventData, $lang, $varMap), $lang == 1 ? 'en' : 'es');
            $response->pause(['length' => 1]);
            if ($repeatCount < 3) {
                $this->speak($response, $this->hydrate($msg[3], $eventData, $lang, $varMap), $lang == 1 ? 'en' : 'es');
                $response->pause(['length' => 1]);
                $response->redirect(route('ivr-script-finish', [
                    'current_question' => $currentQuestionId,
                    'lang' => $lang,
                    'conf' => $confCode,
                    'interaction' => $interaction,
                    'ftype' => $finishType,
                    'finished' => 1,
                    'repeat_count' => $repeatCount,
                    'disposition' => $dispoName,
                    'script_id' => $script_id
                ]));
            }
            return response($response, 200, ['Content-Type' => 'application/xml']);
        }
    }

    private function hydrate(string $text, array $data, int $lang, array $varMap)
    {
        $filter = function (bool $supportsVar, $variable, $lang_id = null, $data = null, $varMap = null) {
            if ($supportsVar) {
                return in_array($variable, ['account.service_address', 'account.bill_address', 'product.uom']);
            }
            switch ($variable) {
                case 'account.service_address':
                case 'account.bill_address':
                    $raw = SupportController::getVariableValue($variable . '_raw', $data, $varMap, $lang_id, true);
                    $out = '';
                    $line_1_raw = mb_strtolower($raw['line_1']);
                    $out .= $this->streetToSpeech($line_1_raw, $lang_id);
                    $out .= ', ';
                    if ($raw['line_2'] !== null) {
                        $line_2_raw = mb_strtolower($raw['line_2']);
                        $out .= $this->unitToSpeech($line_2_raw, $lang_id);
                    }
                    $out .= ', ';
                    $out .= $raw['city'];
                    $out .= ', ';
                    $out .= $raw['state']['name'];
                    $out .= ', ';
                    $out .= $this->sayNumber($raw['zip'], $lang_id);
                    return $out;
                    break;

                case 'product.uom':
                    $raw = SupportController::getVariableValue($variable . '_raw', $data, $varMap, $lang_id, true);
                    switch ($raw) {
                        default:
                            return $raw;

                        case 'unknown':
                            return $lang_id == 1 ? 'unknown' : 'desconocido';

                        case 'kwh':
                            return $lang_id == 1 ? 'kilowatt hour' : 'kilovatios-hora';

                        case 'therm':
                            return $lang_id == 1 ? 'therm' : 'termia';

                        case 'ccf':
                            return $lang_id == 1 ? 'c c f' : 'centum pies cúbicos';

                        case 'mwhs':
                            return $lang_id == 1 ? 'megawatt hour' : 'megavatios-hora';

                        case 'gj':
                            return $lang_id == 1 ? 'giga joule' : 'gigajoules';
                    }
            }
        };
        return SupportController::hydrateVariables($text, $data, $lang, $varMap, $filter);
    }

    private function streetToSpeech($raw, $lang)
    {
        $arr = explode(' ', $raw);
        $out = '';
        foreach ($arr as $item) {
            if (trim($item) == '') {
                continue;
            }
            if (is_numeric($item)) {
                $out .= $this->sayNumber($item, $lang);
            } else {
                switch ($item) {
                    case 'n':
                        $out .= ($lang == 1 ? 'north' : 'norte');
                        break;
                    case 's':
                        $out .= ($lang == 1 ? 'south' : 'sur');
                        break;
                    case 'e':
                        $out .= ($lang == 1 ? 'east' : 'este');
                        break;
                    case 'w':
                        $out .= ($lang == 1 ? 'west' : 'oeste');
                        break;

                    default:
                        // list parsed from http://www.gis.co.clay.mn.us/usps.htm
                        $opts = array(
                            0 =>
                            array(
                                'name' => 'ALLEY',
                                'abbr' => 'ALY',
                            ),
                            1 =>
                            array(
                                'name' => 'ANNEX',
                                'abbr' => 'ANX',
                            ),
                            2 =>
                            array(
                                'name' => 'ARCADE',
                                'abbr' => 'ARC',
                            ),
                            3 =>
                            array(
                                'name' => 'AVENUE',
                                'abbr' => 'AVE',
                            ),
                            4 =>
                            array(
                                'name' => 'BAYOO',
                                'abbr' => 'BYU',
                            ),
                            5 =>
                            array(
                                'name' => 'BEACH',
                                'abbr' => 'BCH',
                            ),
                            6 =>
                            array(
                                'name' => 'BEND',
                                'abbr' => 'BND',
                            ),
                            7 =>
                            array(
                                'name' => 'BLUFF',
                                'abbr' => 'BLF',
                            ),
                            8 =>
                            array(
                                'name' => 'BLUFFS',
                                'abbr' => 'BLFS',
                            ),
                            9 =>
                            array(
                                'name' => 'BOTTOM',
                                'abbr' => 'BTM',
                            ),
                            10 =>
                            array(
                                'name' => 'BOULEVARD',
                                'abbr' => 'BLVD',
                            ),
                            11 =>
                            array(
                                'name' => 'BRANCH',
                                'abbr' => 'BR',
                            ),
                            12 =>
                            array(
                                'name' => 'BRIDGE',
                                'abbr' => 'BRG',
                            ),
                            13 =>
                            array(
                                'name' => 'BROOK',
                                'abbr' => 'BRK',
                            ),
                            14 =>
                            array(
                                'name' => 'BROOKS',
                                'abbr' => 'BRKS',
                            ),
                            15 =>
                            array(
                                'name' => 'BURG',
                                'abbr' => 'BG',
                            ),
                            16 =>
                            array(
                                'name' => 'BURGS',
                                'abbr' => 'BGS',
                            ),
                            17 =>
                            array(
                                'name' => 'BYPASS',
                                'abbr' => 'BYP',
                            ),
                            18 =>
                            array(
                                'name' => 'CAMP',
                                'abbr' => 'CP',
                            ),
                            19 =>
                            array(
                                'name' => 'CANYON',
                                'abbr' => 'CYN',
                            ),
                            20 =>
                            array(
                                'name' => 'CAPE',
                                'abbr' => 'CPE',
                            ),
                            21 =>
                            array(
                                'name' => 'CAUSEWAY',
                                'abbr' => 'CSWY',
                            ),
                            22 =>
                            array(
                                'name' => 'CENTER',
                                'abbr' => 'CTR',
                            ),
                            23 =>
                            array(
                                'name' => 'CENTERS',
                                'abbr' => 'CTRS',
                            ),
                            24 =>
                            array(
                                'name' => 'CIRCLE',
                                'abbr' => 'CIR',
                            ),
                            25 =>
                            array(
                                'name' => 'CIRCLES',
                                'abbr' => 'CIRS',
                            ),
                            26 =>
                            array(
                                'name' => 'CLIFF',
                                'abbr' => 'CLF',
                            ),
                            27 =>
                            array(
                                'name' => 'CLIFFS',
                                'abbr' => 'CLFS',
                            ),
                            28 =>
                            array(
                                'name' => 'CLUB',
                                'abbr' => 'CLB',
                            ),
                            29 =>
                            array(
                                'name' => 'COMMON',
                                'abbr' => 'CMN',
                            ),
                            30 =>
                            array(
                                'name' => 'CORNER',
                                'abbr' => 'COR',
                            ),
                            31 =>
                            array(
                                'name' => 'CORNERS',
                                'abbr' => 'CORS',
                            ),
                            32 =>
                            array(
                                'name' => 'COURSE',
                                'abbr' => 'CRSE',
                            ),
                            33 =>
                            array(
                                'name' => 'COURT',
                                'abbr' => 'CT',
                            ),
                            34 =>
                            array(
                                'name' => 'COURTS',
                                'abbr' => 'CTS',
                            ),
                            35 =>
                            array(
                                'name' => 'COVE',
                                'abbr' => 'CV',
                            ),
                            36 =>
                            array(
                                'name' => 'COVES',
                                'abbr' => 'CVS',
                            ),
                            37 =>
                            array(
                                'name' => 'CREEK',
                                'abbr' => 'CRK',
                            ),
                            38 =>
                            array(
                                'name' => 'CRESCENT',
                                'abbr' => 'CRES',
                            ),
                            39 =>
                            array(
                                'name' => 'CREST',
                                'abbr' => 'CRST',
                            ),
                            40 =>
                            array(
                                'name' => 'CROSSING',
                                'abbr' => 'XING',
                            ),
                            41 =>
                            array(
                                'name' => 'CROSSROAD',
                                'abbr' => 'XRD',
                            ),
                            42 =>
                            array(
                                'name' => 'CURVE',
                                'abbr' => '',
                            ),
                            43 =>
                            array(
                                'name' => 'DALE',
                                'abbr' => 'DL',
                            ),
                            44 =>
                            array(
                                'name' => 'DAM',
                                'abbr' => 'DM',
                            ),
                            45 =>
                            array(
                                'name' => 'DIVIDE',
                                'abbr' => 'DV',
                            ),
                            46 =>
                            array(
                                'name' => 'DRIVE',
                                'abbr' => 'DR',
                            ),
                            47 =>
                            array(
                                'name' => 'DRIVES',
                                'abbr' => 'DRS',
                            ),
                            48 =>
                            array(
                                'name' => 'ESTATE',
                                'abbr' => 'EST',
                            ),
                            49 =>
                            array(
                                'name' => 'ESTATES',
                                'abbr' => 'ESTS',
                            ),
                            50 =>
                            array(
                                'name' => 'EXPRESSWAY',
                                'abbr' => 'EXPY',
                            ),
                            51 =>
                            array(
                                'name' => 'EXTENSION',
                                'abbr' => 'EXT',
                            ),
                            52 =>
                            array(
                                'name' => 'EXTENSIONS',
                                'abbr' => 'EXTS',
                            ),
                            53 =>
                            array(
                                'name' => 'FALL',
                                'abbr' => 'FALL',
                            ),
                            54 =>
                            array(
                                'name' => 'FALLS',
                                'abbr' => 'FLS',
                            ),
                            55 =>
                            array(
                                'name' => 'FERRY',
                                'abbr' => 'FRY',
                            ),
                            56 =>
                            array(
                                'name' => 'FIELD',
                                'abbr' => 'FLD',
                            ),
                            57 =>
                            array(
                                'name' => 'FIELDS',
                                'abbr' => 'FLDS',
                            ),
                            58 =>
                            array(
                                'name' => 'FLAT',
                                'abbr' => 'FLT',
                            ),
                            59 =>
                            array(
                                'name' => 'FLATS',
                                'abbr' => 'FLTS',
                            ),
                            60 =>
                            array(
                                'name' => 'FORD',
                                'abbr' => 'FRD',
                            ),
                            61 =>
                            array(
                                'name' => 'FORDS',
                                'abbr' => 'FRDS',
                            ),
                            62 =>
                            array(
                                'name' => 'FOREST',
                                'abbr' => 'FRST',
                            ),
                            63 =>
                            array(
                                'name' => 'FORGE',
                                'abbr' => 'FRG',
                            ),
                            64 =>
                            array(
                                'name' => 'FORGES',
                                'abbr' => 'FRGS',
                            ),
                            65 =>
                            array(
                                'name' => 'FORK',
                                'abbr' => 'FRK',
                            ),
                            66 =>
                            array(
                                'name' => 'FORKS',
                                'abbr' => 'FRKS',
                            ),
                            67 =>
                            array(
                                'name' => 'FORT',
                                'abbr' => 'FT',
                            ),
                            68 =>
                            array(
                                'name' => 'FREEWAY',
                                'abbr' => 'FWY',
                            ),
                            69 =>
                            array(
                                'name' => 'GARDEN',
                                'abbr' => 'GDN',
                            ),
                            70 =>
                            array(
                                'name' => 'GARDENS',
                                'abbr' => 'GDNS',
                            ),
                            71 =>
                            array(
                                'name' => 'GATEWAY',
                                'abbr' => 'GTWY',
                            ),
                            72 =>
                            array(
                                'name' => 'GLEN',
                                'abbr' => 'GLN',
                            ),
                            73 =>
                            array(
                                'name' => 'GLENS',
                                'abbr' => 'GLNS',
                            ),
                            74 =>
                            array(
                                'name' => 'GREEN',
                                'abbr' => 'GRN',
                            ),
                            75 =>
                            array(
                                'name' => 'GREENS',
                                'abbr' => 'GRNS',
                            ),
                            76 =>
                            array(
                                'name' => 'GROVE',
                                'abbr' => 'GRV',
                            ),
                            77 =>
                            array(
                                'name' => 'GROVES',
                                'abbr' => 'GRVS',
                            ),
                            78 =>
                            array(
                                'name' => 'HARBOR',
                                'abbr' => 'HBR',
                            ),
                            79 =>
                            array(
                                'name' => 'HARBORS',
                                'abbr' => 'HBRS',
                            ),
                            80 =>
                            array(
                                'name' => 'HAVEN',
                                'abbr' => 'HVN',
                            ),
                            81 =>
                            array(
                                'name' => 'HEIGHTS',
                                'abbr' => 'HTS',
                            ),
                            82 =>
                            array(
                                'name' => 'HIGHWAY',
                                'abbr' => 'HWY',
                            ),
                            83 =>
                            array(
                                'name' => 'HILL',
                                'abbr' => 'HL',
                            ),
                            84 =>
                            array(
                                'name' => 'HILLS',
                                'abbr' => 'HLS',
                            ),
                            85 =>
                            array(
                                'name' => 'HOLLOW',
                                'abbr' => 'HOLW',
                            ),
                            86 =>
                            array(
                                'name' => 'INLET',
                                'abbr' => 'INLT',
                            ),
                            87 =>
                            array(
                                'name' => 'INTERSTATE',
                                'abbr' => 'I',
                            ),
                            88 =>
                            array(
                                'name' => 'ISLAND',
                                'abbr' => 'IS',
                            ),
                            89 =>
                            array(
                                'name' => 'ISLANDS',
                                'abbr' => 'ISS',
                            ),
                            90 =>
                            array(
                                'name' => 'ISLE',
                                'abbr' => 'ISLE',
                            ),
                            91 =>
                            array(
                                'name' => 'JUNCTION',
                                'abbr' => 'JCT',
                            ),
                            92 =>
                            array(
                                'name' => 'JUNCTIONS',
                                'abbr' => 'JCTS',
                            ),
                            93 =>
                            array(
                                'name' => 'KEY',
                                'abbr' => 'KY',
                            ),
                            94 =>
                            array(
                                'name' => 'KEYS',
                                'abbr' => 'KYS',
                            ),
                            95 =>
                            array(
                                'name' => 'KNOLL',
                                'abbr' => 'KNL',
                            ),
                            96 =>
                            array(
                                'name' => 'KNOLLS',
                                'abbr' => 'KNLS',
                            ),
                            97 =>
                            array(
                                'name' => 'LAKE',
                                'abbr' => 'LK',
                            ),
                            98 =>
                            array(
                                'name' => 'LAKES',
                                'abbr' => 'LKS',
                            ),
                            99 =>
                            array(
                                'name' => 'LAND',
                                'abbr' => 'LAND',
                            ),
                            100 =>
                            array(
                                'name' => 'LANDING',
                                'abbr' => 'LNDG',
                            ),
                            101 =>
                            array(
                                'name' => 'LANE',
                                'abbr' => 'LN',
                            ),
                            102 =>
                            array(
                                'name' => 'LIGHT',
                                'abbr' => 'LGT',
                            ),
                            103 =>
                            array(
                                'name' => 'LIGHTS',
                                'abbr' => 'LGTS',
                            ),
                            104 =>
                            array(
                                'name' => 'LOAF',
                                'abbr' => 'LF',
                            ),
                            105 =>
                            array(
                                'name' => 'LOCK',
                                'abbr' => 'LCK',
                            ),
                            106 =>
                            array(
                                'name' => 'LOCKS',
                                'abbr' => 'LCKS',
                            ),
                            107 =>
                            array(
                                'name' => 'LODGE',
                                'abbr' => 'LDG',
                            ),
                            108 =>
                            array(
                                'name' => 'LOOP',
                                'abbr' => 'LOOP',
                            ),
                            109 =>
                            array(
                                'name' => 'MALL',
                                'abbr' => 'MALL',
                            ),
                            110 =>
                            array(
                                'name' => 'MANOR',
                                'abbr' => 'MNR',
                            ),
                            111 =>
                            array(
                                'name' => 'MANORS',
                                'abbr' => 'MNRS',
                            ),
                            112 =>
                            array(
                                'name' => 'MEADOW',
                                'abbr' => 'MDW',
                            ),
                            113 =>
                            array(
                                'name' => 'MEADOWS',
                                'abbr' => 'MDWS',
                            ),
                            114 =>
                            array(
                                'name' => 'MEWS',
                                'abbr' => 'MEWS',
                            ),
                            115 =>
                            array(
                                'name' => 'MILL',
                                'abbr' => 'ML',
                            ),
                            116 =>
                            array(
                                'name' => 'MILLS',
                                'abbr' => 'MLS',
                            ),
                            117 =>
                            array(
                                'name' => 'MISSION',
                                'abbr' => 'MSN',
                            ),
                            118 =>
                            array(
                                'name' => 'MOORHEAD',
                                'abbr' => 'MHD',
                            ),
                            119 =>
                            array(
                                'name' => 'MOTORWAY',
                                'abbr' => 'MTWY',
                            ),
                            120 =>
                            array(
                                'name' => 'MOUNT',
                                'abbr' => 'MT',
                            ),
                            121 =>
                            array(
                                'name' => 'MOUNTAIN',
                                'abbr' => 'MTN',
                            ),
                            122 =>
                            array(
                                'name' => 'MOUNTAINS',
                                'abbr' => 'MTNS',
                            ),
                            123 =>
                            array(
                                'name' => 'NECK',
                                'abbr' => 'NCK',
                            ),
                            124 =>
                            array(
                                'name' => 'ORCHARD',
                                'abbr' => 'ORCH',
                            ),
                            125 =>
                            array(
                                'name' => 'OVAL',
                                'abbr' => 'OVAL',
                            ),
                            126 =>
                            array(
                                'name' => 'OVERPASS',
                                'abbr' => 'OPAS',
                            ),
                            127 =>
                            array(
                                'name' => 'PARK',
                                'abbr' => 'PARK',
                            ),
                            128 =>
                            array(
                                'name' => 'PARKS',
                                'abbr' => 'PARK',
                            ),
                            129 =>
                            array(
                                'name' => 'PARKWAY',
                                'abbr' => 'PKWY',
                            ),
                            130 =>
                            array(
                                'name' => 'PARKWAYS',
                                'abbr' => 'PKWY',
                            ),
                            131 =>
                            array(
                                'name' => 'PASS',
                                'abbr' => 'PASS',
                            ),
                            132 =>
                            array(
                                'name' => 'PASSAGE',
                                'abbr' => 'PSGE',
                            ),
                            133 =>
                            array(
                                'name' => 'PATH',
                                'abbr' => 'PATH',
                            ),
                            134 =>
                            array(
                                'name' => 'PIKE',
                                'abbr' => 'PIKE',
                            ),
                            135 =>
                            array(
                                'name' => 'PINE',
                                'abbr' => 'PNE',
                            ),
                            136 =>
                            array(
                                'name' => 'PINES',
                                'abbr' => 'PNES',
                            ),
                            137 =>
                            array(
                                'name' => 'PLACE',
                                'abbr' => 'PL',
                            ),
                            138 =>
                            array(
                                'name' => 'PLAIN',
                                'abbr' => 'PLN',
                            ),
                            139 =>
                            array(
                                'name' => 'PLAINS',
                                'abbr' => 'PLNS',
                            ),
                            140 =>
                            array(
                                'name' => 'PLAZA',
                                'abbr' => 'PLZ',
                            ),
                            141 =>
                            array(
                                'name' => 'POINT',
                                'abbr' => 'PT',
                            ),
                            142 =>
                            array(
                                'name' => 'POINTS',
                                'abbr' => 'PTS',
                            ),
                            143 =>
                            array(
                                'name' => 'PORT',
                                'abbr' => 'PRT',
                            ),
                            144 =>
                            array(
                                'name' => 'PORTS',
                                'abbr' => 'PRTS',
                            ),
                            145 =>
                            array(
                                'name' => 'PRAIRIE',
                                'abbr' => 'PR',
                            ),
                            146 =>
                            array(
                                'name' => 'RADIAL',
                                'abbr' => 'RADL',
                            ),
                            147 =>
                            array(
                                'name' => 'RAMP',
                                'abbr' => 'RAMP',
                            ),
                            148 =>
                            array(
                                'name' => 'RANCH',
                                'abbr' => 'RNCH',
                            ),
                            149 =>
                            array(
                                'name' => 'RAPID',
                                'abbr' => 'RPD',
                            ),
                            150 =>
                            array(
                                'name' => 'RAPIDS',
                                'abbr' => 'RPDS',
                            ),
                            151 =>
                            array(
                                'name' => 'REST',
                                'abbr' => 'RST',
                            ),
                            152 =>
                            array(
                                'name' => 'RIDGE',
                                'abbr' => 'RDG',
                            ),
                            153 =>
                            array(
                                'name' => 'RIDGES',
                                'abbr' => 'RDGS',
                            ),
                            154 =>
                            array(
                                'name' => 'RIVER',
                                'abbr' => 'RIV',
                            ),
                            155 =>
                            array(
                                'name' => 'ROAD',
                                'abbr' => 'RD',
                            ),
                            156 =>
                            array(
                                'name' => 'ROADS',
                                'abbr' => 'RDS',
                            ),
                            157 =>
                            array(
                                'name' => 'ROUTE',
                                'abbr' => 'RTE',
                            ),
                            158 =>
                            array(
                                'name' => 'ROW',
                                'abbr' => 'ROW',
                            ),
                            159 =>
                            array(
                                'name' => 'RUE',
                                'abbr' => 'RUE',
                            ),
                            160 =>
                            array(
                                'name' => 'RUN',
                                'abbr' => 'RUN',
                            ),
                            161 =>
                            array(
                                'name' => 'SHOAL',
                                'abbr' => 'SHL',
                            ),
                            162 =>
                            array(
                                'name' => 'SHOALS',
                                'abbr' => 'SHLS',
                            ),
                            163 =>
                            array(
                                'name' => 'SHORE',
                                'abbr' => 'SHR',
                            ),
                            164 =>
                            array(
                                'name' => 'SHORES',
                                'abbr' => 'SHRS',
                            ),
                            165 =>
                            array(
                                'name' => 'SKYWAY',
                                'abbr' => 'SKWY',
                            ),
                            166 =>
                            array(
                                'name' => 'SPRING',
                                'abbr' => 'SPG',
                            ),
                            167 =>
                            array(
                                'name' => 'SPRINGS',
                                'abbr' => 'SPGS',
                            ),
                            168 =>
                            array(
                                'name' => 'SPUR',
                                'abbr' => 'SPUR',
                            ),
                            169 =>
                            array(
                                'name' => 'SPURS',
                                'abbr' => 'SPUR',
                            ),
                            170 =>
                            array(
                                'name' => 'SQUARE',
                                'abbr' => 'SQ',
                            ),
                            171 =>
                            array(
                                'name' => 'SQUARES',
                                'abbr' => 'SQS',
                            ),
                            172 =>
                            array(
                                'name' => 'STATION',
                                'abbr' => 'STA',
                            ),
                            173 =>
                            array(
                                'name' => 'STREAM',
                                'abbr' => 'STRM',
                            ),
                            174 =>
                            array(
                                'name' => 'STREET',
                                'abbr' => 'ST',
                            ),
                            175 =>
                            array(
                                'name' => 'STREETS',
                                'abbr' => 'STS',
                            ),
                            176 =>
                            array(
                                'name' => 'SUMMIT',
                                'abbr' => 'SMT',
                            ),
                            177 =>
                            array(
                                'name' => 'TERRACE',
                                'abbr' => 'TER',
                            ),
                            178 =>
                            array(
                                'name' => 'THROUGHWAY',
                                'abbr' => 'TRWY',
                            ),
                            179 =>
                            array(
                                'name' => 'TRACE',
                                'abbr' => 'TRCE',
                            ),
                            180 =>
                            array(
                                'name' => 'TRACK',
                                'abbr' => 'TRAK',
                            ),
                            181 =>
                            array(
                                'name' => 'TRAIL',
                                'abbr' => 'TRL',
                            ),
                            182 =>
                            array(
                                'name' => 'TUNNEL',
                                'abbr' => 'TUNL',
                            ),
                            183 =>
                            array(
                                'name' => 'TURNPIKE',
                                'abbr' => 'TPKE',
                            ),
                            184 =>
                            array(
                                'name' => 'UNDERPASS',
                                'abbr' => 'UPAS',
                            ),
                            185 =>
                            array(
                                'name' => 'UNION',
                                'abbr' => 'UN',
                            ),
                            186 =>
                            array(
                                'name' => 'UNIONS',
                                'abbr' => 'UNS',
                            ),
                            187 =>
                            array(
                                'name' => 'VALLEY',
                                'abbr' => 'VLY',
                            ),
                            188 =>
                            array(
                                'name' => 'VALLEYS',
                                'abbr' => 'VLYS',
                            ),
                            189 =>
                            array(
                                'name' => 'VIADUCT',
                                'abbr' => 'VIA',
                            ),
                            190 =>
                            array(
                                'name' => 'VIA',
                                'abbr' => 'VIA',
                            ),
                            191 =>
                            array(
                                'name' => 'VIEW',
                                'abbr' => 'VW',
                            ),
                            192 =>
                            array(
                                'name' => 'VIEWS',
                                'abbr' => 'VWS',
                            ),
                            193 =>
                            array(
                                'name' => 'VILLAGE',
                                'abbr' => 'VLG',
                            ),
                            194 =>
                            array(
                                'name' => 'VILLAGES',
                                'abbr' => 'VLGS',
                            ),
                            195 =>
                            array(
                                'name' => 'VILLE',
                                'abbr' => 'VL',
                            ),
                            196 =>
                            array(
                                'name' => 'VISTA',
                                'abbr' => 'VIS',
                            ),
                            197 =>
                            array(
                                'name' => 'WALK',
                                'abbr' => 'WALK',
                            ),
                            198 =>
                            array(
                                'name' => 'WALKS',
                                'abbr' => 'WALK',
                            ),
                            199 =>
                            array(
                                'name' => 'WALL',
                                'abbr' => 'WALL',
                            ),
                            200 =>
                            array(
                                'name' => 'WAY',
                                'abbr' => 'WAY',
                            ),
                            201 =>
                            array(
                                'name' => 'WAYS',
                                'abbr' => 'WAYS',
                            ),
                            202 =>
                            array(
                                'name' => 'WELL',
                                'abbr' => 'WL',
                            ),
                            203 =>
                            array(
                                'name' => 'WELLS',
                                'abbr' => 'WLS',
                            ),
                        );
                        $uItem = mb_strtoupper(($item));
                        foreach ($opts as $v) {
                            if ($uItem === $v['abbr']) {
                                $item = $v['name'];
                            }
                        }
                        $out .= $item;
                }
            }

            $out .= ', ';
        }
        return str_replace(' ,', '', $out);
    }

    private function unitToSpeech($raw, $lang)
    {
        // list parsed from http://www.gis.co.clay.mn.us/usps.htm
        $opts = [
            0 =>
            [
                'name' => 'APARTMENT',
                'abbr' => 'APT',
            ],
            1 =>
            [
                'name' => 'BASEMENT',
                'abbr' => 'BSMT',
            ],
            2 =>
            [
                'name' => 'BUILDING',
                'abbr' => 'BLDG',
            ],
            3 =>
            [
                'name' => 'DEPARTMENT',
                'abbr' => 'DEPT',
            ],
            4 =>
            [
                'name' => 'FLOOR',
                'abbr' => 'FL',
            ],
            5 =>
            [
                'name' => 'FRONT',
                'abbr' => 'FRNT',
            ],
            6 =>
            [
                'name' => 'HANGAR',
                'abbr' => 'HNGR',
            ],
            7 =>
            [
                'name' => 'LOBBY',
                'abbr' => 'LBBY',
            ],
            8 =>
            [
                'name' => 'LOT',
                'abbr' => 'LOT',
            ],
            9 =>
            [
                'name' => 'LOWER',
                'abbr' => 'LOWR',
            ],
            10 =>
            [
                'name' => 'OFFICE',
                'abbr' => 'OFC',
            ],
            11 =>
            [
                'name' => 'PENTHOUSE',
                'abbr' => 'PH',
            ],
            12 =>
            [
                'name' => 'PIER',
                'abbr' => 'PIER',
            ],
            13 =>
            [
                'name' => 'REAR',
                'abbr' => 'REAR',
            ],
            14 =>
            [
                'name' => 'ROOM',
                'abbr' => 'RM',
            ],
            15 =>
            [
                'name' => 'SIDE',
                'abbr' => 'SIDE',
            ],
            16 =>
            [
                'name' => 'SLIP',
                'abbr' => 'SLIP',
            ],
            17 =>
            [
                'name' => 'SPACE',
                'abbr' => 'SPC',
            ],
            18 =>
            [
                'name' => 'STOP',
                'abbr' => 'STOP',
            ],
            19 =>
            [
                'name' => 'SUITE',
                'abbr' => 'STE',
            ],
            20 =>
            [
                'name' => 'TRAILER',
                'abbr' => 'TRLR',
            ],
            21 =>
            [
                'name' => 'UNIT',
                'abbr' => 'UNIT',
            ],
            22 =>
            [
                'name' => 'UPPER',
                'abbr' => 'UPPR',
            ],
        ];

        $out = mb_strtoupper($raw);

        foreach ($opts as $v) {
            $out = str_replace($v['abbr'], $v['name'], $out);
        }

        return $out;
    }

    private function prepareQuestionText($question, $lang, $data, $varMap)
    {
        $response = new Twiml();

        foreach ($question['question'][$lang == 1 ? 'english' : 'spanish'] as $q) {
            if ($q['as'] === 'text') {
                if (isset($q['recording']) && $q['recording'] !== null) {
                    $response->play(config('services.aws.cloudfront.domain') . '/' . $q['recording']);
                } else {
                    $this->speak(
                        $response,
                        $this->hydrate($q['text'], $data, $lang, $varMap),
                        $lang == 1 ? 'en' : 'es'
                    );
                }
            }
            if ($q['as'] === 'number') {
                $this->speakSlower(
                    $response,
                    $this->sayNumber($this->hydrate($q['text'], $data, $lang, $varMap), $lang),
                    $lang == 1 ? 'en' : 'es'
                );
            }
        }
        return $response;
    }

    private function prepareQuestionInputDef($question, $lang, $data, $varMap, $response, $flags, $inInputId = null)
    {
        $expectation = $question['question']['expectation']['type'];
        $gather = null;
        $shouldPrompt = true;
        if ($expectation === 'number') {
            $hints = [];
            $id = [];
            foreach ($question['question']['expectation']['actions'] as $action) {
                $id[] = $action['CASE'];
                $str = ($lang === '1') ? 'CASE_english' : 'CASE_spanish';
                $case = (isset($action[$str]))
                    ? $action[$str]
                    : null;

                if (
                    isset($case)
                    && trim($case) != ''
                ) {
                    $hints[] = mb_strtolower($case);
                }
            }

            $inputIdA = implode('-', $hints);
            $inputId = md5($inputIdA);
            $flags['inputId'] = $inputId;
            if (
                (!isset($question['question']['expectation']['alwaysRead'])
                    || !$question['question']['expectation']['alwaysRead'])
                && $inInputId === $inputId
            ) {
                $shouldPrompt = false;
            }
            // DEBUG
            $flags['debugShouldPromptIs'] = $shouldPrompt;

            $params = [
                'input' => 'dtmf speech',
                'speechTimeout' => 'auto',
                'hints' => implode(',', $hints),
                'speechModel' => 'numbers_and_commands',
                'numDigits' => 1,
                'action' => route('ivr-script-response', $flags)
            ];

            info(print_r($params, true));

            $gather = $response->gather(
                $params
            );
        }
        if ($expectation === 'record') {
            $this->speak(
                $response,
                $this->hydrate(
                    $question['question']['expectation']['actions'][0]['text'][$lang == 1 ? 'english' : 'spanish'],
                    $data,
                    $lang,
                    $varMap
                ),
                $lang == 1 ? 'en' : 'es'
            );
            $response->record([
                'action' => route('ivr-script-response', $flags),
                'finishOnKey' => '#',
                'playBeep' => true,
                'timeout' => 5,
                'trim' => 'trim-silence',
                'transcribeCallback' => config('app.urls.mgmt') . '/api/hook?command=transcription:update',
            ]);
            $response->redirect(route('ivr-script-question', $flags));
        }

        if ($gather !== null && $shouldPrompt) {
            foreach ($question['question']['expectation']['actions'] as $action) {
                $text = $action['text'][$lang == 1 ? 'english' : 'spanish'];

                $this->speak(
                    $gather,
                    $this->hydrate(
                        $text,
                        $data,
                        $lang,
                        $varMap
                    ),
                    $lang == 1 ? 'en' : 'es'
                );
            }
        }
    }

    private function speakSlower($base, string $text, string $lang): void
    {
        $this->speak($base, $text, $lang, true);
    }

    private function speak($base, string $text, string $lang, bool $slower = false): void
    {
        $cleanText = trim($text);
        if (strlen($cleanText) === 0 || $cleanText === '' || $cleanText == '.') {
            return;
        }
        $say = $base->say('', [
            'voice' => $this->voice[$lang],
            'language' => $lang,
        ]);
        if ($slower) {
            $say->say_As($cleanText, [
                'interpret-as' => 'digits'
            ]);
        } else {
            $say->prosody($cleanText, [
                'rate' => !$slower ? $this->voiceRate : $this->slowerVoiceRate,
                //'pitch' => $this->voicePitchAdjust,
                //'volume' => $this->voiceVolumeAdjust,
            ]);
        }
    }

    private function getQuestionFromId($questions, string $id)
    {
        if ($questions === null || count($questions) === 0) {
            return null;
        }
        $id_parts = explode('.', $id);
        $section_id = $id_parts[0];
        $subsection_id = $id_parts[1];
        $question_id = $id_parts[2];
        foreach ($questions as $question) {
            if ($question['section_id'] == $section_id && $question['subsection_id'] == $subsection_id && $question['question_id'] == $question_id) {
                return $question;
            }
        }
        return null;
    }

    private function sayNumber($number, $lang = 1)
    {
        $out = '';
        for ($i = 0, $len = strlen($number); $i < $len; $i += 1) {
            if (in_array($number[$i], ['+', '-', '(', ')'])) {
                continue;
            }
            if ($number[$i] == '.') {
                $out = $out . ($lang == 1 ? ' point ' : ' punto ');
            } else {
                $out = $out . $number[$i] . ', ';
            }
        }

        return trim($out);
    }
}
