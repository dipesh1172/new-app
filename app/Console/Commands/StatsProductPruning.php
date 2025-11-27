<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\StatsProduct;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Illuminate\Console\Command;

class StatsProductPruning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats:product:pruning {--brand=} {--hours=1} {--forever} {--startDate=} {--endDate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs stats product pruning starting at event products';

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
     * Gets brands whos data should be built.
     *
     * @return Brand
     */
    public function getBrands()
    {
        return Brand::select(
            'brands.id AS brand_id',
            'brands.name'
        )->whereNotNull(
            'brands.client_id'
        )->where(
            'brands.name',
            'NOT LIKE',
            'z_DXC_%'
        )->orderBy(
            'brands.name'
        )->get();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo "-- Starting old record pruning process.\n";

        foreach ($this->getBrands() as $brand) {
            if ($this->option('brand')) {
                if ($brand->brand_id != $this->option('brand')) {
                    continue;
                }
            }

            echo $brand->name . ' (' . $brand->brand_id . ")\n";

            // This process cleans up stats_product entries that were created before the produts
            // were added.  This makes sure that only rows with products exist (if applicable).
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
                    Carbon::now('America/Chicago')->subHours($this->option('hours'))
                );
            }

            $partial = $partial->groupBy(
                'confirmation_code'
            )->havingRaw('COUNT(*) > 1')->get()->toArray();

            for ($i = 0; $i < count($partial); ++$i) {
                $sps = StatsProduct::where(
                    'confirmation_code',
                    $partial[$i]['confirmation_code']
                )->where(
                    'brand_id',
                    $brand->brand_id
                )->orderBy('interaction_created_at', 'desc')->get();

                $results = [];
                foreach ($sps as $sp) {
                    $results[] = $sp->result;
                }

                $results = array_unique($results);

                if (count($results) > 1) {
                    echo 'Confirmation Code: '
                        . $partial[$i]['confirmation_code'] . "\n";
                    print_r($results);

                    StatsProduct::where(
                        'confirmation_code',
                        $partial[$i]['confirmation_code']
                    )->where(
                        'brand_id',
                        $brand->brand_id
                    )->whereNull(
                        'event_product_id'
                    )->delete();
                }
            }
        }
    }
}
