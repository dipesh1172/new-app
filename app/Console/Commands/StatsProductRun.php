<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\ZipCode;
use App\Models\Vendor;
use App\Models\StatsProduct;
use App\Models\Interaction;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\DefaultScCompanyPosition;
use App\Models\Brand;
use App\Models\AuthRelationship;

class StatsProductRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats:product
        {--brand=}
        {--hours=}
        {--forever}
        {--startDate=}
        {--endDate=}
        {--confirmationCode=}';

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
     * Get addresses from $product and structure them properly.
     *
     * @param object $product - available products + addresses
     *
     * @return array - service/billing address
     */
    public function getAddresses($product)
    {
        $service_address = [];
        $billing_address = [];
        if (isset($product['addresses'])) {
            for ($i = 0; $i < count($product['addresses']); ++$i) {
                if ('e_p:service' == $product['addresses'][$i]['id_type']) {
                    $service_address['address'] = $product['addresses'][$i]['address']['line_1'];

                    $service_address['address2'] = $product['addresses'][$i]['address']['line_2'];

                    $service_address['city']
                        = $product['addresses'][$i]['address']['city'];
                    $service_address['state']
                        = $product['addresses'][$i]['address']['state_province'];
                    $service_address['zip']
                        = $product['addresses'][$i]['address']['zip'];

                    $zips = Cache::remember(
                        'zip_code_by_zip' . $service_address['zip'],
                        7200,
                        function () use ($service_address) {
                            return ZipCode::where(
                                'zip',
                                $service_address['zip']
                            )->first();
                        }
                    );

                    if ($zips) {
                        $service_address['county'] = $zips->county;
                        $service_address['country'] = (2 == $zips->country)
                            ? 'Canada' : 'United States';
                    } else {
                        $service_address['county'] = null;
                        $service_address['country'] = 'United States';
                    }
                } else {
                    $billing_address['address'] = $product['addresses'][$i]['address']['line_1'];

                    $billing_address['address2'] = $product['addresses'][$i]['address']['line_2'];

                    $billing_address['city']
                        = $product['addresses'][$i]['address']['city'];
                    $billing_address['state']
                        = $product['addresses'][$i]['address']['state_province'];
                    $billing_address['zip']
                        = $product['addresses'][$i]['address']['zip'];

                    $zips = Cache::remember(
                        'zip_code_by_zip' . $billing_address['zip'],
                        7200,
                        function () use ($billing_address) {
                            return ZipCode::where(
                                'zip',
                                $billing_address['zip']
                            )->first();
                        }
                    );

                    if ($zips) {
                        $billing_address['county'] = $zips->county;
                        $billing_address['country'] = (2 == $zips->country)
                            ? 'Canada' : 'United States';
                    } else {
                        $billing_address['county'] = null;
                        $billing_address['country'] = 'United States';
                    }
                }
            }
        }

        if (!isset($billing_address['address'])) {
            $billing_address = $service_address;
        }

        return ['service' => $service_address, 'billing' => $billing_address];
    }

    private function isValidIpAddress($ipAddr) {

        return !empty($ipAddr) &&
            filter_var($ipAddr, FILTER_VALIDATE_IP);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $current_brand = $this->option('brand');
        if ($this->option('confirmationCode')) {
            $event = Event::where(
                'confirmation_code',
                $this->option('confirmationCode')
            )->first();
            if ($event) {
                $current_brand = $event->brand_id;
            }
        }

        foreach ($this->getBrands() as $brand) {
            if (
                $current_brand
                && $brand->brand_id != $current_brand
            ) {
                continue;
            }

            echo $brand->name . ' (' . $brand->brand_id . ")\n";

            $hours = ($this->option('hours')) ? $this->option('hours') : 3;
            $partial = Interaction::select(
                'interactions.event_id'
            )->leftJoin(
                'events',
                'interactions.event_id',
                'events.id'
            )->where(
                'events.brand_id',
                $brand->brand_id
            );

            if ($this->option('confirmationCode')) {
                echo '-- Running Confirmation Code = ' . $this->option('confirmationCode') . "\n";
                $partial = $partial->where(
                    'events.confirmation_code',
                    $this->option('confirmationCode')
                );
            } else {
                if ($this->option('startDate') && $this->option('endDate')) {
                    $partial = $partial->whereBetween(
                        'interactions.updated_at',
                        [
                            $this->option('startDate'),
                            $this->option('endDate'),
                        ]
                    );
                } else {
                    if (!$this->option('forever')) {
                        $partial = $partial->where(
                            'interactions.updated_at',
                            '>=',
                            Carbon::now('America/Chicago')->subHours($hours)
                        );
                    }
                }
            }
            echo "-- Interaction record: ".$partial->count(),"\n";
            $partial = $partial->groupBy(
                'interactions.event_id'
            )->get()->pluck('event_id')->toArray();

            echo "-- Starting events with products.\n";

            // $event_products = EventProduct::whereHas(
            //     'eventAll',
            //     function ($q) use ($brand, $partial) {
            //         $q->where('brand_id', $brand->brand_id);
            //         $q->whereIn('id', $partial);
            //     }
            // )
            //; // with(....
            EventProduct::
                leftJoin('events','event_product.event_id', 'events.id')
                ->select('event_product.*')
                ->where('events.brand_id', $brand->brand_id)
                ->whereIn('events.id', $partial)
                ->with([
                    'eventAll',
                    'eventAll.brand',
                    'eventAll.vendor',
                    'eventAll.eztpv',
                    'eventAll.eztpv.eztpv_sale_type',
                    'eventAll.eztpv.eztpv_docs',
                    'eventAll.eztpv.eztpv_docs.uploads' => function ($query) {
                        $query->whereNull(
                            'uploads.deleted_at'
                        );
                    },
                    'eventAll.script',
                    'eventAll.script.dnis',
                    'eventAll.office',
                    'eventAll.channel',
                    'eventAll.language',
                    'eventAll.phone.phone_number',
                    'eventAll.email.email',
                    'eventAll.sales_agent',
 //                   'eventAll.sales_agent.user', // remove line (curt c)
 // search trash records also since this will affect stats_product population of sales_agent_name if agent was soft deleted 
 // mainly used to fix Genie records that activated/deactivate with GenieVendorSync. But this fix shouldn't hurt other brands (curt c)
                    'eventAll.sales_agent.user'=> function ($query) {  
                        $query->withTrashed();
                    },
                    'eventAll.interactions' => function ($query) {
                        // Hide warm_transfer, link_tracking, api_call, copy_email_contracts, and welcome_letter_no_sp interactions, expired_link_access
                        $query->whereNotIn(
                            'interaction_type_id',
                            [10, 22, 28, 29, 30, 31]
                        );
                    },
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
                    'eventAll.customFieldStorageAll.customField',
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
            )->orderBy('event_product.created_at', 'desc')->chunk(
                1000,
                function ($results) {
                    // $this->info("Event products:");
                    // print_r($results->toArray());
                    // exit();

                    foreach ($results->toArray() as $key => $value) {
                        $sp = StatsProduct::where(
                            'event_product_id',
                            $value['id']
                        ); // This will decide whether an existing stats product record is updated, or a new one is created.

                        $interactions = !empty($value['event_all']['interactions'])
                            ? $value['event_all']['interactions']
                            : null;

                        $sp = $sp->withTrashed()->first();
                        if (!$sp) {
                            $sp = new StatsProduct();
                        }

                        $product_count = EventProduct::select(
                            'id'
                        )->leftJoin(
                            'events',
                            'event_product.event_id',
                            'events.id'
                        )->where(
                            'events.confirmation_code',
                            $value['event_all']['confirmation_code']
                        )->count();

                        $sp->event_id = $value['event_all']['id'];

                        if (!empty($value['event_all']['hrtpv'])) {
                            $sp->stats_product_type_id = 2;
                        } elseif (
                            !empty($value['event_all']['survey_id'])
                        ) {
                            $sp->stats_product_type_id = 3;
                        } elseif (
                            !empty($value['event_all']['agent_confirmation'])
                        ) {
                            $sp->stats_product_type_id = 4;
                        } else {
                            $sp->stats_product_type_id = 1;
                        }

                        $sp->event_created_at = $value['event_all']['created_at'];

                        if (!empty($interactions)) {
                            $interaction_time = 0;
                            $interaction_notes = null;
                            $ani = null;
                            $forced_phone_validation = 0;

                            for ($x = 0; $x < count($interactions); ++$x) {
                                if (
                                    !empty($interactions[$x]['notes'])
                                ) {
                                    $interaction_notes = $interactions[$x]['notes'];
                                }

                                $interaction_time
                                    += number_format(
                                        $interactions[$x]['interaction_time'], 2, '.', '' // Alex K - 2022-11-17: Removed thousands separator from result, else incrementing $interaction_time fails.
                                    );

                                if (null !== $interaction_notes) {
                                    if (!is_array($interaction_notes)) {
                                        $notes_array = json_decode($interaction_notes, true);
                                    } else {
                                        $notes_array = $interaction_notes;
                                    }

                                    if (is_array($notes_array) && $ani == null) {
                                        if (!empty($notes_array['ani'])) {
                                            $ani = CleanPhoneNumber(trim($notes_array['ani']));
                                        } else {
                                            if (!empty($notes_array['from'])) {
                                                $ani = CleanPhoneNumber(trim($notes_array['from']));
                                            }
                                        }
                                    }
                                }

                                if ($interactions[$x]['forced_phone_validation'] === 1) {
                                    $forced_phone_validation = 1;
                                }
                            }

                            $sp->forced_phone_validation = $forced_phone_validation;
                            $sp->ani = $ani;

                            $final_interaction_time = ($interaction_time > 0)
                                ? number_format($interaction_time, 2, '.', '') 
                                : 0;
                            $sp->interaction_time = $final_interaction_time;

                            $sp->product_time = ($interaction_time > 0
                                && $product_count > 0)
                                ? number_format(
                                    $interaction_time / $product_count,
                                    2
                                )
                                : $final_interaction_time;

                            $ikey = count($interactions) - 1;

                            $sp->interaction_id = $interactions[$ikey]['id'];
                            $sp->interaction_created_at
                                = $interactions[$ikey]['created_at'];

                            $sale_check = false;
                            foreach ($interactions as $key => $interaction) {
                                if ('Sale' === $interaction['result']['result']) {
                                    $sale_check = true;
                                }
                            }

                            if (
                                !empty($interactions[$ikey]['interaction_type']['name']) && 
                                (
                                    ($interactions[$ikey]['interaction_type']['name'] === 'qa_update') ||
                                    ($interactions[$ikey]['interaction_type']['name'] === 'status_update') ||
                                    (
                                        $interactions[$ikey]['interaction_type']['name'] === 'call_outbound'
                                        && isset($interactions[$ikey]['notes']['is_retpv'])
                                        && $interactions[$ikey]['notes']['is_retpv']
                                    )
                                )                                
                            ) {
                                $sale_check = $interactions[$ikey]['result']['result'] == 'Sale';
                            }

                            if ($sale_check) {
                                $sp->result = 'Sale';
                            } else {
                                $sp->result = $interactions[$ikey]['result']['result'];
                            }

                            $sp->interaction_type
                                = $interactions[$ikey]['interaction_type']['name'];
                            $sp->source
                                = !empty($interactions[0]['source']['source'])
                                ? $interactions[0]['source']['source'] : 'Live';

                            if ('No Sale' == $sp->result) {
                                $sp->disposition_id = $interactions[$ikey]['disposition']['id'];
                                $sp->disposition_label
                                    = $interactions[$ikey]['disposition']['brand_label'];
                                $sp->disposition_reason
                                    = $interactions[$ikey]['disposition']['reason'];
                            } else {
                                $sp->disposition_id = null;
                                $sp->disposition_label = null;
                                $sp->disposition_reason = null;
                            }

                            if (
                                !empty($interactions[$ikey]['event_flags'])
                            ) {
                                $flag_key = count(
                                    $interactions[$ikey]['event_flags']
                                ) - 1;
                                $sp->flagged_reason
                                    = $interactions[$ikey]['event_flags'][$flag_key]['flag_reason']['description'];
                                $sp->flagged_by = mb_strtoupper(
                                    $interactions[$ikey]['event_flags'][$flag_key]['flagged_by']['first_name'] . ' ' .
                                        $interactions[$ikey]['event_flags'][$flag_key]['flagged_by']['last_name']
                                );
                                $sp->flagged_by_label
                                    = !empty($interactions[$ikey]['event_flags'][$flag_key]['flagged_by']['username'])
                                    ? $interactions[$ikey]['event_flags'][$flag_key]['flagged_by']['username']
                                    : null;
                            }

                            if (
                                !empty($interactions[$ikey]['tpv_agent'])
                            ) {
                                $sp->tpv_agent_id
                                    = $interactions[$ikey]['tpv_agent']['id'];
                                $sp->tpv_agent_name
                                    = $interactions[$ikey]['tpv_agent']['first_name']
                                    . ' '
                                    . $interactions[$ikey]['tpv_agent']['last_name'];
                                $sp->tpv_agent_label
                                    = $interactions[$ikey]['tpv_agent']['username'];
                                $sp->tpv_agent_call_center_id
                                    = (!empty($interactions[$ikey]['tpv_agent']['call_center_id']))
                                    ? $interactions[$ikey]['tpv_agent']['call_center_id']
                                    : null;
                            }

                            $recordings = [];
                            if (
                                !empty($interactions[$ikey]['recordings'])
                            ) {
                                for ($x = 0; $x < count($interactions[$ikey]['recordings']); ++$x) {
                                    if (
                                        !empty($interactions[$ikey]['recordings'][$x]['recording'])
                                    ) {
                                        $recordings[] = $interactions[$ikey]['recordings'][$x]['recording'];
                                    }
                                }
                            }
                        }

                        if (empty(trim($sp->result))) {
                            $sp->result = 'Closed';
                        }

                        $contracts = [];
                        if (
                            !empty($value['event_all']['eztpv'])
                            && !empty($value['event_all']['eztpv']['eztpv_docs'])
                        ) {
                            for ($x = 0; $x < count($value['event_all']['eztpv']['eztpv_docs']); ++$x) {
                                if (
                                    !empty($value['event_all']['eztpv']['eztpv_docs'][$x]['uploads'])
                                    && 3 == $value['event_all']['eztpv']['eztpv_docs'][$x]['uploads']['upload_type_id']
                                ) {
                                    $contracts[]
                                        = $value['event_all']['eztpv']['eztpv_docs'][$x]['uploads']['filename'];
                                }
                            }
                        }

                        $photos = [];
                        if (
                            !empty($value['event_all']['eztpv'])
                            && !empty($value['event_all']['eztpv']['eztpv_docs'])
                        ) {
                            for ($x = 0; $x < count($value['event_all']['eztpv']['eztpv_docs']); ++$x) {
                                if (
                                    !empty($value['event_all']['eztpv']['eztpv_docs'][$x]['uploads'])
                                    && 4 == $value['event_all']['eztpv']['eztpv_docs'][$x]['uploads']['upload_type_id']
                                ) {
                                    $photos[]
                                        = $value['event_all']['eztpv']['eztpv_docs'][$x]['uploads']['filename'];
                                }
                            }
                        }

                        $recording = !empty($recordings)
                            ? implode(',', array_unique($recordings))
                            : null;
                        $contract = !empty($contracts)
                            ? implode(',', array_unique($contracts))
                            : null;
                        $photo = !empty($photos)
                            ? implode(',', array_unique($photos))
                            : null;
                        $signature_page = !empty($value['event_all']['eztpv'])
                            ? '/summary/' . $value['event_all']['eztpv']['id']
                            : null;

                        $sp->recording = !empty($recording) ? $recording : null;
                        $sp->contracts = !empty($contract) ? $contract : null;
                        $sp->photos = !empty($photo) ? $photo : null;
                        $sp->signature_pages = !empty($signature_page) ? $signature_page : null;
                        $sp->eztpv_initiated = !empty($value['event_all']['eztpv']) ? true : false;
                        $sp->eztpv_id = !empty($value['event_all']['eztpv'])
                            ? $value['event_all']['eztpv']['id'] : null;
                        $sp->eztpv_sale_type = !empty($value['event_all']['eztpv']['eztpv_sale_type'])
                            ? $value['event_all']['eztpv']['eztpv_sale_type']['slug'] : null;
                        $sp->language_id = $value['event_all']['language']['id'];
                        $sp->language = $value['event_all']['language']['language'];
                        $sp->channel_id = $value['event_all']['channel']['id'];
                        $sp->channel = $value['event_all']['channel']['channel'];
                        $sp->confirmation_code = $value['event_all']['confirmation_code'];
                        $sp->lead_id = $value['event_all']['lead_id'];
                        $sp->survey_id = $value['event_all']['survey_id'];
                        $sp->dob = $value['event_all']['ah_date_of_birth'];
                        $sp->brand_id = $value['event_all']['brand']['id'];
                        $sp->brand_name = $value['event_all']['brand']['name'];
                        $sp->pass_fail = $value['pass_fail'];
                        // echo "HERE " . $value['event_all']['ip_addr'] . "\n";

                        if (filter_var($value['event_all']['ip_addr'], FILTER_VALIDATE_IP)) {
                            $sp->ip_address = $value['event_all']['ip_addr'];
                        } else {
                            $sp->ip_address = !empty($value['event_all']['ip_addr'])
                                ? long2ip($value['event_all']['ip_addr'])
                                : null;
                        }

                        //Refactored code
                        if(empty($sp->ip_address) || $sp->ip_address === '0.0.0.0') {
                            
                            $eztpv = $value['event_all']['eztpv'];
                            $ipAddr = !empty($eztpv['ip_addr']) ? $eztpv['ip_addr'] : 0;
    
                            if ($this->isValidIpAddress($ipAddr)) {
                                $sp->ip_address = $ipAddr;
                            }else {
                                if(is_numeric($ipAddr)) { // long2ip requires a numeric value. In certain edge cases, we may see two IP addresses separated by a comma. Ignore those.
                                    $sp->ip_address = long2ip($ipAddr);
                                }
                            }
                        }

                        $sp->gps_coords = $value['event_all']['gps_coords'];
                        $sp->eztpv_contract_delivery = (!empty($value['event_all']['eztpv']['eztpv_contract_delivery']))
                            ? $value['event_all']['eztpv']['eztpv_contract_delivery']
                            : null;

                        if (
                            !empty($value['event_all']['vendor'])
                        ) {
                            $sp->vendor_id = $value['event_all']['vendor']['id'];
                            $sp->vendor_name = $value['event_all']['vendor']['name'];

                            $vendor = Vendor::where(
                                'brand_id',
                                $value['event_all']['brand']['id']
                            )->where(
                                'vendor_id',
                                $value['event_all']['vendor']['id']
                            )->first();

                            if ($vendor) {
                                $sp->vendor_label = $vendor->vendor_label;
                                $sp->vendor_code = $vendor->vendor_code;
                                $sp->vendor_grp_id = $vendor->grp_id;
                            }
                        }

                        if (
                            !empty($value['event_all']['office'])
                        ) {
                            $sp->office_id = $value['event_all']['office']['id'];
                            $sp->office_name = $value['event_all']['office']['name'];
                            $sp->office_label = $value['event_all']['office']['label'];
                        }

                        $sp->market_id = $value['market']['id'];
                        $sp->market = $value['market']['market'];
                        $sp->event_product_id = $value['id'];
                        $sp->commodity_id = $value['event_type']['id'];
                        $sp->commodity = $value['event_type']['event_type'];

                        if (!empty($value['utility_supported_fuel'])) {
                            $sp->utility_supported_fuel_id = $value['utility_supported_fuel']['id'];

                            if (
                                !empty($value['utility_supported_fuel']['brand_utility_supported_fuels'])
                            ) {
                                $sp->utility_commodity_ldc_code
                                    = $value['utility_supported_fuel']['brand_utility_supported_fuels']['ldc_code'];

                                if (
                                    !empty($value['utility_supported_fuel']['brand_utility_supported_fuels']['external_id'])
                                ) {
                                    $sp->utility_commodity_external_id
                                        = $value['utility_supported_fuel']['brand_utility_supported_fuels']['external_id'];
                                }
                            } else {
                                $sp->utility_commodity_ldc_code
                                    = $value['utility_supported_fuel']['utility']['ldc_code'];
                            }
                        }

                        $sp->custom_fields = null;
                        if (!empty($value['event_all']['custom_field_storage_all'])) {
                            $custom_field = [];

                            foreach ($value['event_all']['custom_field_storage_all'] as $cfs) {
                                $custom_field[] = [
                                    'date' => $cfs['custom_field']['created_at'],
                                    'name' => $cfs['custom_field']['name'],
                                    'output_name' => $cfs['custom_field']['output_name'],
                                    'product' => (isset($cfs['product_id']))
                                        ? $cfs['product_id']
                                        : null,
                                    'value' => $cfs['value'],
                                ];
                            }

                            if (!empty($custom_field)) {
                                $sp->custom_fields = json_encode($custom_field, true);
                            }
                        }

                        if (
                            !empty($value['event_all']['sales_agent'])
                        ) {
                            $sp->sales_agent_id = $value['event_all']['sales_agent']['id'];
                            $sp->sales_agent_name = mb_strtoupper(
                                $value['event_all']['sales_agent']['user']['first_name']
                                    . ' ' . $value['event_all']['sales_agent']['user']['last_name']
                            );
                            $sp->sales_agent_rep_id = $value['event_all']['sales_agent']['tsr_id'];
                        }

                        if (
                            !empty($value['event_all']['script']['dnis']['dnis'])
                        ) {
                            $sp->dnis = CleanPhoneNumber($value['event_all']['script']['dnis']['dnis']);
                        }

                        if (
                            !empty($value['home_type'])
                        ) {
                            $sp->structure_type = $value['home_type']['home_type'];
                        }

                        $sp->live_enroll_id = $value['live_enroll'];
                        $sp->company_name = mb_strtoupper(
                            $value['company_name']
                        );
                        $sp->bill_first_name = mb_strtoupper(
                            $value['bill_first_name']
                        );
                        $sp->bill_middle_name = mb_strtoupper(
                            $value['bill_middle_name']
                        );
                        $sp->bill_last_name = mb_strtoupper(
                            $value['bill_last_name']
                        );
                        $sp->auth_first_name = mb_strtoupper(
                            $value['auth_first_name']
                        );
                        $sp->auth_middle_name = mb_strtoupper(
                            $value['auth_middle_name']
                        );
                        $sp->auth_last_name = mb_strtoupper(
                            $value['auth_last_name']
                        );

                        $sp->auth_relationship = $value['auth_relationship'];

                        if (is_numeric($value['auth_relationship'])) {
                            if ($value['auth_relationship'] > 5) {
                                $ar = Cache::remember(
                                    'auth_relationship_lookup_'
                                        . $value['auth_relationship'],
                                    7200,
                                    function () use ($value) {
                                        return DefaultScCompanyPosition::where(
                                            'id',
                                            $value['auth_relationship']
                                        )->first();
                                    }
                                );

                                if ($ar) {
                                    $sp->auth_relationship = $ar->title;
                                }
                            } else {
                                $ar = Cache::remember(
                                    'auth_relationship_lookup_'
                                        . $value['auth_relationship'],
                                    7200,
                                    function () use ($value) {
                                        return AuthRelationship::where(
                                            'id',
                                            $value['auth_relationship']
                                        )->first();
                                    }
                                );

                                if ($ar) {
                                    $sp->auth_relationship = $ar->relationship;
                                }
                            }
                        }

                        if (!empty($value['event_all']['phone']['phone_number']['phone_number'])) {
                            $sp->btn = CleanPhoneNumber($value['event_all']['phone']['phone_number']['phone_number']);
                        }

                        $sp->email_address = (isset($value['event_all']['email'])
                            && null != $value['event_all']['email'])
                            ? mb_strtolower(
                                $value['event_all']['email']['email']['email_address']
                            ) : null;

                        $address = $this->getAddresses($value);
                        if (isset($address)) {
                            if (isset($address['billing'])) {
                                if (isset($address['billing']['address'])) {
                                    $sp->billing_address1 = trim($address['billing']['address']);
                                }
                                if (isset($address['billing']['address2'])) {
                                    $sp->billing_address2 = trim($address['billing']['address2']);
                                }

                                if (isset($address['billing']['city'])) {
                                    $sp->billing_city = trim($address['billing']['city']);
                                }

                                if (isset($address['billing']['state'])) {
                                    $sp->billing_state = $address['billing']['state'];
                                }

                                if (isset($address['billing']['zip'])) {
                                    $sp->billing_zip = $address['billing']['zip'];
                                }

                                if (isset($address['billing']['county'])) {
                                    $sp->billing_county = $address['billing']['county'];
                                }

                                if (isset($address['billing']['country'])) {
                                    $sp->billing_country = $address['billing']['country'];
                                }
                            }

                            if (isset($address['service'])) {
                                if (isset($address['service']['address'])) {
                                    $sp->service_address1 = trim($address['service']['address']);
                                }

                                if (isset($address['service']['address2'])) {
                                    $sp->service_address2 = trim($address['service']['address2']);
                                }

                                if (isset($address['service']['city'])) {
                                    $sp->service_city = $address['service']['city'];
                                }

                                if (isset($address['service']['state'])) {
                                    $sp->service_state = $address['service']['state'];
                                }

                                if (isset($address['service']['zip'])) {
                                    $sp->service_zip = $address['service']['zip'];
                                }

                                if (isset($address['service']['county'])) {
                                    $sp->service_county = $address['service']['county'];
                                }

                                if (isset($address['service']['country'])) {
                                    $sp->service_country = $address['service']['country'];
                                }
                            }
                        }

                        $sp->rate_id = $value['rate']['id'];
                        $sp->rate_program_code = $value['rate']['program_code'];
                        $sp->rate_monthly_fee = $value['rate']['rate_monthly_fee'];
                        $sp->rate_uom = $value['rate']['rate_uom']['uom'];

                        $sp->rate_source_code = $value['rate']['rate_source_code'];
                        $sp->rate_promo_code = $value['rate']['rate_promo_code'];
                        $sp->rate_external_id = $value['rate']['external_rate_id'];
                        $sp->rate_renewal_plan = $value['rate']['rate_renewal_plan'];
                        $sp->rate_channel_source
                            = $value['rate']['rate_channel_source'];

                        $sp->product_id = $value['rate']['product']['id'];
                        $sp->product_name = $value['rate']['product']['name'];

                        if (
                            !empty($value['rate']['product']['rate_type'])
                        ) {
                            $sp->product_rate_type = $value['rate']['product']['rate_type']['rate_type'];
                        }

                        $sp->external_rate_id = $value['rate']['external_rate_id'];
                        $sp->product_term = $value['rate']['product']['term'];

                        if (
                            !empty($value['rate']['product']['term_type'])
                        ) {
                            $sp->product_term_type = $value['rate']['product']['term_type']['term_type'];
                        }

                        $sp->product_intro_term = $value['rate']['product']['intro_term'];

                        if (
                            !empty($value['rate']['product']['intro_term_type'])
                        ) {
                            $sp->product_intro_term_type = $value['rate']['product']['intro_term_type']['term_type'];
                        }

                        $sp->product_daily_fee = $value['rate']['product']['daily_fee'];
                        $sp->product_service_fee = $value['rate']['product']['service_fee'];
                        $sp->product_monthly_fee = $value['rate']['product']['monthly_fee'];
                        $sp->product_intro_service_fee = $value['rate']['product']['intro_service_fee'];
                        $sp->product_rate_amount = $value['rate']['rate_amount'];

                        if (
                            !empty($value['rate']['rate_currency'])
                        ) {
                            $sp->product_rate_amount_currency = $value['rate']['rate_currency']['currency'];
                        }

                        $sp->product_green_percentage = $value['rate']['product']['green_percentage'];
                        $sp->product_cancellation_fee = $value['rate']['cancellation_fee'];
                        $sp->product_admin_fee = $value['rate']['admin_fee'];
                        $sp->utility_id = (!empty($value['utility_supported_fuel']['utility']))
                            ? $value['utility_supported_fuel']['utility']['id']
                            : null;
                        $sp->product_utility_external_id
                            = (!empty($value['utility_supported_fuel']['utility']['brand_identifier']['utility_external_id']))
                            ? $value['utility_supported_fuel']['utility']['brand_identifier']['utility_external_id']
                            : null;
                        $sp->product_utility_name = $value['utility_supported_fuel']['utility']['name'];

                        $account_number1 = null;
                        $account_number2 = null;
                        $name_key = null;

                        $identifiers = $value['identifiers'];

                        for ($x = 0; $x < count($identifiers); ++$x) {
                            $uan_type_id = 0;

                            if (!empty($identifiers[$x]['utility_account_number_type_id'])) {
                                $uan_type_id = $identifiers[$x]['utility_account_number_type_id'];
                            }
                            else if (!empty($identifiers[$x]['utility_account_type']['utility_account_number_type_id'])) {
                                $uan_type_id = $identifiers[$x]['utility_account_type']['utility_account_number_type_id'];
                            }

                            if (empty($uan_type_id)) {
                                switch ($identifiers[$x]['utility_account_type']['id']) {
                                    case 7:
                                    case 8:
                                    case 11:
                                        if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                            $account_number2 = $identifiers[$x]['identifier'];
                                        }
                                        break;
                                    case 9:
                                        if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                            $name_key = $identifiers[$x]['identifier'];
                                        }
                                        break;
                                    default:
                                        if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                            $account_number1 = $identifiers[$x]['identifier'];
                                        }
                                        break;
                                }
                            }
                            else {
                                switch ($uan_type_id) {
                                    case '2':
                                    case 2:
                                        if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                            $account_number2 = $identifiers[$x]['identifier'];
                                        }
                                        break;
                                    case '3':
                                    case 3:
                                        if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                            $name_key = $identifiers[$x]['identifier'];
                                        }
                                        break;
                                    case '1':
                                    case 1:
                                    default:
                                        if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                            $account_number1 = $identifiers[$x]['identifier'];
                                        }
                                        break;
                                }
                            }
                        }

                        $sp->account_number1 = $account_number1;
                        $sp->account_number2 = $account_number2;
                        $sp->name_key = $name_key;

                        if (null != $value['event_all']['deleted_at']) {
                            echo '-- Deleted '
                                . $value['event_all']['confirmation_code'] . "\n";
                            echo '---- Deleted at was ' . $value['event_all']['deleted_at'] . "\n";
                            $sp->deleted_at = $value['event_all']['deleted_at'];
                        } else {
                            $sp->deleted_at = null;
                        }

                        $sp->save();

                        if ($sp->deleted_at === null && $sp->gps_coords !== null) {
                            Artisan::call('calculate:distance:sa:client', [
                                '--confcode' => $sp->confirmation_code,
                            ]);
                        }
                    }
                }
            );

            echo "-- Starting product-less events.\n";

            $partial = Interaction::select(
                'interactions.event_id'
            )->leftJoin(
                'events',
                'interactions.event_id',
                'events.id'
            )->whereNotIn(
                'interactions.interaction_type_id',
                // Exclude warm_transfer, link_tracking, and expired_link_access for Product-Less query
                [10, 22, 31]
            )->where(
                'events.brand_id',
                $brand->brand_id
            )->whereRaw(
                '(SELECT COUNT(id) FROM event_product WHERE event_id = events.id) = 0'
            );

            if ($this->option('startDate') && $this->option('endDate')) {
                $partial = $partial->whereBetween(
                    'interactions.updated_at',
                    [
                        $this->option('startDate'),
                        $this->option('endDate'),
                    ]
                );
            } else {
                if (!$this->option('forever')) {
                    $partial = $partial->where(
                        'interactions.updated_at',
                        '>=',
                        Carbon::now('America/Chicago')->subHours($hours)
                    );
                }
            }

            $partial = $partial->groupBy(
                'interactions.event_id'
            )->withTrashed()->get()->pluck('event_id')->toArray();

            if (count($partial) > 0) {
                Event::with(
                    [
                        'brand',
                        'vendor',
                        'eztpv',
                        'eztpv.eztpv_sale_type',
                        'eztpv.eztpv_docs',
                        'eztpv.eztpv_docs.uploads' => function ($query) {
                            $query->whereNull(
                                'uploads.deleted_at'
                            );
                        },
                        'script',
                        'script.dnis',
                        'office',
                        'channel',
                        'language',
                        'phone.phone_number',
                        'email.email',
                        'sales_agent',
                        'sales_agent.user',
                        'customFieldStorageAll.customField',
                        'interactions' => function ($query) {
                            $query->where(
                                'interaction_type_id',
                                '!=',
                                22
                            );
                        },
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
                    ]
                )->whereIn(
                    'events.id',
                    $partial
                )->where(
                    'brand_id',
                    $brand->brand_id
                )->orderBy('created_at', 'desc')->withTrashed()->chunk(
                    1000,
                    function ($results) {
                        foreach ($results->toArray() as $key => $value) {
                            // print_r($value);
                            // exit();

                            $sp = StatsProduct::where(
                                'confirmation_code',
                                $value['confirmation_code']
                            )->where(
                                'brand_id',
                                $value['brand_id']
                            );

                            $interactions = !empty($value['interactions'])
                                ? $value['interactions'] : null;

                            $sp = $sp->withTrashed()->first();
                            if (!$sp) {
                                $sp = new StatsProduct();
                            }

                            $sp->event_id = $value['id'];

                            if (!empty($value['hrtpv'])) {
                                $sp->stats_product_type_id = 2;
                            } elseif (!empty($value['survey_id'])) {
                                $sp->stats_product_type_id = 3;
                            } elseif (!empty($value['agent_confirmation'])) {
                                $sp->stats_product_type_id = 4;
                            } else {
                                $sp->stats_product_type_id = 1;
                            }

                            $sp->event_created_at = $value['created_at'];

                            $sp->custom_fields = null;
                            if (!empty($value['custom_field_storage_all'])) {
                                $custom_field = [];

                                foreach ($value['custom_field_storage_all'] as $cfs) {
                                    $custom_field[] = [
                                        'date' => $cfs['custom_field']['created_at'],
                                        'name' => $cfs['custom_field']['name'],
                                        'output_name' => $cfs['custom_field']['output_name'],
                                        'product' => (isset($cfs['product_id']))
                                            ? $cfs['product_id']
                                            : null,
                                        'value' => $cfs['value'],
                                    ];
                                }

                                if (!empty($custom_field)) {
                                    $sp->custom_fields = json_encode($custom_field, true);
                                }
                            }

                            if (!empty($interactions)) {
                                $interaction_time = 0;
                                $interaction_notes = null;
                                $forced_phone_validation = 0;

                                for ($x = 0; $x < count($interactions); ++$x) {
                                    if (
                                        !empty($interactions[$x]['notes'])
                                    ) {
                                        $interaction_notes = $interactions[$x]['notes'];
                                    }

                                    $interaction_time
                                        += number_format($interactions[$x]['interaction_time'], 2);

                                    if ($interactions[$x]['forced_phone_validation'] === 1) {
                                        $forced_phone_validation = 1;
                                    }
                                }

                                $ani = null;
                                if (null !== $interaction_notes) {
                                    if (!is_array($interaction_notes)) {
                                        $notes_array = json_decode($interaction_notes, true);
                                    } else {
                                        $notes_array = $interaction_notes;
                                    }

                                    if ($notes_array) {
                                        if (isset($notes_array['ani']) && is_array($notes_array)) {
                                            $ani = '+1' . $notes_array['ani'];
                                        }
                                    }
                                }

                                $sp->ani = $ani;

                                $final_interaction_time = ($interaction_time > 0)
                                    ? number_format(
                                        $interaction_time,
                                        2
                                    ) : 0;

                                $sp->interaction_time = $final_interaction_time;
                                $sp->product_time = $final_interaction_time;

                                $ikey = count($interactions) - 1;

                                $sp->interaction_id = $interactions[$ikey]['id'];
                                $sp->interaction_created_at = $interactions[$ikey]['created_at'];
                                $sp->forced_phone_validation = $forced_phone_validation;
                                $sp->result = $interactions[$ikey]['result']['result'];
                                $sp->interaction_type = $interactions[$ikey]['interaction_type']['name'];
                                $sp->source = (isset($interactions[0]['source']['source'])
                                    && null != $interactions[0]['source']['source'])
                                    ? $interactions[0]['source']['source'] : 'Live';

                                if ('No Sale' == $sp->result) {
                                    $sp->disposition_id = $interactions[$ikey]['disposition']['id'];
                                    $sp->disposition_label = $interactions[$ikey]['disposition']['brand_label'];
                                    $sp->disposition_reason = $interactions[$ikey]['disposition']['reason'];
                                } else {
                                    $sp->disposition_id = null;
                                    $sp->disposition_label = null;
                                    $sp->disposition_reason = null;
                                }

                                if (
                                    !empty($interactions[$ikey]['event_flags'])
                                ) {
                                    $flag_key = count($interactions[$ikey]['event_flags']) - 1;
                                    $sp->flagged_reason
                                        = $interactions[$ikey]['event_flags'][$flag_key]['flag_reason']['description'];
                                    $sp->flagged_by = mb_strtoupper(
                                        $interactions[$ikey]['event_flags'][$flag_key]['flagged_by']['first_name']
                                            . ' ' .
                                            $interactions[$ikey]['event_flags'][$flag_key]['flagged_by']['last_name']
                                    );
                                    $sp->flagged_by_label
                                        = (isset($interactions[$ikey]['event_flags'][$flag_key]['flagged_by']['username']))
                                        ? $interactions[$ikey]['event_flags'][$flag_key]['flagged_by']['username']
                                        : null;
                                }

                                if (
                                    !empty($interactions[$ikey]['tpv_agent'])
                                ) {
                                    $sp->tpv_agent_id = $interactions[$ikey]['tpv_agent']['id'];
                                    $sp->tpv_agent_name = $interactions[$ikey]['tpv_agent']['first_name']
                                        . ' ' . $interactions[$ikey]['tpv_agent']['last_name'];
                                    $sp->tpv_agent_label = $interactions[$ikey]['tpv_agent']['username'];
                                    $sp->tpv_agent_call_center_id
                                        = (isset($interactions[$ikey]['tpv_agent']['call_center_id']))
                                        ? $interactions[$ikey]['tpv_agent']['call_center_id']
                                        : null;
                                }

                                $recordings = [];
                                if (
                                    !empty($interactions[$ikey]['recordings'])
                                ) {
                                    for ($x = 0; $x < count($interactions[$ikey]['recordings']); ++$x) {
                                        if (
                                            !empty($interactions[$ikey]['recordings'][$x]['recording'])
                                        ) {
                                            $recordings[] = $interactions[$ikey]['recordings'][$x]['recording'];
                                        }
                                    }
                                }
                            }

                            if (0 == strlen(trim($sp->result))) {
                                $sp->result = 'Closed';
                            }

                            $contracts = [];
                            if (
                                !empty($value['eztpv'])
                                && !empty($value['eztpv']['eztpv_docs'])
                            ) {

                                for ($x = 0; $x < count($value['eztpv']['eztpv_docs']); ++$x) {
                                    if (
                                        !empty($value['eztpv']['eztpv_docs'][$x]['uploads'])
                                        && 3 == $value['eztpv']['eztpv_docs'][$x]['uploads']['upload_type_id']
                                    ) {
                                        $contracts[] = $value['eztpv']['eztpv_docs'][$x]['uploads']['filename'];
                                    }
                                }
                            }

                            $photos = [];
                            if (
                                !empty($value['eztpv'])
                                && !empty($value['eztpv']['eztpv_docs'])
                            ) {
                                for ($x = 0; $x < count($value['eztpv']['eztpv_docs']); ++$x) {
                                    if (
                                        !empty($value['eztpv']['eztpv_docs'][$x]['uploads'])
                                        && 4 == $value['eztpv']['eztpv_docs'][$x]['uploads']['upload_type_id']
                                    ) {
                                        $photos[] = $value['eztpv']['eztpv_docs'][$x]['uploads']['filename'];
                                    }
                                }
                            }

                            $recording = (!empty($recordings))
                                ? implode(',', array_unique($recordings))
                                : null;
                            $contract = (!empty($contracts))
                                ? implode(',', array_unique($contracts))
                                : null;
                            $photo = (!empty($photos))
                                ? implode(',', array_unique($photos))
                                : null;
                            $signature_page = !empty($value['eztpv'])
                                ? '/summary/' . $value['eztpv']['id']
                                : null;

                            $sp->recording = (isset($recording) && strlen(trim($recording)) > 0)
                                ? $recording : null;
                            $sp->contracts = (isset($contract) && strlen(trim($contract)) > 0)
                                ? $contract : null;
                            $sp->photos = (isset($photo) && strlen(trim($photo)) > 0)
                                ? $photo : null;
                            $sp->signature_pages = (isset($signature_page) && strlen(trim($signature_page)) > 0)
                                ? $signature_page : null;
                            $sp->eztpv_initiated = (isset($value['eztpv'])
                                && null != $value['eztpv']) ? true : false;
                            $sp->eztpv_id = (isset($value['eztpv'])
                                && null != $value['eztpv'])
                                ? $value['eztpv']['id'] : null;
                            $sp->eztpv_sale_type = (isset($value['eztpv']['eztpv_sale_type'])
                                && null != $value['eztpv']['eztpv_sale_type'])
                                ? $value['eztpv']['eztpv_sale_type']['slug'] : null;
                            $sp->language_id = $value['language']['id'];
                            $sp->language = $value['language']['language'];
                            $sp->channel_id = $value['channel']['id'];
                            $sp->channel = $value['channel']['channel'];
                            $sp->confirmation_code = $value['confirmation_code'];
                            $sp->lead_id = $value['lead_id'];
                            $sp->survey_id = $value['survey_id'];
                            $sp->dob = $value['ah_date_of_birth'];
                            $sp->brand_id = $value['brand']['id'];
                            $sp->brand_name = $value['brand']['name'];

                            if (filter_var($value['ip_addr'], FILTER_VALIDATE_IP)) {
                                $sp->ip_address = $value['ip_addr'];
                            } else {
                                $sp->ip_address = (isset($value['ip_addr'])
                                    && $value['ip_addr'] > 0)
                                    ? long2ip($value['ip_addr'])
                                    : null;
                            }

                            if (
                                (empty($sp->ip_address) || $sp->ip_address === '0.0.0.0')
                                && !empty($value['eztpv']['ip_addr'])
                                && filter_var($value['eztpv']['ip_addr'], FILTER_VALIDATE_IP)
                            ) {
                                $sp->ip_address = $value['eztpv']['ip_addr'];
                            }

                            $sp->gps_coords = $value['gps_coords'];
                            $sp->eztpv_contract_delivery = (isset($value['eztpv']['eztpv_contract_delivery']))
                                ? $value['eztpv']['eztpv_contract_delivery']
                                : null;

                            if (
                                !empty($value['vendor'])
                            ) {
                                $sp->vendor_id = $value['vendor']['id'];
                                $sp->vendor_name = $value['vendor']['name'];

                                $vendor = Vendor::where(
                                    'brand_id',
                                    $value['brand']['id']
                                )->where(
                                    'vendor_id',
                                    $value['vendor']['id']
                                )->first();

                                if ($vendor) {
                                    $sp->vendor_label = $vendor->vendor_label;
                                    $sp->vendor_code = $vendor->vendor_code;
                                    $sp->vendor_grp_id = $vendor->grp_id;
                                }
                            }

                            if (
                                !empty($value['office'])
                            ) {
                                $sp->office_id = $value['office']['id'];
                                $sp->office_name = $value['office']['name'];
                                $sp->office_label = $value['office']['label'];
                            }

                            if (
                                !empty($value['sales_agent'])
                            ) {
                                $sp->sales_agent_id = $value['sales_agent']['id'];
                                $sp->sales_agent_name = mb_strtoupper(
                                    $value['sales_agent']['user']['first_name']
                                        . ' ' . $value['sales_agent']['user']['last_name']
                                );
                                $sp->sales_agent_rep_id = $value['sales_agent']['tsr_id'];
                            }

                            if (!empty($value['script']['dnis']['dnis'])) {
                                $sp->dnis = CleanPhoneNumber($value['script']['dnis']['dnis']);
                            }

                            if (null != $value['deleted_at']) {
                                echo '-- Deleted ' . $value['confirmation_code'] . "\n";
                                echo '---- Deleted at was ' . $value['deleted_at'] . "\n";
                                $sp->deleted_at = $value['deleted_at'];
                            } else {
                                $sp->deleted_at = null;
                            }

                            $sp->save();
                        }
                    }
                );
            }
        }
    }
}
