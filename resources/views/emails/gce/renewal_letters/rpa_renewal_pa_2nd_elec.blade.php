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

            ¡Gracias una vez más por elegir a Green Choice Energy como su proveedor de electricidad! Como se especifica en el primer aviso que le enviamos recientemente, su plazo de tasa fija actual vencerá el {{ $respondByDate }}, en el cual su cuenta pasará a una tasa variable de mes a mes si no selecciona un plan nuevo de tasa fija con nosotros. Realmente apreciamos su negocio y, como nuestro valioso cliente, hemos preparado esta oferta especial de tarifa fija para usted:<br /><br />

            Nuestro plan de Cliente Preferido: {{ $term }}-tasa fija mensual a {{ $rateAmount}}¢ por {{ $rateUom }}. *Oferta válida hasta {{ $respondByDate }}. Los detalles adicionales de esta oferta, incluidos, entre otros, todos los impuestos y tarifas aplicables, se proporcionarán antes y durante el momento de la inscripción y, posteriormente, en cualquier kit de renovación.<br /><br />

            Si no desea elegir esta oferta especial, no se preocupe! Tenga en cuenta que es posible que tengamos otras ofertas de tarifa fija disponibles. Puede revisar sus opciones comunicándose con nosotros al <a href="tel:18006850960">1-800-685-0960</a>. Para asegurarse de que cualquier nueva tarifa fija que elija con nosotros entre en vigencia inmediatamente después del vencimiento de su plazo de tarifa fija actual, seleccione un plan nuevo antes de {{ $respondByDate }}.<br /><br />

            Si prefiere nuestra tarifa variable de mes a mes, no necesita hacer nada, ya que la transición a una tarifa variable ocurrirá automáticamente si no elige un plan nuevo de tarifa fija. Para su referencia, se adjuntan los Términos de Servicio de este plan de tarifa variable. Visite nuestro sitio web en <a href="www.greenchoiceenergy.com">www.greenchoiceenergy.com</a> para ver nuestras tarifas históricas. También tiene la opción de seleccionar otro proveedor de energía o regresar a su servicio público local para su suministro de electricidad natural. Como siempre, su servicio público local continuará leyendo su medidor y responderá a cualquier emergencia.<br /><br />

            Independientemente del plan que elija, estamos seguros de que disfrutará de los beneficios de ser cliente de Green Choice Energy. Gracias de nuevo y esperamos seguir sirviendo a sus necesidades energéticas!<br /><br />

            Con apreciación,<br /><br />

            El Equipo de Green Choice Energy
        </td>
        @else
        <td class="content-block">
            Dear {{ $firstName }}<br />

            <br />

            Thank you once again for choosing Green Choice Energy as your electricity supplier! As specified in the first notice we sent you recently, your current fixed rate term will expire on {{ $respondByDate }}, at which point your account will move to a month-to-month variable rate if you do not select a new fixed rate plan with us. We truly appreciate your business and, as our valued customer, we’ve prepared this special fixed rate offer for you:<br /><br />

            Our Preferred Customer Plan: {{ $term }}-month fixed rate at {{ $rateAmount }}¢ per {{ $rateUom }}. *Offer valid through {{ $respondByDate }}. Additional details of this offer, including, but not limited to all applicable taxes and fees will be provided prior to, and during, the time of enrollment, and thereafter in any renewal kit.<br /><br />

            If you don’t wish to choose this special offer, don’t worry! Keep in mind that we may have other fixed rate offers available. You can review your options by contacting us at <a href="tel:18006850960">1-800-685-0960</a>. To ensure that any new fixed rate you choose with us goes into effect immediately after the expiration of your current fixed rate term, select a new plan by {{ $respondByDate }}.<br /><br />

            If you would prefer our month-to-month variable rate, you don’t need to do anything, as the transition to a variable rate will happen automatically if you don’t choose a new fixed rate plan. For your reference, the Terms of Service for this variable rate plan are enclosed. Visit our website at <a href="www.greenchoiceenergy.com">www.green choiceenergy.com</a> to view our historical rates. You also have the option of selecting another energy supplier, or returning to your local utility for your natural electricity supply. As always, your local utility will continue to read your meter and respond to any emergencies.<br /><br />

            Regardless of which plan you choose, we’re confident you’ll enjoy the benefits of being a Green Choice Energy customer. Thanks again, and we look forward to continuing to serve your energy needs!<br /><br />

            With appreciation,<br /><br />

            The Green Choice Energy Team
        </td>
        @endif
    </tr>
</table>
@endsection
