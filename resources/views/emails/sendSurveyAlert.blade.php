@extends('layouts.emails')

@section('title')
Survey File
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block left">
                            Sales Agent Name:
                        </td>
                        <td>
                            {{$sales_agent_name}}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block left">
                           Customer First Name:
                        </td>
                        <td>
                            {{$bill_first_name}}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block left">
                            Customer Last Name:
                        </td>
                        <td>
                            {{$bill_last_name}}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block left">
                            Phone Number:
                        </td>
                        <td>
                            {{$phone_number}}
                        </td>
                    </tr>
                    @foreach ($questions as $q)
                    <tr>
                        <td class="content-block left">
                            Question: {{ $q['question'] }}
                        </td>
                        <td>
                            Response: {{ $q['response'] }}
                        </td>
                    </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
@endsection