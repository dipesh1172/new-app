<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>@lang('sigpage.title')</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    </head>
    <body>
        <table width="100%">
            <tr>
                <td colspan="5">
                    <h1>{{ $event['brand']['name'] }}</h1>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    @if($mapImage !== null)
                    <img width="300px" height="300px" src="{{ $mapImage }}" />
                    @endif
                </td>
                <td colspan="3">
                    <strong>@lang('sigpage.general')</strong><br />
                    <strong>@lang('sigpage.created'):</strong> {{ $event['created_at'] }}<br/>
                    <strong>@lang('sigpage.conf_code'):</strong> {{ $event['confirmation_code'] }}<br/>
                    <strong>@lang('sigpage.agent'):</strong> {{ $event['sales_agent']['user']['first_name'] . ' ' .$event['sales_agent']['user']['last_name'] }}<br/>
                    <strong>@lang('sigpage.agent_id'):</strong> {{ $event['sales_agent']['tsr_id'] }}<br/>
                    <strong>@lang('sigpage.auth_name'):</strong> {{ $products[0]['auth_first_name'] . ' ' . $products[0]['auth_middle_name'] . ' ' . $products[0]['auth_last_name'] }}<br/>
                    @if($products[0]['company_name'] !== null)
                        <strong>@lang('sigpage.company'):</strong> {{ $products[0]['company_name']}} <br/>
                    @endif
                    <strong>@lang('sigpage.phone'):</strong>
                    {{ $event['phone']['phone_number']['phone_number'] }}<br/>

                    <strong>@lang('sigpage.email'):</strong>
                    @if($event['email'] !== null)
                        {{ $event['email']['email_address']['email_address'] }}
                    @endif
                    <br/>
                    <strong>@lang('sigpage.lang'):</strong> {{ $event['language']['language'] }}<br/>
                    <strong>@lang('sigpage.ip'):</strong> {{ $formData['ip_address'] }}<br/>
                    <strong>@lang('sigpage.gps'):</strong> {{ $formData['gps_coords'] }}
                </td>
            </tr>
            <tr>
                <td colspan="5">
                <h2>@lang('sigpage.accts')</h2>
                </td>
            </tr>
            <tr>
                <td width="10%"><strong>@lang('sigpage.type')</strong></td>
                <td><strong>@lang('sigpage.idents')</strong></td>
                <td><strong>@lang('sigpage.bill_name')</strong></td>
                <td><strong>@lang('sigpage.addr')</strong></td>
                <td><strong>@lang('sigpage.product')</strong></td>
            </tr>
            <tr><td colspan="5"><hr /></td></tr>
            @foreach($products->toArray() as $product)
                <tr class="seperated">
                    <td>{{ $product['utility_supported_fuel']['utility_fuel_type']['utility_type'] }}</td>
                    <td>
                        @foreach($product['identifiers'] as $ident)
                            {{ $ident['identifier'] }} ({{ $ident['utility_account_type']['account_type'] }})<br/>
                        @endforeach
                    </td>
                    <td>{{ $product['bill_first_name'] . ' ' . $product['bill_middle_name'] . ' ' . $product['bill_last_name'] }}</td>
                    <td>
                        <strong>@lang('sigpage.svc_addr'):</strong>
                        {{ $product['service_address']['address']['line_1'] }}
                        {{ $product['service_address']['address']['line_2'] }}
                        {{ $product['service_address']['address']['city'] }},
                        {{ $product['service_address']['address']['state_province'] }}
                        {{ $product['service_address']['address']['zip'] }}
                        <br />&nbsp;<br />
                        <strong>@lang('sigpage.bill_addr'):</strong>
                        @if($product['billing_address'] == null)
                        @lang('sigpage.bill_same')
                        @else
                            {{ $product['billing_address']['address']['line_1'] }}
                            {{ $product['billing_address']['address']['line_2'] }}
                            {{ $product['billing_address']['address']['city'] }},
                            {{ $product['billing_address']['address']['state_province'] }}
                            {{ $product['billing_address']['address']['zip'] }}
                        @endif
                    </td>
                    <td>
                        <strong>@lang('sigpage.utility'):</strong> {{ $product['utility_supported_fuel']['utility']['name'] }} <br/>
                        <strong>@lang('sigpage.util_cs'):</strong> {{ $product['utility_supported_fuel']['utility']['customer_service'] }} <br/>
                        <strong>@lang('sigpage.product'):</strong> {{ $product['rate']['product']['name'] }}<br/>
                        <strong>@lang('sigpage.pcode'):</strong> {{ $product['rate']['program_code'] }}<br/>

                        @if($product['rate']['intro_rate_amount'] !== null)
                        <strong>@lang('sigpage.intro_rate'):</strong> {{ $product['rate']['intro_rate_amount'] }} {{ $product['rate']['rate_currency']['currency'] }} per {{ $product['rate']['rate_uom']['uom'] }}<br/>
                        <strong>@lang('sigpage.intro_term'):</strong> {{ $product['rate']['product']['intro_term'] }} {{ $product['rate']['product']['intro_term_type']['term_type'] }}<br/>
                        @if($product['rate']['intro_cancellation_fee'] !== null)
                            <strong>@lang('sigpage.intro_cancel'):</strong> ${{ $product['rate']['intro_cancellation_fee'] }} <br />
                        @endif
                        @endif
                        <strong>@lang('sigpage.rate'):</strong>
                            @if($product['rate']['rate_amount'] == null)
                            @lang('sigpage.variable')
                            @else
                            {{ $product['rate']['rate_amount'] }} {{ $product['rate']['rate_currency']['currency'] }} @lang('sigpage.per') {{ $product['rate']['rate_uom']['uom'] }}
                            @endif
                        <br/>
                        <strong>@lang('Term'):</strong> {{ $product['rate']['product']['term'] }} {{ $product['rate']['product']['term_type']['term_type'] }}<br/>
                        @if($product['rate']['cancellation_fee'] !== null)
                            <strong>@lang('sigpage.cancel_fee'):</strong> ${{ $product['rate']['cancellation_fee'] }} <br />
                        @endif
                        @if($product['rate']['product']['daily_fee'] !== null)
                            <strong>@lang('sigpage.daily_fee'):</strong> ${{ $product['rate']['product']['daily_fee'] }} <br/>
                        @endif
                        @if($product['rate']['product']['monthly_fee'] !== null)
                            <strong>@lang('sigpage.monthly_fee'):</strong> ${{ $product['rate']['product']['monthly_fee'] }} <br/>
                        @endif

                        @if($product['rate']['product']['green_percentage'] !== null)
                            <strong>@lang('sigpage.green'):</strong> {{ $product['rate']['product']['green_percentage'] }}%
                        @endif
                        <br/>

                    </td>
                </tr>
                <tr><td colspan="5"><hr /></td></tr>
            @endforeach

            @if($eztpv['signature'] !== null)
            <!-- <tr>
            <td colspan="5"><hr /></td>
            </tr> -->
            <tr>
                <td
                @if($eztpv['signature2'] !== null)
                colspan="3"
                @else
                colspan="5"
                @endif
                >
                    <img width="350" height="125" src="{{ $eztpv['signature'] }}" />
                    <hr width="90%" />
                    @lang('sigpage.cust')&nbsp;
                    {{ $products[0]['auth_first_name'] . ' ' . $products[0]['auth_middle_name'] . ' ' . $products[0]['auth_last_name'] }} &nbsp;
                    {{ $eztpv['signature_date']}}
                </td>
                @if($eztpv['signature2'] !== null)
                <td colspan="2">
                    <img width="350" heigth="125" src="{{ $eztpv['signature2'] }}" />
                    <hr />
                    @lang('sigpage.agent')&nbsp;
                    {{ $event['sales_agent']['user']['first_name'] . ' ' .$event['sales_agent']['user']['last_name'] }} &nbsp;
                    {{ $eztpv['signature2_date'] }}
                </td>
                @endif
            </tr>
            @endif
        </table>
    </body>
</html>
