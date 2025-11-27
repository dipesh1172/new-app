<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use App\Models\ScriptQuestions;
use App\Models\ScriptAnswer;
use App\Models\Interaction;
use App\Models\Event;
use App\Http\Controllers\SupportController;

class DebugTranscript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:transcript
        {--confirmation_code=}
        {--withQuestion}
        {--debugAnswers}
        {--withHydration}
        {--digitalOnly}
        {--json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View transcripts for a provided confirmation code';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function defaultQuestions($slug, $language)
    {
        switch ($slug) {
            case 'default-svc-bill-confirm':
                $text = ($language === 2)
                    ? 'Está cambiando su (s) cuenta (s) a {{client.name}} con lo siguiente:<br /><br />{{account.identifier_table}}'
                    : 'You are switching your account(s) to {{client.name}} with the following:<br /><br />{{account.identifier_table}}';
                break;

            case 'default-sf-single-bill-name-confirm':
                $text = ($language === 2)
                    ? 'Muestro {{account.bill_name}} como el nombre que aparece en la factura de esta cuenta.'
                    : 'I show {{account.bill_name}} as the name that appears on the bill for this account.';
                break;

            case 'default-idents-confirm':
                $text = ($language === 2)
                    ? 'Os muestro los siguientes datos de cuenta:<br /><br /> {{account.address_table}}'
                    : 'I show the following account details:<br /><br /> {{account.address_table}}';
                break;

            case 'default-dual-bill-name-confirm-same':
            case 'default-dual-bill-name-confirm':
                $text = ($language === 2)
                    ? 'Muestro {{account.bill_name}} como el nombre que aparece en las cuentas de electricidad y gas natural para estas cuentas.'
                    : 'I show {{account.bill_name}} as the name that appears on the electic and natural gas bills for these accounts.';
                break;

            case '-x-verify-contract-no-fee':
            case 'verify-contract-no-fee':
                $text = ($language === 2)
                    ? 'Do you understand that you may cancel this contract at any time with no fee or penalty?'
                    : '¿Comprende que puede cancelar este contrato en cualquier momento sin cargo o penalización?';
                break;

            case 'verify-contract-fee':
                $text = ($language === 2)
                    ? 'Do you understand that you may cancel this contract at any time, however, if you cancel before the end of the contract term a cancellation fee of {{product.cancellation_fee}} will apply?'
                    : '¿Comprende que puede cancelar este contrato en cualquier momento, sin embargo, si cancela antes de la finalización del plazo del contrato, se aplicará un cargo por cancelación de {{product.cancellation_fee}}?';
                break;

            default:
                $text = "Unknown :: " . $slug;
        }

        return $text;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->option('confirmation_code')) {
            echo "Syntax:  php artisan debug:transcript --confirmation_code=<a confirmation code>\n";
            exit();
        }

        $event = Event::where(
            'confirmation_code',
            $this->option('confirmation_code')
        )->first();
        if ($event) {
            $interactions = Interaction::select(
                'interactions.id',
                'interactions.session_call_id',
                'interactions.session_id',
                'interaction_types.name'
            )->leftJoin(
                'interaction_types',
                'interactions.interaction_type_id',
                'interaction_types.id'
            )->where(
                'interactions.event_id',
                $event->id
            )->orderBy(
                'interactions.created_at'
            )->get();
            if ($interactions) {
                $headers = ['Question Date', 'Answer Date', 'Question ID', 'Question', 'Answer', 'Input', 'Confidence'];
                foreach ($interactions as $interaction) {
                    $results = [];

                    // only show digital.
                    if (
                        $this->option('digitalOnly')
                        && $interaction->name !== 'digital'
                    ) {
                        continue;
                    }

                    $answers = ScriptAnswer::select(
                        'script_questions.created_at',
                        'script_answers.created_at as script_answer_created_at',
                        'script_answers.answer',
                        'script_answers.event_id',
                        'script_answers.additional_data',
                        'script_answers.question_id as script_answer_question_id',
                        'script_questions.section_id',
                        'script_questions.subsection_id',
                        'script_questions.question_id',
                        'script_questions.question'
                    )->leftJoin(
                        'script_questions',
                        'script_answers.question_id',
                        'script_questions.id'
                    )->where(
                        'script_answers.interaction_id',
                        $interaction->id
                    )->orderBy(
                        'script_questions.section_id'
                    )->orderBy('script_questions.subsection_id')
                        ->orderBy('script_questions.question_id')
                        ->get()->map(function ($item) {
                            if (empty($item->question)) {
                                $item->decoded = SupportController::decode_script_answer_question_id($item->script_answer_question_id);
                            } else {
                                $item->decoded = null;
                            }
                            return $item;
                        })->sort(function ($a, $b) {
                            if (!empty($a->decoded) && !empty($b->decoded)) {
                                if ($a->decoded['section'] < $b->decoded['section']) {
                                    return -1;
                                }
                                if ($a->decoded['section'] > $b->decoded['section']) {
                                    return 1;
                                }
                                if ($a->decoded['product_group'] < $b->decoded['product_group']) {
                                    return -1;
                                }
                                if ($a->decoded['product_group'] > $b->decoded['product_group']) {
                                    return 1;
                                }
                                if ($a->decoded['product_group_index'] < $b->decoded['product_group_index']) {
                                    return -1;
                                }
                                if ($a->decoded['product_group_index'] > $b->decoded['product_group_index']) {
                                    return 1;
                                }
                                if ($a->decoded['question_index'] < $b->decoded['question_index']) {
                                    return -1;
                                }
                                if ($a->decoded['question_index'] > $b->decoded['question_index']) {
                                    return 1;
                                }
                            }
                            return 0;
                        });

                    if ($answers) {
                        if (!$this->option('json')) {
                            $this->info('Confirmation code = ' . $this->option('confirmation_code'));
                            $this->info('Interaction ID = ' . $interaction->id);
                            $this->info('Interaction Type = ' . $interaction->name);
                            $this->info('Session ID = ' . $interaction->session_id);
                            $this->info('Session Call ID = ' . $interaction->session_call_id);
                        }

                        $varMap = SupportController::getVariableMap();
                        $lang = 1;
                        $eventData = SupportController::gatherEventDetails($event->id);

                        $productGroups = SupportController::group_products($eventData['products'], 'address');

                        foreach ($answers as $answer) {
                            if (!empty($anwser->decoded) && $answer->decoded['product_group'] !== null) {
                                $pgi = $answer->decoded['product_group_index'];
                                if ($pgi === null) {
                                    $pgi = 0;
                                }
                                if (!empty($productGroups[$answer->decoded['product_group']][$pgi])) {
                                    $eventData['selectedProduct'] = $productGroups[$answer->decoded['product_group']][$pgi];
                                } else {
                                    unset($eventData['selectedProduct']);
                                }
                            } else {
                                unset($eventData['selectedProduct']);
                            }
                            $question = '...';
                            if ($this->option('withQuestion')) {
                                if (
                                    isset($answer->question['english'])
                                    && isset($answer->question['english'][0])
                                    && isset($answer->question['english'][0]['text'])
                                ) {
                                    $question = $answer->question['english'][0]['text'];
                                } else {
                                    if (!empty($answer->decoded) && $answer->decoded['summary'] && !empty($answer->decoded['question_id'])) {
                                        $sq = ScriptQuestions::withTrashed()->find($answer->decoded['question_id']);
                                        if (!empty($sq)) {
                                            if (is_array($sq->question)) {
                                                $answer->created_at = $sq->created_at;
                                                $answer->section_id = $sq->section_id;
                                                $answer->subsection_id = $sq->subsection_id;
                                                $answer->question_id = $sq->question_id;

                                                $question = $sq->question['english'];
                                            }
                                        } else {
                                            $answer->created_at = '2000-01-10 00:00:00';
                                            $answer->section_id = $answer->decoded['section'];
                                            $answer->subsection_id = $answer->decoded['product_group'] !== null ? $answer->decoded['product_group'] : 0;
                                            $answer->question_id = $answer->decoded['question_index'] !== null ? $answer->decoded['question_index'] : 0;
                                            $question = $this->defaultQuestions(
                                                $answer->decoded['question_id'],
                                                1 // english
                                            );
                                        }
                                    } else {
                                        //dd($answers->toArray());
                                    }
                                }

                                if ($this->option('withHydration')) {
                                    $question = SupportController::hydrateVariables(
                                        $question,
                                        $eventData,
                                        $lang,
                                        $varMap
                                    );
                                }
                            }

                            $answer->question = $question;
                            $ids = $answer->section_id . '.' . $answer->subsection_id . '.' . $answer->question_id;

                            if (!empty($answer->created_at) && !empty($question)) {
                                $results[] = [
                                    ((string) $answer->created_at === '1970-01-01 00:00:01')
                                        ? 'N/A'
                                        : $answer->created_at,
                                    $answer->script_answer_created_at,
                                    $ids,
                                    preg_replace('/\r|\n/', '', $question),
                                    $answer->answer,
                                    $answer->additional_data['speech_input'],
                                    $answer->additional_data['speech_confidence']
                                ];
                            } else {
                                dd($answer);
                            }
                        }

                        /*usort(
                            $results,
                            function ($a, $b) {
                                return version_compare($a[2], $b[2]);
                            }
                        );*/

                        if ($this->option('debugAnswers')) {
                            dd($answers->toArray());
                        }

                        if (!empty($results)) {
                            if ($this->option('json')) {
                                $out = [];
                                foreach ($results as $result) {
                                    $out[] = [
                                        Str::slug($headers[0]) => $result[0],
                                        Str::slug($headers[1]) => $result[1],
                                        Str::slug($headers[2]) => $result[2],
                                        Str::slug($headers[3]) => $result[3],
                                        Str::slug($headers[4]) => $result[4],
                                        Str::slug($headers[5]) => $result[5],
                                        Str::slug($headers[6]) => $result[6],
                                    ];
                                }
                                $this->line(json_encode($out));
                            } else {
                                $this->table($headers, $results);
                            }
                        } else {
                            if ($this->option('json')) {
                                $this->line('[]');
                            } else {
                                $this->info(' ** No table to display.');
                                $this->line('');
                            }
                        }
                    }
                }
            }
        }
    }
}
