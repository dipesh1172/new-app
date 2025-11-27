@extends('layouts.emails')

@section('title')
Send invoice to client
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            <b>{{ $brand_name }}</b><br />
                            Attached is your invoice from TPV.com for the period between {{ $start_date }} and {{ $end_date }}.<br /><br />
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block aligncenter">
                            <a href="{{ $url }}" class="btn-primary">Click here to view your invoice.</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection