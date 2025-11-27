<?php

namespace App\Console\Commands\VendorLiveEnrollment;

use App\Console\Commands\VendorLiveEnrollment\BrandHandlers\Generic;
use App\Console\Commands\VendorLiveEnrollment\BrandHandlers\IHandler;
use App\Console\Commands\VendorLiveEnrollment\BrandHandlers\NRG;
use App\Console\Commands\VendorLiveEnrollment\BrandHandlers\Residents;
use App\Console\Commands\VendorLiveEnrollment\BrandHandlers\RPA;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\Brand;

class Run extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:vendor:live:enrollments 
        {--brand= : The brand ID} 
        {--debug : Sets the debug option in the HTTP client} 
        {--forever : No date range used when looking for records to submit} 
        {--prevDay : Date range set to previous day when looking for records to submit} 
        {--hoursAgo= : Date range set to specified hours back when looking for records to submit} 
        {--vendorCode= : Limit data to this vendor code when looking for records to submit} 
        {--redo : Allow previously submitted records to be submitted again} 
        {--dry-run : Skip API calls} 
        {--show-sql : Display SQL queries in console} 
        {--confirmationCode= : Limit data to this confirmation code when looking for records to submit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process live enrollments (to vendors)';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Resolve handler object for brand
     * @param Brand $brand
     * @return IHandler
     */
    private function resolve_handler($brand): IHandler
    {
        $this->info('Looking for "' . $brand->name . '" handler...');

        if ($brand->name == 'Clearview Energy')
            return new RPA($brand);
        if ($brand->name == 'RPA Energy')
            return new RPA($brand);
        if ($brand->name == 'IDT Energy')
            return new Residents($brand);
        if ($brand->name == 'Residents Energy')
            return new Residents($brand);
        if ($brand->name == 'NRG')
            return new NRG($brand, $this);

        $this->info('Not found. Using generic handler...');
        return new Generic($brand);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Getting brand info...');

        if ($this->option('brand')) {
            $brand = Brand::find($this->option('brand'));
        } else {
            $this->error('You must specify a brand.');
            exit();
        }

        $this->info('Building TPV data query...');

        $sps = StatsProduct::select(
            'stats_product.*',
            'brand_utilities.service_territory',
            'brand_utilities.utility_label',
            'offices.grp_id AS office_grp_id',
            'events.external_id AS event_external_id'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'events',
            'stats_product.event_id',
            'events.id'
        )->leftJoin(
            'brand_utilities',
            function ($join) {
                $join->on(
                    'stats_product.utility_id',
                    'brand_utilities.utility_id'
                )->on(
                    'brand_utilities.brand_id',
                    'stats_product.brand_id'
                );
            }
        )->leftJoin(
            'offices',
            'stats_product.office_id',
            'offices.id'
        )->whereNull(
            'event_product.live_enroll'
        )->where(
            'stats_product.brand_id',
            $brand->id
        );

        if ($this->option('vendorCode')) {
            $sps = $sps->where(
                'stats_product.vendor_code',
                $this->option('vendorCode')
            );
        }

        if (!$this->option('forever') && !$this->option('confirmationCode')) {
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

        if ($this->option('confirmationCode')) {
            $sps = $sps->where(
                'stats_product.confirmation_code',
                $this->option('confirmationCode')
            );
        }

        $sps = $sps->where(
            'stats_product_type_id',
            1
        );

        // begin using brand handler
        $brand_handler = $this->resolve_handler($brand);
        if (!$brand_handler) {
            $this->error('Cannot find api handler of brand: ' . $brand->name);
            exit();
        }

        $sps = $brand_handler->applyCustomFilter($sps);

        // Display SQL query.        
        if ($this->option('show-sql')) {
            $queryStr = getSqlQueryString($sps);

            $this->info("\nQUERY:");
            $this->info("\n" . $queryStr . "\n");
        }

        $this->info('Getting TPV data...');

        $queryStart = Carbon::now("America/Chicago");
        $sps = $sps->get();
        $queryEnd = Carbon::now("America/Chicago");

        $this->info(count($sps) . ' Record(s) found.');
        $this->info('Query duration: ' . $queryEnd->diff($queryStart)->format('%H:%i:%s'));

        $brand_handler->handleSubmission($sps, $this->options());
    }
}
