@extends('layouts.emails')

@section('title')
Green Choice Welcome
@endsection

@section('content')
<table class="main" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="content-wrap">
            <table width="100%" cellpadding="0" cellspacing="0"> 
                <tr>
                    <td class="content-block">
                        <img alt="TPV.com Logo" src="https://d2ccd9k9w09qtp.cloudfront.net/uploads/brands/7b08b19d-32a5-4906-a320-6a2c5d6d6372/logos/2021/03/01/93495bd5e65e8e3b8f6fc2b3104be2f1.png" /><br />
                    </td>
                </tr>
                <tr>
                    @if($language_id == 2)
                    <td class="content-block">
                        Haga clic en el enlace de abajo para ver los archivos adjuntos de <br>Green Choice Energy</b><br><br>
                        <a class="btn btn-success" target="_blank" href="{{ config('app.urls.clients') }}/rpa/welcomevariable/{{ $event_id }}/{{$email_address}}/{{$account_number}}">Haga clic aquí para proceder</a>
                    </td>
                    @else
                    <td class="content-block">
                        Click the link below to see attachments from <b>Green Choice Energy</b><br><br>
                        <a class="btn btn-success" target="_blank" href="{{ config('app.urls.clients') }}/rpa/welcomevariable/{{ $event_id }}/{{$email_address}}/{{$account_number}}">Click here to proceed</a>
                    </td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection