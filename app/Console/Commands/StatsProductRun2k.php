<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Brand;
use App\Models\EventProduct;
use App\Models\Interaction;
use Carbon\Carbon;
use App\Events\ProductStatsToProcess;
use App\Events\ProductlessStatsToProcess;

/*use App\Models\AuthRelationship;
use App\Models\DefaultScCompanyPosition;
use App\Models\Event;
use App\Models\StatsProduct;
use App\Models\Vendor;
use App\Models\ZipCode;*/

class StatsProductRun2k extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats-ng:product {--brand= : The ID of the Brand to process} {--prune} {--forever} {--hours=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs stats starting at event products';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function doPrune(Brand $brand, $hoursToUse)
    {
        $partial = StatsProduct::select(
            'confirmation_code',
            DB::raw('COUNT(*) AS count')
        )->where(
            'brand_id',
            $brand->brand_id
        );

        if (!$this->option('forever')) {
            $partial = $partial->where(
                'interaction_created_at',
                '>=',
                $hoursToUse
            );
        }

        $partial = $partial->groupBy(
            'confirmation_code'
        )->havingRaw('COUNT(*) > 1')->get()->toArray();

        for ($i = 0; $i < count($partial); ++$i) {
            $sps = StatsProduct::where(
                'confirmation_code',
                $partial[$i]['confirmation_code']
            )->orderBy('interaction_created_at', 'desc')->get();

            $results = [];
            foreach ($sps as $sp) {
                $results[] = $sp->result;
            }

            $results = array_unique($results);

            if (count($results) > 1) {
                StatsProduct::where(
                    'confirmation_code',
                    $partial[$i]['confirmation_code']
                )->whereNull(
                    'event_product_id'
                )->delete();
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
        $isPruneOp = $this->option('prune');
        $isForever = $this->option('forever');
        $brandID = $this->argument('brand');
        $hoursToCheck = $this->argument('hours');
        $hours = intval($hoursToCheck);

        $brand = Brand::find($brandId);
        if ($brand === null) {
            $this->error('The Brand ID {'.$brandID.'} could not be located.');

            return 2;
        }
        if ($hours !== 2 && $isForever) {
            $this->error('Ambiguous options, do not specify forever along with the hours option.');

            return 3;
        }

        if ($hours === 0) {
            $this->error('Hours must be an integer > 0.');

            return 4;
        }

        $hoursToUse = Carbon::now('America/Chicago');
        $hoursToUse->minute = 0;
        $hoursToUse->second = 0;
        $hoursToUse = $hoursToUse->subHours($hours)->subSecond();

        if ($isPruneOp) {
            $this->doPrune($brand, $hoursToUse);

            return;
        }

        $partial = Interaction::select(
            'interactions.event_id',
            'interactions.updated_at'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->where(
            'events.brand_id',
            $brand->brand_id
        );

        if (!$isForever) {
            $partial = $partial->where(
                'interactions.created_at',
                '>=',
                $hoursToUse
            );
        }

        $partial = $partial->groupBy(
            'interactions.updated_at',
            'DESC'
        )->get()->pluck('event_id')->toArray();

        EventProduct::whereHas(
            'eventAll',
            function ($q) use ($brand, $partial) {
                $q->where('brand_id', $brand->brand_id);
                $q->whereIn('id', $partial);
            }
        )->with(
            [
                'eventAll',
                'eventAll.brand',
                'eventAll.vendor',
                'eventAll.eztpv',
                'eventAll.eztpv.eztpv_sale_type',
                'eventAll.eztpv.eztpv_docs',
                'eventAll.eztpv.eztpv_docs.uploads',
                'eventAll.script',
                'eventAll.script.dnis',
                'eventAll.office',
                'eventAll.channel',
                'eventAll.language',
                'eventAll.phone.phone_number',
                'eventAll.email.email',
                'eventAll.sales_agent',
                'eventAll.sales_agent.user',
                'eventAll.interactions',
                'eventAll.interactions.interaction_type',
                'eventAll.interactions.service_types',
                'eventAll.interactions.tpv_agent',
                'eventAll.interactions.disposition',
                'eventAll.interactions.source',
                'eventAll.interactions.result',
                'eventAll.interactions.event_flags',
                'eventAll.interactions.event_flags.flag_reason',
                'eventAll.interactions.event_flags.flagged_by',
                'eventAll.interactions.recordings',
                'eventAll.customFieldStorage.customField',
                'home_type',
                'identifiers',
                'market',
                'rate' => function ($query) {
                    $query->withTrashed();
                },
                'rate.rate_currency',
                'rate.rate_uom',
                'rate.product' => function ($query) {
                    $query->withTrashed();
                },
                'identifiers.utility_account_type',
                'addresses',
                'event_type',
                'utility_supported_fuel',
                'utility_supported_fuel.brand_utility_supported_fuels' => function ($query) use ($brand) {
                    $query->where('brand_id', $brand->brand_id);
                },
                'utility_supported_fuel.utility',
                'utility_supported_fuel.utility.brand_identifier' => function ($query) use ($brand) {
                    $query->where('brand_id', $brand->brand_id);
                },
                'rate.product.rate_type',
                'rate.product.term_type',
                'rate.product.intro_term_type',
            ]
        )->orderBy('created_at', 'desc')
        ->chunk(
            100,
            function ($results) use ($brand) {
                event(new ProductStatsToProcess($brand, $results));
            }
        );

        $partial = Interaction::select(
            'interactions.event_id'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->where(
            'events.brand_id',
            $brand->brand_id
        )->whereRaw(
            '(SELECT COUNT(*) FROM event_product WHERE event_id = events.id) = 0'
        );

        if (!$isForever) {
            $partial = $partial->where(
                'interactions.created_at',
                '>=',
                $hoursToUse
            );
        }

        $partial = $partial->groupBy(
            'interactions.event_id'
        )->withTrashed()->get()->pluck('event_id')->toArray();

        Event::with(
            'brand',
            'vendor',
            'eztpv',
            'eztpv.eztpv_sale_type',
            'eztpv.eztpv_docs',
            'eztpv.eztpv_docs.uploads',
            'script',
            'script.dnis',
            'office',
            'channel',
            'language',
            'phone.phone_number',
            'email.email',
            'sales_agent',
            'sales_agent.user',
            'customFieldStorage.customField',
            'interactions',
            'interactions.interaction_type',
            'interactions.service_types',
            'interactions.tpv_agent',
            'interactions.disposition',
            'interactions.source',
            'interactions.result',
            'interactions.event_flags',
            'interactions.event_flags.flag_reason',
            'interactions.event_flags.flagged_by',
            'interactions.recordings'
        )->whereIn(
            'events.id',
            $partial
        )->where(
            'brand_id',
            $brand->brand_id
        )->orderBy('created_at', 'desc')->withTrashed()
        ->chunk(
            100,
            function ($results) use ($brand) {
                event(new ProductlessStatsToProcess($brand, $results));
            }
        );
    }
}
