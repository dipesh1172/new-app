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

            Gracias por elegir a Green Choice Energy como su proveedor minorista de electricidad. ¡Estamos encantados de tenerlo como parte de nuestra familia de clientes y esperamos que esté disfrutando de los beneficios de nuestro servicio!<br /><br />

            Le informamos que el plazo de tarifa fija de su suministro eléctrico vencerá el {{ $rateExpirationDate }}.<br /><br />

            Elija una de nuestras tarifas fijas para Clientes Preferidos hoy llamándonos al <a href="tel:18006850960">1-800-685-0960</a>. Seleccione un plan nuevo de tarifa fija con nosotros antes del {{ $respondByDate }} para asegurarse de que entre en vigor inmediatamente después de la expiración de su tasa fija actual. Nuestra oferta actual de tasa fija es ${{ $rateAmount }} por {{ $rateUom }}. Por favor, llame para conocer los términos.<br /><br />

            Si decide no tomar ninguna medida y deja que su tasa fija actual caduque, su cuenta continuará automáticamente con una tasa variable de mes a mes. Puede cambiar o cancelar su plan en cualquier momento sin penalización comunicándose con nosotros al <a href="tel:18006850960">1-800-685-0960</a>. Nuestra tasa variable mensual puede cambiar cada mes según nuestra evaluación de los costos históricos y proyectados asociados con el servicio de su cuenta, incluidos los costos actuales y estimados de Green Choice para obtener suministro eléctrico al por mayor, ajustes de períodos anteriores costos de inventario y balance, costos de transporte y transmisión incurridos por Green Choice, y otros factores relacionados con el mercado y el negocio, tales como costos administrativos, gastos y márgenes. La tarifa variable puede ser más alta o más baja que cualquier precio ofrecido anteriormente en este contrato o la tarifa actual de su servicio público, no hay límite ni limite en la tarifa variable de un ciclo de facturación al siguiente y no garantizamos ahorros. Puede revisar nuestras tarifas variables actuales y futuras visitando www.GreenChoiceEnergy.com y refiriéndose a la sección específica del Distrito de Columbia ubicada en la parte inferior de la página. Se adjuntan los términos y condiciones para el servicio de suministro de tarifa variable de mes a mes. Si desea terminar su servicio con nosotros sin penalización y no seguir siendo cliente de Green Choice Energy, puede hacerlo llamando al número anterior, enviándonos un correo electrónico a info@greenchoiceenergy.com o enviando una solicitud por escrito a Green Choice Energy, 14 Wall St 2nd Fl Huntington, NY 11743. Si rescinde su contrato de suministro sin seleccionar otro proveedor, volverá al Servicio de Oferta Estándar de Electricidad de su empresa de servicios públicos. Información adicional sobre opciones de suministro de energía está disponible comunicándose con la Comisión de Servicios Públicos en <a href="http://www.dcpsc.org">www.dcpsc.org</a> o <a href="tel:12026265100">202-626-5100</a>.<br /><br />

            Con apreciación,<br /><br />

            El Equipo de Green Choice Energy
        </td>
        @else
        <td class="content-block">
            Dear {{ $firstName }}<br />

            <br />

            Thank you for choosing Green Choice Energy as your retail electricity supplier. We are thrilled to have you as part of our family of customers, and hope that you are enjoying the benefits of our service!<br /><br />

            We would like to inform you that the fixed rate term for your electricity supply will expire on {{ $rateExpirationDate }}.<br /><br />

            Choose one of our Preferred Customer fixed rates today by calling us at <a href="tel:18006850960">1-800-685-0960</a>. Select a new fixed rate plan with us before {{ $respondByDate }} to ensure that it goes into effect immediately upon the expiration of your current fixed rate. Our current fixed rate offer is ${{ $rateAmount }} per {{ $rateUom }}. Please call for terms.<br /><br />

            If you decide to take no action and let your current fixed rate expire, your account will automatically continue on a month-to-month variable rate. You can change or terminate your plan at any time without penalty by contacting us at <a href="tel:18006850960">1-800-685-0960</a>. Our monthly variable rate may change each month based on our assessment of historic and projected costs associated with serving your account including Green Choice’s actual and estimated costs of obtaining wholesale electricity supply, prior period adjustments, inventory and balancing costs, transportation and transmission costs incurred by Green Choice, and other market and business related factors such as administrative costs, expenses, and margins. The variable rate may be higher or lower than any price offered previously on this contract or your utility’s current rate, there is no cap or limit on the variable rate from one billing cycle to the next, and we do not guarantee savings. You can review our current and upcoming variable rates by visiting www. GreenChoiceEnergy.com and referring to the District of Columbia-specific section located at the bottom of the page. The terms and conditions for the month- to-month variable rate supply service are enclosed.If you wish to terminate your service with us without penalty and not remain a Green Choice Energy customer, you may do so by calling the number above, emailing us at info@greenchoiceenergy.com, or sending a written request to Green Choice Energy, 14 Wall St 2nd Fl Huntington, NY 11743. If you terminate your supply agreement without selecting another supplier, you will return to your utility’s Electric Standard Offer Service. Additional information on energy supply choices are available by contacting the Public Service Commission at <a href="http://www.dcpsc.org">www.dcpsc.org</a> or <a href="tel:12026265100">202-626-5100</a>.<br /><br />

            With appreciation,<br /><br />

            The Green Choice Energy Team
        </td>
        @endif
    </tr>
</table>
@endsection
