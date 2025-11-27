<?php

namespace App\Helpers;

use App\Models\InvoiceRateCard;
use App\Models\Interaction;

class InvoiceHelper
{
    public static function flatPerInteraction($first_day, $end_day, $brand_id, $invoice_rate_card_id)
    {
        $irc = InvoiceRateCard::where('id', $invoice_rate_card_id)->first();
        $interactions = Interaction::select(
            'interactions.id'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->where(
            'events.brand_id',
            $brand_id
        )->whereIn(
            'interactions.interaction_type_id',
            [
                1, // call_inbound
                2, // call_outbound
            ]
        )->whereBetween(
            'interactions.created_at',
            [
                $first_day,
                $end_day
            ]
        )->where(
            'interactions.interaction_time',
            '>',
            0
        )->whereNull(
            'events.deleted_at'
        )->groupBy(
            'interactions.id'
        )->get();

        return [
            'minutes' => count($interactions),
            'rate' => $irc['live_flat_rate'],
            'total' => (count($interactions) * $irc['live_flat_rate']),
            'level_text' => null,
        ];
    }

    public static function flatPerEvent($first_day, $end_day, $brand_id, $invoice_rate_card_id)
    {
        $irc = InvoiceRateCard::where('id', $invoice_rate_card_id)->first();
        $interactions = Interaction::select(
            'interactions.id'
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->where(
            'events.brand_id',
            $brand_id
        )->whereIn(
            'interactions.interaction_type_id',
            [
                1, // call_inbound
                2, // call_outbound
            ]
        )->whereBetween(
            'interactions.created_at',
            [
                $first_day,
                $end_day
            ]
        )->where(
            'interactions.interaction_time',
            '>',
            0
        )->whereNull(
            'events.deleted_at'
        )->groupBy(
            'interactions.event_id'
        )->get();

        return [
            'minutes' => count($interactions),
            'rate' => $irc['live_flat_rate'],
            'total' => (count($interactions) * $irc['live_flat_rate']),
            'level_text' => null,
        ];
    }

    public static function flatRate($invoice_rate_card_id, $mins)
    {
        $irc = InvoiceRateCard::where('id', $invoice_rate_card_id)->first();

        return [
            'minutes' => $mins,
            'rate' => $irc['live_flat_rate'],
            'total' => ($mins * $irc['live_flat_rate']),
            'level_text' => null
        ];
    }

    public static function slidingScale($invoice_rate_card_id, $mins)
    {
        $irc = InvoiceRateCard::where('id', $invoice_rate_card_id)->first();
        $array = [];

        if (count($irc['levels']) > 0) {
            $levels = json_decode($irc['levels']);

            for ($i = 0; $i < count($levels); $i++) {
                if ($mins < $levels[$i]['level']) {
                    $array = [
                        'minutes' => $mins,
                        'rate' => $levels[$i-1]['rate'],
                        'total' => ($levels[$i-1]['rate'] * $mins),
                        'level_text' => "> ".$levels[$i-1]['level']
                    ];
                    break;
                }
            }
        }

        return $array;
    }

    public static function stepScale($invoice_rate_card_id, $mins)
    {
        $irc = InvoiceRateCard::where('id', $invoice_rate_card_id)->first()->toArray();
        $moving_total = $mins;
        $array = [];

        if (count($irc['levels']) > 0) {
            $levels = $irc['levels'];
            for ($i = 0; $i < count($levels); $i++) {
                if ($levels[$i]['level'] == 0) {
                    $level_text = "0 to ".number_format($levels[$i+1]['level'], 0, ".", ",")." minutes";
                } else {
                    if (isset($levels[$i+1])) {
                        $level_text = number_format($levels[$i]['level'] + 1, 0, ".", ",")." to ".number_format($levels[$i+1]['level'], 0, ".", ",")." minutes";
                    } else {
                        $level_text = number_format($levels[$i]['level'], 0, ".", ",")."+ minutes";
                    }
                }

                if ($moving_total <= 0) {
                    $array[] = array('minutes' => 0, 'rate' => $levels[$i]['rate'], 'total' => 0, 'level_text' => $level_text);
                } else {
                    if (count($levels) == ($i + 1)) {
                        // Final level so just grab the remainder...
                        $array[] = array('minutes' => $moving_total, 'rate' => $levels[$i]['rate'], 'total' => ($moving_total * $levels[$i]['rate']), 'level_text' => $level_text);
                    } else {
                        if ($moving_total <= $levels[$i]['level']) {
                            $array[] = array('minutes' => $moving_total, 'rate' => $levels[$i]['rate'], 'total' => ($moving_total * $levels[$i]['rate']), 'level_text' => $level_text);
                            $moving_total = $moving_total - $levels[$i]['level'];
                        } else {
                            if ($moving_total < $levels[$i+1]['level']) {
                                $array[] = array('minutes' => $moving_total, 'rate' => $levels[$i]['rate'], 'total' => ($moving_total * $levels[$i]['rate']), 'level_text' => $level_text);
                                $moving_total = $moving_total - $levels[$i+1]['level'];
                            } else {
                                $array[] = array('minutes' => ($levels[$i+1]['level']), 'rate' => $levels[$i]['rate'], 'total' => ($levels[$i+1]['level'] * $levels[$i]['rate']), 'level_text' => $level_text);
                                $moving_total = $moving_total - $levels[$i+1]['level'];
                            }
                        }
                    }
                }
            }
        }

        // print_r($array);
        // exit();

        return $array;
    }
}
