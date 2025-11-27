<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * 
 */
trait FindDualFuelMatchesTrait
{
    public function cleanUp($string) 
    {
        return trim(mb_strtoupper($string));
    }
    
    public function find_dual_fuel_matches($products)
    {
        Log::debug(
            'find_dual_fuel_matches was called with count ' . count($products)
        );

        // echo "<pre>";
        // print_r($products);
        
        switch (count($products)) {
        case 1:
            // 1 product is not a dual fuel
            return ['result' => false];
            break;
        case 2:
            if (isset($products[0]) 
                && isset($products[1])
                && $products[0]['event_type_id'] == $products[1]['event_type_id']
            ) {
                // Dual Fuel can't have the same event types
                return ['result' => false];
            }

            if ($this->cleanUp($products[0]['service_address1']) == $this->cleanUp($products[1]['service_address1'])
                && $this->cleanUp($products[0]['service_address2']) == $this->cleanUp($products[1]['service_address2'])
                && $this->cleanUp($products[0]['service_city']) == $this->cleanUp($products[1]['service_city'])
                && $this->cleanUp($products[0]['service_state']) == $this->cleanUp($products[1]['service_state'])
                && $this->cleanUp($products[0]['service_zip']) == $this->cleanUp($products[1]['service_zip'])
            ) {
                // Service address matches.
                // Return the 1st and 2nd product array keys
                // for setting the linked_to
                return [
                    'result' => true, 
                    'results' => [
                        [0, 1],
                    ]
                ];
            }

            break;
        default:
            $addresses = [];
            for ($i = 0; $i < count($products); $i++) {
                $address_hash = md5(
                    $this->cleanUp($products[$i]['service_address1']) .
                    $this->cleanUp($products[$i]['service_address2']) .
                    $this->cleanUp($products[$i]['service_city']) .
                    $this->cleanUp($products[$i]['service_state']) .
                    $this->cleanUp($products[$i]['service_zip'])
                );

                // Let's remember the ID it had in the main array
                $products[$i]['main_array_id'] = $i;
                $addresses[$address_hash][$products[$i]['event_type_id']][] 
                    = $products[$i];
            }
 
            // echo "<pre>";
            // print_r($addresses);

            $dualsets = [];
            foreach ($addresses as $hash) {
                $electric = (isset($hash[1])) ? $hash[1] : null;
                $gas = (isset($hash[2])) ? $hash[2] : null;

                if (count($electric) == count($gas)) {
                    $thearray = $electric;
                    $otherarray = $gas;
                } elseif (count($electric) > count($gas)) {
                    $thearray = $electric;
                    $otherarray = $gas;
                } else {
                    $thearray = $gas;
                    $otherarray = $electric;
                }

                for ($i = 0; $i < count($thearray); $i++) {
                    if (isset($thearray[$i]) && isset($otherarray[$i])) {
                        if ($thearray[$i]['main_array_id'] > $otherarray[$i]['main_array_id']) {
                            $first = $otherarray[$i]['main_array_id'];
                            $second = $thearray[$i]['main_array_id'];
                        } else {
                            $first = $thearray[$i]['main_array_id'];
                            $second = $otherarray[$i]['main_array_id'];
                        }

                        if (isset($first)
                            && strlen(trim($first)) > 0
                            && isset($second)
                            && strlen(trim($second)) > 0
                        ) {
                            $dualsets[] = [$first, $second];
                        }
                    }
                }

                //print_r($dualsets);
            }

            if (count($dualsets) > 0) {
                return [
                    'result' => true, 
                    'results' => $dualsets
                ];
            } else {
                return [
                    'result' => false
                ];
            }

            break;
        }

        return [
            'result' => false
        ];
    }

    public function doDual($event, $array, $data)
    {
        $finalized_products = [];

        foreach ($array as $key => $index) {
            $finalized_products[] = $data['updated_products'][$index];
        }

        foreach ($array as $key => $index) {
            unset($data['updated_products'][$index]);
        }

        $data['finalized_products'][] = $finalized_products;

        return $data;
    }
}
