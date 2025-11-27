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
            <div align="center">AVISO IMPORTANTE DE RENOVACIÓN</div>
            <br /><br />

            Estimado {{ $firstName }}<br />

            <br />

            Gracias por elegir a Green Choice Energy como su proveedor minorista de gas natural.<br /><br />

            ¡Estamos emocionados de tenerte como parte de nuestra familia de clientes y esperamos que estés disfrutando de los beneficios de nuestro servicio!<br /><br />

            Nos gustaría informarle que el plazo con tasa fija para su suministro de gas natural expirará el {{ $rateExpirationDate }}. Su contrato actual no tiene gastos de cancelación.<br /><br />

            Elija hoy mismo una de nuestras tarifas fijas para Clientes Preferentes llamándonos al <a href="tel:18006850960">1-800-685-0960</a>. Seleccione un nuevo plan de tarifa fija con nosotros antes de {{ $respondByDate }} para que entre en vigor inmediatamente después de que expire su tipo fijo actual.<br /><br />

            60 días antes de que venza el plazo de su actual tarifa fija, le enviaremos otra carta recordándole sus opciones. Si decide no hacer nada y dejar que venza su actual tipo fijo, su cuenta continuará automáticamente con un tipo variable mes a mes, que podrá cambiar en cualquier momento sin penalización ni comisión por cancelación anticipada.<br /><br />

            Agradecemos sinceramente su confianza y esperamos seguir atendiendo sus necesidades energéticas.<br /><br />

            Con mucho aprecio,<br /><br />

            El equipo de Green Choice Energy
        </td>
        @else
        <td class="content-block">
            <div align="center">IMPORTANT RENEWAL NOTICE</div>
            <br /><br />

            Dear {{ $firstName }}<br />

            <br />

            Thank you for choosing Green Choice Energy as your retail natural gas supplier.<br /><br />

            We are thrilled to have you as part of our family of customers and hope that you are enjoying the benefits of our service!<br /><br />

            We would like to inform you that the fixed rate term for your natural gas supply will expire on {{ $rateExpirationDate }}. Your current contract has no cancellation fee. <br /><br />

            Choose one of our Preferred Customer fixed rates today by calling us at <a href="tel:18006850960">1-800-685-0960</a>. Select a new fixed rate plan with us before {{ $respondByDate }} to ensure that it goes into effect immediately upon the expiration of your current fixed rate.<br /><br />

            60 days prior to the expiration of your existing fixed rate term, we will send you another letter reminding you of your options. If you decide to take no action and let your current fixed rate expire, your account will automatically continue on a month-to-month variable rate, which you can change at any time without penalty or early termination fee.<br /><br />

            We truly appreciate your business, and look forward to continuing to serve your energy needs!<br /><br />

            With appreciation,<br /><br />

            The Green Choice Energy Team
        </td>
        @endif
    </tr>
</table>
@endsection
