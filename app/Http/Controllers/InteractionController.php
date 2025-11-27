<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\ScriptQuestions;
use App\Models\ScriptAnswer;
use App\Models\Script;
use App\Models\Recording;
use App\Models\InteractionMonitor;
use App\Models\Interaction;
use App\Models\Event;
use App\Http\Controllers\SupportController;

class InteractionController extends Controller
{
    protected $no_question_message = 'couldnt find script question with the id: ';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('interactions/interactions');
    }

    public function monitorInteraction(Request $request, $id)
    {
        $im = new InteractionMonitor();
        $im->interaction_id = $id;
        $im->tpv_staff_id = Auth::user()->id;
        $im->save();

        return redirect('interactions');
    }

    public function listInteractions(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $search = $request->get('search');

        $interactions = Interaction::select(
            'interactions.id',
            'interactions.created_at',
            'call_centers.call_center',
            'events.confirmation_code',
            'brands.name AS brand_name',
            'tpv_staff.id AS tpv_agent_id',
            'tpv_staff.username',
            'tpv_staff.last_name',
            'tpv_staff.first_name',
            'event_results.result',
            'channels.channel',
            'events.id as event_id',
            'interaction_monitor.id AS monitored'
        )->join(
            'events',
            'interactions.event_id',
            'events.id'
        )->join(
            'brands',
            'events.brand_id',
            'brands.id'
        )->leftJoin(
            'tpv_staff',
            'interactions.tpv_staff_id',
            'tpv_staff.id'
        )->leftJoin(
            'call_centers',
            'tpv_staff.call_center_id',
            'call_centers.id'
        )->leftJoin(
            'event_results',
            'interactions.event_result_id',
            'event_results.id'
        )->join(
            'channels',
            'events.channel_id',
            'channels.id'
        )->leftJoin(
            'interaction_monitor',
            'interactions.id',
            'interaction_monitor.interaction_id'
        )->where(
            'interactions.interaction_type_id',
            '!=',
            3
        );

        if ($search != null) {
            $interactions = $interactions->search($search);
        }

        if ($column && $direction) {
            $interactions = $interactions->orderBy($column, $direction);
        } else {
            $interactions = $interactions->orderBy(
                'interactions.created_at',
                'desc'
            );
        }

        return response()->json($interactions->paginate(20));
    }

    public function getInteraction($id)
    {
        return Interaction::select(
            'interactions.id',
            'interactions.interaction_type_id',
            'interactions.created_at',
            'interactions.station_id',
            'call_centers.call_center',
            'events.confirmation_code',
            'brands.name AS brand_name',
            'tpv_staff.id AS tpv_agent_id',
            'tpv_staff.username',
            'tpv_staff.last_name',
            'tpv_staff.first_name',
            'event_results.result',
            'channels.channel',
            'events.id as event_id',
            'events.language_id as language'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->leftJoin(
            'brands',
            'events.brand_id',
            'brands.id'
        )->leftJoin(
            'tpv_staff',
            'interactions.tpv_staff_id',
            'tpv_staff.id'
        )->leftJoin(
            'call_centers',
            'tpv_staff.call_center_id',
            'call_centers.id'
        )->leftJoin(
            'event_results',
            'interactions.event_result_id',
            'event_results.id'
        )->leftJoin(
            'channels',
            'events.channel_id',
            'channels.id'
        )->where(
            'interactions.id',
            $id
        )->first();
    }

    public function transcript($id, $event)
    {
        $event = Event::find($event);
        $script = Script::find($event->script_id);
        $interaction = $this->getInteraction($id);

        $recording = Recording::where('interaction_id', $id)->orderBy('duration', 'desc')->first();
        if ($recording) {
            $interaction->recording = $recording->recording;
            $interaction->duration = $recording->duration;
            $interaction->size = $recording->size;
        }

        $answers = ScriptAnswer::select(
            'script_answers.created_at',
            'script_answers.question_id',
            'script_questions.question',
            'script_answers.answer_type',
            'script_answers.answer',
            'script_answers.additional_data',
            'script_questions.section_id',
            'script_questions.subsection_id',
            'script_questions.question_id as sq_id'
        )->leftJoin(
            'script_questions',
            'script_questions.id',
            'script_answers.question_id'
        )->where(
            'script_answers.interaction_id',
            $id
        )->orderBy('script_questions.section_id')
            ->orderBy('script_questions.subsection_id')
            ->orderBy('script_questions.question_id')
            ->get();

        $defaultSPQuestions = $this->get_default_sp_questions();

        $answers->map(function ($item) use ($defaultSPQuestions) {
            if ($item->question === null) {
                $matches = [];

                if (
                    preg_match(
                        '([a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12})',
                        $item->question_id,
                        $matches
                    ) === 1
                ) {
                    $real_id = $matches[0];
                    $sq = ScriptQuestions::find($real_id);
                    if ($sq) {
                        $item->question = $sq->question;
                        $item->question_id = $real_id;
                        $item->is_summary = true;
                    } else {
                        info('this is messed up: ' . $item->question_id);
                        $found = false;
                        foreach ($defaultSPQuestions as $dq) {
                            if (strpos($item->question_id, $dq['id']) !== false) {
                                $item->question = $dq['question'];
                                $item->is_summary = true;
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            info($this->no_question_message . $real_id, $matches);
                        }
                    }
                } else {
                    $found = false;
                    foreach ($defaultSPQuestions as $dq) {
                        if (strpos($item->question_id, $dq['id']) !== false) {
                            $item->question = $dq['question'];
                            $item->is_summary = true;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        info($this->no_question_message . $item->question_id);
                    }
                }
            } else {
                $real_id = null;
                if ($item->question === null) {
                    info('question is null');
                    foreach ($defaultSPQuestions as $dq) {
                        if (strpos($item->question_id, $dq['id']) !== false) {
                            $item->question = $dq['question'];
                            $item->is_summary = true;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        info($this->no_question_message);
                    }
                } else {
                    $item->is_summary = false;
                }
            }

            return $item;
        });

        // Get Event Details
        $data = SupportController::gatherEventDetails($interaction->event_id);

        // Filter data
        $filter = function($shouldUseFilter, $value, $lang_id = 1, $data = null, $varMap = null) use ($interaction) {
            if($shouldUseFilter) {
                if(in_array($value, ['date.voice','date', 'time'])) {
                    return true;
                }
                return false;
            }
            if($value == 'date' || $value == 'date.voice') {
                return $interaction->created_at->format('l, F j, Y');
            }
            if($value == 'time') {
                return $interaction->created_at->format('g:i a');
            }
            return null;
        };
        $hydrate = function($text, $product) use ($data, $filter) {
            $data['selectedProduct'] = $product;
            return SupportController::hydrateVariables($text, $data, $data['event']['language_id'], null, $filter, 'strong');
        };
        
        print_r($data['selectedProduct']);exit;

        return view(
            'interactions.transcript',
            [
                'interaction' => $interaction,
                'script' => $script,
                'answers' => $answers,
                'data' => $data,
                'hydrate' => $hydrate
            ]
        );
    }

    private function get_default_sp_questions()
    {
        $jsonText = <<<EOT
        [
            {
                "id": "default-dual-bill-name-confirm-same",
                "question": {
                    "english":"I show {{account.bill_name.electric}} as the name that appears on the electric and natural gas bills for these accounts.",
                    "spanish":"Muestro {{account.bill_name.electric}} como el nombre que aparece en las facturas de electricidad y gas natural para estas cuentas."
                }
            },
            {
                "id": "default-svc-bill-confirm",
                "question": {
                    "english": "You are switching your [[!SUMMARY_FT]] account(s) to {{client.name}} with the following: {{account.address_table}}",
                    "spanish": "Está cambiando su [[!SUMMARY_FT]] cuenta(s) a {{client.name}} con lo siguiente: {{account.address_table}}"
                }
            },
            {
                "id": "default-res-sf-single-bill-name-confirm",
                "question": {
                    "english": "I show {{account.bill_name}} as the name that appears on the bill for this account, is that correct?",
                    "spanish": "¿{{account.bill_name}} es el nombre que aparece en la factura de esta cuenta, ¿es correcto?"
                }
            },
            {
                "id": "default-res-sf-multi-bill-name-confirm",
                "question": {
                    "english": "I show {{account.bill_name}} as the name that appears on the bills for these [[!SUMMARY_FT]] accounts, is that correct?",
                    "spanish": "Muestro {{account.bill_name}} como el nombre que aparece en las facturas de estas [[!SUMMARY_FT]] cuentas, ¿es correcto?"
                }
            },
            {
                "id": "default-res-dual-bill-name-confirm",
                "question": {
                    "english": "I show {{account.bill_name}} as the name that appears on the electric and natural gas bills for these accounts, is that correct?",
                    "spanish": "Muestro {{account.bill_name}} como el nombre que aparece en las facturas de electricidad y gas natural para estas cuentas, ¿es correcto?"
                }
            },
            {
                "id": "default-comm-sf-single-bill-name-confirm",
                "question": {
                    "english": "I show the name of the company that appears on the bill for this account is {{account.company_name}}, is that correct?",
                    "spanish": "Muestro el nombre de la empresa que aparece en la factura de esta cuenta es {{account.company_name}}, ¿es correcto?"
                }
            },
            {
                "id": "default-comm-sf-multi-bill-name-confirm",
                "question": {
                    "english": "I show {{account.company_name}} as the name of the company that appears on the bills for these [[!SUMMARY_FT]] accounts, is that correct?",
                    "spanish": "Muestro {{account.company_name}} como el nombre de la compañía que aparece en las facturas de estas [[!SUMMARY_FT]] cuentas, ¿es correcto?"
                }
            },
            {
                "id": "default-comm-dual-bill-name-confirm",
                "question": {
                    "english": "I show the name of the company that appears on the electric and natural gas bills for these accounts is {{account.company_name}}, is that correct?",
                    "spanish": "Muestro el nombre de la compañía que aparece en las facturas de electricidad y gas natural para estas cuentas es {{account.company name}}, ¿es correcto?"
                }
            },
            {
                "id": "default-idents-confirm",
                "question": {
                    "english": "I show the following account details:",
                    "spanish": "Muestro los siguientes detalles de la cuenta:"
                }
            },
            {
                "id": "verify-rate-single-fixed",
                "question": {
                    "english": "{{client.name}} will provide {{product.fuel}} service at a fixed rate of {{product.amount}} {{product.currency}} per {{product.uom}} for {{product.term}} {{product.term_type}}. Does this information match the information given to you by the sales agent?",
                    "spanish": "{{client.name}} proporcionará el servicio {{product.fuel}} a una tasa fija de {{product.amount}} {{product.currency}} por {{product.uom}} para {{product.term}} {{product.term_type}}. ¿Esta información coincide con la información proporcionada por el agente de ventas?"
                }
            },
            {
                "id": "verify-rate-dual-fixed",
                "question": {
                    "english": "{{client.name}} will provide electric service at a fixed rate of {{product.amount.electric}} {{product.currency.electric}} per {{product.uom.electric}} for {{product.term.electric}} {{product.term_type.electric}} {{client.name}} will also provide natural gas service at a fixed rate of {{product.amount.gas}} {{product.currency.gas}} per  {{product.uom.gas}} for {{product.term.gas}} {{product.term_type.gas}}.  Does this information match the information given to you by the sales agent?",
                    "spanish": "{{client.name}} proporcionará servicio eléctrico a una tasa fija de {{product.amount.electric}} {{product.currency.electric}} por {{product.uom.electric}} para {{product.term .electric}} {{product.term_type.electric}} {{client.name}} también proporcionará servicio de gas natural a una tasa fija de {{product.amount.gas}} {{product.currency.gas}} por {{product.uom.gas}} para {{product.term.gas}} {{product.term_type.gas}}. ¿Esta información coincide con la información proporcionada por el agente de ventas?"
                }
            },
            {
                "id": "verify-contract-no-fee",
                "question": {
                    "english": "Do you understand that you may cancel this contract at any time with no fee or penalty?",
                    "spanish": "¿Comprende que puede cancelar este contrato en cualquier momento sin cargo o penalización?"
                }
            },
            {
                "id": "verify-contract-fee",
                "question": {
                    "english": "Do you understand that you may cancel this contract at any time, however, if you cancel before the end of the contract term a cancellation fee of {{product.cancellation_fee}} will apply?",
                    "spanish": "¿Comprende que puede cancelar este contrato en cualquier momento, sin embargo, si cancela antes de la finalización del plazo del contrato, se aplicará un cargo por cancelación de {{product.cancellation_fee}}?"
                }
            },
            {
                "id": "default-disclosure-intro",
                "question": {
                    "english": "Great! Now I just have a few disclosures to go over with you.",
                    "spanish": "¡Genial! Ahora solo tengo algunas revelaciones para repasar con usted."
                }
            },
            {
                "id": "default-disclosure-intro-not-utility",
                "question": {
                    "english": "{{client.name}} is not your utility, but is a licensed independent third party vendor of electricty and/or natural gas. Your utility will remain responsible for the delivery, maintenance, and billing of your service. If you have any problems or questions related to your service please contact the appropriate utility company. Do you understand?",
                    "spanish": "{{client.name}} no es su utilidad, pero es un proveedor independiente de electricidad y gas natural con licencia. Su servicio público seguirá siendo responsable de la entrega, el mantenimiento y la facturación de su servicio. Si tiene algún problema o pregunta relacionada con su servicio, comuníquese con la compañía de servicios públicos correspondiente. Lo entiendes?"
                }
            },
            {
                "id": "disclosure-cancel-timeline-electric-il",
                "question": {
                    "english": "After recieving confirmation from your electric utility you will have 10 <em>business</em> days to cancel this contract before it is effective.",
                    "spanish": "Después de recibir la confirmación de su compañía eléctrica, tendrá 10 días <em>hábiles</em> para cancelar este contrato antes de que entre en vigencia."
                }
            },
            {
                "id": "disclosure-cancel-how-electric-il",
                "question": {
                    "english": "You may cancel by contacting {{client.name}} at {{client.service_phone}} or {{utility.name.electric}} at {{utility.customer_service.electric}}.",
                    "spanish": "Puede cancelar contactando a {{client.name}} en {{client.service_phone}} o {{utility.name.electric}} en {{utility.customer_service.electric}}."
                }
            },
            {
                "id": "disclosure-cancel-timeline-gas-il",
                "question": {
                    "english": "Ater receiving confirmation from your natural gas utility you will have 10 <em>calendar</em> days to cancel this contract before it is effective.",
                    "spanish": "Después de recibir la confirmación de su servicio de gas natural, tendrá 10 días <em>del calendario</em> para cancelar este contrato antes de que entre en vigencia."
                }
            },
            {
                "id": "disclosure-cancel-how-gas-il",
                "question": {
                    "english": "You may cancel by contacting {{client.name}} at {{client.service_phone}} or {{utility.name.gas}} at {{utility.customer_service.gas}}.",
                    "spanish": "Puede cancelar contactando a {{client.name}} en {{client.service_phone}} o {{utility.name.gas}} en {{utility.customer_service.gas}}."
                }
            }
        ]
EOT;
        return json_decode($jsonText, true);
    }
}
