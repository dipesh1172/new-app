<?php

namespace App\Http\Controllers;

use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\SalesPitch;
use App\Models\Interaction;
use App\Models\Eztpv;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\EmailAddress;
use App\Models\CustomFieldStorage;
use App\Models\BrandUser;
use App\Models\Brand;
use App\Traits\IndraAudit;
use App\Traits\SearchFormTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class SupportController extends Controller
{
    use IndraAudit;
    use SearchFormTrait;

    protected const BRAND_IDS = [
        'energy_bpo' => [
            '09dcd3eb-ecc2-4075-9bf1-a8d3bee2a83f', // Prod
            'c4e9c8d1-a1e7-45a3-ab11-e48452fdcb26'  // Staging
        ]
    ];

    public static function gatherEventDetails(string $event_id, $confCode = null, $brand = null)
    {
        $event = Cache::remember(
            'gather-event-' . $event_id . $confCode,
            60 /* 1 minute */,
            function () use ($event_id, $confCode, $brand) {
                $ev = Event::select(
                    'events.*',
                    'phone_numbers.phone_number',
                    'email_addresses.email_address',
                    'vendors.vendor_label',
                    'vendors.vendor_code',
                    'vendors.grp_id as vendor_grp_id'
                )->with(
                    [
                        'brand',
                        'vendor',
                        'lead',
                        'channel',
                        'language',
                        'identification',
                        'identification.state',
                        'identification.identification_type',
                        'sales_agent',
                        'sales_agent.user',
                        'sales_agent.employee_of',
                        'eztpv:id,eztpv_contract_delivery',
                        'enrollment_intent',
                    ]
                )->leftJoin(
                    'vendors',
                    function ($join) {
                        $join->on(
                            'events.vendor_id',
                            'vendors.vendor_id'
                        )->whereRaw('`vendors`.`brand_id` = `events`.`brand_id`');
                    }
                )->leftJoin(
                    'phone_number_lookup',
                    function ($join) {
                        $join->on(
                            'events.id',
                            'phone_number_lookup.type_id'
                        )->where('phone_number_lookup.phone_number_type_id', 3);
                    }
                )->leftJoin(
                    'phone_numbers',
                    'phone_number_lookup.phone_number_id',
                    'phone_numbers.id'
                )->leftJoin(
                    'email_address_lookup',
                    function ($join) {
                        $join->on(
                            'events.id',
                            'email_address_lookup.type_id'
                        )->where('email_address_lookup.email_address_type_id', 3);
                    }
                )->leftJoin(
                    'email_addresses',
                    'email_address_lookup.email_address_id',
                    'email_addresses.id'
                );
                if ($confCode === null) {
                    $ev = $ev->where(
                        'events.id',
                        $event_id
                    );
                } else {
                    $ev = $ev->where(
                        'events.confirmation_code',
                        $confCode
                    );
                }
                if ($brand !== null) {
                    $ev = $ev->where('events.brand_id', $brand);
                }
                $ev = $ev->whereNull('phone_number_lookup.deleted_at')
                    ->whereNull('email_address_lookup.deleted_at')
                    ->first();
                if ($ev !== null) {
                    $ev = $ev->toArray();
                }

                return $ev;
            }
        );

        if ($confCode !== null) {
            $event_id = $event['id'];
        }

        $customFields = Cache::remember(
            'gather-custom-fields-' . $event_id,
            60 /* 1 minute */,
            function () use ($event_id) {
                return CustomFieldStorage::select(
                    'custom_field_storages.value',
                    'custom_fields.id',
                    'custom_fields.validation_regex',
                    'custom_fields.validation_function',
                    'custom_fields.output_name as name',
                    'custom_field_storages.product_id',
                    'custom_field_storages.event_id'
                )->leftJoin(
                    'custom_fields',
                    'custom_fields.id',
                    'custom_field_storages.custom_field_id'
                )->where(
                    'custom_field_storages.event_id',
                    $event_id
                )->get()->toArray();
            }
        );

        $products = Cache::remember(
            'gather-event-products-' . $event_id,
            60 /* 1 minute */,
            function () use ($event_id) {
                return EventProduct::where('event_id', $event_id)
                    ->with(
                        [
                            'identifiers',
                            'identifiers.utility_account_type',
                            'rate',
                            'rate.rate_currency',
                            'rate.cancel_fee_currency',
                            'rate.intro_cancel_fee_currency',
                            'rate.rate_uom',
                            'rate.product',
                            'rate.product.rate_type',
                            'rate.product.term_type',
                            'rate.product.intro_term_type',
                            'rate.product.transaction_fee_currency',
                            'rate.cancellation_fee_term_type',
                            'event_type',
                            'market',
                            'home_type',
                            'addresses',
                            'addresses.address.state',
                            'utility_supported_fuel',
                            'utility_supported_fuel.utility',
                            'utility_supported_fuel.identifiers',
                            'utility_supported_fuel.utility_fuel_type',
                            //'customFields',
                            //'customFields.customField',
                            'promotion',
                        ]
                    )->get()->toArray();
            }
        );

        $data = [
            'customFields' => $customFields,
            'products' => $products,
            'event' => $event,
        ];

        return $data;
    }

    public static function resolveBooleanCondition(array $code, array $data, int $lang_id = 1, $resuseVarMap = null)
    {
        if (count($code) !== 3) {
            throw new \InvalidArgumentException('Input array must have 3 elements');
        }
        $varMap = null;
        if ($resuseVarMap !== null) {
            $varMap = $resuseVarMap;
        } else {
            $varMap = self::getVariableMap();
        }
        $ops = [
            'EQ' => function ($a, $b) {
                return $a === $b;
            },
            'NE' => function ($a, $b) {
                return $a !== $b;
            },
            'GT' => function ($a, $b) {
                return $a > $b;
            },
            'GTE' => function ($a, $b) {
                return $a >= $b;
            },
            'LT' => function ($a, $b) {
                return $a < $b;
            },
            'LTE' => function ($a, $b) {
                return $a <= $b;
            },
            'AND' => function ($a, $b) {
                return $a && $b;
            },
            'OR' => function ($a, $b) {
                return $a || $b;
            },
            'IN' => function ($a, $b) {
                return in_array($a, $b);
            },
            'NOT_IN' => function ($a, $b) {
                return !in_array($a, $b);
            },
        ];

        $simplify = function ($in) use ($data, $varMap, $lang_id, $ops) {
            if (is_string($in)) {
                if ($in[0] === '^') {
                    $var = str_replace('^', '', $in);

                    return self::getVariableValue($var, $data, $varMap, $lang_id);
                }

                return $in;
            }
            if (is_array($in)) {
                if (count($in) === 3 && is_string($in[1])) {
                    $opNames = array_keys($ops);
                    if (in_array(strtoupper($in[1]), $opNames, true)) {
                        return self::resolveBooleanCondition($in, $data, $lang_id, $varMap);
                    }
                }

                return $in;
            }

            return $in;
        };

        $left = $simplify($code[0]);
        $op = $simplify($code[1]);
        $right = $simplify($code[2]);

        $opNames = array_keys($ops);
        if (is_string($op)) {
            $op = strtoupper($op);
            if (in_array($op, $opNames)) {
                return $ops[$op]($left, $right);
            }
        }

        throw new \InvalidArgumentException('Invalid boolean array: ' . json_encode($code));
    }

    /**
     * @method hydrateVariables
     *
     * @param string   $in_text
     * @param array    $data
     * @param int      $lang_id
     * @param mixed    $varMap  (null)
     * @param callable $filter  (null) callable with signature: function (bool $supportsVar, $variable, $lang_id, $data, $varMap) : string
     *
     * @return string
     */
    public static function hydrateVariables(string $in_text, array $data, int $lang_id, $reuseVarMap = null, $filter = null, $varWrapHtmlTag = null)
    {
        $varMap = $reuseVarMap !== null ? $reuseVarMap : self::getVariableMap();
        $text = trim($in_text);

        $matches = [];
        preg_match_all(
            "/\{\{(.*?)\}\}/",
            $text,
            $matches
        );

        // info(print_r($matches, true));

        if (count($matches) > 0) {
            $values = array_unique($matches[1]);

            foreach ($values as $value) {
                try {
                    $useFilter = false;
                    if ($filter !== null && is_callable($filter)) {
                        $useFilter = $filter(true, $value);
                    }
                    if ($useFilter) {
                        if ($varWrapHtmlTag != null) {
                            $text = str_replace(
                                '{{' . $value . '}}',
                                '<' . $varWrapHtmlTag . '>' . $filter(false, $value, $lang_id, $data, $varMap) . '</' . $varWrapHtmlTag . '>',
                                $text
                            );
                        } else {
                            $text = str_replace(
                                '{{' . $value . '}}',
                                $filter(false, $value, $lang_id, $data, $varMap),
                                $text
                            );
                        }
                    } else {
                        if ($varWrapHtmlTag != null) {
                            $text = str_replace(
                                '{{' . $value . '}}',
                                '<' . $varWrapHtmlTag . '>' . self::getVariableValue($value, $data, $varMap, $lang_id, true) . '</' . $varWrapHtmlTag . '>',
                                $text
                            );
                        } else {
                            $text = str_replace(
                                '{{' . $value . '}}',
                                self::getVariableValue($value, $data, $varMap, $lang_id, true),
                                $text
                            );
                        }
                    }
                } catch (\Exception $e) {
                    if ($varWrapHtmlTag != null) {
                        $text = str_replace('{{' . $value . '}}', '<' . $varWrapHtmlTag . '>' . ' , MISSING VARIABLE [' . $value . '], ' . '</' . $varWrapHtmlTag . '>', $text);
                    } else {
                        $text = str_replace('{{' . $value . '}}', ' , MISSING VARIABLE [' . $value . '], ', $text);
                    }
                }
            }
        }

        return $text;
    }

    public static function getVariableValue(string $var, array $data, array $varMap, int $lang_id = 1, bool $rethrow = false)
    {
        try {
            $ret = self::mapVarToVarMap($var, $varMap);
            if ($ret['return'] === false) {
                try {
                    $ret = self::mapVarToData($var, $data);

                    return $ret;
                } catch (\Exception $e) {
                    throw new \Exception('Unsupported variable: ' . $var);
                }
            }
            if (is_string($ret['return'])) {
                try {
                    return self::mapVarToData($ret['return'], $data);
                } catch (\Exception $e) {
                    return self::getVariableValue($ret['return'], $data, $varMap, $lang_id, $rethrow);
                }
            }
            if (is_callable($ret['return'])) {
                return $ret['return']($data, $lang_id, $ret['flags']);
            }
            throw new \Exception('Unknown return type', [$ret]);
        } catch (\Exception $e) {
            if (!$rethrow) {
                info('Error during variable lookup', [$e]);

                return null;
            }
            throw $e;
        }
    }

    private static function mapVarToVarMap(string $var, array $varMap)
    {
        $ret = [
            'return' => null,
            'flags' => null,
        ];

        $locator = explode('.', $var);
        $base = $varMap;
        $found = false;

        while ($found === false) {
            $next = array_shift($locator);
            if (isset($base[$next])) {
                if (is_array($base[$next])) {
                    $base = $base[$next];
                } else {
                    $found = $base[$next];
                    break;
                }
            } else {
                break;
            }
        }

        $ret['return'] = $found;
        $ret['flags'] = $locator;

        return $ret;
    }

    private static function mapVarToData(string $var, array $data)
    {
        $locator = explode('.', $var);
        $base = $data;
        $found = false;

        while ($found === false) {
            $next = array_shift($locator);
            if (isset($base[$next])) {
                if (is_array($base[$next])) {
                    $base = $base[$next];
                } else {
                    return $base[$next];
                }
            } else {
                break;
            }
        }

        throw new \Exception('Item not found ' . $var);
    }

    public static function getVariableMap()
    {
        $getSelectedProduct = function ($data, $flags, $selectAtBegin = true, $search = false, $supportDualFuel = false) {
            if (empty($data['products'])) {
                return null;
            }
            if (!isset($data['selectedProduct'])) {
                return $data['products'][0];
            }
            if (!isset($data['selectedProduct']['dualFuel'])) {
                return $data['selectedProduct'];
            }

            if (empty($flags) && !empty($data['selectedProduct']['electric']) && !$supportDualFuel) {
                return $data['selectedProduct']['electric'];
            }

            if ($selectAtBegin && $search === false && !empty($flags) && isset($data['selectedProduct'][$flags[0]])) {
                return $data['selectedProduct'][$flags[0]];
            }
            if (!$selectAtBegin && $search === false && !empty($flags) && isset($data['selectedProduct'][$flags[count($flags) - 1]])) {
                return $data['selectedProduct'][$flags[count($flags) - 1]];
            }
            if ($search && !empty($flags)) {
                foreach ($flags as $flag) {
                    if ($flag === 'electric' || $flag === 'gas') {
                        return $data['selectedProduct'][$flag];
                    }
                }
            }

            if ($supportDualFuel && !empty($data['selectedProduct'])) {
                return $data['selectedProduct'];
            }

            return $data['products'][0];
        };

        $nameChanger = function ($name) {
            if (!is_string($name)) {
                return null;
            }
            $iname = strtolower($name);
            $iname = str_replace(' ', '_', $iname);

            return preg_replace('/[^a-z|_]/', '', $iname);
        };

        $defaultAccountTypes = Cache::remember(
            'default-ac-types',
            (60 * 5) /* 5 minutes */,
            function () {
                return json_decode(json_encode(DB::table('utility_account_types')
                    ->select('id', 'account_type', 'utility_account_number_type_id as type_id')
                    ->get()
                    ->toArray()), true);
            }
        );

        return [
            'custom' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                $product = $getSelectedProduct($data, $flags);
                if (count($flags) === 0) {
                    return null;
                }

                foreach ($data['customFields'] as $customField) {
                    if ($customField['name'] === $flags[0]) {
                        if ($customField['product_id'] === null || $customField['product_id'] === $product['id']) {
                            return $customField['value'];
                        }
                    }
                }

                return null;
            },
            'client' => [
                'name' => function ($data, $lang_id, $flags) {
                    return $data['event']['brand']['name'];
                },
                'legal_name' => function ($data, $lang_id, $flags) {
                    if ($data['event']['brand']['legal_name'] !== null && $data['event']['brand']['legal_name'] !== '') {
                        return $data['event']['brand']['legal_name'];
                    }

                    return $data['event']['brand']['name'];
                },
                'vendor' => 'event.vendor.name',
                'vendor_label' => 'event.vendor_label',
                'vendor_code' => 'event.vendor_code',
                'grp_id' => 'event.vendor_grp_id',
                'service_number' => 'client.service_phone',
                'service_phone' => function ($data, $lang_id, $flags) {
                    $ret = $data['event']['brand']['service_number'];
                    if (is_string($ret)) {
                        return FormatPhoneNumber($ret);
                    }

                    return $ret;
                },
                'service_phone_raw' => function ($data, $lang_id, $flags) {
                    return $data['event']['brand']['service_number'];
                },
            ],
            'account' => [
                'product_count' => 'event.product_count',
                'type' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['event_type']['event_type'];
                },
                'bill_name' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $out = '';
                    $out .= $product['bill_first_name'];
                    if ($product['bill_middle_name'] !== null) {
                        $out .= ' ' . $product['bill_middle_name'];
                    }
                    $out .= ' ' . $product['bill_last_name'];

                    return $out;
                },
                'bill_address' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $service = null;
                    $billing = null;
                    foreach ($product['addresses'] as $address) {
                        if ($address['id_type'] === 'e_p:service') {
                            $service = $address['address'];
                        }
                        if ($address['id_type'] === 'e_p:billing') {
                            $billing = $address['address'];
                        }
                    }
                    $using = $billing;
                    if ($using === null) {
                        $using = $service;
                    }
                    if ($using !== null) {
                        $out = '';
                        $out .= $using['line_1'];
                        if ($using['line_2'] !== null) {
                            $out .= ' ' . $using['line_1'];
                        }
                        $out .= ', ' . $using['city'];
                        $out .= ', ' . $using['state']['name'];
                        $out .= ', ' . $using['zip'];

                        return $out;
                    }

                    return null;
                },
                'bill_address_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $service = null;
                    $billing = null;
                    foreach ($product['addresses'] as $address) {
                        if ($address['id_type'] === 'e_p:service') {
                            $service = $address['address'];
                        }
                        if ($address['id_type'] === 'e_p:billing') {
                            $billing = $address['address'];
                        }
                    }
                    if ($billing !== null) {
                        return $billing;
                    }

                    return $service;
                },
                'company_name' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['company_name'];
                },
                'service_address' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    foreach ($product['addresses'] as $address) {
                        if ($address['id_type'] === 'e_p:service') {
                            $out = '';
                            $out .= $address['address']['line_1'];
                            if ($address['address']['line_2'] !== null) {
                                $out .= ' ' . $address['address']['line_1'];
                            }
                            $out .= ', ' . $address['address']['city'];
                            $out .= ', ' . $address['address']['state']['name'];
                            $out .= ', ' . $address['address']['zip'];

                            return $out;
                        }
                    }

                    return null;
                },
                'service_address_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    foreach ($product['addresses'] as $address) {
                        if ($address['id_type'] === 'e_p:service') {
                            return $address['address'];
                        }
                    }

                    return null;
                },
                'state_abbr' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    foreach ($product['addresses'] as $address) {
                        if ($address['id_type'] === 'e_p:service') {
                            return $address['address']['state_province'];
                        }
                    }

                    return null;
                },
                'state' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    foreach ($product['addresses'] as $address) {
                        if ($address['id_type'] === 'e_p:service') {
                            return $address['address']['state']['name'];
                        }
                    }

                    return null;
                },
                'number_location' => function ($data, $lang_id, $flags) use ($getSelectedProduct, $nameChanger, $defaultAccountTypes) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $type = null;
                    if (count($product['identifiers']) === 1) {
                        $type = $product['identifiers'][0]['utility_account_type_id'];
                    }

                    if ($type !== null) {
                        $wants = count($flags) > 1 ? $nameChanger($flags[1]) : '_primary_';
                        foreach ($product['identifiers'] as $identifier) {
                            if ($wants === '_primary_' && $identifier['utility_account_number_type_id'] === 1) {
                                $type = $identifier['utility_account_type_id'];
                                break;
                            } else {
                                if ($wants === $nameChanger($identifier['utility_account_type']['account_type'])) {
                                    $type = $identifier['utility_account_type_id'];
                                    break;
                                }
                            }
                        }
                    }
                    if ($type !== null) {
                        foreach ($product['utility_supported_fuel']['identifiers'] as $identifier) {
                            if ($type === $identifier['utility_account_type_id']) {
                                if ($identifier['bill_location'] !== null) {
                                    return $identifier['bill_location'][$lang_id === 1 ? 'en' : 'sp'];
                                }
                            }
                        }
                    }

                    return null;
                },
                'number' => function ($data, $lang_id, $flags) use ($getSelectedProduct, $nameChanger, $defaultAccountTypes) {
                    $product = $getSelectedProduct($data, $flags);

                    if ($product === null) {
                        return 'null';
                    }

                    if (count($flags) > 1) {
                        $wants = $nameChanger($flags[1]);
                    } else {
                        if (!empty($flags)) {
                            $wants = $nameChanger($flags[0]);
                        } else {
                            $wants = '_primary_';
                        }
                    }


                    foreach ($product['identifiers'] as $identifier) {
                        $ant_id = null;
                        foreach ($product['utility_supported_fuel']['identifiers'] as $usfi) {
                            if ($identifier['utility_account_type_id'] == $usfi['utility_account_type_id']) {
                                $ant_id = $usfi['utility_account_number_type_id'];
                            }
                        }
                        if ($wants === '_primary_' && ($ant_id === 1 || count($product['identifiers']) == 1)) {
                            return $identifier['identifier'];
                        } else {
                            if ($wants === $nameChanger($identifier['utility_account_type']['account_type'])) {
                                return $identifier['identifier'];
                            }
                            if (
                                $wants === '_primary_name_'
                                && ($ant_id === 1
                                    || count($product['identifiers']) == 1)
                            ) {
                                return $identifier['utility_account_type']['account_type'];
                            }
                        }
                    }

                    if (count($product['identifiers']) === 1) {
                        return $product['identifiers'][0]['identifier'];
                    }

                    return 'null';
                },
                'number_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['identifiers'];
                },
                'address_table' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    $service = null;
                    $billing = null;
                    $addrAreTheSame = false;

                    foreach ($product['addresses'] as $address) {
                        if ($address['id_type'] === 'e_p:service') {
                            $service = $address['address'];
                        }
                        if ($address['id_type'] === 'e_p:billing') {
                            $billing = $address['address'];
                        }
                    }

                    if (json_encode($service) === json_encode($billing) || ($service !== null && $billing === null)) {
                        $addrAreTheSame = true;
                    }

                    if ($service === null) {
                        return null;
                    }

                    switch ($lang_id) {
                        case 1:
                        case '1':
                            $a_both = 'Service and Billing Address';
                            $a_service = 'Service Address';
                            $a_billing = 'Billing Address';
                            break;

                        case 2:
                        case '2':
                            $a_both = 'Dirección de Servicio y Facturación';
                            $a_service = 'Dirección de Servicio';
                            $a_billing = 'Dirección de Envio';
                            break;

                        default:
                            return null;
                    }

                    $combinedTemplate = '<table class="table table-bordered"><thead><tr><th colspan="2">{a_both}</th></tr></thead><tbody><tr><td colspan="2">{service}</td></tr></tbody></table>';
                    $defaultTemplate = '<table class="table table-bordered"><thead><tr><th>{a_service}</th><th>{a_billing}</th></tr></thead><tbody><tr><td>{service}</td><td>{billing}</td></tr></tbody></table>';

                    $template = $addrAreTheSame ? $combinedTemplate : $defaultTemplate;

                    $basicFormatAddress = function ($address) {
                        if (!empty($address)) {
                            return $address['line_1'] . (empty($address['line_2']) ? '' : ' ' . $address['line_2']) . '<br>' . $address['city'] . ' ' . $address['state_province'] . ' ' . $address['zip'];
                        }
                        return '';
                    };

                    $out = $template;
                    if ($addrAreTheSame) {
                        $out = str_replace('{a_both}', $a_both, $out);
                    } else {
                        $out = str_replace('{a_service}', $a_service, $out);
                        $out = str_replace('{a_billing}', $a_billing, $out);
                    }
                    $out = str_replace('{service}', $basicFormatAddress($service), $out);
                    $out = str_replace('{billing}', $basicFormatAddress($billing), $out);

                    return trim($out);
                },
                'identifier_table' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags, true, false, true);
                    if ($product === null) {
                        return null;
                    }

                    $template = '<table class="table table-bordered"><tbody>{body}</tbody></table>';
                    $itemTemplate = '<tr><td>{fuel}</td><td>{ident} ({ident_type})</td></tr>';

                    $identProcessor = function ($p) use ($itemTemplate) {
                        $body = '';
                        foreach ($p['identifiers'] as $ident) {
                            $ibody = $itemTemplate;
                            $ibody = str_replace('{fuel}', $p['utility_supported_fuel']['utility_fuel_type']['utility_type'], $ibody);
                            $ibody = str_replace('{ident}', $ident['identifier'], $ibody);
                            $ibody = str_replace('{ident_type}', $ident['utility_account_type']['account_type'], $ibody);
                            $body .= $ibody;
                        }
                        return $body;
                    };

                    if (isset($product['dualFuel']) && $product['dualFuel']) {
                        $ebody = $identProcessor($product['electric']);
                        $gbody = $identProcessor($product['gas']);

                        return str_replace('{body}', $ebody . $gbody, $template);
                    } else {
                        $body = $identProcessor($product);

                        return str_replace('{body}', $body, $template);
                    }
                },
            ],
            'date' => function ($data, $lang_id, $flags) {
                if (is_array($flags) && count($flags) > 0) {
                    if (in_array('voice', $flags)) {
                        return now()->format('l, F j, Y');
                    }
                }
                return now()->format('m-d-Y');
            },
            'time' => function ($data, $lang_id, $flags) {
                $tz = 'America/New_York';
                if (is_array($flags) && count($flags) > 0) {
                    $tz = $flags[0];
                }
                $t = \Carbon\Carbon::now($tz);

                return $t->format('g:i a');
            },
            'day' => function ($data, $lang_id, $flags) {
                return now()->day;
            },
            'next-month' => function ($data, $lang_id, $flags) {
                $t = now()->addMonth();
                $t->day = 1;

                return $t->format('Y-m-d');
            },
            'agent' => false,
            'event' => [
                'enrollment_intent' => function ($data, $lang_id, $flags) {
                    if ($data['event']['enrollment_intent'] !== null) {
                        return $data['event']['enrollment_intent']['enrollment_intent'];
                    }
                    return null;
                },
                'product_count' => function ($data, $lang_id, $flags) {
                    return count($data['products']);
                },
                'confirmation' => 'event.confirmation_code',
                'date' => 'event.created_at',
                'channel' => function ($data, $lang_id, $flags) {
                    switch ($lang_id) {
                        case 2: //spanish
                            switch ($data['event']['channel_id']) {
                                default:
                                case 1:
                                    return 'Door to Door';
                                case 2:
                                    return 'Telemarketing';
                                case 3:
                                    return 'Retail';
                            }
                            break;

                        default:
                        case 1:
                            switch ($data['event']['channel_id']) {
                                default:
                                case 1:
                                    return 'Door to Door';
                                case 2:
                                    return 'Telemarketing';
                                case 3:
                                    return 'Retail';
                            }
                    }
                },
                'channel_raw' => 'event.channel_id',
                'market' => function ($data, $lang_id, $flags) {
                    if (count($data['products']) > 0) {
                        switch ($lang_id) {
                            case 2: //spanish
                                switch ($data['products'][0]['market_id']) {
                                    default:
                                    case 1:
                                        return 'Residential';
                                    case 2:
                                        return 'Commercial';
                                }
                                break;

                            default:
                            case 1:
                                switch ($data['products'][0]['market_id']) {
                                    default:
                                    case 1:
                                        return 'Residential';
                                    case 2:
                                        return 'Commercial';
                                }
                        }
                    } else {
                        return null;
                    }
                },
                'market_raw' => function ($data, $lang_id, $flags) {
                    if (count($data['products']) > 0) {
                        return $data['products'][0]['market_id'];
                    }
                    return null;
                },
                'is_ez' => function ($data, $lang_id, $flags) {
                    if ($data['event']['eztpv_id'] !== null) {
                        return 'y';
                    }

                    return 'n';
                },
                'is_eztpv' => function ($data, $lang_id, $flags) {
                    if ($data['event']['eztpv_id'] !== null) {
                        return true;
                    }

                    return false;
                },
                'contract_delivery' => function ($data, $lang_id, $flags) {
                    if (isset($data['event']['eztpv'])) {
                        if (isset($data['event']['eztpv']['eztpv_contract_delivery'])) {
                            return $data['event']['eztpv']['eztpv_contract_delivery'];
                        }
                    }

                    return null;
                },
                'id_provided' => function ($data, $lang_id, $flags) {
                    if (isset($data['event']['identification'])) {
                        if ($data['event']['identification'] !== null) {
                            return true;
                        }
                    }

                    return false;
                },
                'id_type' => 'event.identification.identification_type.name',
                'id_name' => 'event.identification.named_person',
                'id_number' => 'event.identification.control_number',
                'id_state' => 'event.identification.state.name',
                'id_state_id' => 'event.identification.state_id',
            ],
            'product' => [
                'rate' => 'product.amount',
                'green_percentage' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['product']['green_percentage'] !== null ? $product['rate']['product']['green_percentage'] : 0;
                },
                'name' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['product']['name'];
                },
                'service_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $fee = $product['rate']['product']['service_fee'];
                    if ($fee === null) {
                        return 0;
                    }

                    return $fee;
                },
                'intro_service_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $fee = $product['rate']['product']['intro_service_fee'];
                    if ($fee === null) {
                        return 0;
                    }

                    return $fee;
                },
                'amount' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    //return rtrim(sprintf('%01.2f', $product['rate']['rate_amount']), '0');
                    return $product['rate']['rate_amount'];
                },
                'amount_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['rate_amount'];
                },
                'amount_in_cents' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $currency = $product['rate']['rate_currency_id'];
                    if ($currency == 2) {
                        return $product['rate']['rate_amount'] * 100;
                    }

                    return $product['rate']['rate_amount'];
                },
                'intro_amount' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $amt = $product['rate']['intro_rate_amount'];
                    if ($amt === null) {
                        return 0;
                    }

                    //return sprintf('%01.2f', $amt);
                    return $amt;
                },
                'intro_amount_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $amt = $product['rate']['intro_rate_amount'];
                    if ($amt === null) {
                        return 0;
                    }

                    return $amt;
                },
                'fuel' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if (isset($data['selectedProduct']) && isset($data['selectedProduct']['dualFuel']) && $data['selectedProduct']['dualFuel']) {
                        switch ($lang_id) {
                            default:
                            case 1:
                                return 'dual';

                            case 2:
                                return 'doble';
                        }
                    }
                    $fuelId = $product['utility_supported_fuel']['utility_fuel_type_id'];
                    switch ($lang_id) {
                        default:
                        case 1:
                            switch ($fuelId) {
                                default:
                                case 1:
                                    return 'Electric';
                                case 2:
                                    return 'Natural Gas';
                            }

                            // no break
                        case 2:
                            switch ($fuelId) {
                                default:
                                case 1:
                                    return 'electricidad';
                                case 2:
                                    return 'gas natural';
                            }
                    }
                },
                'fuel_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if (isset($data['selectedProduct']) && isset($data['selectedProduct']['dualFuel']) && $data['selectedProduct']['dualFuel']) {
                        return 'dual';
                    }

                    return strtolower($product['utility_supported_fuel']['utility_fuel_type']['utility_type']);
                },
                'currency' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($lang_id === 1) {
                        return $product['rate']['rate_currency']['currency'];
                    }
                    switch ($product['rate']['rate_currency']['currency']) {
                        default:
                        case 'cents':
                            return 'centavos';
                        case 'dollars':
                            return 'dólares';
                    }
                },
                'currency_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['rate_currency']['currency'];
                },
                'uom' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($lang_id === 1) {
                        return $product['rate']['rate_uom']['uom'];
                    }
                    switch ($product['rate']['rate_uom']['uom']) {
                        default:
                        case 'unknown':
                            return 'desconocido';
                        case 'therm':
                            return 'termia';
                        case 'kwh':
                            return 'kilovatios-hora';
                        case 'unknown':
                            return 'desconocido';
                        case 'ccf':
                            return 'centum pies cúbicos';
                        case 'mwhs':
                            return 'megavatios-hora';
                        case 'gj':
                            return 'gigajoules';
                        case 'mcf':
                            return 'mcf';
                        case 'day':
                            return 'dia';
                    }
                },
                'uom_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['rate_uom']['uom'];
                },
                'term' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $term = $product['rate']['product']['term'];
                    if ($term === null || $term === 0) {
                        switch ($lang_id) {
                            default:
                            case 1:
                                return 'month to month';

                            case 2:
                                return 'mes a mes';
                        }
                    }

                    return $term;
                },
                'term_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['product']['term'];
                },
                'term_type' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $termType = $lang_id === 1 ? 'month' : 'mes';
                    if ($product['rate']['product']['term_type'] !== null) {
                        $termType = $product['rate']['product']['term_type']['term_type'];
                        if ($lang_id === 2) {
                            switch ($termType) {
                                case 'day':
                                    $termType = 'día';
                                    break;

                                case 'week':
                                    $termType = 'semana';
                                    break;

                                case 'month':
                                    $termType = 'mes';
                                    break;

                                case 'year':
                                    $termType = 'año';
                                    break;

                                default:
                                    break;
                            }
                        }
                    }
                    if ($product['rate']['product']['term'] > 1) {
                        if ($termType === 'mes') {
                            $termType = 'meses';
                        } else {
                            $termType .= 's';
                        }
                    }

                    return $termType;
                },
                'term_type_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($product['rate']['product']['term_type'] === null) {
                        return null;
                    }

                    return $product['rate']['product']['term_type']['term_type'];
                },
                'intro_term' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($product['rate']['product']['intro_term'] === null) {
                        return 0;
                    }

                    return $product['rate']['product']['intro_term'];
                },
                'intro_term_type' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $termType = $lang_id === 1 ? 'month' : 'mes';
                    if ($product['rate']['product']['intro_term_type'] !== null) {
                        $termType = $product['rate']['product']['intro_term_type']['term_type'];
                        if ($lang_id === 2) {
                            switch ($termType) {
                                case 'day':
                                    $termType = 'día';
                                    break;

                                case 'week':
                                    $termType = 'semana';
                                    break;

                                case 'month':
                                    $termType = 'mes';
                                    break;

                                case 'year':
                                    $termType = 'año';
                                    break;

                                default:
                                    break;
                            }
                        }
                    }
                    if ($product['rate']['product']['intro_term'] > 1) {
                        if ($termType === 'mes') {
                            $termType = 'meses';
                        } else {
                            $termType .= 's';
                        }
                    }

                    return $termType;
                },
                'intro_term_type_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($product['rate']['product']['intro_term_type'] === null) {
                        return null;
                    }

                    return $product['rate']['product']['intro_term_type']['term_type'];
                },
                'type' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($lang_id === 1) {
                        return $product['rate']['product']['rate_type']['rate_type'];
                    }
                    switch ($product['rate']['product']['rate_type']['rate_type']) {
                        case 'fixed':
                            return 'fijado';

                        case 'tiered':
                            return 'escalonado';

                        default:
                            return $product['rate']['product']['rate_type']['rate_type'];
                    }
                },
                'type_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if (isset($data['selectedProduct']) && isset($data['selectedProduct']['dualFuel']) && $data['selectedProduct']['dualFuel']) {
                        return $data['selectedProduct']['electric']['rate']['product']['rate_type']['rate_type'] . '|' . $data['selectedProduct']['gas']['rate']['product']['rate_type']['rate_type'];
                    }

                    return $product['rate']['product']['rate_type']['rate_type'];
                },
                'cancellation_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if (isset($data['selectedProduct']) && isset($data['selectedProduct']['dualFuel']) && $data['selectedProduct']['dualFuel']) {
                        // dual fuel
                        $electric = $data['selectedProduct']['electric']['rate']['cancellation_fee'];
                        if ($electric === null) {
                            $electric = 0;
                        }
                        $gas = $data['selectedProduct']['gas']['rate']['cancellation_fee'];
                        if ($gas === null) {
                            $gas = 0;
                        }

                        return $electric + $gas;
                    }
                    if ($product['rate']['cancellation_fee'] !== null) {
                        return $product['rate']['cancellation_fee'];
                    }

                    return 0;
                },
                'daily_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($product['rate']['product']['daily_fee'] !== null) {
                        return sprintf('%01.2f', $product['rate']['product']['daily_fee']);
                    }

                    return 0;
                },
                'intro_daily_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($product['rate']['product']['intro_daily_fee'] !== null) {
                        return sprintf('%01.2f', $product['rate']['product']['intro_daily_fee']);
                    }

                    return 0;
                },
                'has_promo' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['brand_promotion_id'] !== null;
                },
                'has_rate_scripting' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return false;
                    }

                    if ($product['rate'] !== null) {
                        if (isset($product['rate']['scripting']) && $product['rate']['scripting'] !== null && $product['rate']['scripting'] !== '') {
                            try {
                                $parsed = json_decode($product['rate']['scripting'], true);
                                if (isset($parsed['english']) && is_array($parsed['english']) && count($parsed['english']) > 0) {
                                    return true;
                                }
                            } catch (\Exception $e) {
                                // pass
                            }
                        }
                        if (isset($product['rate']['product']['scripting']) && $product['rate']['product']['scripting'] !== null && $product['rate']['product']['scripting'] !== '') {
                            try {
                                $parsed = json_decode($product['rate']['product']['scripting'], true);
                                if (isset($parsed['english']) && is_array($parsed['english']) && count($parsed['english']) > 0) {
                                    return true;
                                }
                            } catch (\Exception $e) {
                                // pass
                            }
                        }
                    }

                    return false;
                },
                '__rate_scripting' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    if ($product['rate'] !== null) {
                        if (isset($product['rate']['scripting']) && $product['rate']['scripting'] !== null && $product['rate']['scripting'] !== '') {
                            try {
                                $parsed = json_decode($product['rate']['scripting'], true);
                                if (isset($parsed['english']) && is_array($parsed['english']) && count($parsed['english']) > 0) {
                                    return $product['rate']['scripting'];
                                }
                            } catch (\Exception $e) {
                                // pass
                            }
                        }
                        if (isset($product['rate']['product']['scripting']) && $product['rate']['product']['scripting'] !== null && $product['rate']['product']['scripting'] !== '') {
                            try {
                                $parsed = json_decode($product['rate']['product']['scripting'], true);
                                if (isset($parsed['english']) && is_array($parsed['english']) && count($parsed['english']) > 0) {
                                    return $product['rate']['product']['scripting'];
                                }
                            } catch (\Exception $e) {
                                // pass
                            }
                        }
                    }

                    return null;
                },
                'has_rescission_scripting' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return false;
                    }

                    if ($product['rate'] !== null) {
                        if (isset($product['rate']['rescission']) && $product['rate']['rescission'] !== null && $product['rate']['rescission'] !== '') {
                            try {
                                $parsed = json_decode($product['rate']['rescission'], true);
                                if (isset($parsed['english']) && is_array($parsed['english']) && count($parsed['english']) > 0) {
                                    return true;
                                }
                            } catch (\Exception $e) {
                                // pass
                            }
                        }
                        if (isset($product['rate']['product']['rescission']) && $product['rate']['product']['rescission'] !== null && $product['rate']['product']['rescission'] !== '') {
                            try {
                                $parsed = json_decode($product['rate']['product']['rescission'], true);
                                if (isset($parsed['english']) && is_array($parsed['english']) && count($parsed['english']) > 0) {
                                    return true;
                                }
                            } catch (\Exception $e) {
                                // pass
                            }
                        }
                    }

                    return false;
                },
                '__rescission_scripting' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    if ($product['rate'] !== null) {
                        if (isset($product['rate']['rescission']) && $product['rate']['rescission'] !== null && $product['rate']['rescission'] !== '') {
                            try {
                                $parsed = json_decode($product['rate']['rescission'], true);
                                if (isset($parsed['english']) && is_array($parsed['english']) && count($parsed['english']) > 0) {
                                    return $product['rate']['rescission'];
                                }
                            } catch (\Exception $e) {
                                // pass
                            }
                        }
                        if (isset($product['rate']['product']['rescission']) && $product['rate']['product']['rescission'] !== null && $product['rate']['product']['rescission'] !== '') {
                            try {
                                $parsed = json_decode($product['rate']['product']['rescission'], true);
                                if (isset($parsed['english']) && is_array($parsed['english']) && count($parsed['english']) > 0) {
                                    return $product['rate']['product']['rescission'];
                                }
                            } catch (\Exception $e) {
                                // pass
                            }
                        }
                    }

                    return null;
                },
                'promo_code' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['promotion']['promotion_code'];
                },
                'promo_name' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    if ($lang_id == 2) {
                        return $product['promotion']['name_spanish'];
                    }

                    return $product['promotion']['name'];
                },
                'promo_type' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['promotion']['promotion_type'];
                },
                'promo_key' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['promotion']['promotion_key'];
                },
                'promo_reward' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['promotion']['reward'];
                },
                'promo_text' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    switch ($lang_id) {
                        default:
                        case 1:
                            return $product['promotion']['promo_text_english'];

                        case 2:
                            return $product['promotion']['promo_text_spanish'];
                    }
                },
                'start_month' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($product['rate']['start_month'] === null) {
                        return 'default';
                    }

                    return $product['rate']['start_month'];
                },
                'custom_data_1' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['custom_data_1'];
                },
                'custom_data_2' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['custom_data_2'];
                },
                'custom_data_3' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['custom_data_3'];
                },
                'custom_data_4' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['custom_data_4'];
                },
                'custom_data_5' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['custom_data_5'];
                },
                'program_code' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['program_code'];
                },
                'currency' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($lang_id === 1) {
                        return $product['rate']['rate_currency']['currency'];
                    }
                    switch ($product['rate']['rate_currency']['currency']) {
                        default:
                        case 'cents':
                            return 'centavos';
                        case 'dollars':
                            return 'dólares';
                    }
                },
                'remaining_term' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    // the js version handles if the terms aren't of the same type, i.e. 1 is months and 1 is weeks but i think this is not needed
                    return $product['rate']['product']['term'] - $product['rate']['product']['intro_term'];
                },
                'admin_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['admin_fee'];
                },
                'monthly_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['product']['monthly_fee'];
                },
                'transaction_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['product']['transaction_fee'];
                },
                'transaction_fee_currency' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['product']['transaction_fee_currency']['currency'];
                },
                'prepaid' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['product']['prepaid'] === 1;
                },
                'enroll_date' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['enroll_date'];
                },
                'end_date' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['date_to'];
                },
                'estimated_total_cost' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $rateAmount = $product['rate']['rate_amount'];
                    $currency = $product['rate']['rate_currency_id'];
                    if ($currency === 1) {
                        $rateAmount /= 100;
                    }
                    $monthlyFee = $product['rate']['rate_monthly_fee'] !== null ? $product['rate']['rate_monthly_fee'] : 0;
                    $level = 500;
                    if (count($flags) === 1 && ($flags[0] !== 'electric' && $flags[0] !== 'gas')) {
                        $level = intval($flags[0], 10);
                    }
                    if (count($flags) === 2 && ($flags[1] !== 'electric' && $flags[1] !== 'gas')) {
                        $level = intval($flags[1], 10);
                    }

                    return sprintf('%01.2f', (($rateAmount * $level) + $monthlyFee));
                },
                'estimated_total_cost_x100' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $rateAmount = $product['rate']['rate_amount'];
                    $currency = $product['rate']['rate_currency_id'];
                    if ($currency === 1) {
                        $rateAmount /= 100;
                    }
                    $monthlyFee = $product['rate']['rate_monthly_fee'] !== null ? $product['rate']['rate_monthly_fee'] : 0;
                    $level = 500;
                    if (count($flags) === 1 && ($flags[0] !== 'electric' && $flags[0] !== 'gas')) {
                        $level = intval($flags[0], 10);
                    }
                    if (count($flags) === 2 && ($flags[1] !== 'electric' && $flags[1] !== 'gas')) {
                        $level = intval($flags[1], 10);
                    }

                    return sprintf('%01.2f', (($rateAmount * $level) + $monthlyFee) * 100);
                },
                'intro_estimated_total_cost' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $rateAmount = $product['rate']['intro_rate_amount'];
                    $currency = $product['rate']['rate_currency_id'];
                    if ($currency === 1) {
                        $rateAmount /= 100;
                    }
                    $monthlyFee = $product['rate']['rate_monthly_fee'] !== null ? $product['rate']['rate_monthly_fee'] : 0;
                    $level = 500;
                    if (count($flags) === 1 && ($flags[0] !== 'electric' && $flags[0] !== 'gas')) {
                        $level = intval($flags[0], 10);
                    }
                    if (count($flags) === 2 && ($flags[1] !== 'electric' && $flags[1] !== 'gas')) {
                        $level = intval($flags[1], 10);
                    }

                    return sprintf('%01.2f', (($rateAmount * $level) + $monthlyFee));
                },
                'intro_estimated_total_cost_x100' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $rateAmount = $product['rate']['intro_rate_amount'];
                    $currency = $product['rate']['rate_currency_id'];
                    if ($currency === 1) {
                        $rateAmount /= 100;
                    }
                    $monthlyFee = $product['rate']['rate_monthly_fee'] !== null ? $product['rate']['rate_monthly_fee'] : 0;
                    $level = 500;
                    if (count($flags) === 1 && ($flags[0] !== 'electric' && $flags[0] !== 'gas')) {
                        $level = intval($flags[0], 10);
                    }
                    if (count($flags) === 2 && ($flags[1] !== 'electric' && $flags[1] !== 'gas')) {
                        $level = intval($flags[1], 10);
                    }

                    return sprintf('%01.2f', (($rateAmount * $level) + $monthlyFee) * 100);
                },
                'estimated_cost' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $rateAmount = $product['rate']['rate_amount'];
                    $currency = $product['rate']['rate_currency_id'];
                    if ($currency === 1) {
                        $rateAmount /= 100;
                    }
                    $monthlyFee = $product['rate']['rate_monthly_fee'] !== null ? $product['rate']['rate_monthly_fee'] : 0;
                    $level = 500;
                    if (count($flags) === 1 && ($flags[0] !== 'electric' && $flags[0] !== 'gas')) {
                        $level = intval($flags[0], 10);
                    }
                    if (count($flags) === 2 && ($flags[1] !== 'electric' && $flags[1] !== 'gas')) {
                        $level = intval($flags[1], 10);
                    }

                    return sprintf('%01.2f', ((($rateAmount * $level) + $monthlyFee) / $level));
                },
                'estimated_cost_x100' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    $rateAmount = $product['rate']['rate_amount'];
                    $currency = $product['rate']['rate_currency_id'];
                    if ($currency === 1) {
                        $rateAmount /= 100;
                    }
                    $monthlyFee = $product['rate']['rate_monthly_fee'] !== null ? $product['rate']['rate_monthly_fee'] : 0;
                    $level = 500;
                    if (count($flags) === 1 && ($flags[0] !== 'electric' && $flags[0] !== 'gas')) {
                        $level = intval($flags[0], 10);
                    }
                    if (count($flags) === 2 && ($flags[1] !== 'electric' && $flags[1] !== 'gas')) {
                        $level = intval($flags[1], 10);
                    }

                    return sprintf('%01.2f', ((($rateAmount * $level) + $monthlyFee) / $level) * 100);
                },
                'extra' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null || empty($flags)) {
                        return null;
                    }

                    if (!empty($product['rate']['extra_fields'])) {
                        for ($i = 0, $len = count($product['rate']['extra_fields']); $i < $len; $i += 1) {
                            if ($product['rate']['extra_fields'][$i]['name'] === $flags[0]) {
                                return $product['rate']['extra_fields'][$i]['value'];
                            }
                        }
                    }
                    return null;
                },
                '_rate_id' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['rate']['id'];
                },
            ],
            'user' => [
                'name' => function ($data, $lang_id, $flags) {
                    $out = '';
                    $out .= $data['products'][0]['auth_first_name'];
                    if ($data['products'][0]['auth_middle_name'] !== null) {
                        $out .= ' ' . $data['products'][0]['auth_middle_name'];
                    }
                    $out .= ' ' . $data['products'][0]['auth_last_name'];

                    return $out;
                },
                'first_name' => function ($data, $lang_id, $flags) {
                    return $data['products'][0]['auth_first_name'];
                },
                'last_name' => function ($data, $lang_id, $flags) {
                    return $data['products'][0]['auth_last_name'];
                },
                'middle_name' => function ($data, $lang_id, $flags) {
                    return $data['products'][0]['auth_middle_name'];
                },
                'relationship' => function ($data, $lang_id, $flags) {
                },
                'phone' => function ($data, $lang_id, $flags) {
                    $ret = $data['event']['phone_number'];
                    if (is_string($ret)) {
                        return FormatPhoneNumber($ret);
                    }

                    return $ret;
                },
                'phone_raw' => 'event.phone_number',
                'email' => 'event.email_address',
                'birthdate' => 'event.ah_date_of_birth',
                'request_dob' => false,
                'credit_prequalified' => 'event.lead.credit_pass',
                'lead_id' => 'event.lead.external_lead_id',
                'lead_campaign' => 'event.lead.lead_campaign',
                'lead_type' => function ($data, $lang_id, $flags) {
                    if (isset($data['event']['lead'])) {
                        switch ($data['event']['lead']['lead_type_id']) {
                            case 1:
                                return 'blacklist';
                            case 2:
                                return 'onlist';
                            case 3:
                                return 'offlist';
                        }
                    }
                    return null;
                },
            ],
            'utility' => [
                'monthly_fee' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    return $product['utility_supported_fuel']['utility_monthly_fee'];
                },
                'rate_addendum' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    return $product['utility_supported_fuel']['utility_rate_addendum'];
                },
                'name' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['utility_supported_fuel']['utility']['name'];
                },
                'label' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }
                    if ($product['utility_supported_fuel']['utility']['ldc_code'] !== null) {
                        return $product['utility_supported_fuel']['utility']['ldc_code'];
                    }

                    return $product['utility_supported_fuel']['utility']['name'];
                },
                'discount_program' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['utility_supported_fuel']['utility']['discount_program'];
                },
                'disclosure_document' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['utility_supported_fuel']['utility']['disclosure_document'];
                },
                'customer_service' => 'utility.service_phone',
                'service_phone' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return FormatPhoneNumber($product['utility_supported_fuel']['utility']['customer_service']);
                },
                'service_phone_raw' => function ($data, $lang_id, $flags) use ($getSelectedProduct) {
                    $product = $getSelectedProduct($data, $flags);
                    if ($product === null) {
                        return null;
                    }

                    return $product['utility_supported_fuel']['utility']['customer_service'];
                },
            ],
            'sales_agent' => [
                'name' => function ($data, $lang_id, $flags) {
                    $out = '';
                    $out .= $data['event']['sales_agent']['user']['first_name'];
                    if ($data['event']['sales_agent']['user']['middle_name'] !== null) {
                        $out .= $data['event']['sales_agent']['user']['middle_name'];
                    }
                    $out .= $data['event']['sales_agent']['user']['last_name'];

                    return $out;
                },
                'first_name' => 'event.sales_agent.user.first_name',
                'middle_name' => 'event.sales_agent.user.middle_name',
                'last_name' => 'event.sales_agent.user.last_name',
                'employer' => 'event.sales_agent.employee_of.name',
                'employer_id' => 'event.sales_agent.employee_of_id',
            ],
        ];
    }

    public function show_tlp_test_page()
    {
        $pcount = request()->input('count');
        if ($pcount === null) {
            $pcount = 1;
        }

        return view('support.test_tlp_submit')->with(['pcount' => $pcount]);
    }

    public function show_email_lookup_tool()
    {
        return view('support.email_lookup');
    }

    public function email_tool_lookup(Request $request)
    {
        info('Email Lookup Request', [$request->all()]);
        $email = trim(mb_strtolower($request->email));
        if (!empty($email)) {
            $em = EmailAddress::where('email_address', 'like', $email)->first();
            if ($em) {
                return response()->json(['error' => false, 'id' => $em->id, 'deliverable' => $em->undeliverable == 0 || $em->undeliverable === false]);
            }
        }
        return response()->json(['error' => true, 'email' => $email]);
    }

    public function email_tool_reset(Request $request)
    {
        info('Email Reset Request', [$request->all()]);
        $email = trim(mb_strtolower($request->email));
        $message = null;

        if (!empty($email)) {
            $em = EmailAddress::where('email_address', 'like', $email)->get();
            $em->each(function ($item) {
                $item->undeliverable = 0;
                $item->save();
            });

            $mgApiKey = config('services.mailgun.secret');
            if (!empty($mgApiKey)) {
                $mgUrl = 'https://api.mailgun.net/v3/' . config('services.mailgun.domain') . '/bounces/' . $email;
                $http = new Client();
                try {
                    $ret = $http->delete($mgUrl, [
                        'auth' => ['api', $mgApiKey],
                    ]);
                    $statusCode = $ret->getStatusCode();

                    if ($statusCode !== 200 && $statusCode !== 404) {
                        $message = 'The email ' . $email . ' was cleared in our system but there was an error removing it from the bounce list on mailgun (send to IT): ' . $ret->getStatusCode() . ' ' . $ret->getReasonPhrase();
                        info('Error deleting bounce record from mailgun server (' . $ret->getStatusCode() . '): ' . $ret->getReasonPhrase(), [(string)$ret->getBody()]);
                        return response()->json(['error' => true, 'message' => $message]);
                    }
                    if ($statusCode === 404) {
                        $message =
                            'The email ' . $email . ' was cleared in our system but it was not found on the bounce list on mailgun, please contact IT. ';
                    }
                } catch (\Exception $e) {
                    $message =
                        'The email ' . $email . ' was cleared in our system but there was an error removing it from the bounce list on mailgun (send to IT): ' . $e->getMessage();
                    info('Error deleting bounce record from mailgun server', [$e]);
                    return response()->json(['error' => true, 'message' => $message]);
                }
            } else {
                $message = 'Unable to check mailgun api, mailgun is not configured.';
            }

            return response()->json(['error' => false, 'message' => $message]);
        }
        $message = 'Invalid email';
        return response()->json(['error' => true, 'message' => $message]);
    }

    public function show_sales_pitch_test_page()
    {
        $brands = Brand::whereNotNull('client_id')->orderBy('name', 'asc')->get();

        return view('support.sales_pitch_test')->with(['brands' => $brands]);
    }

    private function generate_sp_ref_id()
    {
        $unique = false;
        $candidate = null;
        while (!$unique) {
            $now = \Carbon\Carbon::now();
            $z = $now->format('z');
            if (strlen($z) < 3) {
                if (strlen($z) == 1) {
                    $z = '00' . $z;
                } else {
                    $z = '0' . $z;
                }
            } else {
                $z = substr($z, 0, 3);
            }
            $candidate = $now->format('y') . $z . $now->format('Hu');
            $candidate = substr($candidate, 1, 9);
            $hasIt = SalesPitch::where('ref_id', $candidate)->count();
            if ($hasIt == 0) {
                $unique = true;
                break;
            }
            usleep(1);
        }
        return $candidate;
    }

    public function create_sp_record(Request $request)
    {
        $brandId = $request->input('brand');
        $salesAgentTsrId = strtolower($request->input('tsr'));
        if (config('app.env') === 'production') {
            $phone = '+18559244878';
            $phone_formatted = '(855) 924-4878';
        } else {
            $phone = '+15392025614';
            $phone_formatted = '(539) 202-5614';
        }

        $brand = Brand::find($brandId);
        if (empty($brandId) || empty($brand)) {
            $request->session()->flash('flash_message', 'Invalid Brand');
            return redirect()->back();
        }
        $bu = BrandUser::where('works_for_id', $brandId)->where('tsr_id', $salesAgentTsrId)->first();
        if (empty($salesAgentTsrId) || empty($bu)) {
            $request->session()->flash('flash_message', 'Brand user not found');
            return redirect()->back();
        }

        $sp = new SalesPitch();
        $sp->created_at = Carbon::now('America/Chicago');
        $sp->updated_at = Carbon::now('America/Chicago');
        $sp->ref_id = $this->generate_sp_ref_id();
        $sp->brand_id = $brandId;
        $sp->interaction_id = 'sp_init_test';
        $sp->sales_agent_id = $bu->id;
        $sp->lang = 'en';
        $sp->save();

        return view('support.sales_pitch_test')->with(['pitch' => $sp->ref_id, 'phone' => $phone, 'phone_f' => $phone_formatted]);
    }

    public function alert_testing_tool(Request $request)
    {
        set_time_limit(240);

        $mode = 'view';
        $imode = $request->mode;
        switch ($imode) {
            case 'funcs':
            case 'check':
            case 'view':
            case 'pdata':
                $mode = $imode;
                break;

            default:
                $mode = 'view';
                break;
        }

        $confCode = $request->input('confcode');
        $rawInput = $request->input('rawinput');
        $category = intval($request->input('category'));
        $ani = CleanPhoneNumber($request->input('ani'));
        $writeEntries = false;
        if ($request->has('write') && $request->input('write') == 'on') {
            $writeEntries = true;
        }

        switch ($mode) {
            default:
            case 'view':
                return view('support.alert_test')->with([
                    'mode' => $mode,
                    'category' => $category,
                    'confcode' => $confCode,
                    'ani' => $ani,
                    'writeEntries' => $writeEntries,
                    'rawinput' => $rawInput,
                ]);

            case 'pdata':
                $sc = new ClientAlertController();
                $rawData = json_decode($rawInput, true);
                $standardizedData = $sc->standardizeProductData($rawData);
                $rdHash = md5(json_encode($rawData));
                $stdHash = md5(json_encode($standardizedData));
                return view('support.alert_test')->with([
                    'mode' => $mode,
                    'category' => $category,
                    'confcode' => $confCode,
                    'ani' => $ani,
                    'writeEntries' => $writeEntries,
                    'rawinput' => $rawInput,
                    'rawData' => $rawData,
                    'standardizedData' => $standardizedData,
                    'dataIsEqual' => hash_equals($rdHash, $stdHash),
                ]);

            case 'funcs':
                $sc = new ClientAlertController();

                if ($category < 1 || $category > 4) {
                    abort(400, 'Invalid Category');
                }
                $event = Event::where('confirmation_code', $confCode)->first();
                if (empty($event)) {
                    abort(400, 'Invalid Confirmation Code');
                }
                $funcs = $sc->GetAlertsForBrand($event->brand_id, $category);
                return view('support.alert_test')->with([
                    'mode' => $mode,
                    'category' => $category,
                    'confcode' => $confCode,
                    'ani' => $ani,
                    'funcs' => $funcs,
                    'writeEntries' => $writeEntries,
                    'rawinput' => $rawInput,
                ]);

            case 'check':
                $sc = new ClientAlertController();
                $productIndex = 0;

                $raw_event_data = $this->gatherEventDetails('', $confCode);
                if ($request->has('product_index')) {
                    $productIndex = intval($request->product_index);
                    if ($productIndex >= count($raw_event_data['products'])) {
                        $productIndex = 0;
                    }
                }

                $funcs = $sc->GetAlertsForBrand($raw_event_data['event']['brand_id'], $category);
                $response = [
                    'errors' => [],
                    'disposition' => [],
                    'stop-call' => [],
                    'message' => [],
                ];
                $formattedData = [
                    'event' => $raw_event_data['event']['id'],
                    'agent' => $raw_event_data['event']['sales_agent_id'],
                    'calledFrom' => !empty($ani) ? $ani : $raw_event_data['event']['phone_number'],
                    'calledInto' => '+19188675309',
                    'interaction' => null,
                    'email' => $raw_event_data['event']['email_address'],
                    'phone' => !empty($ani) ? $ani : $raw_event_data['event']['phone_number'],
                    'channel' => $raw_event_data['event']['channel_id']
                ];
                if ($category == 2 && isset($raw_event_data['products'][$productIndex])) {
                    $formattedData['auth_name'] = [
                        'first_name' => $raw_event_data['products'][$productIndex]['auth_first_name'],
                        'middle_name' => $raw_event_data['products'][$productIndex]['auth_middle_name'],
                        'last_name' => $raw_event_data['products'][$productIndex]['auth_last_name'],
                    ];
                }
                if ($category == 3 && isset($raw_event_data['products'][$productIndex])) {
                    $formattedData['product'] = [
                        'auth_relationship' => $raw_event_data['products'][$productIndex]['auth_relationship'],
                        'bill_fname' => $raw_event_data['products'][$productIndex]['bill_first_name'],
                        'bill_mname' => $raw_event_data['products'][$productIndex]['bill_middle_name'],
                        'bill_lname' => $raw_event_data['products'][$productIndex]['bill_last_name'],
                        'event_type_id' => $raw_event_data['products'][$productIndex]['event_type_id'],
                        'home_type_id' => $raw_event_data['products'][$productIndex]['home_type_id'],
                        'market_id' => $raw_event_data['products'][$productIndex]['market_id'],
                        'addresses' => self::get_product_address_obj($raw_event_data['products'][$productIndex]['addresses']),
                        'selection' => [
                            $raw_event_data['products'][$productIndex]['utility_supported_fuel']['utility_id'] => [
                                [],
                                []
                            ],
                        ],
                    ];
                    $pdata = [
                        'identifiers' => $raw_event_data['products'][$productIndex]['identifiers'],
                        'product' => $raw_event_data['products'][$productIndex]['rate']['product_id'],
                        'productName' => $raw_event_data['products'][$productIndex]['rate']['product']['name'],
                        'fuel_id' => $raw_event_data['products'][$productIndex]['utility_id'],
                        'fuel_type' => $raw_event_data['products'][$productIndex]['utility_supported_fuel']['utility_fuel_type_id'],
                    ];
                    $formattedData['product']['selection'][$raw_event_data['products'][$productIndex]['utility_supported_fuel']['utility_id']][$pdata['fuel_type'] - 1] = $pdata;
                }
                $originalData = $formattedData;
                $formattedData = $sc->standardizeProductData($formattedData);

                $start = hrtime(true);
                $sc->ProcessEventForAlerts($funcs, $raw_event_data['event']['brand_id'], $category . '', $formattedData, $response, $writeEntries);
                $end = hrtime(true) - $start;
                $rawResponse = $response;

                if (isset($response['errors']) && is_array($response['errors'])) {
                    $oerror = [];
                    foreach ($response['errors'] as $error) {
                        if ($error !== false) {
                            $oerror[] = $error;
                        }
                    }
                    if (empty($oerror)) {
                        $response['errors'] = 'No Errors';
                    } else {
                        $response['errors'] = implode(',', $oerror);
                    }
                }

                if (isset($response['stop-call']) && is_array($response['stop-call'])) {
                    $stop = false;
                    foreach ($response['stop-call'] as $wouldStop) {
                        if ($wouldStop === true) {
                            $stop = true;
                        }
                    }
                    $response['stop-call'] = $stop;
                }

                if (isset($response['disposition']) && is_array($response['disposition']) && !empty($response['disposition'])) {
                    $response['disposition'] = $response['disposition'][0];
                }

                if (isset($response['message']) && is_array($response['message'])) {
                    $msgs = array_filter($response['message']);
                    if (empty($msgs)) {
                        $response['message'] = null;
                    } else {
                        $response['message'] = $msgs;
                    }
                }

                return view('support.alert_test')->with([
                    'mode' => $mode,
                    'category' => $category,
                    'confcode' => $confCode,
                    'ani' => $ani,
                    'product_index' => $productIndex,
                    'pcount' => count($raw_event_data['products']),
                    'originalData' => $originalData,
                    'formattedData' => $formattedData,
                    'fullData' => $raw_event_data,
                    'ttp' => $end / 1e+6, // convert nanoseconds to milliseconds
                    'writeEntries' => $writeEntries,
                    'response' => $response,
                    'rawResponse' => $rawResponse,
                    'rawinput' => $rawInput,
                ]);
        }
    }

    public static function addresses_are_equal($addrA, $addrB): bool
    {
        if (empty($addrB) && !empty($addrA)) {
            return true;
        }
        return self::object_is_equal($addrA, $addrB);
    }

    public static function object_is_equal($a, $b): bool
    {
        $ja = json_encode($a);
        $jb = json_encode($b);

        return $ja === $jb;
    }

    private static function _linkedProductIndex($pid, $products)
    {
        for ($i = 0, $len = count($products); $i < $len; $i += 1) {
            if ($products[$i]['id'] === $pid) {
                return $i;
            }
        }
        return null;
    }

    private static function _getDualFuelOrThisProduct($thisProduct, $products)
    {
        $lpi = self::_linkedProductIndex($thisProduct['linked_to'], $products);
        if ($lpi !== null && $thisProduct['event_type_id'] == 2) {
            return [
                'dualFuel' => true,
                'electric' => $products[$lpi],
                'gas' => $thisProduct,
            ];
        }
        return $thisProduct;
    }

    private static function group_products_into_dualfuel($products, $callback = null)
    {
        $outProducts = [];
        $included = [];
        for ($i = 0, $len = count($products); $i < $len; $i += 1) {
            $ret = self::_getDualFuelOrThisProduct($products[$i], $products);
            if (!isset($ret['dualFuel'])) {
                if (in_array($ret['id'], $included)) {
                    continue;
                }
                $included[] = $ret['id'];
            } else if (in_array($ret['electric']['id'], $included) || in_array($ret['gas']['id'], $included)) {
                $removeDuplicateSingleFuels = function ($p) use ($ret) {
                    return !(!empty($p['id']) && ($p['id'] === $ret['electric']['id'] || $p['id'] === $ret['gas']['id']));
                };
                $outProducts = array_filter($outProducts, $removeDuplicateSingleFuels);
                if (!in_array($ret['electric']['id'], $included)) {
                    $included[] = $ret['electric']['id'];
                }
                if (!in_array($ret['gas']['id'], $included)) {
                    $included[] = $ret['gas']['id'];
                }
            } else {
                $included[] = $ret['electric']['id'];
                $included[] = $ret['gas']['id'];
            }

            if ($callback !== null) {
                $cret = $callback($ret);
                if ($cret) {
                    $outProducts[] = $ret;
                }
            } else {
                $outProducts[] = $ret;
            }
        }
        return $outProducts;
    }

    private static function get_address_obj_from_product(array $product): array
    {
        $out = [
            'service' => null,
            'billing' => null
        ];

        for ($i = 0, $len = count($product['addresses']); $i < $len; $i = $i + 1) {
            if ($product['addresses'][$i]['id_type'] === 'e_p:service') {
                $out['service'] = $product['addresses'][$i]['address'];
            }
            if ($product['addresses'][$i]['id_type'] === 'e_p:billing') {
                $out['billing'] = $product['addresses'][$i]['address'];
            }
        }

        return $out;
    }

    private static function group_products_by_program_code(array $products): array
    {
        $outProducts = [];
        $pcodes = [];

        $outProducts = self::group_products_into_dualfuel($products, function ($p) use ($pcodes) {
            if (isset($p['dualFuel'])) {
                $a = false;
                if (!empty($p['electric']['rate']) && !in_array($p['electric']['rate']['program_code'], $pcodes)) {
                    $pcodes[] = $p['electric']['rate']['program_code'];
                    $a = true;
                }
                if (!empty($p['gas']['rate']) && !in_array($p['gas']['rate']['program_code'], $pcodes)) {
                    $pcodes[] = $p['gas']['rate']['program_code'];
                    $a = true;
                }

                if ($a) {
                    return true;
                }
            } else {
                if (!empty($p['rate']) && !in_array($p['rate']['program_code'], $pcodes)) {
                    $pcodes[] = $p['rate']['program_code'];
                    return true;
                }
            }
            return false;
        });

        $pcodes = [];

        $dualFuelOnly = array_filter($outProducts, function ($v) {
            if (!empty($v['dualFuel']) && $v['dualFuel'] === true) {
                return true;
            }
            return false;
        });

        $singleFuelOnly = array_filter($outProducts, function ($v) {
            if (!empty($v['dualFuel']) && $v['dualFuel'] === true) {
                return false;
            }
            return true;
        });

        $out = array_merge($dualFuelOnly, $singleFuelOnly);
        return array_filter($out, function ($v) use ($pcodes) {
            if (!empty($v) && !empty($v['dualFuel'])) {
                if (in_array($v['electric']['rate']['program_code'], $pcodes) && in_array($v['gas']['rate']['program_code'], $pcodes)) {
                    return false;
                }
                $pcodes[] = $v['electric']['rate']['program_code'];
                $pcodes[] = $v['gas']['rate']['program_code'];
                return true;
            }

            if (empty($v) || (!empty($v['rate']['program_code']) && in_array($v['rate']['program_code'], $pcodes))) {
                return false;
            }
            $pcodes[] = $v['rate']['program_code'];
            return true;
        });
    }

    private static function group_products_by_address(array $products): array
    {
        $outProducts = [];
        $svcAddresses = [];

        for ($i = 0, $len = count($products); $i < $len; $i += 1) {
            $address = self::get_address_obj_from_product($products[$i]);
            if (
                !empty($address['service'])
                && !in_array($address['service']['id'], $svcAddresses)
            ) {
                $svcAddresses[] = $address['service']['id'];
            }
        }

        for ($n = 0, $nlen = count($svcAddresses); $n < $nlen; $n = $n + 1) {
            $taddress = $svcAddresses[$n];
            $allWithAddress = [];
            $billAddressSame = [];
            $billAddressDifferent = [];
            for ($i = 0, $len = count($products); $i < $len; $i = $i + 1) {
                $address = self::get_address_obj_from_product($products[$i]);
                if (
                    !empty($address['service'])
                    && $address['service']['id'] === $taddress
                ) {
                    $allWithAddress[] = $products[$i];
                }
            }
            if (!empty($allWithAddress)) {
                $address = self::get_address_obj_from_product($allWithAddress[0]);
                $refBill = $address['billing'];
                $refSvc = $address['service'];
                $sameAsSvc = self::addresses_are_equal($refSvc, $refBill);
                for ($i = 0, $len = count($allWithAddress); $i < $len; $i = $i + 1) {
                    $Iaddress = self::get_address_obj_from_product($allWithAddress[$i]);
                    if ($sameAsSvc) {
                        if ($Iaddress['billing'] === null) {
                            $billAddressSame[] = $allWithAddress[$i];
                        } else if (self::addresses_are_equal($Iaddress['service'], $Iaddress['billing'])) {
                            $billAddressSame[] = $allWithAddress[$i];
                        } else {
                            $billAddressDifferent[] = $allWithAddress[$i];
                        }
                    } else if (self::object_is_equal($refBill, $Iaddress["billing"])) {
                        $billAddressSame[] = $allWithAddress[$i];
                    } else {
                        $billAddressDifferent[] = $allWithAddress[$i];
                    }
                }
                if (!empty($billAddressSame)) {
                    $outProducts[] = $billAddressSame;
                }
                if (!empty($billAddressDifferent)) {
                    $outProducts[] = $billAddressDifferent;
                }
            }
        }

        if (!empty($outProducts)) {
            $outProducts = array_map(function ($i) {
                return self::group_products_into_dualfuel($i);
            }, $outProducts);
        }

        return $outProducts;
    }

    private static function group_products_by_this_address(array $products, array $address): array
    {
        return self::group_products_by_program_code(self::all_products_for_address_pair($products, $address));
    }

    private static function all_products_for_address_pair(array $products, array $address): array
    {
        $out = [];
        for ($i = 0, $len = count($products); $i < $len; $i += 1) {
            $pAddr = self::get_product_address_obj($products[$i]);
            if (self::addresses_are_equal($address['service'], $pAddr['service'])) {
                if (self::addresses_are_equal($address['service'], $address['billing'])) {
                    if (self::addresses_are_equal($pAddr['service'], $pAddr['billing'])) {
                        $out[] = $products[$i];
                    }
                } else if (self::addresses_are_equal($address['billing'], $pAddr['billing'])) {
                    $out[] = $products[$i];
                }
            }
        }
        return $out;
    }

    public static function group_products(array $products, string $byWhat = 'pcode', $withAddress = null): array
    {
        switch ($byWhat) {
            case 'pcode':
                return self::group_products_by_program_code($products);
                break;

            case 'address':
                if (empty($withAddress)) {
                    return self::group_products_by_address($products);
                }
                return self::group_products_by_this_address($products, $withAddress);
                break;

            default:
                throw new \RuntimeException('Invalid "byWhat" value to "group_products"');
        }
    }

    private static function get_product_address_obj($pdata)
    {
        $out = [
            'service' => null,
            'billing' => null,
        ];

        foreach ($pdata as $address) {
            $atype = 'service';
            if ($address['id_type'] !== 'e_p:service') {
                $atype = 'billing';
            }
            $out[$atype] = [
                'line_1' => $address['address']['line_1'],
                'line_2' => $address['address']['line_2'],
                'city' => $address['address']['city'],
                'state_province' => $address['address']['state_province'],
                'zip' => $address['address']['zip'],
                'country_id' => $address['address']['country_id'],
            ];
        }
        return $out;
    }

    public static function decode_script_answer_question_id(string $iqid): array
    {
        $out = [
            'section' => null,
            'product_group' => null,
            'product_group_index' => null,
            'question_index' => null,
            'question_id' => null,
            'summary' => true,
        ];

        $guidRegex = '/^[{]?[0-9a-fA-F]{8}-([0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}[}]?$/';

        $x = strpos($iqid, '-x-'); // checks for Section 3
        if ($x !== false) {
            $out['section'] = 3;
            $out['question_id'] = substr($iqid, $x + 3);
            $fp = substr($iqid, 0, $x);
            $fpA = explode('-', $fp);
            $out['product_group'] = intval($fpA[1]);
            $out['product_group_index'] = intval($fpA[0]);
            $out['question_index'] = intval($fpA[4]);
            return $out;
        }

        if (substr($iqid, 0, 3) === '00-') { // section 1
            $out['section'] = 1; // digital
            $fpA = explode('-', $iqid, 4);
            $out['question_id'] = $fpA[3];
            $out['question_index'] = intval($fpA[2]);
            return $out;
        }

        $fpA = explode('-', $iqid);
        if (!empty($fpA)) {
            if ($fpA[1] == 0) {
                // section 1 (digital or summary)
                $out['section'] = 1;
                $out['question_index'] = $fpA[2];
                $fpB = explode('-', $iqid, 4);
                $out['question_id'] = $fpB[3];
                return $out;
            }

            if (count($fpA) === 6) { // section 4
                $fpB = explode('-', $iqid, 1);
                if (count($fpB) > 1 && preg_match($guidRegex, $fpB[1]) !== false) {
                    $out['section'] = 4;
                    $out['question_id'] = $fpB[1];
                    return $out;
                }
            }
        }


        if (preg_match($guidRegex, $iqid) !== false) {
            $out['question_id'] = $iqid;
            $out['summary'] = false;
            return $out;
        }


        // decodes all other variants
        $fpA = explode('-', $iqid, 5);
        $out['section'] = intval($fpA[1]);
        $out['product_group'] = intval($fpA[0]);
        $out['product_group_index'] = intval($fpA[2]);
        $out['question_index'] = intval($fpA[3]);
        $out['question_id'] = $fpA[4];

        return $out;
    }

    public function do_clear_cache(Request $request)
    {
        if (config('app.env', 'production') !== 'production') {
            info('User ' . Auth::id() . ' requested cache:clear');
            Artisan::call('redis:flushdb');
            $request->session()->flash('flash_message', 'Cache Cleared!');
        } else {
            $request->session()->flash('flash_message', 'I\'m sorry, but I can\'t let you do that Dave.');
        }

        return redirect('/support/clear_cache');
    }

    public function send_live_enroll(Request $request)
    {
        $code = $request->input('code');
        if (empty($code)) {
            return view('support.send_live_enroll');
        }

        $output = null;
        $event = Event::where('confirmation_code', $code)->first();
        if ($event) {
            $output = \App\Http\Controllers\QaController::event_reprocess($event, true);
        } else {
            $output = 'Event not found';
        }

        return view('support.send_live_enroll')->with(['code' => $code, 'output' => $output]);
    }

    public function send_file_transfers(Request $request)
    {
        $codes = $request->input('code');
        if (empty($codes)) {
            return view('support.send_file_transfers');
        }

        $codes = explode(',', $codes);
        $output = null;

        ob_start();

        foreach ($codes as $code) {
            $event = Event::where(
                'confirmation_code',
                trim($code)
            )->first();
            if ($event) {
                $opts = [
                    '--brand' => $event->brand_id,
                    '--confirmation_code' => $event->confirmation_code,
                    '--ignoreSynced' => true,
                    '--debug' => true,
                    '--force' => true,
                ];

                Artisan::call('brand:file:sync', $opts);

                info('Running brand:file:sync for ' . $event->confirmation_code);

                $output .= ob_get_contents();
            }
        }

        ob_end_clean();

        session()->flash('flash_message', 'Ran re-sync of specified confirmation codes.');

        return view('support.send_file_transfers')->with(
            [
                'output' => $output,
                'code' => $code,
            ]
        );
    }

    public function run_contract()
    {
        $code = request()->input('code');
        $preview = request()->input('preview');
        if ($code === null) {
            return view('support.contract_test')->with(['multiple' => false]);
        }
        $retformat = request()->input('format');
        $hasMultiple = false;
        if (strpos($code, ',') !== false) {
            $hasMultiple = true;
            $code = collect(explode(',', $code));
            $code->map(function ($item) {
                return trim($item);
            })->filter(function ($item) {
                if ($item === null || strlen($item) === 0) {
                    return false;
                }
                return true;
            })->toArray();
        }

        if ($hasMultiple === false) {
            ob_start();
            $output = new BufferedOutput();
            $opts = [
                '--confirmation_code' => $code,
                '--no-ansi' => true,
                '--debug' => true,
                '--noDelivery' => true,
            ];
            if (!empty($preview)) {
                $opts['--preview'] = true;
                $opts['--unfinished'] = true;
            }
            if (config('app.env') === 'local') {
                $opts['--override-local'] = true;
            }

            $command = 'eztpv:generateContracts';

            // Check if producless version of contract generator needs to be run instead.
            // Currently, this is supported only for brand 'EnergyBPO'
            $event = Event::where('confirmation_code', $code)->first();
            if(
                $event && 
                in_array($event->brand_id, self::BRAND_IDS['energy_bpo'])
            ) {
                $command = 'eztpv:generateContractsProductless';
            }

            Artisan::call($command, $opts, $output);
            $out1 = ob_get_contents();
            ob_end_clean();
            $output = $out1 . "\n" . $output->fetch();
        } else {
            $output = null;
        }

        if ($retformat === 'text') {
            return response($output, 200);
        }

        return view('support.contract_test')->with([
            'output' => $output,
            'code' => $code,
            'preview' => $preview,
            'multiple' => $hasMultiple,
        ]);
    }

    public function clear_test_calls()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'clear-test-calls',
                'title' => 'Support: Clear Test Calls',
            ]
        );
    }

    public function get_events_by_confirmation_code($request)
    {
        $codes = [];
        if ($request->confirmation_codes) {
            $codes = explode(',', $request->confirmation_codes);
        }

        return Event::select(
            'events.id',
            'events.created_at',
            'events.confirmation_code',
            'brands.name as brand_name',
            'event_product.auth_first_name',
            'event_product.auth_last_name',
            'event_product.bill_first_name',
            'event_product.bill_last_name',
            'events.vendor_id'
        )->leftJoin(
            'brands',
            'brands.id',
            'events.brand_id'
        )->leftJoin(
            'event_product',
            'event_product.event_id',
            'events.id'
        )->whereIn(
            'events.confirmation_code',
            $codes
        )->with(['vendor'])->groupBy('events.id')->get();
    }

    private function get_events_by_testvendor()
    {
        $vendor = Brand::where('name', 'TPV.com Test Vendor')->first();

        return Event::select(
            'events.id',
            'events.created_at',
            'events.confirmation_code',
            'brands.name as brand_name',
            'event_product.auth_first_name',
            'event_product.auth_last_name',
            'event_product.bill_first_name',
            'event_product.bill_last_name',
            'events.vendor_id'
        )->leftJoin(
            'brands',
            'brands.id',
            'events.brand_id'
        )->leftJoin(
            'event_product',
            'event_product.event_id',
            'events.id'
        )->where(
            'events.vendor_id',
            $vendor->id
        )->with(['vendor'])->groupBy('events.id')->get();
    }

    public function list_clear_test_calls(Request $request)
    {
        if ($request->confirmation_codes === 'testvendor') {
            return $this->get_events_by_testvendor();
        }
        return $this->get_events_by_confirmation_code($request);
    }

    public function delete_test_calls(Request $request)
    {
        $events = $this->get_events_by_confirmation_code($request);
        foreach ($events as $event) {
            $sps = StatsProduct::where('confirmation_code', $event->confirmation_code)->get();
            foreach ($sps as $sp) {
                $sp->delete();
            }
            if ($event->eztpv_id != null) {
                $eztpv = Eztpv::where('id', $event->eztpv_id)->first();
                if ($eztpv) {
                    $eztpv->delete();
                }
            }
            $products = EventProduct::where('event_id', $event->id)->get();
            foreach ($products as $product) {
                $product->delete();
            }
            $event->delete();
        }
        session()->flash('flash_message', 'All calls were successfully deleted!');

        return back();
    }

    /**
     * Indra Audit Web routes
     */
    public function indra_audit()
    {
        return view('support.indra_audit', [
            'brands' => $this->get_brands()
        ]);
    }

    public function indra_audit_list(Request $request)
    {
        $page = $request->get('page') ?? 1;
        $export = $request->has('export') ? true : false;
        $size = 20;
        $collect = collect($this->getIndraRealTimeReport($request->all()));

        if($export) {
            $collect = $collect->toArray();
            if(count($collect) <= 0)
                return redirect()->back();

            $headers = [
                'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
                'Content-type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename=' . 'audit-report' . '.csv',
                'Expires'             => '0',
                'Pragma'              => 'public'
            ];

            $column_headers = array_keys((array)$collect[0]);

            $callback = function () use ($collect, $column_headers) {
                $FH = fopen('php://output', 'w');
                fputcsv($FH, $column_headers);
                foreach ($collect as $row) {
                    fputcsv($FH, array_values((array)$row));
                }
                fclose($FH);
            };

            return response()->stream($callback, 200, $headers);

            die();
        } else {
            return response()->json(
                new LengthAwarePaginator(
                    $collect->forPage($page, $size),
                    $collect->count(), 
                    $size, 
                    $page
                  )
            );
        }
    }

    public function indra_audit_reviewed($event_id, Request $request)
    {
        $good_or_bad = $request->has('good_or_bad') ? $request->good_or_bad : null;
        $comment = $request->comment ?? '';

        if(is_null($good_or_bad))
            return response('Invalid good_or_bad value', 400);

        $existing = DB::select("select * from indra_audits where event_id='$event_id'");
        if($existing && isset($existing[0])) {
            $ret = DB::statement("update indra_audits set good_or_bad=$good_or_bad, comment='$comment' where event_id='$event_id'");
        } else {
            $ret = DB::statement("insert indra_audits (event_id, reviewed, good_or_bad, comment) values('$event_id', 1, $good_or_bad, '$comment')");
        }

        if($ret)
            return response('ok');
        else
            return response('Failed to check the record!', 500);
    }
}
