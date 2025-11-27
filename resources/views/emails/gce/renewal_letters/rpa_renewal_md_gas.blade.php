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

            Gracias por elegir a Green Choice Energy como su proveedor minorista de gas natural. Estamos encantados de tenerlo como parte de nuestra familia de clientes y esperamos que esté disfrutando de los beneficios de nuestro servicio.<br /><br />

            Nos gustaría informarle que el plazo de la tasa fija para su suministro de gas natural vencerá el {{ $rateExpirationDate }}.<br /><br />

            Elija una de nuestras tarifas fijas para Clientes Preferidos hoy llamándonos al <a href="tel:18006850960">1-800-685-0960</a>. Seleccione un plan nuevo de tarifa fija con nosotros antes de {{ $respondByDate }} para asegurarse de que entre en vigencia inmediatamente después de que expire su tarifa fija actual. Nuestra oferta de tasa fija actual es ${{ $rateAmount }} por {{ $rateUom }}. Por favor, llame para conocer los términos.<br /><br />

            Si decide no tomar ninguna medida y deja que su tasa fija actual caduque, su cuenta continuará automáticamente con una tasa variable de mes a mes. Puede cambiar o cancelar su plan en cualquier momento sin penalización. Nuestra tasa variable mensual puede cambiar cada mes según nuestra evaluación del costo histórico y proyectado asociado con el servicio de su cuenta. La tarifa variable puede ser más alta o más baja que cualquier precio ofrecido anteriormente en este contrato o la tarifa actual de su servicio público, no hay límite en la tarifa variable de un ciclo de facturación al siguiente y no garantizamos ahorros. Puede revisar nuestras tarifas variables actuales y futuras visitando <a href="www.GreenChoiceEnergy.com">www.GreenChoiceEnergy.com</a> y refiriéndose a la sección específica de Maryland ubicada en la parte inferior de la página.<br /><br />

            Si desea terminar su servicio con nosotros sin penalización y no seguir siendo un cliente de Green Choice Energy, puede hacerlo llamando al número anterior, enviándonos un correo electrónico a info@greenchoiceenergy.com o enviando una solicitud por escrito a Green Choice Energy, 14 Wall St 2nd FL Huntington, NY 11743. Si rescinde su contrato de suministro sin seleccionar otro proveedor, volverá al servicio básico de su utilidad.<br /><br />

            Con apreciación,<br /><br />

            El Equipo de Green Choice Energy
        </td>
        @else
        <td class="content-block">
            Dear {{ $firstName }}<br />

            <br />

            Thank you for choosing Green Choice Energy as your retail natural gas supplier. We are thrilled to have you as part of our family of customers, and hope that you are enjoying the benefits of our service!<br /><br />

            We would like to inform you that the fixed rate term for your natural gas supply will expire on {{ $rateExpirationDate }}.<br /><br />

            Choose one of our Preferred Customer fixed rates today by calling us at <a href="tel:18006850960">1-800-685-0960</a>. Select a new fixed rate plan with us before {{ $respondByDate }} to ensure that it goes into effect immediately upon the expiration of your current fixed rate. Our current fixed rate offer is ${{ $rateAmount }} per {{ $rateUom }}. Please call for terms.<br /><br />

            If you decide to take no action and let your current fixed rate expire, your account will automatically continue on a month-to-month variable rate. You can change or terminate your plan at any time without penalty. Our monthly variable rate may change each month based on our assessment of historic and projected cost associated with serving your account. The variable rate may be higher or lower than any price offered previously on this contract or your utility’s current rate, there is no cap or limit on the variable rate from one billing cycle to the next, and we do not guarantee savings. You can review our current and upcoming variable rates by visiting <a href="www.GreenChoiceEnergy.com">www.GreenChoiceEnergy.com</a> and referring to the Maryland-specific section located at the bottom of the page.<br /><br />

            If you wish to terminate your service with us without penalty and not remain a Green Choice Energy customer, you may do so by calling the number above, emailing us at info@greenchoiceenergy.com, or sending a written request to Green Choice Energy, 14 Wall St 2nd FL Huntington, NY 11743. If you terminate your supply agreement without selecting another supplier, you will return to your utility’s commodity service.<br /><br />

            With appreciation,<br /><br />

            The Green Choice Energy Team
        </td>
        @endif
    </tr>
</table>
@endsection
