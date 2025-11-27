<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\ScriptAnswer;
use App\Models\StatsProduct;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GMNoSurveyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gm:survey:noreport {--debug} {--internal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Green Mountain No answer Survey Report';

    /**
     * Create a new command instance.
     *
     * @return void
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
        if (config('app.env') != 'production' && !$this->option('internal')) {
            echo "This is only run in production unless the --internal flag is used as well.\n";
            exit();
        }

        $debug = $this->option('debug');
        $brand = Brand::where(
            'name',
            'Green Mountain Energy Company'
        )->first();
        if ($brand) {
            $results = ScriptAnswer::select(
                'script_answers.created_at',
                'surveys.customer_first_name',
                'surveys.customer_last_name',
                'surveys.refcode',
                'surveys.account_number',
                'events.confirmation_code',
                'script_answers.answer_type',
                'phone_numbers.phone_number AS btn',
                'states.state_abbrev',
                'interactions.notes',
                DB::raw('CONCAT(script_questions.section_id, ".", script_questions.subsection_id, ".", script_questions.question_id) AS qid'),
                'script_questions.question'
            )->leftJoin(
                'interactions',
                'script_answers.interaction_id',
                'interactions.id'
            )->leftJoin(
                'events',
                'interactions.event_id',
                'events.id'
            )->leftJoin(
                'surveys',
                'events.survey_id',
                'surveys.id'
            )->leftJoin(
                'script_questions',
                'script_answers.question_id',
                'script_questions.id'
            )->leftJoin(
                'states',
                'surveys.state_id',
                'states.id'
            )->leftJoin(
                'phone_number_lookup',
                function ($join) {
                    $join->on(
                        'phone_number_lookup.type_id',
                        'events.id'
                    )->where(
                        'phone_number_lookup.phone_number_type_id',
                        3
                    );
                }
            )->leftJoin(
                'phone_numbers',
                'phone_number_lookup.phone_number_id',
                'phone_numbers.id'
            )->where(
                'events.brand_id',
                $brand->id
            )->where(
                'script_answers.answer_type',
                'No'
            )->whereNotNull(
                'events.survey_id'
            )->where(
                'interactions.created_at',
                '>=',
                Carbon::now('America/Chicago')->subMonth()
            )->orderBy(
                'interactions.created_at',
                'desc'
            )->get()->map(
                function ($item) {
                    $item->feedback = (isset($item->notes) && isset($item->notes['feedback']))
                        ? preg_replace(
                            '/\s\s+/',
                            ' ',
                            $item->notes['feedback']
                        ) : null;

                    $item->question = trim(
                        preg_replace(
                            '/\s\s+/',
                            ' ',
                            $item->question['english']
                        )
                    );

                    unset($item->notes);

                    return $item;
                }
            );

            // print_r($results->toArray());
            // exit();

            $filename = "survey-nos-" . time() . ".csv";
            $path = public_path("tmp/" . $filename);
            $file = fopen($path, "w");

            $data_array = $results->toArray();
            $data_written = (count($data_array) > 0)
                ? true
                : false;

            $i = 0;
            foreach ($data_array as $result) {
                if ($i == 0) {
                    fputcsv($file, array_keys($result));
                }

                fputcsv($file, array_values($result));
                $i++;
            }

            fclose($file);

            if ($data_written) {
                try {
                    $subject = "Survey Report - " . date("F j, Y");

                    if ($this->option('internal')) {
                        $email_address = [
                            'eduardo@tpv.com',
                            'justin@tpv.com',
                            'paul@tpv.com',
                            'report@tpv.com'
                        ];
                    } else {
                        $email_address = [
                            'TXQualityAssurance@greenmountain.com',
                            'Lauren.Feldman@nrg.com',
                            'Omar.Tello@nrg.com',
                            'PVSanch1@reliant.com',
                            'report@tpv.com'
                        ];
                    }

                    $data = [];

                    Mail::send(
                        'emails.sendNoSurveyReport',
                        $data,
                        function ($message) use ($subject, $email_address, $path) {
                            $message->subject($subject);
                            $message->from('no-reply@tpvhub.com');
                            $message->to($email_address);
                            $message->attach($path);
                        }
                    );
                } catch (\Exception $e) {
                    echo "Error: " . $e . "\n";
                }

                unlink($path);
            }
        }
    }
}
