<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\ProviderIntegration;
use App\Models\Brand;

class IndraVendorEnrollments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'indra:vendor:enrollments
        {--debug}
        {--forever}
        {--prevDay}
        {--hoursAgo=}
        {--confirmation_code=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process vendor live enrollments for Indra';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function getProviderIntegration($brand_id)
    {
        $env_id = (config('app.env') === 'production') ? 1 : 2;
        $pi = ProviderIntegration::where(
            'brand_id',
            $brand_id
        )->where(
            'service_type_id',
            26
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

    public function getResults($brand_id, $vendor_id)
    {
        $sps = StatsProduct::select(
            'events.external_id',
            'events.confirmation_code',
            'stats_product.result',
            'stats_product.disposition_reason'
        )->leftJoin(
            'events',
            'stats_product.event_id',
            'events.id'
        )->where(
            'stats_product.brand_id',
            $brand_id
        )->where(
            'stats_product.vendor_id',
            $vendor_id
        );

        if ($this->option('confirmation_code')) {
            $sps = $sps->where(
                'events.confirmation_code',
                $this->option('confirmation_code')
            );
        } else {
            if (!$this->option('forever')) {
                if ($this->option('prevDay')) {
                    $sps = $sps->where(
                        'stats_product.event_created_at',
                        '>=',
                        Carbon::yesterday()
                    )->where(
                        'stats_product.event_created_at',
                        '<=',
                        Carbon::today()->add(-1, 'second')
                    );
                } else {
                    if ($this->option('hoursAgo')) {
                        $sps = $sps->where(
                            'stats_product.event_created_at',
                            '>=',
                            Carbon::now()->subHours($this->option('hoursAgo'))
                        );
                    } else {
                        $sps = $sps->where(
                            'stats_product.event_created_at',
                            '>=',
                            Carbon::now()->subHours(48)
                        );
                    }
                }
            }
        }

        return $sps->whereNotNull(
            'events.external_id'
        )->get();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $brand = Brand::where(
            'name',
            'Indra Energy'
        )->first();
        if (!$brand) {
            echo "Unable to find a brand.\n";
            exit();
        }

        $vendor = Brand::where(
            'name',
            'Transparent BPO'
        )->first();
        if (!$vendor) {
            echo "Unable to find a vendor.\n";
            exit();
        }

        $results = $this->getResults($brand->id, $vendor->id);

        if ($this->option('debug')) {
            print_r($results->toArray());
        }

        $pi = $this->getProviderIntegration($brand->id);

        foreach ($results as $result) {
            $params = [
                'un' => $pi->username,
                'pw' => $pi->password,
                'LeadID' => $result->external_id,
                'TransferStatusUpdate' => ($result->result === 'Sale')
                    ? 'Verified'
                    : 'Rejected',
                'RejectionReason' => (isset($result->disposition_reason))
                    ? $result->disposition_reason
                    : '',
            ];

            $url = $pi->hostname . "/API/Lead/Update?" . http_build_query($params);

            if (!$this->option('debug')) {
                $client = new \GuzzleHttp\Client();
                $res = $client->get($url);
                $body = $res->getBody();
                $stringBody = (string) $body;

                echo ' -- Confirmation Code: ' . $result->confirmation_code . "\n";
                echo ' -- LeadID: ' . $result->external_id . "\n";
                echo ' -- URL: ' . $url . "\n";
                echo ' -- HTTP CODE: ' . $res->getStatusCode() . "\n";
                echo ' -- RESPONSE: ' . $stringBody . "\n";
            } else {
                echo "DEBUG: " . $url . "\n";
            }
        }
    }
}
