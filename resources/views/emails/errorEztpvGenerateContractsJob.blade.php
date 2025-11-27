@extends('layouts.emails')

@section('title')
Error during MGMT eztpv:generateContracts job
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            An error occurred while processing EzTPV Contracts. Details below:
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block">
                            Step = {{$step}}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block">
                            Additional details = {{ print_r(@$additional) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block">
                            Command Options:
                            <br>
                            <pre>
                                {{print_r($command_options)}}
                            </pre>
                        </td>
                    </tr><tr>
                        <td class="content-block">
                            eztpvs.id = {{$eztpv_id}}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block">
                            events.confirmation_code = {{$event_confirmation_code}}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block">
                            Company name: {{ $company }}<br>
                            State: {{ @$state }}<br>
                            Channel: {{ @$channel }}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block">
                            Product(s):<br>
                            @if (isset($products))
                            @foreach ($products as $product)
                                {{ $product->name }} ({{ $product->id }})<br>
                            @endforeach
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block">
                            Queries:<br>
                            <table>
                                @if (isset($query))
                                    @foreach ($query as $q)
                                        <tr>
                                            <td>Query:</td><td>{{ $q['query'] }}</td>
                                        </tr>
                                        <tr>
                                            <td>Bindings:</td><td><pre>{{ print_r($q['bindings']) }}</td></pre>
                                        </tr>
                                    @endforeach
                                @else
                                        <tr>
                                            <td>No queries found.</td>
                                        </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block">
                            Data:
                            <br>
                            <pre>
                                {{print_r($data)}}
                            </pre>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
