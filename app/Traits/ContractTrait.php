<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Upload;
use App\Models\State;
use App\Models\Eztpv;
use App\Models\EventProduct;
use App\Models\Event;

use App\Models\ContractConfigTc;
use App\Models\ContractConfigPage;
use App\Models\ContractConfig;

trait ContractTrait
{
    public function getTemplate($event, $eztpv, $language, $products, $logoPath)
    {
        $brand = (isset($logoPath) && $logoPath)
            ? '<img width="100" src="' . config('services.aws.cloudfront.domain') . '/' . $logoPath->filename . '" />'
            : '<h2>' . $event->brand->name . '</h2>';
        $gps = (isset($event['gps_coords']))
            ? explode(',', $event['gps_coords'])
            : null;

        if (isset($eztpv->signature_customer)) {
            $customer_signature = $eztpv->signature_customer->signature;
            $customer_signature_updated = $eztpv->signature_customer->updated_at;
        } elseif (isset($eztpv->signature)) {
            $customer_signature = $eztpv->signature;
            if (isset($eztpv->signature_date)) {
                $customer_signature_updated = $eztpv->signature_date;
            }
        } else {
            $customer_signature = 'https://tpv-assets.s3.amazonaws.com/blank.jpg';
            $customer_signature_updated = null;
        }
        if (isset($eztpv->signature_agent)) {
            $agent_signature = $eztpv->signature_agent->signature;
            $agent_signature_updated = $eztpv->signature_agent->updated_at;
        } elseif (isset($eztpv->signature2)) {
            $agent_signature = $eztpv->signature2;
            if (isset($eztpv->signature2_date)) {
                $agent_signature_updated = $eztpv->signature2_date;
            }
        } else {
            $agent_signature = 'https://tpv-assets.s3.amazonaws.com/blank.jpg';
            $agent_signature_updated = null;
        }

        $template = '
            <html>
                <head>
                <style>
                body {font-size: 10pt; font-family: serif;}
                p {	margin: 0pt; }
                table.items {
                    border: 0.1mm solid #000000;
                }
                td { vertical-align: top; }
                .items td {
                    border-left: 0.1mm solid #000000;
                    border-right: 0.1mm solid #000000;
                }
                table thead td { background-color: #EEEEEE;
                    text-align: center;
                    border: 0.1mm solid #000000;
                    font-variant: small-caps;
                }
                .items td.blanktotal {
                    background-color: #EEEEEE;
                    border: 0.1mm solid #000000;
                    background-color: #FFFFFF;
                    border: 0mm none #000000;
                    border-top: 0.1mm solid #000000;
                    border-right: 0.1mm solid #000000;
                }
                .items td.totals {
                    text-align: right;
                    border: 0.1mm solid #000000;
                }
                .items td.cost {
                    text-align: "." center;
                }
                .page_break { page-break-before: always; }
                </style>
                </head>
                <body>
                <table width="100%">
                    <tr>
                        <td width="33%">' . $brand . '</td>
                        <td width="33%" align="center">
                            <br /><br /><h3>' . $this->hydrateVar($language['signature_page']) . '</h3>
                        </td>
                        <td width="33%" align="right">
                            <br />
                            <b>' . $event->brand->name . '</b><br />
                            ' . $event->brand->address . '<br />
                            ' . $event->brand->city . ', ' . $event->brand->brandState->state_abbrev . ' ' . $event->brand->zip . '<br />';

        if ($event->brand->email_address) {
            $template .= $event->brand->email_address . '<br />';
        }

        if ($event->brand->service_number) {
            $template .= substr_replace(substr_replace(str_replace("+1", "", $event->brand->service_number), '-', 3, 0), '-', 7, 0) . '<br />';
        }

        $the_channel = null;
        switch ($event->channel_id) {
            default:
            case 1:
                $the_channel = 'D2D';
                break;
            case 2:
                $the_channel = 'TM';
                break;
            case 3:
                $the_channel = 'Retail';
                break;
        }

        $template .= '
                </td>
            </tr>
        </table>

        <hr />

        <table width="100%">
            <tr>
                <td width="60%">
                    <strong>' . $this->hydrateVar($language['thank_you'], ['brand' => $event->brand->name]) . '</strong><br /><br />

                <p>' . $this->hydrateVar($language['sig_page_confirms'], ['brand' => $event->brand->name]) . '</p><br />
                <p><b>' . $this->hydrateVar($language['enrollment_processing']) . '</b></p><br />
                <p>' . $this->hydrateVar($language['enrollment_utility'], ['brand' => $event->brand->name]) . '</p><br />
                <p>' . $this->hydrateVar($language['service_summary'], ['brand' => $event->brand->name]) . '</p><br /><br />
            </td>
            <td width="40%">
                <table width="100%" style="font-family: serif;" cellpadding="10">
                    <tr>
                        <td width="45%" style="border: 0.1mm solid #888888;">
                            <span style="font-size: 7pt; color: #555555;">Information</span><br /><br />
                            <strong>' . $this->hydrateVar($language['created']) . ':</strong> ' . $event->created_at . '<br/>
                            <strong>' . $this->hydrateVar($language['confirmation_code']) . ':</strong> ' . $event->confirmation_code . '<br/>
                            <strong>' . $this->hydrateVar($language['agent']) . ':</strong> ' . @$event->sales_agent->user->first_name . ' ' . @$event->sales_agent->user->last_name . '<br/>
                            <strong>' . $this->hydrateVar($language['agent_id']) . ':</strong> ' . $event->sales_agent->tsr_id . '<br/>
                            <strong>' . $this->hydrateVar($language['channel']) . ':</strong> ' . $the_channel . '<br/>
                            <strong>' . $this->hydrateVar($language['auth_name']) . ':</strong> ' . $products[0]->auth_first_name . ' ' . $products[0]->auth_last_name . '<br/>
                            <strong>' . $this->hydrateVar($language['phone']) . ':</strong> ' . substr_replace(substr_replace(str_replace("+1", "", $event->phone->phone_number->phone_number), '-', 3, 0), '-', 7, 0) . '<br/>';

        if (isset($event->email->email_address->email_address)) {
            $template .= '
                                <strong>' . $this->hydrateVar($language['email']) . ':</strong> ' . $event->email->email_address->email_address . '<br/>';
        }

        if (isset($event->ip_addr) && $event->ip_addr !== '0.0.0.0') {
            $template .= '
                <strong>' . $this->hydrateVar($language['ip_addr']) . ':</strong> ' . $event->ip_addr . '<br/>';
        }

        if ($gps) {
            $template .= '
            <strong>' . $this->hydrateVar($language['gps_lat']) . ':</strong> ' . $gps[0] . '<br/>
            <strong>' . $this->hydrateVar($language['gps_lon']) . ':</strong> ' . $gps[1] . '<br/>';
        }

        $template .= '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table width="100%" border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>' . $this->hydrateVar($language['identifier']) . '</th>
            <th>' . $this->hydrateVar($language['billing_name']) . '</th>
            <th>' . $this->hydrateVar($language['address']) . '</th>
            <th>' . $this->hydrateVar($language['product']) . '</th>
        </tr>';

        foreach ($products->toArray() as $product) {
            switch ($product['utility_supported_fuel']['utility_fuel_type']['utility_type']) {
                case 'Natural Gas':
                    $utility_type = $this->hydrateVar($language['gas']);
                    break;
                case 'Electric':
                    $utility_type = $this->hydrateVar($language['electric']);
                    break;
            }

            $template .= '<tr><td>';
            $template .= $this->hydrateVar($language['type']) . ': ' . $utility_type . '<br /><br />';

            foreach ($product['identifiers'] as $ident) {
                switch ($ident['utility_account_type']['account_type']) {
                    case 'Account Number':
                        $account_type = $this->hydrateVar($language['account_number']);
                        break;
                    default:
                        $account_type = $ident['utility_account_type']['account_type'];
                        break;
                }

                $template .= $ident['identifier'] . '<br />(' . $account_type . ')<br />';
            }

            $template .= '</td>';

            if ($product['market_id'] === 1) {
                $template .= '<td>' . $product['bill_first_name'] . ' ' . $product['bill_middle_name'] . ' ' . $product['bill_last_name'] . '</td>';
            } else {
                $template .= '<td>' . $product['company_name'] . '</td>';
            }

            $template .= '<td>
                    <strong>' . $this->hydrateVar($language['service_address']) . ':</strong> '
                . $product['service_address']['address']['line_1']
                . ' ' . $product['service_address']['address']['line_2']
                . '<br />' . $product['service_address']['address']['city']
                . ', ' . $product['service_address']['address']['state_province']
                . ' ' . $product['service_address']['address']['zip'] . '<br />';

            if (
                isset($product['billing_address']['address']['line_1'])
                && strlen(trim($product['billing_address']['address']['line_1'])) > 0
            ) {
                $template .= '
                    <strong>' . $this->hydrateVar($language['billing_address']) . ':</strong> '
                    . $product['billing_address']['address']['line_1']
                    . ' ' . $product['billing_address']['address']['line_2']
                    . '<br />' . $product['billing_address']['address']['city']
                    . ', ' . $product['billing_address']['address']['state_province']
                    . ' ' . $product['billing_address']['address']['zip'];
            }

            $template .= '
                </td>
                <td>
                    <strong>' . $this->hydrateVar($language['utility']) . ':</strong> ' . $product['utility_supported_fuel']['utility']['name'] . '<br/>
                    <strong>' . $this->hydrateVar($language['product']) . ':</strong> ' . $product['rate']['product']['name'] . '<br/>
                    <strong>' . $this->hydrateVar($language['program_code']) . ':</strong> ' . $product['rate']['program_code'] . '<br/>';

            if (
                isset($product['rate']['intro_rate_amount'])
                && null !== $product['rate']['intro_rate_amount']
                && 0 !== $product['rate']['intro_rate_amount']
                && '0' !== $product['rate']['intro_rate_amount']
            ) {
                $template .= '
                <strong>' . $this->hydrateVar($language['intro_rate']) . ':</strong> ';

                if ($product['rate']['rate_currency']['currency'] == 'dollars') {
                    $template .= '$' . $product['rate']['intro_rate_amount'] . ' ';
                } else {
                    $template .= $product['rate']['intro_rate_amount'] . ' cents ';
                }

                $template .= $this->hydrateVar($language['per']) . ' ' . $product['rate']['rate_uom']['uom'] . '<br/>';
            }

            if (isset($product['rate']['intro_term']) && null !== $product['rate']['intro_term']) {
                $template .= '
                <strong>' . $this->hydrateVar($language['intro_term']) . ':</strong> ' . $product['rate']['product']['intro_term'] . ' ' . $product['rate']['product']['intro_term_type']['term_type'] . '<br/>';
            }

            if (isset($product['rate']['intro_cancellation_fee']) && $product['rate']['intro_cancellation_fee'] !== null) {
                $template .= '<strong>' . $this->hydrateVar($language['intro_cancellation']) . ':</strong> $' . $product['rate']['intro_cancellation_fee'] . '<br />';
            }

            $template .= '<strong>' . $this->hydrateVar($language['rate_amount']) . ':</strong> ';

            if (null == $product['rate']['rate_amount']) {
                $template .= $this->hydrateVar($language['variable']) . '<br />';
            } else {
                if ($product['rate']['rate_currency']['currency'] == 'dollars') {
                    $template .= '$' . $product['rate']['rate_amount'] . ' ';
                } else {
                    $template .= $product['rate']['rate_amount'] . ' cents ';
                }

                $template .= $this->hydrateVar($language['per']) . ' ' . $product['rate']['rate_uom']['uom'] . '<br />';
            }

            if (isset($product['rate']['product']['term']) && null !== $product['rate']['product']['term'] && $product['rate']['product']['term'] > 0) {
                $template .= '<strong>' . $this->hydrateVar($language['term']) . ':</strong> ' . $product['rate']['product']['term'] . ' ' . $product['rate']['product']['term_type']['term_type'] . '<br/>';
            }

            if (isset($product['rate']['cancellation_fee']) && null !== $product['rate']['cancellation_fee']) {
                $template .= '<strong>' . $this->hydrateVar($language['cancellation']) . ':</strong> $'
                    . $product['rate']['cancellation_fee'] . ' ';

                switch (@$product['rate']['cancellation_fee_term_type']['term_type']) {
                    case 'month':
                        $template .= $this->hydrateVar($language['month_remaining']);
                        break;
                    default:
                        $template .= $this->hydrateVar($language['onetime']);
                }

                $template .= '<br />';
            }

            if (isset($product['rate']['product']['daily_fee']) && null !== $product['rate']['product']['daily_fee']) {
                $template .= '<strong>' . $this->hydrateVar($language['daily_fee']) . ':</strong> $' . $product['rate']['product']['daily_fee'] . '<br/>';
            }

            if (isset($product['rate']['product']['monthly_fee']) && $product['rate']['product']['monthly_fee'] > 0) {
                $template .= '<strong>' . $this->hydrateVar($language['monthly_fee']) . ':</strong> $' . $product['rate']['product']['monthly_fee'] . ' ' . $this->hydrateVar($language['per']) . ' ' . $this->hydrateVar($language['month']) . '<br/>';
            }

            if (isset($product['rate']['rate_monthly_fee']) && $product['rate']['rate_monthly_fee'] > 0) {
                $template .= '<strong>' . $this->hydrateVar($language['monthly_fee']) . ':</strong> $' . $product['rate']['rate_monthly_fee'] . ' ' . $this->hydrateVar($language['per']) . ' ' . $this->hydrateVar($language['month']) . '<br/>';
            }

            if (null !== $product['rate']['product']['green_percentage']) {
                $template .= '<strong>' . $this->hydrateVar($language['green']) . ' %:</strong> ' . $product['rate']['product']['green_percentage'] . '<br/>';
            }

            $template .= '
            </td>
        </tr>';
        }

        $template .= '
            </table>

            <br />

            <b>' . $this->hydrateVar($language['authorized_by']) . ':</b>
            <table width="100%" border="0">
                <tr>
                    <td width="50%" align="center" style="height:' . $eztpv->sig_row_height . 'px;vertical-align:bottom;">
                        <table width="100%">
                            <tr>
                                <td>
                                    <img style="height: ' . $eztpv->signature_customer_height . 'px;" src="' . $customer_signature . '" />
                                    <hr />
                                    <table width="100%" style="font-size: 10pt;">
                                        <tr>
                                            <td align="center">' . $this->hydrateVar($language['customer_sig']) . '</td>
                                            <td align="center">' . $products[0]['auth_first_name'] . ' ' . $products[0]['auth_middle_name'] . ' ' . $products[0]['auth_last_name'] . '</td>
                                            <td align="center">' . $customer_signature_updated . '</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="50%" align="center" style="height:' . $eztpv->sig_row_height . 'px;vertical-align:bottom;">
                        <table width="100%">
                            <tr>
                                <td>
                                    <img style="height: ' . $eztpv->signature_agent_height . 'px;" src="' . $agent_signature . '" />
                                    <hr />
                                    <table width="100%" style="font-size: 10pt;">
                                        <tr>
                                            <td align="center">' . $this->hydrateVar($language['agent_sig']) . '</td>
                                            <td align="center">' . $event->sales_agent->user->first_name . ' ' . $event->sales_agent->user->last_name . '</td>
                                            <td align="center">' . $agent_signature_updated . '</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';

        return $template;
    }

    private function hydrateVar($text, $vars = null)
    {
        // info($text);
        // info(print_r($vars, true));

        if ($vars !== null && count($vars) > 0) {
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
                    // info('value = ' . $value);
                    if (array_key_exists($value, $vars)) {
                        $text = str_replace(
                            '{{' . $value . '}}',
                            $vars[$value],
                            $text
                        );
                    }
                }
            }
        }

        return $text;
    }

    public function getTranslations($language)
    {
        $english = [
            'account_number' => 'Account Number',
            'address' => 'Address',
            'agent' => 'Agent',
            'agent_id' => 'Agent ID',
            'agent_sig' => 'Agent',
            'auth_name' => 'Auth Name',
            'authorized_by' => 'Authorized By',
            'billing_address' => 'Billing',
            'billing_name' => 'Billing Name',
            'cancellation' => 'Cancellation',
            'channel' => 'Sales Channel',
            'confirmation_code' => 'Confirmation Code',
            'created' => 'Created',
            'customer_sig' => 'Customer',
            'daily_fee' => 'Daily Fee',
            'email' => 'Email',
            'electric' => 'Electric',
            'enrollment_processing' => 'We are currently processing your enrollment.',
            'enrollment_utility' => 'Your enrollment has been sent to your utility. Your utility will send you a confirmation notice confirming your selection of {{brand}} as your supplier.',
            'gas' => 'Natural Gas',
            'gps_lat' => 'GPS Lat',
            'gps_lon' => 'GPS Lon',
            'green' => 'Green',
            'identifier' => 'Identifier',
            'information' => 'Information',
            'intro_cancellation' => 'Intro Cancellation',
            'intro_rate' => 'Initial Rate',
            'intro_term' => 'Intro Term',
            'ip_addr' => 'IP Address',
            'monthly_fee' => 'Monthly Fee',
            'month' => 'month',
            'per' => 'per',
            'phone' => 'Phone',
            'product' => 'Product',
            'program_code' => 'Program Code',
            'rate_amount' => 'Rate Amount',
            'service_address' => 'Service',
            'service_meter' => 'Your service will begin with your first meter read by your utility after your enrollment is accepted, which may take up to 1-2 billing cycles.',
            'service_summary' => 'Below is a summary of your service account with <b>{{brand}}</b>:',
            'sig_page_confirms' => 'This signature page confirms your choice to enroll with <b>{{brand}}</b> and provides a summary of your new service account. The terms and conditions are attached for your reference.',
            'signature_page' => 'SIGNATURE PAGE TO SERVICES AGREEMENT',
            'term' => 'Term',
            'thank_you' => 'Thank you for choosing {{brand}}!',
            'type' => 'Type',
            'utility' => 'Utility',
            'variable' => 'Variable',
            'contract_summary' => 'CONTRACT SUMMARY',
            'onetime' => 'one time fee',
            'month_remaining' => 'per month remaining on the contract'
        ];

        $spanish = [
            'account_number' => 'Número de Cuenta',
            'address' => 'Dirección',
            'agent' => 'Agente',
            'agent_id' => 'ID del Agente ',
            'agent_sig' => 'Agente',
            'auth_name' => 'Nombre de Autenticación',
            'authorized_by' => 'Autorizado por',
            'billing_address' => 'Envio',
            'billing_name' => 'Nombre de Facturación',
            'cancellation' => 'Cancelación',
            'channel' => 'Canal de Ventas',
            'confirmation_code' => 'Código de Confirmación',
            'created' => 'Creado',
            'customer_sig' => 'Cliente',
            'daily_fee' => 'Tarifa Diaria',
            'email' => 'Correo Electrónico',
            'electric' => 'Electricidad',
            'enrollment_processing' => 'Actualmente estamos procesando su inscripción.',
            'enrollment_utility' => 'Su inscripción ha sido enviada a su empresa de servicios públicos. Su utilidad le enviará un aviso de confirmación confirmando su selección de {brand} como su proveedor.',
            'gas' => 'Gas Natural ',
            'gps_lat' => 'GPS Lat',
            'gps_lon' => 'GPS Lon',
            'green' => 'Verde',
            'identifier' => 'Identificador',
            'information' => 'Información',
            'intro_cancellation' => 'Cancelación de Introducción',
            'intro_rate' => 'Tasa inicial',
            'intro_term' => 'Término de Introducción',
            'ip_addr' => 'Dirección IP',
            'monthly_fee' => 'Tarifa mensual',
            'month' => 'mes',
            'per' => 'por',
            'phone' => 'Teléfono',
            'product' => 'Producto',
            'program_code' => 'Código de Programa',
            'rate_amount' => 'Cantidad de Tarifa',
            'service_address' => 'Servicio',
            'service_meter' => 'Su servicio comenzará con su primer medidor leído por su empresa de servicios públicos después de que se acepte su inscripción, lo que puede demorar hasta 1-2 ciclos de facturación.',
            'service_summary' => 'A continuación se muestra un resumen de su cuenta de servicio con <b>{{brand}}</b>:',
            'sig_page_confirms' => 'Esta página de firma confirma su elección de inscribirse con <b>{{brand}}</b> y proporciona un resumen de su nueva cuenta de servicio. Los términos y condiciones se adjuntan para su referencia.',
            'signature_page' => 'PÁGINA DE FIRMA DEL ACUERDO DE SERVICIOS',
            'term' => 'Término',
            'thank_you' => 'Gracias por elegir {{brand}}!',
            'type' => 'Tipo',
            'utility' => 'Utilidad',
            'variable' => 'Variable',
            'contract_summary' => 'RESUMEN DEL CONTRATO',
            'onetime' => 'tarifa única',
            'month_remaining' => 'por mes restante en el contrato'
        ];

        if ($language === 2) {
            return $spanish;
        }

        return $english;
    }

    public function generateContract(
        $confirmation_code,
        $contractOnly = false,
        $debug = false,
        $good_sale_required = true
    ) {
        $summary = [];
        $params = [];

        if ($debug) {
            DB::enableQueryLog();
        }

        $eztpv = Eztpv::select(
            'eztpvs.*'
        )->join(
            'events',
            'eztpvs.id',
            'events.eztpv_id'
        )->join(
            'interactions',
            'events.id',
            'interactions.event_id'
        )->where(
            'interactions.event_result_id',
            ($good_sale_required) ? 1 : 2
        )->where(
            'events.confirmation_code',
            $confirmation_code
        )->orderBy('eztpvs.created_at')->first();
        if ($eztpv) {
            $event = Event::where(
                'eztpv_id',
                $eztpv->id
            )->with(
                [
                    'brand',
                    'phone',
                    'phone.phone_number',
                    'email',
                    'email.email_address',
                    'sales_agent',
                    'sales_agent.user',
                    'customFieldStorage',
                    'language',
                ]
            )->first();
            if ($event) {
                $products = EventProduct::where(
                    'event_id',
                    $event->id
                )->with(
                    [
                        'rate',
                        'rate.product' => function ($query) {
                            $query->withTrashed();
                        },
                        'rate.product.intro_term_type',
                        'rate.product.term_type',
                        'rate.product.rate_type',
                        'rate.rate_uom',
                        'rate.term_type',
                        'rate.rate_currency',
                        'rate.cancellation_fee_term_type',
                        'serviceAddress',
                        'billingAddress',
                        'identifiers',
                        'identifiers.utility_account_type',
                        'market',
                        'utility_supported_fuel',
                        'home_type',
                        'customFields',
                        'utility_supported_fuel.utility',
                        'utility_supported_fuel.utility_fuel_type',
                    ]
                )->get();

                $brandState = State::find($event->brand->state);
                $event->brand->brandState = $brandState;
            } else {
                $this->error('No event found.');
                return;
            }

            if (0 == $products->count()) {
                $this->error('No products found.');
                return;
            }

            foreach ($products as $key => $ep) {
                if (!isset($summary['brand_logo_id'])) {
                    if (isset($ep->event->brand->logo_path)) {
                        $summary['brand_logo_id'] = $ep->event->brand->logo_path;
                    }
                }
            }

            $logoPath = null;
            if (isset($summary['brand_logo_id'])) {
                $logoPath = Upload::select(
                    'filename'
                )->where(
                    'uploads.id',
                    $summary['brand_logo_id']
                )->first();
            }

            if (isset($event->brand->state)) {
                $state = State::find($event->brand->state);
                if ($state) {
                    $event->brand->state_abbrev = $state->state_abbrev;
                }
            }

            if (isset($eztpv->signature_customer)) {
                $customer_signature = $eztpv->signature_customer->signature;
            } elseif (isset($eztpv->signature)) {
                $customer_signature = $eztpv->signature;
            } else {
                $customer_signature = null;
            }
            if (isset($eztpv->signature_agent)) {
                $agent_signature = $eztpv->signature_agent->signature;
            } elseif (isset($eztpv->signature2)) {
                $agent_signature = $eztpv->signature2;
            } else {
                $agent_signature = null;
            }

            // signature image sizing
            // customer
            if (isset($customer_signature)) {
                $size = getimagesize($customer_signature);
                if ($size[1] > 100) {
                    $eztpv->signature_customer_height = 100;
                } else {
                    $eztpv->signature_customer_height = $size[1];
                }
            } else {
                return [
                    'error' => true,
                    'message' => 'Missing customer signature',
                    'file' => null,
                ];
            }

            // agent
            if (isset($agent_signature)) {
                $size = getimagesize($agent_signature);
                if ($size[1] > 100) {
                    $eztpv->signature_agent_height = 100;
                } else {
                    $eztpv->signature_agent_height = $size[1];
                }
            } else {
                return [
                    'error' => true,
                    'message' => 'Missing agent signature',
                    'file' => null,
                ];
            }

            // compare image heights
            if ($eztpv->signature_customer_height > $eztpv->signature_agent_height) {
                $eztpv->sig_row_height = $eztpv->signature_customer_height;
            } else {
                $eztpv->sig_row_height = $eztpv->signature_agent_height;
            }

            $language = $this->getTranslations($event->language_id);
            $template = $this->getTemplate($event, $eztpv, $language, $products, $logoPath);

            $tcs = [];
            $custom_fields = [];
            foreach ($products as $product) {
                foreach ($product->event->customFieldStorage as $cfs) {
                    $name = $cfs->customField['output_name'];
                    $custom_fields[$name] = $cfs->value;
                }

                $data = $product->toArray();
                $service_state = $data['service_address']['address']['state_province'];
                $ccp_language = ($product->event->language_id === 2)
                    ? 'spanish'
                    : 'english';

                switch ($product->event->channel_id) {
                    case 1:
                        $channel = 'DTD';
                        break;
                    case 2:
                        $channel = 'TM';
                        break;
                    case 3:
                        $channel = 'RETAIL';
                        break;
                }

                switch ($product->market_id) {
                    case 1:
                        $market = 'RESIDENTIAL';
                        break;
                    case 2:
                        $market = 'COMMERCIAL';
                        break;
                }

                switch ($product->utility_supported_fuel->utility_fuel_type->utility_type) {
                    case 'Electric':
                        $commodity = 'electric';
                        break;
                    case 'Natural Gas':
                    case 'Gas':
                        $commodity = 'gas';
                        break;
                }

                $rate_type_id = $product->rate->product->rate_type_id;
                switch ($product->rate->product->rate_type_id) {
                    case 1:
                        $rate_type = 'fixed';
                        break;
                    case 2:
                        $rate_type = 'variable';
                        break;
                    case 3:
                        $rate_type = 'tiered';
                        break;
                }

                info("Rate Type is " . $rate_type);

                if ($rate_type === 'tiered') {
                    if (isset($product) && isset($product->rate) && isset($product->rate->rate_amount)) {
                        info('Rate Amount is ' . $product->rate->rate_amount);
                        info('Rate Amount is ' . $product->rate->intro_rate_amount);
                    }

                    if ($product->rate->rate_amount > 0 && $product->rate->intro_rate_amount > 0) {
                        $rate_type = 'tiered fixed';
                        $rate_type_id = 4;
                    } else {
                        $rate_type = 'tiered variable';
                        $rate_type_id = 5;
                    }
                }

                $cc = ContractConfig::select(
                    'contract_config.id',
                    'contract_config.page_intro',
                    'contract_config.terms_and_conditions',
                    'contract_config.terms_and_conditions_spanish'
                )->leftJoin(
                    'states',
                    'contract_config.state_id',
                    'states.id'
                )->where(
                    'contract_config.brand_id',
                    $product->event->brand_id
                )->where(
                    function ($query) use ($rate_type_id) {
                        if (!empty($rate_type_id)) {
                            $query->where(
                                'contract_config.rate_type',
                                $rate_type_id
                            );
                        } else {
                            $query->whereNull(
                                'contract_config.rate_type'
                            );
                        }
                    }
                )->where(
                    function ($query) use ($service_state) {
                        $query->where(
                            'states.state_abbrev',
                            $service_state
                        )->orWhereNull(
                            'contract_config.state_id'
                        );
                    }
                )->where(
                    function ($query) use ($channel) {
                        $query->where(
                            'contract_config.channel',
                            'LIKE',
                            '%' . $channel . '%'
                        );
                    }
                )->where(
                    function ($query) use ($market) {
                        $query->where(
                            'contract_config.market',
                            'LIKE',
                            '%' . $market . '%'
                        );
                    }
                )->where(
                    function ($query) use ($commodity) {
                        $query->where(
                            'contract_config.commodities',
                            $commodity
                        )->orWhere(
                            'contract_config.commodities',
                            'any'
                        );
                    }
                )->first();
                if ($cc) {
                    if ($ccp_language === 'english' && $cc->terms_and_conditions !== null) {
                        if (!in_array($cc->terms_and_conditions, $tcs)) {
                            $tcs[] = $cc->terms_and_conditions;
                        }
                    }

                    if ($ccp_language === 'spanish' && $cc->terms_and_conditions_spanish !== null) {
                        if (!in_array($cc->terms_and_conditions_spanish, $tcs)) {
                            $tcs[] = $cc->terms_and_conditions_spanish;
                        }
                    }

                    if (is_string($cc->page_intro)) {
                        $cc->page_intro = json_decode($cc->page_intro, true);
                    }

                    $ccps = ContractConfigPage::where(
                        'contract_config_id',
                        $cc->id
                    )->orderBy('sort')->get();
                    if ($ccps && $ccps->count() > 0) {
                        $list = [
                            'client.name' => $product->event->brand->name,
                            'user.name' => $product->auth_first_name . " " . $product->auth_last_name,
                            'date' => $product->event->event_date,
                            'account.bill_name' => $product->bill_first_name . " " . $product->bill_last_name,
                            'event.confirmation_code' => $product->event->confirmation_code,
                            'client.service_phone' => $product->event->brand->service_number,
                            'commodity' => $commodity,
                            'product.amount' => $product->rate->rate_amount,
                            'product.intro_amount' => $product->rate->intro_rate_amount,
                            'product.cancellation_fee' => $product->rate->cancellation_fee,
                            'product.intro_cancellation_fee' => $product->rate->intro_cancellation_fee,
                            'product.term' => $product->rate->product->term,
                            'product.intro_term' => $product->rate->product->intro_term,
                            'product.service_fee' => $product->rate->product->service_fee,
                            'product.daily_fee' => $product->rate->product->daily_fee,
                            'product.monthly_fee' => ($product->rate->rate_monthly_fee > 0)
                                ? $product->rate->rate_monthly_fee
                                : $product->rate->product->monthly_fee,
                            'product.program_code' => $product->rate->program_code,
                            'product.uom' => @$product->rate->rate_uom->uom,
                            'product.currency' => @$product->rate->rate_currency->currency,
                            'utility.name' => $product->utility_supported_fuel->utility->name,
                            'utility.customer_service' => $product->utility_supported_fuel->utility->customer_service,
                        ];

                        foreach ($custom_fields as $key => $value) {
                            $list['custom.' . $key] = $value;
                        }

                        // echo "<pre>";
                        // print_r($list);
                        // echo "</pre>";
                        // exit();

                        $intro_header = ($cc->page_intro && is_array($cc->page_intro))
                            ? $this->hydrateVar($cc->page_intro[$ccp_language], $list)
                            : '<h2 style="text-align: center;">' . $this->hydrateVar($language['contract_summary']) . '</h2>';

                        $template .= '
                            <div class="page_break"></div>

                            <br /><br />

                            ' . $intro_header . '
                            <table width="100%" border="1" cellpadding="8" cellspacing="0">';

                        foreach ($ccps as $value) {
                            if (is_string($value->label)) {
                                $value->label = json_decode($value->label, true);
                            }

                            if (is_string($value->body)) {
                                $value->body = json_decode($value->body, true);
                            }

                            $template .= '<tr>';
                            $template .= '<td>';
                            $template .= $this->hydrateVar($value->label[$ccp_language], $list);
                            $template .= '</td>';
                            $template .= '<td>';
                            $template .= $this->hydrateVar($value->body[$ccp_language], $list);
                            $template .= '</td>';
                            $template .= '</tr>';
                        }

                        $template .= '</table>';
                    }
                }
            }

            $template .= '
                </body>
                </html>
                ';

            $merge = false;
            if (count($tcs) > 0) {
                $merge = true;
            }

            $mpdf = new \Mpdf\Mpdf(
                [
                    'tempDir' => public_path('tmp'),
                    'mode' => 'utf-8',
                    'format' => [216, 279],
                    'margin_left' => 5,
                    'margin_right' => 5,
                    'margin_top' => 0,
                    'margin_bottom' => 5,
                    'margin_header' => 0,
                    'margin_footer' => 0
                ]
            );

            $mpdf->SetAuthor('TPV.com');

            $mpdf->WriteHTML($template);

            $sigPageFile = public_path('/tmp/' . md5(time()) . '.pdf');
            if ($merge) {
                $sigPagePdfContent = $mpdf->Output(null, \Mpdf\Output\Destination::STRING_RETURN);
                file_put_contents($sigPageFile, $sigPagePdfContent);

                for ($i = 0; $i < count($tcs); $i++) {
                    $cct = ContractConfigTc::find($tcs[$i]);
                    if ($cct) {
                        $upload = Upload::find($cct->upload_id);
                        if ($upload) {
                            $tc = public_path('/tmp/' . md5(time() . $upload->id) . '.pdf');
                            $contents = file_get_contents(config('services.aws.cloudfront.domain') . '/' . $upload->filename);
                            file_put_contents($tc, $contents);

                            Artisan::call('pdf:merge', [
                                '--output' => $sigPageFile,
                                'inputFiles' => [$sigPageFile, $tc],
                            ]);

                            @unlink($tc);
                        }
                    }
                }
            } else {
                $mpdf->Output($sigPageFile, \Mpdf\Output\Destination::FILE);
            }

            if ($debug) {
                $params = [
                    'query_log' => DB::getQueryLog(),
                    'list' => @$list,
                    'tcs' => $tcs,
                    'error' => false,
                    'message' => null,
                    'file' => $sigPageFile,
                    'products' => $products->toArray(),
                ];

                @unlink($sigPageFile);

                echo "<pre>";
                print_r($params);
                echo "</pre>";
                exit();
            } else {
                if ($contractOnly) {
                    return [
                        'error' => false,
                        'message' => null,
                        'file' => $sigPageFile,
                    ];
                } else {
                    $dir = 'uploads/pdfs/' . $eztpv->brand_id . '/' . date('Y-m-d');
                    $s3filename = md5($sigPageFile) . '.pdf';
                    $keyname = $dir . '/' . $s3filename;

                    try {
                        Storage::disk('s3')->put(
                            $keyname,
                            file_get_contents($sigPageFile),
                            'public'
                        );
                    } catch (\Aws\S3\Exception\S3Exception $e) {
                        @unlink($sigPageFile);

                        return [
                            'error' => true,
                            'message' => 'S3 error: ' . $e,
                            'file' => null,
                        ];
                    }

                    @unlink($sigPageFile);

                    return [
                        'error' => false,
                        'message' => null,
                        'file' => $keyname
                    ];
                }
            }
        }
    }
}
