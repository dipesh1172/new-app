<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\ScriptAnswer;
use App\Models\Brand;

class GMSurveyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gm:survey:report {--debug} {--internal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Green Mountain Survey Report';

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
        if ('production' != config('app.env') && !$this->option('internal')) {
            echo "This is only run in production unless the --internal flag is used as well.\n";
            exit();
        }

        $debug = $this->option('debug');
        $brand = Brand::where(
            'name',
            'Green Mountain Energy Company'
        )->first();
        if ($brand) {
            $results = StatsProduct::select(
                'stats_product.event_id',
                'stats_product.event_created_at',
                'stats_product.interaction_id',
                'stats_product.interaction_created_at',
                'stats_product.confirmation_code',
                'stats_product.btn',
                'stats_product.tpv_agent_name',
                'stats_product.tpv_agent_label',
                'surveys.refcode',
                'surveys.account_number',
                'surveys.customer_first_name',
                'surveys.customer_last_name',
                'stats_product.language',
                'stats_product.channel',
                DB::raw('DATE(surveys.customer_enroll_date) AS customer_enroll_date'),
                'surveys.referral_id',
                'surveys.srvc_address',
                'surveys.account_number',
                'surveys.agency',
                'surveys.enroll_source',
                'surveys.agent_vendor',
                'surveys.agent_name as survey_agent_name',
                'surveys.contr_acct_id',
                'stats_product.result',
                'stats_product.disposition_id',
                'stats_product.disposition_reason',
                'interactions.notes',
                'stats_product.service_city',
                'stats_product.service_zip',
                'stats_product.service_state'
            )->with(
                'interactions',
                'interactions.disposition'
            )->where(
                'stats_product_type_id',
                3
            )->leftJoin(
                'surveys',
                'stats_product.survey_id',
                'surveys.id'
            )->leftJoin(
                'interactions',
                'stats_product.interaction_id',
                'interactions.id'
            )->where(
                'stats_product.brand_id',
                $brand->id
            )->where(
                'interaction_created_at',
                '>=',
                Carbon::now('America/Chicago')->subMonth()
            )->orderBy(
                'interaction_created_at',
                'desc'
            )->get()->map(
                function ($item) {
                    $dt_attempt1 = null;
                    $dt_attempt2 = null;
                    $dt_attempt3 = null;

                    $attempt1 = null;
                    $attempt2 = null;
                    $attempt3 = null;

                    $attempt1_id = null;
                    $attempt2_id = null;
                    $attempt3_id = null;

                    for ($i = 0; $i < 3; ++$i) {
                        if (isset($item->interactions[$i])) {
                            if (null === $dt_attempt1) {
                                $attempt1_id = $item->interactions[$i]['id'];
                                $dt_attempt1 = $item->interactions[$i]['created_at']->toDateTimeString();
                                $attempt1 = (isset($item->interactions[$i]['disposition']))
                                    ? $item->interactions[$i]['disposition']['reason']
                                    : null;
                                continue;
                            }

                            if (null === $dt_attempt2) {
                                $attempt2_id = $item->interactions[$i]['id'];
                                $dt_attempt2 = $item->interactions[$i]['created_at']->toDateTimeString();
                                $attempt2 = (isset($item->interactions[$i]['disposition']))
                                    ? $item->interactions[$i]['disposition']['reason']
                                    : null;
                                continue;
                            }

                            if (null === $dt_attempt3) {
                                $attempt3_id = $item->interactions[$i]['id'];
                                $dt_attempt3 = $item->interactions[$i]['created_at']->toDateTimeString();
                                $attempt3 = (isset($item->interactions[$i]['disposition']))
                                    ? $item->interactions[$i]['disposition']['reason']
                                    : null;
                                continue;
                            }
                        }
                    }

                    $item->dt_attempt1 = $dt_attempt1;
                    $item->attempt1 = $attempt1;
                    $item->attempt1_id = $attempt1_id;

                    $item->dt_attempt2 = $dt_attempt2;
                    $item->attempt2 = $attempt2;
                    $item->attempt2_id = $attempt2_id;

                    $item->dt_attempt3 = $dt_attempt3;
                    $item->attempt3 = $attempt3;
                    $item->attempt3_id = $attempt3_id;

                    if (!isset($item->feedback)) {
                        if (isset($item->notes) && isset($item->notes['feedback'])) {
                            $item->feedback = trim(
                                preg_replace(
                                    '/\s\s+/',
                                    ' ',
                                    $item->notes['feedback']
                                )
                            );
                        }
                    }

                    $qas = [];
                    $questions_answers = ScriptAnswer::select(
                        'script_questions.section_id',
                        'script_questions.subsection_id',
                        'script_questions.question_id',
                        'script_questions.question',
                        'script_answers.answer_type',
                        'script_answers.answer'
                    )->leftJoin(
                        'script_questions',
                        'script_answers.question_id',
                        'script_questions.id'
                    )->where(
                        'script_answers.interaction_id',
                        $item->interaction_id
                    )->get();
                    foreach ($questions_answers as $qa) {
                        $qid = $qa->section_id . '.'
                            . $qa->subsection_id . '.'
                            . $qa->question_id;
                        $qas[$qid] = (isset($qa->answer) && 'null' !== $qa->answer && null !== $qa->answer)
                            ? $qa->answer : $qa->answer_type;
                    }

                    $item->agent_polite = (isset($qas['3.1.1']))
                        ? $qas['3.1.1'] : null;
                    $item->understand_chose_supplier = (isset($qas['4.1.1']))
                        ? $qas['4.1.1'] : null;
                    $item->signed_form_call = (isset($qas['5.1.1']))
                        ? $qas['5.1.1'] : null;
                    $item->reason_for_choosing = (isset($qas['7.1.2']))
                        ? $qas['7.1.2'] : null;
                    $item->agent_rating = (isset($qas['8.1.1']))
                        ? $qas['8.1.1'] : null;
                    $item->agent_gave_toc = (isset($qas['6.1.1']))
                        ? $qas['6.1.1'] : null;

                    return $item;
                }
            );

            // print_r($results->toArray());
            // exit();

            $filename = 'survey-' . time() . '.csv';
            $path = public_path('tmp/' . $filename);
            $file = fopen($path, 'w');

            $headers = [
                'contract_id',
                'esi_id',
                'brand_name',
                'btn',
                // 'ani',
                'language',
                'agent_id',
                'agent_name',
                'customer_name',
                'referral_id',
                'srvc_address',
                'agency',
                'enroll_source',
                'agent_vendor',
                'survey_agent_name',
                'contr_acct_id',
                // 'email',
                // 'trainid',
                'date',
                'datesavedimported',
                'status',
                // 'product_code',
                // 'centerid',
                // 'operator_id',
                // 'duration',
                'account_number',
                // 'service_address1',
                // 'service_address2',
                'service_city',
                'service_state',
                'service_zip',
                // 'reason_code',
                // 'reason_description',
                // 'utility',
                'vendor_name',
                'dt_attempt1',
                'attempt1',
                'dt_attempt2',
                'attempt2',
                'dt_attempt3',
                'attempt3',
                'agent_polite',
                'understand_chose_supplier',
                'signed_form_call',
                // 'likely_to_recommend',
                'additional_comments',
                // 'clearly_represent_retailer',
                // 'agree_gm_to_supply',
                'agent_gave_toc',
                'reason_for_choosing',
                'agent_rating',
                // 'agent_rating_reason',
                // 'carrier',
                // 'phone_type',
                // 'enrollment_id',
                // 'enrollment_type',
                // 'retail_location_id',
                // 'retail_location',
                // 'email_validation',
                // 'used_ecl',
                // 'location_description',
                'rec_id',
            ];

            fputcsv($file, $headers);

            $data_array = $results->toArray();
            $data_written = (count($data_array) > 0)
                ? true
                : false;

            foreach ($data_array as $result) {
                // if ($debug) {
                //     $array['event_id'] = $result['event_id'];
                //     $array['interaction_id'] = $result['interaction_id'];
                // }

                $array['contract_id'] = $result['refcode'];
                $array['esi_id'] = '' . $result['account_number'];
                $array['brand_name'] = $brand->name;
                $array['btn'] = (isset($result['btn']))
                    ? $result['btn'] : null;
                // $array['ani'] = null;
                $array['language'] = $result['language'];
                $array['agent_id'] = (isset($result['tpv_agent_label']))
                    ? $result['tpv_agent_label'] : null;
                $array['agent_name'] = (isset($result['tpv_agent_name']))
                    ? $result['tpv_agent_name'] : null;
                $array['customer_name'] = $result['customer_first_name'] . ' ' . $result['customer_last_name'];
                $array['referral_id'] = $result['referral_id'];
                $array['srvc_address'] = $result['srvc_address'];
                $array['agency'] = $result['agency'];
                $array['enroll_source'] = $result['enroll_source'];
                $array['agent_vendor'] = $result['agent_vendor'];
                $array['survey_agent_name'] = $result['survey_agent_name'];
                $array['contr_acct_id'] = $result['contr_acct_id'];
                // $array['email'] = null;
                // $array['trainid'] = $result['refcode'];
                $array['date'] = $result['interaction_created_at'];
                $array['datesavedimported'] = $result['interaction_created_at'];
                $array['status'] = ('Sale' == $result['result'])
                    ? 'Complete' : 'Unsuccessful';
                // $array['product_code'] = null;
                // $array['centerid'] = null;
                // $array['operator_id'] = null;
                // $array['duration'] = null;
                $array['account_number'] = '' . $result['account_number'];
                // $array['service_address1'] = null;
                // $array['service_address2'] = null;
                $array['service_city'] = $result['service_city'];
                $array['service_state'] = $result['service_state'];
                $array['service_zip'] = $result['service_zip'];
                // $array['reason_code'] = null;
                // $array['reason_description'] = null;
                // $array['utility'] = null;
                $array['vendor_name'] = $brand->name;

                if ($debug) {
                    $array['attempt1_id'] = $result['attempt1_id'];
                }

                $array['dt_attempt1'] = $result['dt_attempt1'];
                $array['attempt1'] = $result['attempt1'];

                if ($debug) {
                    $array['attempt2_id'] = $result['attempt2_id'];
                }

                $array['dt_attempt2'] = $result['dt_attempt2'];
                $array['attempt2'] = $result['attempt2'];

                if ($debug) {
                    $array['attempt3_id'] = $result['attempt3_id'];
                }

                $array['dt_attempt3'] = $result['dt_attempt3'];
                $array['dt_attemp3'] = $result['attempt3'];
                $array['agent_polite'] = $result['agent_polite'];
                $array['understand_chose_supplier'] = $result['understand_chose_supplier'];
                $array['signed_form_call'] = $result['signed_form_call'];
                // $array['likely_to_recommend'] = null;
                $array['additional_comments'] = (isset($result['feedback']))
                    ? $result['feedback'] : null;
                // $array['clearly_represent_retailer'] = null;
                // $array['agree_gm_to_supply'] = null;
                $array['agent_gave_toc'] = $result['agent_gave_toc'];
                $array['reason_for_choosing'] = $result['reason_for_choosing'];
                $array['agent_rating'] = $result['agent_rating'];
                // $array['agent_rating_reason'] = null;
                // $array['carrier'] = null;
                // $array['phone_type'] = null;
                // $array['enrollment_id'] = null;
                // $array['enrollment_type'] = null;
                // $array['retail_location_id'] = null;
                // $array['retail_location'] = null;
                // $array['email_validation'] = null;
                // $array['used_ecl'] = null;
                // $array['location_description'] = null;
                $array['rec_id'] = $result['confirmation_code'];

                if ($debug) {
                    print_r($array);
                    exit();
                }

                fputcsv($file, $array);
            }

            if ($data_written) {
                // $content = @file_get_contents($path);
                // $year = date('Y');
                // $month = date('m');
                // $day = date('d');
                // $keyname = "uploads/brands/{$brand->id}/recordings/{$year}/{$month}/{$day}";
                // Storage::disk('s3')
                //     ->put("{$keyname}/{$filename}", $content, 'public');

                // $s3_file = config('services.aws.cloudfront.domain') . "/" . $keyname . "/" .$filename . "\n";

                try {
                    $subject = 'Survey Report - ' . date('F j, Y');

                    if ('production' !== config('app.env')) {
                        $email_address = [
                            // 'eduardo@tpv.com',
                            // 'justin@tpv.com',
                            'paul@tpv.com',
                            // 'wilberto@tpv.com',
                            'brian@tpv.com',
                            'jeff@tpv.com',
                            'report@tpv.com'
                        ];
                    } else {
                        $email_address = [
                            'TXQualityAssurance@greenmountain.com',
                            'Lauren.Feldman@nrg.com',
                            'Omar.Tello@nrg.com',
                            'PVSanch1@reliant.com',
                            'report@tpv.com',
                            'Eliseo.Angel@GreenMountain.com',
                            'Michael.Gonzalez@GreenMountain.com',
                        ];
                    }

                    $data = [];

                    Mail::send(
                        'emails.sendSurveyReport',
                        $data,
                        function ($message) use ($subject, $email_address, $path) {
                            $message->subject($subject);
                            $message->from('no-reply@tpvhub.com');
                            $message->to($email_address);
                            $message->attach($path);
                        }
                    );
                } catch (\Exception $e) {
                    echo 'Error: ' . $e . "\n";
                }

                unlink($path);
            }
        }
    }
}
