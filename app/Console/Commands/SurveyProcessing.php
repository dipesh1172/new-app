<?php

namespace App\Console\Commands;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Survey;
use App\Models\Script;
use App\Models\PhoneNumberLookup;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\CustomFieldStorage;
use App\Models\CustomField;
use App\Models\BrandCustomField;

class SurveyProcessing extends Command
{
    private $twilioClient;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey:processing
                            {-b|--brand= : Brand Identification}
                            {-t|--trickle= : Send this many surveys to the call queue}
                            {-s|--spanish : Send only spanish surveys}
                            {-r|--reset : Reset all surveys to their initial configuration}
                            {-c|--clearcalltime : Reset the last call time for all surveys}
                            {--force : Force a reset/clearcalltime in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process surveys into queue';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->twilioClient = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('reset')) {
            if (config('app.env') !== 'production' || $this->option('force')) {
                Survey::update(['deleted_at' => null, 'last_call' => null]);
                $this->info('Surveys were reset back to defaults.');
            } else {
                $this->error('Cannot clear last call time in production.');
            }

            return 0;
        }

        if ($this->option('clearcalltime')) {
            if (config('app.env') !== 'production' || $this->option('force')) {
                Survey::update(['last_call' => null]);
                $this->info('Surveys last_call time was cleared.');
            } else {
                $this->error('Cannot clear last call time in production');
            }

            return 0;
        }

        if (!$this->option('trickle')) {
            $this->error('Syntax: php artisan survey:process --trickle=<amount to release to queue>');

            return 2;
        }

        $surveys = Survey::select(
            'surveys.id',
            'surveys.event_id',
            'surveys.brand_id',
            'surveys.script_id',
            'surveys.language_id',
            'phone_numbers.phone_number',
            'surveys.customer_first_name',
            'surveys.customer_last_name',
            'surveys.customer_enroll_date',
            'surveys.custom_data'
        )->leftJoin(
            'phone_number_lookup',
            function ($join) {
                $join->on(
                    'surveys.id',
                    'phone_number_lookup.type_id'
                )->where(
                    'phone_number_type_id',
                    6
                )->whereNull(
                    'phone_number_lookup.deleted_at'
                );
            }
        )->leftJoin(
            'phone_numbers',
            'phone_number_lookup.phone_number_id',
            'phone_numbers.id'
        );

        if ($this->option('brand')) {
            $surveys = $surveys->where(
                'brand_id',
                $this->option('brand')
            );
        }

        if ($this->option('spanish')) {
            $surveys = $surveys->where(
                'language_id',
                2
            );
        } else {
            $surveys = $surveys->where(
                'language_id',
                1
            );
        }

        // $surveys = $surveys->whereDate(
        //     'surveys.customer_enroll_date',
        //     '<>',
        //     Carbon::now('America/Chicago')->today()->format('Y-m-d')
        // )

        $surveys = $surveys->where(
            'surveys.created_at',
            '<=',
            Carbon::now('America/Chicago')
        )->where(
            function ($query) {
                $query->where(
                    'surveys.last_call',
                    '<=',
                    Carbon::now('America/Chicago')->subHours(4)
                )->orWhereNull(
                    'surveys.last_call'
                );
            }
        )->orderBy(
            'surveys.customer_enroll_date',
            'asc'
        )->groupBy(
            'surveys.id'
        )->limit(
            $this->option('trickle')
        )->get();
        if ($surveys) {
            $bar = $this->output->createProgressBar(count($surveys));
            foreach ($surveys as $survey) {
                $script = Script::select(
                    'scripts.id',
                    'dnis.dnis',
                    'scripts.channel_id'
                )->leftJoin(
                    'dnis',
                    'scripts.dnis_id',
                    'dnis.id'
                )->where(
                    'scripts.id',
                    $survey->script_id
                )->first();

                $surveyType = null;
                if ($survey->event_id) {
                    $event = Event::find($survey->event_id);
                    $surveyType = 'post_tpv_survey';
                } else {
                    $surveyType = 'survey';
                    $event = Event::where(
                        'survey_id',
                        $survey->id
                    )->where(
                        'brand_id',
                        $survey->brand_id
                    )->where(
                        'script_id',
                        $survey->script_id
                    )->first();
                }
                if (!$event) {
                    $event = new Event();
                    $event->created_at = Carbon::now('America/Chicago');
                    $event->updated_at = Carbon::now('America/Chicago');
                    $event->brand_id = $survey->brand_id;
                    $event->channel_id = $script->channel_id;
                    $event->language_id = $survey->language_id;
                    $event->generateConfirmationCode();
                    $event->survey_id = $survey->id;
                    $event->script_id = $survey->script_id;
                    $event->save();

                    $event_product = new EventProduct();
                    $event_product->created_at = Carbon::now('America/Chicago');
                    $event_product->updated_at = Carbon::now('America/Chicago');
                    $event_product->event_id = $event->id;
                    $event_product->auth_first_name = $survey->customer_first_name;
                    $event_product->auth_last_name = $survey->customer_last_name;
                    $event_product->enroll_date = $survey->customer_enroll_date;
                    $event_product->save();

                    $pnl1 = PhoneNumberLookup::where(
                        'phone_number_type_id',
                        6
                    )->where(
                        'type_id',
                        $survey->id
                    )->first();
                    if ($pnl1) {
                        $pnl = new PhoneNumberLookup();
                        $pnl->phone_number_type_id = 3;
                        $pnl->type_id = $event->id;
                        $pnl->phone_number_id = $pnl1->phone_number_id;
                        $pnl->save();
                    }
                }

                if ($event) {
                    if ($survey['custom_data']) {
                        $custom_data = json_decode($survey['custom_data'], true);
                        foreach ($custom_data as $key => $value) {
                            $cf = CustomField::select(
                                'custom_fields.id'
                            )->join(
                                'brand_custom_fields',
                                'custom_fields.id',
                                'brand_custom_fields.custom_field_id'
                            )->where(
                                'custom_fields.output_name',
                                $key
                            )->where(
                                'brand_custom_fields.brand_id',
                                $survey['brand_id']
                            )->first();
                            if (!$cf) {
                                $cf = new CustomField();
                                $cf->name = $key;
                                $cf->output_name = $key;
                                $cf->description = $key;
                                $cf->custom_field_type_id = 2;
                                $cf->question = [
                                    'english' => 'N/A',
                                    'spanish' => 'N/A',
                                    'choices' => null,
                                    'optional' => true,
                                ];
                                $cf->save();

                                $newBCF = new BrandCustomField();
                                $newBCF->custom_field_id = $cf->id;
                                $newBCF->brand_id = $survey['brand_id'];
                                $newBCF->associated_with_type = 'Event';
                                $newBCF->save();
                            }

                            $cfs = new CustomFieldStorage();
                            $cfs->custom_field_id = $cf->id;
                            $cfs->value = $value;
                            $cfs->event_id = $event->id;
                            $cfs->save();
                        }
                    }

                    $language = ($event->language_id == 2)
                        ? 'spanish' : 'english';
                    Log::debug('Adding ' . $survey->phone_number . ' (' . $language . ') to queue.');

                    $attributes = [
                        'type' => 'survey',
                        'subtype' => $surveyType,
                        'script_id' => $survey->script_id,
                        'language' => $language,
                        'selected_language' => $language,
                        'contact' => $survey->phone_number,
                        'dnis' => $script->dnis,
                        'outbound_call_only' => true,
                        'event_id' => $event->id,
                        'channel_id' => $survey->channel_id,
                    ];

                    // info($attributes);

                    $survey->last_call = Carbon::now('America/Chicago');
                    $survey->save();

                    $this->twilioClient->taskrouter->workspaces(
                        config('services.twilio.workspace')
                    )->tasks->create(
                        [
                            'workflowSid' => config('services.twilio.workflow'),
                            'attributes' => json_encode($attributes),
                        ]
                    );
                }

                $bar->advance();
            }

            $bar->finish();
        } else {
            $this->error('No surveys were available to process.');

            return 4;
        }
    }
}
