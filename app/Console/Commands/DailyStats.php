<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\Interaction;
use App\Models\EztpvDocument;
use App\Models\Eztpv;
use App\Models\DigitalSubmission;
use App\Models\DailyStat;
use App\Models\Brand;

class DailyStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily:stats {--brand=} {--days=} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily Stats Command.';

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
        $dates = array();
        $total_days = ($this->option('days'))
            ? $this->option('days')
            : 7;

        // Build an array of the last $total_days dates
        for ($i = 0; $i < $total_days; ++$i) {
            $dates[] = date('Y-m-d', strtotime("-$i days"));
        }

        // info(json_encode($dates));

        for ($j = 0; $j < count($dates); ++$j) {
            $today = Carbon::parse($dates[$j]);
            $brands = Brand::select(
                'brands.id AS brand_id',
                'brands.name AS brand_name'
            )->join(
                'invoice_rate_card',
                'brands.id',
                'invoice_rate_card.brand_id'
            )->where(
                'brands.name',
                'NOT LIKE',
                'z_DXC_%'
            )->whereNotNull(
                'brands.client_id'
            );

            if ($this->option('brand')) {
                $brands = $brands->where(
                    'brands.id',
                    $this->option('brand')
                );
            } else {
                $brands = $brands->orderBy(
                    'brands.name'
                );
            }

            $brands = $brands->get()->toArray();

            for ($i = 0; $i < count($brands); ++$i) {
                $sps = StatsProduct::where(
                    'brand_id',
                    $brands[$i]['brand_id']
                )->whereDate(
                    'event_created_at',
                    $today->format('Y-m-d')
                )->where(
                    'hrtpv',
                    0
                )->where(
                    'interaction_type',
                    '!=',
                    'sales_pitch'
                )->where(
                    'interaction_type',
                    '!=',
                    'ivr_script'
                )->where(
                    'interaction_type',
                    '!=',
                    'ivr_review'
                )->get();

                $total_records = $sps->count();
                $total_live = (clone $sps)->sum('product_time');

                $total_tulsa = (clone $sps)->where(
                    'tpv_agent_call_center_id',
                    1
                )->sum('product_time');

                $total_tahlequah = (clone $sps)->where(
                    'tpv_agent_call_center_id',
                    2
                )->sum('product_time');

                $total_lasvegas = (clone $sps)->where(
                    'tpv_agent_call_center_id',
                    3
                )->sum('product_time');

                $total_live_inbound = (clone $sps)->where(
                    'interaction_type',
                    'call_inbound'
                )->sum('product_time');

                $total_live_outbound = (clone $sps)->where(
                    'interaction_type',
                    'call_outbound'
                )->sum('product_time');

                $total_english = (clone $sps)->where(
                    'language_id',
                    1
                )->sum('product_time');

                $total_spanish = (clone $sps)->where(
                    'language_id',
                    2
                )->sum('product_time');

                $total_good_sale = (clone $sps)->where(
                    'result',
                    'Sale'
                )->sum('product_time');

                $total_no_sale = (clone $sps)->where(
                    'result',
                    'No Sale'
                )->sum('product_time');

                $total_dtd = (clone $sps)->where(
                    'channel_id',
                    1
                )->sum('product_time');

                $total_tm = (clone $sps)->where(
                    'channel_id',
                    2
                )->sum('product_time');

                $total_retail = (clone $sps)->where(
                    'channel_id',
                    3
                )->sum('product_time');

                $digital = DigitalSubmission::leftJoin(
                    'events',
                    'digital_submission.event_id',
                    'events.id'
                )->where(
                    'events.brand_id',
                    $brands[$i]['brand_id']
                )->whereDate(
                    'digital_submission.created_at',
                    $today->format('Y-m-d')
                )->whereNull(
                    'events.deleted_at'
                )->count();

                // HRTPV
                // $sps = StatsProduct::where(
                //     'brand_id',
                //     $brands[$i]['brand_id']
                // )->whereDate(
                //     'event_created_at',
                //     $today->format('Y-m-d')
                // )->where(
                //     'stats_product_type_id',
                //     2
                // )->whereNull(
                //     'stats_product.deleted_at'
                // )->get();

                // $total_hrtpv_records = $sps->count();
                // $total_hrtpv_live = (clone $sps)->sum('product_time');

                $total_dtd_eztpvs = Eztpv::leftJoin(
                    'events',
                    'events.eztpv_id',
                    'eztpvs.id'
                )->whereNull(
                    'events.deleted_at'
                )->where(
                    'channel_id',
                    1
                )->where(
                    'events.brand_id',
                    $brands[$i]['brand_id']
                )->whereDate(
                    'events.created_at',
                    $today->format('Y-m-d')
                )->where(
                    'eztpvs.webenroll',
                    0
                )->count();

                $total_retail_eztpvs = Eztpv::leftJoin(
                    'events',
                    'events.eztpv_id',
                    'eztpvs.id'
                )->whereNull(
                    'events.deleted_at'
                )->where(
                    'channel_id',
                    3
                )->where(
                    'events.brand_id',
                    $brands[$i]['brand_id']
                )->whereDate(
                    'events.created_at',
                    $today->format('Y-m-d')
                )->where(
                    'eztpvs.webenroll',
                    0
                )->count();

                $total_tm_eztpvs = Eztpv::leftJoin(
                    'events',
                    'events.eztpv_id',
                    'eztpvs.id'
                )->whereNull(
                    'events.deleted_at'
                )->where(
                    'channel_id',
                    2
                )->where(
                    'events.brand_id',
                    $brands[$i]['brand_id']
                )->whereDate(
                    'events.created_at',
                    $today->format('Y-m-d')
                )->where(
                    'eztpvs.webenroll',
                    0
                )->count();

                $total_eztpv_contract = EztpvDocument::leftJoin(
                    'eztpvs',
                    'eztpv_documents.eztpv_id',
                    'eztpvs.id'
                )->leftJoin(
                    'uploads',
                    'eztpv_documents.uploads_id',
                    'uploads.id'
                )->leftJoin(
                    'events',
                    'eztpvs.id',
                    'events.eztpv_id'
                )->where(
                    'uploads.upload_type_id',
                    3
                )->where(
                    'eztpvs.brand_id',
                    $brands[$i]['brand_id']
                )->whereDate(
                    'events.created_at',
                    $today->format('Y-m-d')
                )->whereNull(
                    'uploads.deleted_at'
                )->whereNull(
                    'eztpv_documents.deleted_at'
                )->whereNull(
                    'events.deleted_at'
                )->count();

                $total_eztpv_photo = EztpvDocument::leftJoin(
                    'eztpvs',
                    'eztpv_documents.eztpv_id',
                    'eztpvs.id'
                )->leftJoin(
                    'uploads',
                    'eztpv_documents.uploads_id',
                    'uploads.id'
                )->leftJoin(
                    'events',
                    'eztpvs.id',
                    'events.eztpv_id'
                )->where(
                    'uploads.upload_type_id',
                    4
                )->where(
                    'eztpvs.brand_id',
                    $brands[$i]['brand_id']
                )->whereDate(
                    'eztpv_documents.created_at',
                    $today->format('Y-m-d')
                )->whereNull(
                    'uploads.deleted_at'
                )->whereNull(
                    'eztpv_documents.deleted_at'
                )->whereNull(
                    'events.deleted_at'
                )->count();

                $voice_imprint = Interaction::leftJoin(
                    'events',
                    'interactions.event_id',
                    'events.id'
                )->where(
                    'events.brand_id',
                    $brands[$i]['brand_id']
                )->whereDate(
                    'interactions.created_at',
                    $today->format('Y-m-d')
                )->whereNull(
                    'events.deleted_at'
                )->where(
                    'interactions.interaction_type_id',
                    8
                )->count();

                // Surveys
                // $sps = StatsProduct::where(
                //     'brand_id',
                //     $brands[$i]['brand_id']
                // )->whereDate(
                //     'event_created_at',
                //     $today->format('Y-m-d')
                // )->where(
                //     'stats_product_type_id',
                //     3
                // )->whereNull(
                //     'stats_product.deleted_at'
                // )->get();

                // $total_survey_live = (clone $sps)->sum('product_time');

                if ($this->option('debug')) {
                    if ($total_records > 0) {
                        echo $brands[$i]['brand_name'] . ' (' . $dates[$j] . ")\n";
                        echo ' -- Total Records: ' . $total_records . "\n";
                        echo ' -- Live: ' . $total_live . "\n";
                        echo ' ---- Inbound: ' . $total_live_inbound . "\n";
                        echo ' ---- Outbound: ' . $total_live_outbound . "\n";
                        echo " -- EZTPV:\n";
                        echo ' ---- DTD: ' . $total_dtd_eztpvs . "\n";
                        echo ' ---- Retail: ' . $total_retail_eztpvs . "\n";
                        echo ' ---- TM: ' . $total_tm_eztpvs . "\n";
                        echo ' ---- Contracts: ' . $total_eztpv_contract . "\n";
                        echo ' ---- Photos: ' . $total_eztpv_photo . "\n";
                        // echo ' -- HRTPV: ' . $total_hrtpv_records . "\n";
                        // echo ' ---- Minutes: ' . $total_hrtpv_live . "\n";
                        // echo " -- Surveys:\n";
                        // echo ' ---- Minutes: ' . $total_survey_live . "\n";
                        echo ' -- Digital: ' . $digital . "\n";
                        echo "-------------\n";
                    }
                }

                DailyStat::disableAuditing();

                $ds = DailyStat::where(
                    'stats_date',
                    $today->format('Y-m-d')
                )->where('brand_id', $brands[$i]['brand_id']);
                if (!$ds->exists()) {
                    $ds = new DailyStat();
                    $ds->stats_date = $today->format('Y-m-d');
                    $ds->brand_id = $brands[$i]['brand_id'];
                } else {
                    $ds = $ds->first();
                }

                $ds->total_records = $total_records;
                $ds->total_live_min = $total_live;
                $ds->total_live_inbound_min = $total_live_inbound;
                $ds->total_live_outbound_min = $total_live_outbound;
                $ds->live_english_min = $total_english;
                $ds->live_spanish_min = $total_spanish;
                $ds->live_good_sale = $total_good_sale;
                $ds->live_no_sale = $total_no_sale;
                $ds->live_channel_dtd = $total_dtd;
                $ds->live_channel_tm = $total_tm;
                $ds->live_channel_retail = $total_retail;
                $ds->live_cc_tulsa_min = $total_tulsa;
                $ds->live_cc_tahlequah_min = $total_tahlequah;
                $ds->live_cc_lasvegas_min = $total_lasvegas;
                $ds->total_dtd_eztpvs = $total_dtd_eztpvs;
                $ds->total_retail_eztpvs = $total_retail_eztpvs;
                $ds->total_tm_eztpvs = $total_tm_eztpvs;
                $ds->total_eztpvs = $total_dtd_eztpvs + $total_retail_eztpvs + $total_tm_eztpvs;
                $ds->ld_dom = null;
                $ds->ld_intl = null;
                $ds->eztpv_contract = $total_eztpv_contract;
                $ds->eztpv_photo = $total_eztpv_photo;
                $ds->hrtpv_live_min = null;
                $ds->hrtpv_records = null;
                $ds->survey_live_min = null;
                $ds->digital_transaction = $digital;
                $ds->voice_imprint = $voice_imprint;
                $ds->save();

                DailyStat::enableAuditing();
            }
        }
    }
}
