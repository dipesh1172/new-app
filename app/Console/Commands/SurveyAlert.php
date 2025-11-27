<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\ScriptAnswer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Models\ScriptQuestions;
use App\Models\Interaction;

//use Illuminate\Support\Facades\Log;

class SurveyAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gm:survey:alert {--debug} {--internal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Green Mountain Alert Survey';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $brand_id = Brand::select(
            'id'
        )->where(
            'name',
            'Green Mountain Energy Company'
        )->first()->id;
        if ($brand_id) {
            $now = Carbon::now('America/Chicago')->format('Y-m-d H:i:s');
            $minus_five_min = Carbon::now('America/Chicago')->subMinutes(10)->format('Y-m-d H:i:s');
            //$minus_five_min = Carbon::now('America/Chicago')->subHour(2)->format('Y-m-d H:i:s');

            //Searching for the interactions with disposition.reason = Person answered, wrong number
            $interactions = Interaction::select(
                'interactions.id as interaction_id',
                'stats_product.bill_first_name',
                'stats_product.bill_last_name',
                'stats_product.auth_first_name',
                'stats_product.auth_last_name',
                'surveys.agent_name AS sales_agent_name',
                'stats_product.btn as phone_number'
            )->leftJoin(
                'dispositions',
                'interactions.disposition_id',
                'dispositions.id'
            )->leftJoin(
                'stats_product',
                'interactions.event_id',
                'stats_product.event_id'
            )->leftJoin(
                'surveys',
                'stats_product.survey_id',
                'surveys.id'
            )->where(
                'dispositions.reason',
                'Person answered, wrong number'
            )->where(
                'interactions.survey_processed',
                0
            )->whereNotNull(
                'stats_product.survey_id'
            )->whereBetween(
                'interactions.created_at',
                [$minus_five_min, $now]
            )->get()->map(function ($i) {
                $i['questions'] = [
                    0 => [
                        'question' => 'Hello this is {{agent.first_name}} calling on behalf of {{client.name}}.  Am I speaking with Mr./Mrs. {{user.name}}?',
                        'response' => 'Person answered, wrong number',
                        ],
                    ];

                return $i;
            })->toArray();

            // Capturing the id for the required questions
            $script_questions = ScriptQuestions::select(
                'script_questions.id',
                'script_questions.question'
            )->leftJoin(
                'scripts',
                'script_questions.script_id',
                'scripts.id'
            )->where(
                'scripts.script_type_id',
                4
            )->where(
                'scripts.brand_id',
                $brand_id
            )->get()->filter(function ($item) {
                //echo var_dump($item->question['english']);
                $q = trim(str_replace(PHP_EOL, ' ', $item->question['english']));
                switch ($q) {
                    case 'Was the agent who you spoke with at the table polite and courteous to you in speaking about our products and services?':
                        $item->question = $q;

                        break;
                    case 'Do you understand that you have chosen {{client.name}} as your new supplier?':
                        $item->question = $q;

                        break;
                    case 'Do you recall signing a tablet or completing a verification call for this enrollment?':
                        $item->question = $q;

                        break;
                    case 'Did you receive your "Thank You brochure" with the notice of cancellation?':
                        $item->question = $q;

                        break;
                    default:
                        break;
                }

                return $item;
            });

            if ($script_questions) {
                $script_answers = ScriptAnswer::select(
                    'script_answers.interaction_id',
                    'script_answers.question_id',
                    'stats_product.bill_first_name',
                    'stats_product.bill_last_name',
                    'stats_product.auth_first_name',
                    'stats_product.auth_last_name',
                    'surveys.agent_name AS sales_agent_name',
                    'stats_product.btn as phone_number'
                )->leftJoin(
                    'stats_product',
                    'script_answers.event_id',
                    'stats_product.event_id'
                )->leftJoin(
                    'interactions',
                    'script_answers.interaction_id',
                    'interactions.id'
                )->leftJoin(
                    'surveys',
                    'stats_product.survey_id',
                    'surveys.id'
                )->where(
                    'interactions.survey_processed',
                    0
                )->where(
                    'script_answers.answer_type',
                    'No'
                )->whereIn(
                    'script_answers.question_id',
                    $script_questions->pluck('id')
                )->whereNotNull(
                    'stats_product.survey_id'
                )->whereBetween(
                    'interactions.created_at',
                    [$minus_five_min, $now]
                )->get();
                if ($script_answers) {
                    // Array to store all the interactions with the desired script_answers / script_questions_id
                    $interactions_s = [];
                    $sq = $script_questions->mapWithKeys(function ($item) {
                        return [$item['id'] => $item['question']];
                    })->toArray();

                    // print_r($sq);
                    // print_r($script_answers->toArray());
                    // exit();

                    foreach ($script_answers as $sa) {
                        $q = $sq[$sa->question_id]['english'];

                        if (in_array($sa->interaction_id, $interactions_s)) {
                            array_push(
                                $interactions_s[$sa->interaction_id]['questions'],
                                [
                                    'question' => $q,
                                    'response' => 'No',
                                ]
                            );
                        } else {
                            $interactions_s[$sa->interaction_id] = [
                                'interaction_id' => $sa->interaction_id,
                                'bill_first_name' => ($sa->bill_first_name)
                                    ? $sa->bill_first_name
                                    : $sa->auth_first_name,
                                'bill_last_name' => ($sa->bill_last_name)
                                    ? $sa->bill_last_name
                                    : $sa->auth_last_name,
                                'phone_number' => $sa->phone_number,
                                'sales_agent_name' => $sa->sales_agent_name,
                                'questions' => [
                                    0 => [
                                        'question' => $q,
                                        'response' => 'No',
                                    ],
                                ],
                            ];
                        }
                    }

                    // Merging the two arrays into on single result
                    $interactions = (count($interactions) > 0)
                        ? array_merge($interactions, $interactions_s)
                        : $interactions_s;
                }
            }

            if (count($interactions) > 0) {
                //Log::info('Interactions', ['interactions' => $interactions]);
                //Sending an email for each iteration
                foreach ($interactions as $i) {
                    $data = [
                        'sales_agent_name' => $i['sales_agent_name'],
                        'bill_first_name' => $i['bill_first_name'],
                        'bill_last_name' => $i['bill_last_name'],
                        'bill_first_name' => ($i['bill_first_name'])
                            ? $i['bill_first_name']
                            : $i['auth_first_name'],
                        'bill_last_name' => ($i['bill_last_name'])
                            ? $i['bill_last_name']
                            : $i['auth_last_name'],
                        'phone_number' => $i['phone_number'],
                        'questions' => $i['questions'],
                    ];

                    // print_r($data);
                    // exit();

                    try {
                        //Updating the Interaction
                        $i_update = Interaction::find($i['interaction_id']);
                        $i_update->survey_processed = 1;
                        $i_update->save();

                        $subject = 'Survey Alert - '.date('F j, Y');

                        if ('production' !== config('app.env')) {
                            $email_address = [
                                        // 'eduardo@tpv.com',
                                        // 'justin@tpv.com',
                                        'paul@tpv.com',
                                        // 'shelly@tpv.com',
                                        // 'lauren@tpv.com',
                                        // //,'wilberto@tpv.com',
                                        'brian@tpv.com',
                                    ];
                        } else {
                            $email_address = [
                                'txqualityassurance@greenmountain.com',
                                'Paloma.Sanchez@nrg.com',
                                'CERodri2@nrg.com',
                            ];
                        }

                        if (!empty($data['sales_agent_name'])
                            && !empty($data['bill_first_name'])
                            && !empty($data['bill_last_name'])
                            && !empty($data['phone_number'])
                        ) {
                            Mail::send(
                                'emails.sendSurveyAlert',
                                $data,
                                function ($message) use ($subject, $email_address) {
                                    $message->subject($subject);
                                    $message->from('no-reply@tpvhub.com');
                                    $message->to($email_address);
                                }
                            );
                        }
                    } catch (\Exception $e) {
                        echo 'Error: '.$e."\n";
                    }
                }
            }
        }
    }
}
