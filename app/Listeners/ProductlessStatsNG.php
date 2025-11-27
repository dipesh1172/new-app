<?php

namespace App\Listeners;

use App\Models\Event;
use App\Models\StatsProduct;
use App\Models\Vendor;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProductlessStatsNG implements ShouldQueue
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
     * @param ProductlessStatsToProcess $event
     */
    public function handle(ProductlessStatsToProcess $event)
    {
        $brand = $event->brand;
        $results = $event->products;

        foreach ($results as $key => $value) {
            $sp = StatsProduct::where(
                'confirmation_code',
                $value['confirmation_code']
            );

            $interactions = (isset($value['interactions'])
                && count($value['interactions']) > 0)
                ? $value['interactions'] : null;

            $sp = $sp->withTrashed()->first();
            if (!$sp) {
                $sp = new StatsProduct();
            }

            $product_count = 0;
            $sp->event_id = $value['id'];

            if (isset($value['hrtpv']) && $value['hrtpv']) {
                $sp->stats_product_type_id = 2;
            } elseif (isset($value['survey_id']) && !empty($value['survey_id'])) {
                $sp->stats_product_type_id = 3;
            } elseif (isset($value['agent_confirmation']) && $value['agent_confirmation']) {
                $sp->stats_product_type_id = 4;
            } else {
                $sp->stats_product_type_id = 1;
            }

            $sp->event_created_at = $value['created_at'];

            if (isset($value['custom_field_storage'])) {
                $custom_field = [];

                foreach ($value['custom_field_storage'] as $cfs) {
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

            if ($interactions != null) {
                $interaction_time = 0;
                for ($x = 0; $x < count($interactions); ++$x) {
                    $interaction_time
                        += number_format($interactions[$x]['interaction_time'], 2);
                }

                $sp->interaction_time = number_format($interaction_time, 2);
                $sp->product_time = number_format($interaction_time, 2);

                $key = count($interactions) - 1;
                $sp->interaction_id = $interactions[$key]['id'];
                $sp->interaction_created_at = $interactions[$key]['created_at'];
                $sp->forced_phone_validation = $interactions[$key]['forced_phone_validation'];
                $sp->result = $interactions[$key]['result']['result'];
                $sp->interaction_type = $interactions[$key]['interaction_type']['name'];
                $sp->source = (isset($interactions[0]['source']['source'])
                    && $interactions[0]['source']['source'] != null)
                    ? $interactions[0]['source']['source'] : 'Live';

                if ($sp->result == 'No Sale') {
                    $sp->disposition_id = $interactions[$key]['disposition']['id'];
                    $sp->disposition_label = $interactions[$key]['disposition']['brand_label'];
                    $sp->disposition_reason = $interactions[$key]['disposition']['reason'];
                } else {
                    $sp->disposition_id = null;
                    $sp->disposition_label = null;
                    $sp->disposition_reason = null;
                }

                if (isset($interactions[$key]['event_flags'])
                    && count($interactions[$key]['event_flags']) > 0
                ) {
                    $flag_key = count($interactions[$key]['event_flags']) - 1;
                    $sp->flagged_reason
                        = $interactions[$key]['event_flags'][$flag_key]['flag_reason']['description'];
                    $sp->flagged_by = mb_strtoupper(
                        $interactions[$key]['event_flags'][$flag_key]['flagged_by']['first_name']
                            .' '.
                            $interactions[$key]['event_flags'][$flag_key]['flagged_by']['last_name']
                    );
                    $sp->flagged_by_label
                        = (isset(
                            $interactions[$key]['event_flags'][$flag_key]['flagged_by']['username']
                        ))
                        ? $interactions[$key]['event_flags'][$flag_key]['flagged_by']['username']
                        : null;
                }

                if (isset($interactions[$key]['tpv_agent'])
                    && $interactions[$key]['tpv_agent'] != null
                ) {
                    $sp->tpv_agent_id = $interactions[$key]['tpv_agent']['id'];
                    $sp->tpv_agent_name = $interactions[$key]['tpv_agent']['first_name']
                        .' '.$interactions[$key]['tpv_agent']['last_name'];
                    $sp->tpv_agent_label = $interactions[$key]['tpv_agent']['username'];
                }

                $recordings = [];
                if (isset($interactions[$key]['recordings'])
                    && $interactions[$key]['recordings'] != null
                ) {
                    for ($x = 0; $x < count($interactions[$key]['recordings']); ++$x) {
                        if (isset($interactions[$key]['recordings'][$x]['recording'])
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
            if (isset($value['eztpv'])
                && $value['eztpv'] != null
            ) {
                if (isset($value['eztpv']['eztpv_docs'])
                    && $value['eztpv']['eztpv_docs'] != null
                ) {
                    for ($x = 0; $x < count($value['eztpv']['eztpv_docs']); ++$x) {
                        if (isset($value['eztpv']['eztpv_docs'][$x]['uploads'])
                            && $value['eztpv']['eztpv_docs'][$x]['uploads'] != null
                            && $value['eztpv']['eztpv_docs'][$x]['uploads']['upload_type_id'] == 3
                        ) {
                            $contracts[] = $value['eztpv']['eztpv_docs'][$x]['uploads']['filename'];
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
            $sp->eztpv_initiated = (isset($value['eztpv'])
                && $value['eztpv'] != null) ? true : false;
            $sp->eztpv_id = (isset($value['eztpv'])
                && $value['eztpv'] != null)
                ? $value['eztpv']['id'] : null;
            $sp->eztpv_sale_type = (isset($value['eztpv']['eztpv_sale_type'])
                && $value['eztpv']['eztpv_sale_type'] != null)
                ? $value['eztpv']['eztpv_sale_type']['slug'] : null;
            $sp->language_id = $value['language']['id'];
            $sp->language = $value['language']['language'];
            $sp->channel_id = $value['channel']['id'];
            $sp->channel = $value['channel']['channel'];
            $sp->confirmation_code = $value['confirmation_code'];
            $sp->lead_id = $value['lead_id'];
            $sp->survey_id = $value['survey_id'];
            $sp->brand_id = $value['brand']['id'];
            $sp->brand_name = $value['brand']['name'];

            if (isset($value['vendor'])
                && $value['vendor'] != null
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

            if (isset($value['office'])
                && $value['office'] != null
            ) {
                $sp->office_id = $value['office']['id'];
                $sp->office_name = $value['office']['name'];
                $sp->office_label = $value['office']['label'];
            }

            if (isset($value['sales_agent'])
                && $value['sales_agent'] != null
            ) {
                $sp->sales_agent_id = $value['sales_agent']['id'];
                $sp->sales_agent_name = mb_strtoupper(
                    $value['sales_agent']['user']['first_name']
                        .' '.$value['sales_agent']['user']['last_name']
                );
                $sp->sales_agent_rep_id = $value['sales_agent']['tsr_id'];
            }

            if (isset($value['script']['dnis']['dnis'])
                && strpos($value['script']['dnis']['dnis'], '+1') !== 0
            ) {
                $sp->dnis = '+1'.$value['script']['dnis']['dnis'];
            } else {
                $sp->dnis = $value['script']['dnis']['dnis'];
            }

            if ($value['deleted_at'] != null) {
                echo '-- Deleted '.$value['confirmation_code']."\n";
                echo '---- Deleted at was '.$value['deleted_at']."\n";
                $sp->deleted_at = $value['deleted_at'];
            } else {
                $sp->deleted_at = null;
            }

            $sp->save();
        }
    }
}
