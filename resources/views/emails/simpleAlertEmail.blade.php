@extends('layouts.emails')

@section('title')
Alert
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            <center><img alt="TPV.com Logo" src="https://tpv-assets.s3.amazonaws.com/tpv-new-220x120.png" /></center><br />

                            <center><b>{!! $alert_name !!}</b></center><br />
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block aligncenter">
                            {!! $body_text !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
