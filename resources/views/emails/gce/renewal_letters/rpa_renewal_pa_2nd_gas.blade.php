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
            Estimado {{ $firstName }}<br />

            <br />

            ¡Gracias una vez más por elegir a Green Choice Energy como su proveedor minorista de gas natural! Como se especifica en el primer aviso que le enviamos recientemente, el plazo de su tarifa fija actual vencerá el {{ $respondByDate }}, en cuyo momento su inscripción con Green Choice se renovará automáticamente con una tarifa variable de mes a mes si no selecciona un nuevo plan de tarifa fija con nosotros. Realmente apreciamos su negocio y, como nuestro valioso cliente, hemos preparado esta oferta especial de tarifa fija para usted:<br /><br />

            Nuestro Plan Cliente Preferente: {{ $term }}-mes a tipo fijo a {{ $rateAmount }}¢ por {{ $rateUom }}. *Oferta válida hasta el {{ $respondByDate }}. Los detalles adicionales de esta oferta, incluidos, entre otros, todos los impuestos y tasas aplicables, se facilitarán antes y durante el momento de la afiliación, y posteriormente en cualquier kit de renovación.<br /><br />

            Si no desea elegir esta oferta especial, ¡no se preocupe! Tenga en cuenta que podemos tener otras ofertas de tarifa fija disponibles. Puede inscribirse en el Plan Cliente Preferente o puede revisar sus opciones poniéndose en contacto con nosotros en el <a href="tel:18006850960">1-800-685-0960</a>. Para asegurarse de que cualquier nueva tarifa fija que elija con nosotros entre en vigor inmediatamente después de la expiración de su actual plazo de tarifa fija, seleccione un nuevo plan antes del {{ $respondByDate }}.<br /><br />

            Si prefiere nuestra tarifa variable mes a mes, no tiene que hacer nada, ya que la transición a una tarifa variable se producirá automáticamente si no elige un nuevo plan de tarifa fija. Para su referencia, se adjuntan las Condiciones de servicio de este plan de tarifa variable. La tarifa para su primer mes de servicio con el plan de tarifa variable será de {{ $greenChoiceRate }}, y a partir de entonces la tarifa puede cambiar como se explica en las Condiciones del Servicio. Visite nuestra página web <a href="www.greenchoiceenergy.com">www.green choiceenergy.com</a> para consultar nuestras tarifas históricas. Usted también tiene la opción de seleccionar otro proveedor de energía, o volver a su empresa local de servicios públicos para su suministro de gas natural. Como siempre, su compañía local seguirá leyendo su contador y respondiendo a cualquier emergencia.<br /><br />

            Independientemente del plan que elijas, estamos seguros de que disfrutarás de los beneficios de ser cliente de Green Choice Energy. Gracias de nuevo, ¡y esperamos seguir atendiendo tus necesidades de energía!<br /><br />

            Con aprecio,<br /><br />

            El equipo de Green Choice Energy

            Oficina del Defensor del Consumidor de Pensilvania <a href="tel:18006846560">800.684.6560</a>             <a href="www.oca.state.pa.us">www.oca.state.pa.us</a><br />
            Comisión de Servicios Públicos de Pensilvania      <a href="tel:18006927380">800.692.7380</a>             <a href="www.puc.state.pa.us">www.puc.state.pa.us</a><br />
            <a href="www.pagasswitch.com">www.pagasswitch.com</a>
            
        </td>
        @else
        <td class="content-block">
            Dear {{ $firstName }}<br />

            <br />

            Thank you once again for choosing Green Choice Energy as your retail natural gas supplier! As specified in the first notice we sent you recently, your current fixed rate term will expire on {{ $respondByDate }}, at which point your enrollment with Green Choice will automatically renew on a month-to-month variable rate if you do not select a new fixed rate plan with us. We truly appreciate your business and, as our valued customer, we’ve prepared this special fixed rate offer for you:<br /><br />

            Our Preferred Customer Plan: {{ $term }}-month fixed rate at {{ $rateAmount }}¢ per {{ $rateUom }}. *Offer valid through {{ $respondByDate }}. Additional details of this offer, including, but not limited to all applicable taxes and fees will be provided prior to, and during, the time of enrollment, and thereafter in any renewal kit.<br /><br />

            If you don’t wish to choose this special offer, don’t worry! Keep in mind that we may have other fixed rate offers available. You can enroll in the Preferred Customer Plan or you can review your options by contacting us at <a href="tel:18006850960">1-800-685-0960</a>. To ensure that any new fixed rate you choose with us goes into effect immediately after the expiration of your current fixed rate term, select a new plan by {{ $respondByDate }}.<br /><br />

            If you would prefer our month-to-month variable rate, you don’t need to do anything, as the transition to a variable rate will happen automatically if you don’t choose a new fixed rate plan. For your reference, the Terms of Service for this variable rate plan are enclosed. The rate for your first month of service under the variable rate plan will be {{ $greenChoiceRate }}, and thereafter the rate may change as explained in the Terms of Service. Visit our website at <a href="www.greenchoiceenergy.com">www.green choiceenergy.com</a> to view our historical rates. You also have the option of selecting another energy supplier, or returning to your local utility for your natural gas supply. As always, your local utility will continue to read your meter and respond to any emergencies.<br /><br />

            Regardless of which plan you choose, we’re confident you’ll enjoy the benefits of being a Green Choice Energy customer. Thanks again, and we look forward to continuing to serve your energy needs!<br /><br />

            With appreciation,<br /><br />

            The Green Choice Energy Team<br /><br />

            Pennsylvania Office of Consumer Advocate           <a href="tel:18006846560">800.684.6560</a>             <a href="www.oca.state.pa.us">www.oca.state.pa.us</a><br />
            Pennsylvania Public Utility Commission             <a href="tel:18006927380">800.692.7380</a>             <a href="www.puc.state.pa.us">www.puc.state.pa.us</a><br />
            <a href="www.pagasswitch.com">www.pagasswitch.com</a>

        </td>
        @endif
    </tr>
</table>
@endsection
