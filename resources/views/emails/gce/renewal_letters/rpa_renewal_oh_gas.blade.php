@extends('layouts.emails')

@section('title')
RPA Welcome
@endsection

@section('content')
<table width="100%" cellpadding="0" cellspacing="0" class="main content-wrap">
    <tr>
        <td class="content-block">
            <center><img alt="TPV.com Logo" src="https://tpv-assets.s3.amazonaws.com/rpa_logo.png" /></center><br />
        </td>
    </tr>

    <tr>
        @if ($languageId == 2)
        <td class="content-block">
            Querido/a {{ $firstName }}<br />

            <br />

            Gracias por elegir a Green Choice Energy como su proveedor minorista de gas natural.<br /><br />

            ¡Estamos encantados de tenerlo como parte de nuestra familia de clientes y esperamos que esté disfrutando de los beneficios de nuestro servicio!<br /><br />

            Nos gustaría informarle que el plazo de tarifa fija para su suministro de gas natural vencerá el {{ $rateExpirationDate }}.<br /><br />

            Elija una de nuestras tarifas fijas para Clientes Preferidos hoy llamándonos al <a href="tel:18006850960">1-800-685-0960</a>. Seleccione un plan nuevo de tarifa fija con nosotros antes de {{ $respondByDate }} para asegurarse de que entre en efecto inmediatamente después de la expiración de su tasa fija actual.<br /><br />

            Si decide no tomar ninguna medida y deja que su tasa fija actual caduque, su cuenta continuará automáticamente con una tasa variable de mes a mes, que puede cambiar en cualquier momento sin penalización ni cargo por cancelación anticipada.<br /><br />

            ¡Realmente apreciamos su negocio y esperamos continuar satisfaciendo sus necesidades energéticas!<br /><br />

            Con apreciación,<br /><br />

            El Equipo de Green Choice Energy
        </td>
        @else
        <td class="content-block">
            Dear {{ $firstName }}<br />

            <br />

            Thank you for choosing Green Choice Energy as your retail natural gas supplier.<br /><br />

            We are thrilled to have you as part of our family of customers, and hope that you are enjoying the benefits of our service!<br /><br />

            We would like to inform you that the fixed rate term for your natural gas supply will expire on {{ $rateExpirationDate }}.<br /><br />

            Choose one of our Preferred Customer fixed rates today by calling us at <a href="tel:18006850960">1-800-685-0960</a>. Select a new fixed rate plan with us before {{ $respondByDate }} to ensure that it goes into effect immediately upon the expiration of your current fixed rate.<br /><br />

            If you decide to take no action and let your current fixed rate expire, your account will automatically continue on a month-to-month variable rate, which you can change at any time without penalty or early termination fee.<br /><br />

            We truly appreciate your business, and look forward to continuing to serve your energy needs!<br /><br />

            With appreciation,<br /><br />

            The Green Choice Energy Team
        </td>
        @endif
    </tr>
</table>
@endsection
