<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Vendor;
use App\Models\StatsProduct;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\DefaultScCompanyPosition;
use App\Models\AuthRelationship;
use App\Events\ProductStatsToProcess;

class ProductStatsNG implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param ProductStatsToProcess $event
     */
    public function handle(ProductStatsToProcess $event)
    {
        $brand = $event->brand;
        $results = $event->products;
        foreach ($results as $key => $value) {
            $sp = StatsProduct::where(
                'event_product_id',
                $value['id']
            );

            $interactions = (isset($value['event_all']['interactions'])
                && count($value['event_all']['interactions']) > 0)
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

            if (isset($value['event_all']['hrtpv']) && $value['event_all']['hrtpv']) {
                $sp->stats_product_type_id = 2;
            } elseif (isset($value['event_all']['survey_id']) && !empty($value['event_all']['survey_id'])) {
                $sp->stats_product_type_id = 3;
            } elseif (isset($value['event_all']['agent_confirmation']) && $value['event_all']['agent_confirmation']) {
                $sp->stats_product_type_id = 4;
            } else {
                $sp->stats_product_type_id = 1;
            }

            $sp->event_created_at = $value['event_all']['created_at'];

            if ($interactions != null) {
                $interaction_time = 0;
                for ($x = 0; $x < count($interactions); ++$x) {
                    $interaction_time
                        += number_format(
                            $interactions[$x]['interaction_time'],
                            2
                        );
                }

                $sp->interaction_time = number_format(
                    $interaction_time,
                    2
                );
                $sp->product_time = ($interaction_time > 0
                    && $product_count > 0)
                    ? number_format(
                        $interaction_time / $product_count,
                        2
                    )
                    : number_format($interaction_time, 2);

                $key = count($interactions) - 1;

                $sp->interaction_id = $interactions[$key]['id'];
                $sp->interaction_created_at
                    = $interactions[$key]['created_at'];
                $sp->forced_phone_validation = $interactions[$key]['forced_phone_validation'];

                $sale_check = false;
                foreach ($interactions as $key => $interaction) {
                    if ($interaction['result']['result'] === 'Sale') {
                        $sale_check = true;
                    }
                }
                if ($sale_check) {
                    $sp->result = 'Sale';
                } else {
                    $sp->result = $interactions[$key]['result']['result'];
                }

                $sp->interaction_type
                    = $interactions[$key]['interaction_type']['name'];
                $sp->source
                    = (isset($interactions[0]['source']['source'])
                        && $interactions[0]['source']['source'] != null)
                    ? $interactions[0]['source']['source'] : 'Live';

                if ($sp->result == 'No Sale') {
                    $sp->disposition_id = $interactions[$key]['disposition']['id'];
                    $sp->disposition_label
                        = $interactions[$key]['disposition']['brand_label'];
                    $sp->disposition_reason
                        = $interactions[$key]['disposition']['reason'];
                } else {
                    $sp->disposition_id = null;
                    $sp->disposition_label = null;
                    $sp->disposition_reason = null;
                }

                if (
                    isset($interactions[$key]['event_flags'])
                    && count($interactions[$key]['event_flags']) > 0
                ) {
                    $flag_key = count(
                        $interactions[$key]['event_flags']
                    ) - 1;
                    $sp->flagged_reason = $interactions[$key]['event_flags'][$flag_key]['flag_reason']['description'];
                    $sp->flagged_by = mb_strtoupper(
                        $interactions[$key]['event_flags'][$flag_key]['flagged_by']['first_name'] . ' ' .
                            $interactions[$key]['event_flags'][$flag_key]['flagged_by']['last_name']
                    );
                    $sp->flagged_by_label
                        = (isset($interactions[$key]['event_flags'][$flag_key]['flagged_by']['username']))
                        ? $interactions[$key]['event_flags'][$flag_key]['flagged_by']['username']
                        : null;
                }

                if (
                    isset($interactions[$key]['tpv_agent'])
                    && $interactions[$key]['tpv_agent'] != null
                ) {
                    $sp->tpv_agent_id
                        = $interactions[$key]['tpv_agent']['id'];
                    $sp->tpv_agent_name
                        = $interactions[$key]['tpv_agent']['first_name']
                        . ' '
                        . $interactions[$key]['tpv_agent']['last_name'];
                    $sp->tpv_agent_label
                        = $interactions[$key]['tpv_agent']['username'];
                }

                $recordings = [];
                if (
                    isset($interactions[$key]['recordings'])
                    && $interactions[$key]['recordings'] != null
                ) {
                    for ($x = 0; $x < count($interactions[$key]['recordings']); ++$x) {
                        if (
                            isset($interactions[$key]['recordings'][$x]['recording'])
                            && $interactions[$key]['recordings'][$x]['recording'] != null
                        ) {
                            $recordings[] = $interactions[$key]['recordings'][$x]['recording'];
                        }
                    }
                }
            }

            if (strlen(trim($sp->result)) == 0) {
                $sp->result = 'Closed';
            }

            $contracts = [];
            if (
                isset($value['event_all']['eztpv'])
                && $value['event_all']['eztpv'] != null
            ) {
                if (
                    isset($value['event_all']['eztpv']['eztpv_docs'])
                    && $value['event_all']['eztpv']['eztpv_docs'] != null
                ) {
                    for ($x = 0; $x < count($value['event_all']['eztpv']['eztpv_docs']); ++$x) {
                        if (
                            isset($value['event_all']['eztpv']['eztpv_docs'][$x]['uploads'])
                            && $value['event_all']['eztpv']['eztpv_docs'][$x]['uploads'] != null
                            && $value['event_all']['eztpv']['eztpv_docs'][$x]['uploads']['upload_type_id'] == 3
                        ) {
                            $contracts[] = $value['event_all']['eztpv']['eztpv_docs'][$x]['uploads']['filename'];
                        }
                    }
                }
            }

            $recording = (isset($recordings))
                ? implode(',', array_unique($recordings))
                : null;
            $contract = (isset($contracts))
                ? implode(',', array_unique($contracts))
                : null;

            $sp->recording = (isset($recording) && strlen(trim($recording)) > 0)
                ? $recording : null;
            $sp->contracts = (isset($contract) && strlen(trim($contract)) > 0)
                ? $contract : null;
            $sp->eztpv_initiated = (isset($value['event_all']['eztpv'])
                && $value['event_all']['eztpv'] != null) ? true : false;
            $sp->eztpv_id = (isset($value['event_all']['eztpv'])
                && $value['event_all']['eztpv'] != null)
                ? $value['event_all']['eztpv']['id'] : null;
            $sp->eztpv_sale_type = (isset($value['event_all']['eztpv']['eztpv_sale_type'])
                && $value['event_all']['eztpv']['eztpv_sale_type'] != null)
                ? $value['event_all']['eztpv']['eztpv_sale_type']['slug'] : null;
            $sp->language_id = $value['event_all']['language']['id'];
            $sp->language = $value['event_all']['language']['language'];
            $sp->channel_id = $value['event_all']['channel']['id'];
            $sp->channel = $value['event_all']['channel']['channel'];
            $sp->confirmation_code = $value['event_all']['confirmation_code'];
            $sp->lead_id = $value['event_all']['lead_id'];
            $sp->survey_id = $value['event_all']['survey_id'];
            $sp->brand_id = $value['event_all']['brand']['id'];
            $sp->brand_name = $value['event_all']['brand']['name'];
            $sp->pass_fail = $value['pass_fail'];

            if (
                isset($value['event_all']['vendor'])
                && $value['event_all']['vendor'] != null
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
                isset($value['event_all']['office'])
                && $value['event_all']['office'] != null
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

            if (
                isset($value['utility_supported_fuel'])
                && isset($value['utility_supported_fuel']['brand_utility_supported_fuels'])
                && $value['utility_supported_fuel']['brand_utility_supported_fuels'] != null
            ) {
                $sp->utility_supported_fuel_id = $value['utility_supported_fuel']['id'];
                $sp->utility_commodity_ldc_code
                    = $value['utility_supported_fuel']['brand_utility_supported_fuels']['ldc_code'];

                if (
                    isset($value['utility_supported_fuel']['brand_utility_supported_fuels']['external_id'])
                    && strlen(
                        trim(
                            $value['utility_supported_fuel']['brand_utility_supported_fuels']['external_id']
                        )
                    ) > 0
                ) {
                    $sp->utility_commodity_external_id
                        = $value['utility_supported_fuel']['brand_utility_supported_fuels']['external_id'];
                }
            }

            if (isset($value['event_all']['custom_field_storage'])) {
                $custom_field = [];

                foreach ($value['event_all']['custom_field_storage'] as $cfs) {
                    $custom_field[] = [
                        'date' => $cfs['custom_field']['created_at'],
                        'name' => $cfs['custom_field']['name'],
                        'output_name' => $cfs['custom_field']['output_name'],
                        'value' => $cfs['value'],
                    ];
                }
            }

            $sp->custom_fields = (isset($custom_field))
                ? json_encode($custom_field, true)
                : null;

            if (
                isset($value['event_all']['sales_agent'])
                && $value['event_all']['sales_agent'] != null
            ) {
                $sp->sales_agent_id = $value['event_all']['sales_agent']['id'];
                $sp->sales_agent_name = mb_strtoupper(
                    $value['event_all']['sales_agent']['user']['first_name']
                        . ' ' . $value['event_all']['sales_agent']['user']['last_name']
                );
                $sp->sales_agent_rep_id = $value['event_all']['sales_agent']['tsr_id'];
            }

            if (
                isset($value['event_all']['script']['dnis']['dnis'])
                && strpos($value['event_all']['script']['dnis']['dnis'], '+1') !== 0
            ) {
                $sp->dnis = '+1' . $value['event_all']['script']['dnis']['dnis'];
            } else {
                $sp->dnis = $value['event_all']['script']['dnis']['dnis'];
            }

            if (
                isset($value['home_type'])
                && $value['home_type'] != null
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
                        120,
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
                        120,
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

            if (strpos($value['event_all']['phone']['phone_number']['phone_number'], '+1') !== 0) {
                $sp->btn = '+1' . $value['event_all']['phone']['phone_number']['phone_number'];
            } else {
                $sp->btn = $value['event_all']['phone']['phone_number']['phone_number'];
            }

            $sp->email_address = (isset($value['event_all']['email'])
                && $value['event_all']['email'] != null)
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
                isset($value['rate']['product']['rate_type'])
                && $value['rate']['product']['rate_type'] != null
            ) {
                $sp->product_rate_type = $value['rate']['product']['rate_type']['rate_type'];
            }

            $sp->external_rate_id = $value['rate']['external_rate_id'];
            $sp->product_term = $value['rate']['product']['term'];

            if (
                isset($value['rate']['product']['term_type'])
                && $value['rate']['product']['term_type'] != null
            ) {
                $sp->product_term_type = $value['rate']['product']['term_type']['term_type'];
            }

            $sp->product_intro_term = $value['rate']['product']['intro_term'];

            if (
                isset($value['rate']['product']['intro_term_type'])
                && $value['rate']['product']['intro_term_type'] != null
            ) {
                $sp->product_intro_term_type = $value['rate']['product']['intro_term_type']['term_type'];
            }

            $sp->product_daily_fee = $value['rate']['product']['daily_fee'];
            $sp->product_service_fee = $value['rate']['product']['service_fee'];
            $sp->product_monthly_fee = $value['rate']['product']['monthly_fee'];
            $sp->product_intro_service_fee = $value['rate']['product']['intro_service_fee'];
            $sp->product_rate_amount = $value['rate']['rate_amount'];

            if (
                isset($value['rate']['rate_currency'])
                && $value['rate']['rate_currency'] != null
            ) {
                $sp->product_rate_amount_currency = $value['rate']['rate_currency']['currency'];
            }

            $sp->product_green_percentage = $value['rate']['product']['green_percentage'];
            $sp->product_cancellation_fee = $value['rate']['cancellation_fee'];
            $sp->product_admin_fee = $value['rate']['admin_fee'];
            $sp->utility_id = (isset($value['utility_supported_fuel']['utility']))
                ? $value['utility_supported_fuel']['utility']['id']
                : null;
            $sp->product_utility_external_id
                = (isset($value['utility_supported_fuel']['utility']['brand_identifier']['utility_external_id']))
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
                        case 2:
                            if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                $account_number2 = $identifiers[$x]['identifier'];
                            }
                            break;
                        case 3:
                            if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                $name_key = $identifiers[$x]['identifier'];
                            }
                            break;
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

            if ($value['event_all']['deleted_at'] != null) {
                echo '-- Deleted '
                    . $value['event_all']['confirmation_code'] . "\n";
                echo '---- Deleted at was ' . $value['event_all']['deleted_at'] . "\n";
                $sp->deleted_at = $value['event_all']['deleted_at'];
            } else {
                $sp->deleted_at = null;
            }

            $sp->save();
        }
    }
}
