<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\ProviderIntegration;
use App\Models\Interaction;
use App\Models\Brand;

class InspireLiveEnroll extends Command
{
    public $provider_integration;
    public $client;
    public $url = '/api/1/clients/DXC3C269/tpv_results';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inspire:live:enrollments
        {--debug}
        {--debugInteractions}
        {--limit=}
        {--confirmation_code=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspire Live Enrollments (at the interaction level instead of event product)';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->client = new \GuzzleHttp\Client(['verify' => false]);
    }

    public function getBrand()
    {
        $brand = Brand::where(
            'name',
            'Inspire Energy'
        )->first();
        if (!$brand) {
            echo "Cannot find Inspire Energy in the brand table.\n";
            exit();
        }

        return $brand;
    }

    public function getProviderIntegration($brand)
    {
        $env_id = (env('APP_ENV') === 'production') ? 1 : 2;
        $pi = ProviderIntegration::where(
            'brand_id',
            $brand->id
        )->where(
            'env_id',
            $env_id
        )->first();
        if (!$pi) {
            echo "Unable to find provider integration information.\n";
            exit();
        }

        return $pi;
    }

    public function getInteractions($brand)
    {
        $limit = ($this->option('limit')) ? $this->option('limit') : 20;
        $interactions = Interaction::select(
            'interactions.id',
            'interactions.event_id',
            'interactions.created_at',
            'interactions.event_result_id',
            'interactions.tpv_staff_id',
            'interactions.interaction_type_id',
            'interactions.interaction_time',
            'interactions.notes',
            'dispositions.reason',
            'events.confirmation_code',
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
        )->leftJoin(
            'dispositions',
            'interactions.disposition_id',
            'dispositions.id'
        )->where(
            'events.brand_id',
            $brand->id
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

        return $interactions->whereNotNull(
            'interactions.event_result_id'
        )->whereNull(
            'events.deleted_at'
        )->orderBy(
            'interactions.created_at',
            'desc'
        )->with(
            [
                'interaction_type',
                'tpv_agent',
                'recordings',
                'event',
                'event.documents.uploads',
                'event.customFieldStorage',
                'event.customFieldStorage.customField',
                'event.vendor',
                'event.sales_agent' => function ($query) {
                    $query->withTrashed();
                },
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
                'event.phone.phone_number'
            ]
        )->limit($limit)->get();
    }

    public function buildPayload($interaction)
    {
        $record = [];
        if (isset($interaction->event->products) && $interaction->event->products->count() > 0) {
            $record['tpv_confirmation_code'] = $interaction->event->confirmation_code;
            $record['call_time'] = number_format($interaction->interaction_time, 2);
            $record['rep_id'] = @$interaction->event->sales_agent->tsr_id;
            $record['success'] = ($interaction->event_result_id === 1)
                ? true
                : false;
            $record['verification_dt'] = $interaction->event->created_at->format('Y-m-d\TH:i:s');
            $record['account_number'] = null;
            $record['description'] = (isset($interaction->reason))
                ? $interaction->reason
                : null;

            foreach ($interaction->event->products as $product) {
                foreach ($product->identifiers as $identifier) {
                    $record['account_number'] = @$identifier->identifier;
                }
            }
        }

        return $record;
    }

    public function runInteractions($interactions)
    {
        foreach ($interactions as $interaction) {
            echo "Running " . $interaction->confirmation_code . "\n";

            $payload = $this->buildPayload($interaction);
            if ($this->option('debug') && is_array($payload)) {
                echo json_encode($payload) . "\n";
            }

            if (isset($payload['tpv_confirmation_code'])) {
                $this->postData($payload, $interaction);
            } else {
                echo "-- Payload was empty.\n";
                $interaction->enrolled = 'FocusNoProductsFound';
                $interaction->save();
            }
        }
    }

    public function postData($payload, $interaction)
    {
        echo "-- Attempting to POST\n";
        $res = $this->client->request(
            'POST',
            $this->provider_integration->hostname . $this->url,
            [
                'debug' => false,
                'http_errors' => false,
                'auth' => [
                    $this->provider_integration->username,
                    $this->provider_integration->password
                ],
                'headers' => [
                    'User-Agent'   => 'TPV.com Focus Live Enroller',
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($payload),
            ]
        );

        if ($res->getStatusCode() === 200) {
            echo '-- Marking ' . $interaction->id . ' as complete...' . "\n";
            $interaction->enrolled = Carbon::now('America/Chicago');
            $interaction->save();
        } else {
            $results = json_decode($res->getBody(), true);

            if (isset($results['type'])) {
                echo "-- Error posting data (" . $results['type'] . ")\n";

                $interaction->enrolled = $results['type'];
                $interaction->save();
            }
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $brand = $this->getBrand();
        if ($brand !== null) {
            $this->provider_integration = $this->getProviderIntegration($brand);
            if ($this->provider_integration !== null) {
                $this->info("Running in " . config('app.env'));

                $interactions = $this->getInteractions($brand);

                if ($this->option('debugInteractions')) {
                    print_r($interactions->toArray());
                }

                if (!$interactions || $interactions->count() === 0) {
                    echo "No interactions were found.\n";
                    exit();
                }

                $this->runInteractions($interactions);
            }
        }
    }
}
