<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use App\Models\JsonDocument;
use App\Models\Interaction;
use App\Models\Brand;

class ArcadiaLiveEnroll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'arcadia:live:enrollments
        {--skipRecordingCheck}
        {--debug}
        {--debugInteractions}
        {--limit=}
        {--nopost}
        {--confirmation_code=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Arcadia Live Enrollments (at the interaction level instead of event product)';

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
        $brand = Brand::whereIn(
            'name',
            [
                'Arcadia'
            ]
        )->first();

        if (!$brand) {
            echo "Cannot find Arcadia in the brand table.\n";
            exit();
        }

        $client = new \GuzzleHttp\Client(['verify' => false]);

        $interactions = Interaction::select(
            'interactions.id',
            'interactions.event_id',
            'interactions.created_at',
            'interactions.event_result_id',
            'interactions.tpv_staff_id',
            'interactions.interaction_type_id',
            'interactions.interaction_time',
            'interactions.disposition_id',
            'interactions.notes',
            'eztpvs.id AS eztpv_id',
            'eztpvs.ip_addr'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->leftJoin(
            'eztpvs',
            'events.eztpv_id',
            'eztpvs.id'
        )->where(
            'events.brand_id',
            $brand->id
        )->whereNotNull(
            'interactions.event_result_id'
        );

        if ($this->option('confirmation_code')) {
            echo "Resetting " . $this->option('confirmation_code') . " enrolled to NULL\n";
            $interactions = $interactions->where(
                'events.confirmation_code',
                $this->option('confirmation_code')
            );
        } else {
            $interactions = $interactions->whereNull(
                'interactions.enrolled'
            );
        }

        $interactions = $interactions->whereIn(
            'interactions.interaction_type_id',
            [
                1, // call_inbound
                2, // call_outbound
                3, // eztpv
                6, // digital
                88, // qa_update
                99, // status_update
            ]
        )->orderBy(
            'interactions.created_at',
            'asc'
        )->with(
            'interaction_type',
            'tpv_agent',
            'recordings',
            'event',
            'event.documents.uploads',
            'event.customFieldStorage',
            'event.customFieldStorage.customField',
            'event.vendor',
            'event.sales_agent',
            'event.sales_agent.user',
            'event.products.rate.product',
            'event.products.rate.utility',
            'event.products.rate.utility.identifiers',
            'event.products.rate.utility.utility',
            'event.products.serviceAddress',
            'event.products.billingAddress',
            'event.products.identifiers',
            'event.products.identifiers.utility_account_type',
            'event.phone',
            'event.phone.phone_number',
            'disposition'
        )->get();

        if ($this->option('debugInteractions')) {
            print_r($interactions->toArray());
            exit();
        }

        if (!$interactions || $interactions->count() === 0) {
            echo "No interactions were found.\n";
            exit();
        }

        $url = 'https://hooks.zapier.com/hooks/catch/133054/byaoqr1/';

        foreach ($interactions as $interaction) {
            $record = [];
            if (!isset($interaction->event->confirmation_code)) {
                continue;
            }

            $this->info('Running ' . $interaction->event->confirmation_code);

            $sendDate = new Carbon($interaction->created_at->format('Y-m-d H:i:s'), 'America/Chicago');
            $record['Date'] = $sendDate->setTimezone('America/New_York')->toIso8601String();
            $record['ConfirmationCode'] = (isset($interaction->event->confirmation_code))
                ? $interaction->event->confirmation_code
                : null;
            $record['RefID'] = (isset($interaction->event->external_id))
                ? $interaction->event->external_id
                : null;

            $record['CustomerFirstName'] = null;
            $record['CustomerLastName'] = null;

            if (isset($interaction->event->products)) {
                foreach ($interaction->event->products as $product) {
                    $record['CustomerFirstName'] = $product->auth_first_name;
                    $record['CustomerLastName'] = $product->auth_last_name;
                }
            }

            $record['CustomerPhoneNumber'] = @str_replace(
                '+',
                '',
                $interaction->event->phone->phone_number->phone_number
            );

            $record['Result'] = ($interaction->event_result_id === 1)
                ? 'Good Sale'
                : 'No Sale';
            $record['Reason'] = (isset($interaction->disposition->reason))
                ? $interaction->disposition->reason
                : null;
            $record['InteractionType'] = @$interaction->interaction_type->name;

            $record['RepPhone'] = null;
            $record['EmailUpdated'] = null;
            $record['BTNUpdated'] = null;

            if (isset($interaction->event)
                && isset($interaction->event->customFieldStorage)
                && $interaction->event->customFieldStorage !== null
            ) {
                foreach ($interaction->event->customFieldStorage as $customField) {
                    if (isset($customField)
                        && isset($customField->customField)
                        && isset($customField->customField->output_name)
                    ) {
                        if ($customField->customField->output_name === 'rep_phone') {
                            $record['RepPhone'] = $customField->value;
                        }

                        if ($customField->customField->output_name === 'email_updated') {
                            $record['EmailUpdated'] = $customField->value;
                        }

                        if ($customField->customField->output_name === 'btn_updated') {
                            $record['BTNUpdated'] = $customField->value;
                        }
                    }
                }
            }

            $ani = null;

            if (!empty($interaction->notes) && isset($interaction->notes['ani'])) {
                $ani = CleanPhoneNumber(trim($interaction->notes['ani']));
            }

            if (!empty($interaction->notes) && isset($interaction->notes['calledFrom'])) {
                $ani = CleanPhoneNumber(trim($interaction->notes['calledFrom']));
            }

            if (!empty($interaction->notes) && isset($interaction->notes['caller'])) {
                $ani = CleanPhoneNumber(trim($interaction->notes['caller']));
            }

            if (!empty($interaction->notes) && isset($interaction->notes['from'])) {
                $ani = CleanPhoneNumber(trim($interaction->notes['from']));
            }

            $record['ANI'] = str_replace('+1', '', $ani);
            $record['Recording'] = (isset($interaction->recordings)
                && isset($interaction->recordings[0])
                && isset($interaction->recordings[0]->recording))
                ? config('services.aws.cloudfront.domain') . '/' . $interaction->recordings[0]->recording
                : null;

            if (empty($record['Recording'])) {
                info('Recording not available.  Skipping for now...');
                continue;
            }

            if ($this->option('debug') && isset($record)) {
                echo json_encode($record) . "\n";
            }

            if ($this->option('nopost')) {
                exit();
            }

            if (!empty($record)) {
                try {
                    $res = $client->post(
                        $url,
                        [
                            'verify' => false,
                            'debug' => $this->option('debug'),
                            'headers' => [
                                'User-Agent' => 'TPVLiveEnrollment/' . config('app.version', 'debug'),
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/json'
                            ],
                            'body' => json_encode($record),
                        ]
                    );
                    if (200 == $res->getStatusCode() || 201 == $res->getStatusCode()) {
                        $body = $res->getBody();
                        $response = json_decode($body, true);

                        if ($this->option('debug')) {
                            print_r($response);
                        }

                        $jd = new JsonDocument();
                        $jd->ref_id = $interaction->id;
                        $jd->document = [
                            'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                            'response' => $response,
                            'response-headers' => $res->getHeaders(),
                            'request-data' => $record,
                        ];
                        $jd->document_type = 'arcadia-live-enroll';
                        $jd->save();

                        $interaction->enrolled = $response['request_id'];
                        $interaction->save();
                    } else {
                        $jd = new JsonDocument();
                        $jd->ref_id = $interaction->id;
                        $jd->document = [
                            'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                            'response' => $res->getBody(),
                            'response-headers' => $res->getHeaders(),
                            'request-data' => $record
                        ];
                        $jd->document_type = 'arcadia-live-enroll';
                        $jd->save();
                    }
                } catch (\Exception $e) {
                    echo '!!Exception: ' . $e->getMessage();
                    $jd = new JsonDocument();
                    $jd->ref_id = $interaction->id;
                    if ($e instanceof RequestException && $e->hasResponse()) {
                        $res = $e->getResponse();
                        echo '!!Response: ' . Psr7\str($res);

                        $jd->document = [
                            'error' => $res->getStatusCode() . ' ' . $res->getReasonPhrase(),
                            'response' => $res->getBody(),
                            'response-headers' => $res->getHeaders(),
                            'request-data' => $record
                        ];
                    } else {
                        $jd->document = [
                            'error' => $e->getMessage(),
                            'code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'request-data' => $record
                        ];
                    }
                    $jd->document_type = 'arcadia-live-enroll';
                    $jd->save();
                }
            }
        }
    }
}
