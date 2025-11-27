<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\WebEnrollSubmission;
use App\Models\PaySubmission;
use App\Models\Invoiceable;
use App\Models\InvoiceStatus;
use App\Models\InvoiceItem;
use App\Models\InvoiceAdditional;
use App\Models\Invoice;
use App\Models\Interaction;
use App\Models\DailyStat;
use App\Models\DailyQuestionSubmission;
use App\Models\BrandService;
use App\Models\Brand;
use App\Models\ApiSubmission;
use App\Helpers\InvoiceHelper;

class GenInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:invoices {--force} {--brand=*} {--dateRangeStart=} {--dateRangeEnd=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Invoices Command.';

    private $startDateFormat = 'Y-m-d 00:00:00';
    private $endDateFormat = 'Y-m-d 23:59:59';

    public function lookupDailyStats($first_day, $end_day, $brands)
    {
        return DailyStat::whereBetween(
            'stats_date',
            [
                $first_day->format($this->startDateFormat),
                $end_day->format($this->endDateFormat),
            ]
        )->whereIn(
            'brand_id',
            $brands
        )->orderBy(
            'stats_date'
        )->get()->toArray();
    }

    private function addInvoiceItem(string $invoice_id, float $quantity, int $desc_id, float $rate, float $total, string $notes = '')
    {
        $ii = new InvoiceItem();
        $ii->invoice_id = $invoice_id;
        $ii->quantity = $quantity;
        $ii->invoice_desc_id = $desc_id;
        $ii->rate = $rate;
        $ii->total = $total;
        if (strlen($notes) > 0) {
            $ii->note = $notes;
        }
        $ii->save();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // abort if no date range set. Remove this conditional to make it
        // run with date range as an option.
        if (
            null != $this->option('dateRangeStart')
            && null != $this->option('dateRangeEnd')
        ) {
            $brands = Brand::select(
                'brands.id AS brand_id',
                'brands.name AS brand_name',
                'brands.client_id',
                'invoice_rate_card.id AS invoice_rate_card_id',
                'invoice_rate_card.bill_frequency_id',
                'invoice_rate_card.bill_methodology_id',
                'invoice_rate_card.term_days',
                'invoice_rate_card.storage_in_gb_min',
                'invoice_rate_card.ld_billback_dom',
                'invoice_rate_card.ld_billback_intl',
                'invoice_rate_card.minimum',
                'invoice_rate_card.eztpv_rate',
                'invoice_rate_card.eztpv_tm_monthly',
                'invoice_rate_card.eztpv_tm_rate',
                'invoice_rate_card.eztpv_contract',
                'invoice_rate_card.eztpv_photo',
                'invoice_rate_card.eztpv_sms',
                'invoice_rate_card.digital_transaction',
                'invoice_rate_card.cell_number_verification',
                'invoice_rate_card.api_submission',
                'invoice_rate_card.web_enroll_submission',
                'invoice_rate_card.pay_submission',
                'invoice_rate_card.ivr_rate',
                'invoice_rate_card.esiid_lookup',
                'invoice_rate_card.sales_pitch',
                'invoice_rate_card.ivr_voiceprint',
                'invoice_rate_card.daily_questionnaire',
                'invoice_rate_card.gps_distance_cust_sa',
                'invoice_rate_card.server_hosting',
                'invoice_rate_card.welcome_email',
                'invoice_rate_card.renewal_email'
            )->join(
                'invoice_rate_card',
                'brands.id',
                'invoice_rate_card.brand_id'
            );

            if ($this->option('brand')) {
                $brands = $brands->whereIn(
                    'brands.id',
                    $this->option('brand')
                );
            }

            $brands = $brands->get()->toArray();

            for ($i = 0; $i < count($brands); ++$i) {
                $total_live_results = 0;
                $total_eztpv_contract = 0;
                $total_eztpv_photo = 0;
                $total_eztpvs = 0;
                $total_did_tollfree = 0;
                $total_did_local = 0;
                $total_ld_dom = 0;
                $total_ld_intl = 0;
                $total_dtd_eztpvs = 0;
                $total_retail_eztpvs = 0;
                $total_tm_eztpvs = 0;
                $total_hrtpv_records = 0;
                $total_hrtpv_min = 0;
                $digital = 0;

                $first_day = Carbon::parse($this->option('dateRangeStart'))->startOfDay();
                $end_day = Carbon::parse($this->option('dateRangeEnd'))->endOfDay();

                $ds = $this->lookupDailyStats($first_day, $end_day, [$brands[$i]['brand_id']]);

                for ($d = 0; $d < count($ds); ++$d) {
                    $total_live_results += $ds[$d]['total_live_min'];

                    // digital
                    $digital += $ds[$d]['digital_transaction'];

                    // eztpvs
                    $total_dtd_eztpvs += $ds[$d]['total_dtd_eztpvs'];
                    $total_retail_eztpvs += $ds[$d]['total_retail_eztpvs'];
                    $total_tm_eztpvs += $ds[$d]['total_tm_eztpvs'];
                    $total_eztpvs += $total_dtd_eztpvs
                        + $total_retail_eztpvs
                        + $total_tm_eztpvs;
                    $total_eztpv_contract += $ds[$d]['eztpv_contract'];
                    $total_eztpv_photo += $ds[$d]['eztpv_photo'];

                    // long distance
                    $total_ld_dom += $ds[$d]['ld_dom'];
                    $total_ld_intl += $ds[$d]['ld_intl'];

                    $total_hrtpv_records += $ds[$d]['hrtpv_records'];
                    $total_hrtpv_min += $ds[$d]['hrtpv_live_min'];

                    if ($total_hrtpv_min > 0) {
                        $total_live_results += $ds[$d]['hrtpv_live_min'];
                    }
                }

                $multi_brand = false;
                $total_other_live_results = 0;
                $sameClient = Brand::where(
                    'client_id',
                    $brands[$i]['client_id']
                )->get();

                if ($sameClient && $sameClient->count() > 1) {
                    $otherBrands = [];
                    foreach ($sameClient as $b) {
                        if ($b->id !== $brands[$i]['brand_id']) {
                            $otherBrands[] = $b->id;
                        }
                    }

                    if (!empty($otherBrands)) {
                        $multi_brand = true;
                        $sameDS = $this->lookupDailyStats($first_day, $end_day, $otherBrands);

                        for ($d = 0; $d < count($sameDS); ++$d) {
                            $total_other_live_results += $sameDS[$d]['total_live_min'];
                        }
                    }
                }

                $combined_live_results = $total_live_results + $total_other_live_results;

                $live = [];

                switch ($brands[$i]['bill_methodology_id']) {
                    case 1:
                        $live = InvoiceHelper::flatRate(
                            $brands[$i]['invoice_rate_card_id'],
                            $combined_live_results
                        );
                        break;

                    case 2:
                        $live = InvoiceHelper::stepScale(
                            $brands[$i]['invoice_rate_card_id'],
                            $combined_live_results
                        );
                        break;

                    case 3:
                        $live = InvoiceHelper::slidingScale(
                            $brands[$i]['invoice_rate_card_id'],
                            $combined_live_results
                        );
                        break;

                    case 4:
                        $live = InvoiceHelper::flatPerEvent(
                            $first_day,
                            $end_day,
                            $brands[$i]['brand_id'],
                            $brands[$i]['invoice_rate_card_id']
                        );
                        break;

                    case 5:
                        $live = InvoiceHelper::flatPerInteraction(
                            $first_day,
                            $end_day,
                            $brands[$i]['brand_id'],
                            $brands[$i]['invoice_rate_card_id']
                        );
                        break;

                    default:
                        $this->error('Unknown bill methodology: ' . json_encode($brands[$i]['bill_methodology_id']));
                }

                info(print_r($live, true));

                $live_minutes_grand_total = 0;
                if (isset($live[0])) {
                    for ($k = 0; $k < count($live); ++$k) {
                        if ($live[$k]['total'] > 0) {
                            $live_minutes_grand_total += $live[$k]['total'];
                        }
                    }
                } else {
                    $live_minutes_grand_total += $live['total'];
                }

                $exists = Invoice::where(
                    DB::raw('DATE(invoice_start_date)'),
                    date('Y-m-d', strtotime($first_day))
                )->where('brand_id', $brands[$i]['brand_id'])->first();

                if (null == $exists || $this->option('force')) {
                    if ($this->option('force') && $exists) {
                        $inv = Invoice::find($exists->id);

                        InvoiceItem::where(
                            'invoice_id',
                            $inv->id
                        )->delete();
                    } else {
                        $inv = new Invoice();
                        $inv->brand_id = $brands[$i]['brand_id'];
                        $inv->invoice_start_date = $first_day;
                        $inv->invoice_end_date = $end_day;
                        $inv->invoice_due_date = Carbon::now()->addDays($brands[$i]['term_days']);
                        $inv->invoice_bill_date = Carbon::now()->format('Y-m-d');
                        $inv->account_number = rand(0, getrandmax());
                        $uniq = false;
                        while (!$uniq) { // make sure the invoice number is unique
                            $inv->invoice_number = date('Ym', strtotime($end_day)) . '-' . rand(1000, 9999);
                            $check = Invoice::where('invoice_number', $inv->invoice_number)->first();
                            if (empty($check)) {
                                $uniq = true;
                            }
                        }
                        $inv->save();
                    }

                    $is = InvoiceStatus::find($inv->id);
                    if (!$is) {
                        // Status
                        $is = new InvoiceStatus();
                        $is->invoices_id = $inv->id;
                        $is->users_id = 'system';
                        $is->billing_status = 'unbilled';
                        $is->status = 'generated';
                        $is->note = null;
                        $is->save();
                    }

                    // Storage
                    if (1 == $brands[$i]['bill_frequency_id']) {
                        // If the bill frequency is bi-monthly, split up the payment
                        $storage_total = $brands[$i]['storage_in_gb_min'] / 2;
                    } else {
                        $storage_total = $brands[$i]['storage_in_gb_min'];
                    }

                    $this->addInvoiceItem($inv->id, 1, 11, $storage_total, $storage_total);

                    // Live Minutes
                    if ($brands[$i]['bill_methodology_id'] === 4) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $live['minutes'],
                            38,
                            $live['rate'],
                            $live['total']
                        );
                    } elseif ($brands[$i]['bill_methodology_id'] === 5) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $live['minutes'],
                            39,
                            $live['rate'],
                            $live['total']
                        );
                    } else {
                        if ($multi_brand) {
                            $this->addInvoiceItem(
                                $inv->id,
                                $total_live_results,
                                1,
                                ($live_minutes_grand_total > 0 && $combined_live_results > 0)
                                    ? $live_minutes_grand_total / $combined_live_results
                                    : 0,
                                $live_minutes_grand_total
                            );
                        } else {
                            $this->addInvoiceItem(
                                $inv->id,
                                $total_live_results,
                                1,
                                ($live_minutes_grand_total > 0 && $total_live_results > 0)
                                    ? $live_minutes_grand_total / $total_live_results
                                    : 0,
                                $live_minutes_grand_total
                            );
                        }

                        if ($total_live_results > 0) {
                            // Long Distance Domestic
                            $this->addInvoiceItem($inv->id, $total_live_results, 9, $brands[$i]['ld_billback_dom'], ($brands[$i]['ld_billback_dom'] * $total_live_results));
                        }

                        if ($total_ld_intl > 0) {
                            // Long Distance International
                            $this->addInvoiceItem($inv->id, $total_ld_intl, 10, $brands[$i]['ld_billback_intl'], ($brands[$i]['ld_billback_intl'] * $total_ld_intl));
                        }
                    }

                    $volume_total = $live_minutes_grand_total;

                    if ($total_dtd_eztpvs > 0) {
                        $this->addInvoiceItem($inv->id, $total_dtd_eztpvs, 6, $brands[$i]['eztpv_rate'], ($brands[$i]['eztpv_rate'] * $total_dtd_eztpvs));

                        $volume_total += ($brands[$i]['eztpv_rate'] * $total_dtd_eztpvs);
                    }

                    if ($total_retail_eztpvs > 0) {
                        $this->addInvoiceItem($inv->id, $total_retail_eztpvs, 7, $brands[$i]['eztpv_rate'], ($brands[$i]['eztpv_rate'] * $total_retail_eztpvs));

                        $volume_total += ($brands[$i]['eztpv_rate']
                            * $total_retail_eztpvs);
                    }

                    if ($total_tm_eztpvs > 0) {
                        $this->addInvoiceItem($inv->id, $total_tm_eztpvs, 16, $brands[$i]['eztpv_tm_rate'], ($brands[$i]['eztpv_tm_rate'] * $total_tm_eztpvs));

                        $volume_total += ($brands[$i]['eztpv_tm_rate']
                            * $total_tm_eztpvs);
                    }

                    if ($total_eztpv_contract > 0) {
                        $this->addInvoiceItem($inv->id, $total_eztpv_contract, 13, $brands[$i]['eztpv_contract'], ($brands[$i]['eztpv_contract'] * $total_eztpv_contract));

                        $volume_total += ($brands[$i]['eztpv_contract']
                            * $total_eztpv_contract);
                    }

                    if ($total_did_local > 0) {
                        //did_local
                        $this->addInvoiceItem($inv->id, $total_did_local, 5, $brands[$i]['did_local'], ($brands[$i]['did_local'] * $total_did_local));

                        $volume_total += ($brands[$i]['did_local']
                            * $total_did_local);
                    }

                    if ($total_did_tollfree > 0) {
                        //did_tollfree
                        $this->addInvoiceItem($inv->id, $total_did_tollfree, 4, $brands[$i]['did_tollfree'], ($brands[$i]['did_tollfree'] * $total_did_tollfree));

                        $volume_total += ($brands[$i]['did_tollfree']
                            * $total_did_tollfree);
                    }

                    if ($digital > 0) {
                        // Digital TPV
                        $this->addInvoiceItem(
                            $inv->id,
                            $digital,
                            25,
                            $brands[$i]['digital_transaction'],
                            (!empty($brands[$i]['digital_transaction']))
                                ? ($brands[$i]['digital_transaction'] * $digital)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['digital_transaction']))
                            ? ($brands[$i]['digital_transaction'] * $digital)
                            : 0;
                    }

                    // SMS
                    $sms = Invoiceable::where(
                        'brand_id',
                        $brands[$i]['brand_id']
                    )->where(
                        'invoiceable_type_id',
                        1
                    )->whereBetween(
                        'created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($sms > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $sms,
                            14,
                            $brands[$i]['eztpv_sms'],
                            (isset($brands[$i]['eztpv_sms']))
                                ? ($brands[$i]['eztpv_sms'] * $sms)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['eztpv_sms']))
                            ? ($brands[$i]['eztpv_sms'] * $sms)
                            : 0;
                    }

                    // ESI ID Lookup
                    $esiid = Invoiceable::where(
                        'brand_id',
                        $brands[$i]['brand_id']
                    )->where(
                        'invoiceable_type_id',
                        5
                    )->whereBetween(
                        'created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($esiid > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $esiid,
                            31,
                            $brands[$i]['esiid_lookup'],
                            (isset($brands[$i]['esiid_lookup']))
                                ? ($brands[$i]['esiid_lookup'] * $esiid)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['esiid_lookup']))
                            ? ($brands[$i]['esiid_lookup'] * $esiid)
                            : 0;
                    }

                    // VOIP Lookup
                    $voip = Invoiceable::where(
                        'brand_id',
                        $brands[$i]['brand_id']
                    )->whereIn(
                        'invoiceable_type_id',
                        [2, 4]
                    )->whereBetween(
                        'created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($voip > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $voip,
                            26,
                            $brands[$i]['cell_number_verification'],
                            (isset($brands[$i]['cell_number_verification']))
                                ? ($brands[$i]['cell_number_verification'] * $voip)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['cell_number_verification']))
                            ? ($brands[$i]['cell_number_verification'] * $voip)
                            : 0;
                    }

                    // Additional Invoice items
                    $ias = InvoiceAdditional::where(
                        'brand_id',
                        $brands[$i]['brand_id']
                    )->whereBetween(
                        'date_of_work',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->get();
                    if ($ias) {
                        foreach ($ias as $ia) {
                            if ($ia->ticket) {
                                $this->addInvoiceItem($inv->id, $ia->duration, $ia->category, $ia->rate, $ia->rate * $ia->duration, $ia->description . ' (' . $ia->ticket . ')');
                            } else {
                                $this->addInvoiceItem($inv->id, $ia->duration, $ia->category, $ia->rate, $ia->rate * $ia->duration, $ia->description);
                            }

                            $ia->invoice_id = $inv->id;
                            $ia->save();
                        }
                    }

                    // API Submissions
                    $api = ApiSubmission::where(
                        'brand_id',
                        $brands[$i]['brand_id']
                    )->where(
                        'type',
                        'tpv'
                    )->whereBetween(
                        'created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($api > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $api,
                            28,
                            $brands[$i]['api_submission'],
                            (isset($brands[$i]['api_submission']))
                                ? ($brands[$i]['api_submission'] * $api)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['api_submission']))
                            ? ($brands[$i]['api_submission'] * $api)
                            : 0;
                    }

                    // Web Enroll Submissions
                    $we = WebEnrollSubmission::where(
                        'brand_id',
                        $brands[$i]['brand_id']
                    )->whereBetween(
                        'created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($we > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $we,
                            29,
                            $brands[$i]['web_enroll_submission'],
                            (isset($brands[$i]['web_enroll_submission']))
                                ? ($brands[$i]['web_enroll_submission'] * $we)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['web_enroll_submission']))
                            ? ($brands[$i]['web_enroll_submission'] * $we)
                            : 0;
                    }

                    // Pay Submissions
                    $ps = PaySubmission::where(
                        'brand_id',
                        $brands[$i]['brand_id']
                    )->whereBetween(
                        'created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($ps > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $ps,
                            30,
                            $brands[$i]['pay_submission'],
                            (isset($brands[$i]['pay_submission']))
                                ? ($brands[$i]['pay_submission'] * $ps)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['pay_submission']))
                            ? ($brands[$i]['pay_submission'] * $ps)
                            : 0;
                    }

                    // Sales pitch
                    $salespitch = Interaction::leftJoin(
                        'events',
                        'interactions.event_id',
                        'events.id'
                    )->where(
                        'events.brand_id',
                        $brands[$i]['brand_id']
                    )->where(
                        'interactions.interaction_type_id',
                        21
                    )->whereBetween(
                        'interactions.created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if (isset($brands[$i]['sales_pitch']) && $salespitch > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $salespitch,
                            32,
                            $brands[$i]['sales_pitch'],
                            (isset($brands[$i]['sales_pitch']))
                                ? ($brands[$i]['sales_pitch'] * $salespitch)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['sales_pitch']))
                            ? ($brands[$i]['sales_pitch'] * $salespitch)
                            : 0;
                    }

                    // IVR
                    $ivr = Interaction::leftJoin(
                        'events',
                        'interactions.event_id',
                        'events.id'
                    )->where(
                        'events.brand_id',
                        $brands[$i]['brand_id']
                    )->where(
                        'interactions.interaction_type_id',
                        20
                    )->whereBetween(
                        'interactions.created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($ivr > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $ivr,
                            3,
                            $brands[$i]['ivr_rate'],
                            (isset($brands[$i]['ivr_rate']))
                                ? ($brands[$i]['ivr_rate'] * $ivr)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['ivr_rate']))
                            ? ($brands[$i]['ivr_rate'] * $ivr)
                            : 0;
                    }

                    // Voice Print
                    $voice_print = Interaction::leftJoin(
                        'events',
                        'interactions.event_id',
                        'events.id'
                    )->where(
                        'events.brand_id',
                        $brands[$i]['brand_id']
                    )->where(
                        'interactions.interaction_type_id',
                        8
                    )->whereBetween(
                        'interactions.created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($voice_print > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $voice_print,
                            33,
                            $brands[$i]['ivr_voiceprint'],
                            (isset($brands[$i]['ivr_voiceprint']))
                                ? ($brands[$i]['ivr_voiceprint'] * $voice_print)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['ivr_voiceprint']))
                            ? ($brands[$i]['ivr_voiceprint'] * $voice_print)
                            : 0;
                    }

                    // Welcome Email
                    $welcome_emails = Interaction::leftJoin(
                        'events',
                        'interactions.event_id',
                        'events.id'
                    )->where(
                        'events.brand_id',
                        $brands[$i]['brand_id']
                    )->whereIn(
                        'interactions.interaction_type_id',
                        [24, 30]
                    )->whereBetween(
                        'interactions.created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if (isset($brands[$i]['welcome_email']) && $welcome_emails > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $welcome_emails,
                            40,
                            $brands[$i]['welcome_email'],
                            (isset($brands[$i]['welcome_email']))
                                ? ($brands[$i]['welcome_email'] * $welcome_emails)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['welcome_email']))
                            ? ($brands[$i]['welcome_email'] * $welcome_emails)
                            : 0;
                    }

                    // Renewal Email
                    $renewal_emails = Interaction::leftJoin(
                        'events',
                        'interactions.event_id',
                        'events.id'
                    )->where(
                        'events.brand_id',
                        $brands[$i]['brand_id']
                    )->where(
                        'interactions.interaction_type_id',
                        26
                    )->whereBetween(
                        'interactions.created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if (isset($brands[$i]['renewal_email']) && $renewal_emails > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $renewal_emails,
                            40,
                            $brands[$i]['renewal_email'],
                            (isset($brands[$i]['renewal_email']))
                                ? ($brands[$i]['renewal_email'] * $renewal_emails)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['renewal_email']))
                            ? ($brands[$i]['renewal_email'] * $renewal_emails)
                            : 0;
                    }

                    // Daily Questionnaires
                    $daily_questionnaires = DailyQuestionSubmission::where(
                        'brand_id',
                        $brands[$i]['brand_id']
                    )->whereBetween(
                        'created_at',
                        [
                            $first_day->format($this->startDateFormat),
                            $end_day->format($this->endDateFormat),
                        ]
                    )->count();
                    if ($daily_questionnaires > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            $daily_questionnaires,
                            34,
                            $brands[$i]['daily_questionnaire'],
                            (isset($brands[$i]['daily_questionnaire']))
                                ? ($brands[$i]['daily_questionnaire'] * $daily_questionnaires)
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['daily_questionnaire']))
                            ? ($brands[$i]['daily_questionnaire'] * $daily_questionnaires)
                            : 0;
                    }

                    // GPS Distance (Customer <> Sales Agent)
                    $bs = BrandService::leftJoin(
                        'brand_service_types',
                        'brand_services.brand_service_type_id',
                        'brand_service_types.id'
                    )->where(
                        'brand_service_types.slug',
                        'gps_sales_agent_to_customer'
                    )->where(
                        'brand_services.brand_id',
                        $brands[$i]['brand_id']
                    )->count();
                    if ($bs > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            1,
                            35,
                            $brands[$i]['gps_distance_cust_sa'],
                            (isset($brands[$i]['gps_distance_cust_sa']))
                                ? $brands[$i]['gps_distance_cust_sa']
                                : 0
                        );

                        $volume_total += (isset($brands[$i]['gps_distance_cust_sa']))
                            ? $brands[$i]['gps_distance_cust_sa']
                            : 0;
                    }

                    if ($brands[$i]['server_hosting'] > 0) {
                        $this->addInvoiceItem(
                            $inv->id,
                            1,
                            36,
                            ($brands[$i]['bill_frequency_id'] === 1)
                                ? $brands[$i]['server_hosting'] / 2
                                : $brands[$i]['server_hosting'],
                            ($brands[$i]['bill_frequency_id'] === 1)
                                ? $brands[$i]['server_hosting'] / 2
                                : $brands[$i]['server_hosting']
                        );

                        $volume_total += (isset($brands[$i]['server_hosting']))
                            ? $brands[$i]['server_hosting']
                            : 0;
                    }

                    if (
                        $brands[$i]['minimum'] > 0
                        && $volume_total < $brands[$i]['minimum']
                    ) {
                        $adjusted = $brands[$i]['minimum']
                            - $volume_total;
                        $this->addInvoiceItem(
                            $inv->id,
                            1,
                            2,
                            $adjusted,
                            $adjusted,
                            '$' . number_format(
                                $brands[$i]['minimum'],
                                2,
                                '.',
                                ','
                            )
                        );
                    }
                } else {
                    echo 'Invoice already exists for '
                        . $brands[$i]['brand_name']
                        . ' (' . $first_day->format('Y-m-d') . ").\n";
                }
            }
        } else {
            info('You must specify a date range
                (--dateRangeStart=<YYYY-MM-DAY> --dateRangeEnd=<YYYY-MM-DD>).
                No invoices were generated.');
            echo 'You must specify a date range
                (--dateRangeStart=<YYYY-MM-DAY> --dateRangeEnd=<YYYY-MM-DD>).
                No invoices were generated.';
        }
    }
}
