@extends('layouts.emails')

@section('title')
Invoice
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            <center><img src="https://tpv-assets.s3.amazonaws.com/tpv-new-220x120.png" /></center><br />

                            <center><b>{{ $brand_name }}</b></center><br />

                            <center>Attached is your <b>{{ $start_date }}</b> thru <b>{{ $end_date}}</b> invoice.</center>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
