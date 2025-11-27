@extends('layouts.emails')

@section('title')
RPA Welcome
@endsection

@section('content')
<table class="main" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="content-wrap">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="content-block">
                        <center><img alt="TPV.com Logo" src="https://tpv-assets.s3.amazonaws.com/rpa_logo.png" /></center><br />
                    </td>
                </tr>
                <tr>
                    @if($language_id == 2)
                    <td class="content-block">
                        Haga clic en el enlace de abajo para ver los archivos adjuntos de <br>Green Choice Energy</b><br><br>

                        <a class="btn btn-success" target="_blank" href="{{ config('app.urls.clients') }}/rpa/welcome/{{ $event_id }}">Haga clic aquí para proceder</a>
                    </td>
                    @else
                    <td class="content-block">
                        Click the link below to see attachments from <b>Green Choice Energy</b><br><br>

                        <a class="btn btn-success" target="_blank" href="{{ config('app.urls.clients') }}/rpa/welcome/{{ $event_id }}">Click here to proceed</a>
                    </td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
