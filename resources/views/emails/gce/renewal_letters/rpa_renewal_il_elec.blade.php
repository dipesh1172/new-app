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
            {{ $currentDate }}<br /><br />
            {{ $firstName }} {{ $lastName }}<br />
            {{ $address }}<br />
            {{ $city }}, {{ $state }} {{ $zip }}<br /><br />

            <div align="center">Anuncio de renovación de contrato</div>

            Estimado, {{ $firstName }},<br />

            <br />

            Gracias por elegir a Green Choice Energy como su proveedor minorista de electricidad.<br /><br />

            ¡Estamos encantados de que forme parte de nuestra familia de clientes y esperamos que disfrute de las ventajas de nuestro servicio!<br /><br />

            <strong>
                Le informamos de que el plazo de su actual tipo de interés fijo expirará el {{ $rateExpirationDate }}, en ese momento su cuenta continuará automáticamente bajo una oferta especial de tarifa fija que hemos preparado para usted como nuestro valioso cliente, cuyo negocio apreciamos.
            </strong>

            <br /><br />

            A continuación, se ofrece una comparación de los tipos fijos actuales y los de renovación:<br /><br />

            <table>
                <tr>
                    <td>Tarifa actual: {{ $rateAmount }} centavos/kWh</td>
                    <td>Nueva tarifa: {{ $renewalRate }} centavos/kWh</td>
                </tr>
            </table>

            <br />

            Su tarifa fija existente no cambiará antes del vencimiento de su plan de tarifa fija existente. Su nuevo plan fijo entrará en vigor en el primer ciclo de facturación tras el vencimiento de su actual tarifa fija. No se cobrará ninguna cuota de cancelación anticipada.<br /><br />

            Si no desea elegir esta oferta, póngase en contacto con nosotros para conocer otras opciones disponibles.<br /><br />

            Puede revisar sus opciones poniéndose en contacto con nosotros en el <a href="tel:18006850960">1-800-685-0960</a>. Si no desea renovar su contrato, debe llamarnos antes del {{ $respondByDate }} al <a href="tel:18006850960">1-800-685-0960</a>. Si desea rescindir su contrato después del inicio del plazo de renovación, puede hacerlo poniéndose en contacto con nosotros en el <a href="tel:18006850960">1-800-685-0960</a>.<br /><br />

            ¡Agradecemos sinceramente su confianza y esperamos seguir atendiendo sus necesidades energéticas!<br /><br />

            Si desea presentar una consulta o queja como consumidor, puede hacerlo poniéndose en contacto con la Comisión de Comercio de Illinois en el teléfono <a href="tel:18005240795">1-800-524-0795</a> o en línea visitando <a href="www.icc.illinois.gov">www.icc.illinois.gov</a> o con el Fiscal General de Illinois en el teléfono <a href="tel:18003865438">1-800-386-5438</a> o en línea visitando, <a href="www.illinoisattorneygeneral.gov">www.illinoisattorneygeneral.gov</a>.<br /><br />

            Con aprecio,<br /><br />

            El equipo de Green Choice Energy.
        </td>
        @else
        <td class="content-block">
            {{ $currentDate }}<br /><br />
            {{ $firstName }} {{ $lastName }}<br />
            {{ $address }}<br />
            {{ $city }}, {{ $state }} {{ $zip }}<br /><br />

            <div align="center">Contract Renewal Notice</div>

            Dear {{ $firstName }},<br /><br />

            Thank you for choosing Green Choice Energy as your retail electricity supplier.<br /><br />

            We are thrilled to have you as part of our family of customers and hope that you are enjoying the benefits of our service!<br /><br />

            <strong>
                We would like to inform you that your current fixed rate term will expire on {{ $rateExpirationDate }}, at which point your account will automatically continue under a special fixed rate offer which we have prepared for you as our valued customer, whose business we appreciate.   
            </strong>

            <br /><br />

            The following is a side-by-side comparison of your existing and renewal fixed rates:<br /><br />

            <table>
                <tr>
                    <td>Current rate: {{ $rateAmount }} cents/kWh</td>
                    <td>Renewal rate: {{ $renewalRate }} cents/kWh</td>
                </tr>
            </table>

            <br />

            Your existing fixed rate will not change prior to the expiration of your existing fixed rate plan. Your new fixed plan will take effect in the first billing cycle after the expiration of your existing fixed rate term. There is no early termination fee to cancel.<br /><br />

            If you don’t wish to choose this offer, please contact us for any other available options.<br /><br />

            You can review your options by contacting us at <a href="tel:18006850960">1-800-685-0960</a>. If you do not wish your contract to be renewed you must call us by {{ $respondByDate }} at <a href="tel:18006850960">1-800-685-0960</a>. If you desire to terminate your contract after the beginning of your renewal term, you can do so by contacting us at <a href="tel:18006850960">1-800-685-0960</a>.<br /><br />

            We truly appreciate your business and look forward to continuing to serve your energy needs!<br /><br />

            If you wish to submit a consumer inquiry or complaint, you can do so by contacting the Illinois Commerce Commission at <a href="tel:18005240795">1-800-524-0795</a> or online by visiting <a href="www.icc.illinois.gov">www.icc.illinois.gov</a> or the Illinois Attorney General at <a href="tel:18003865438">1-800-386-5438</a> or online by visiting, <a href="www.illinoisattorneygeneral.gov">www.illinoisattorneygeneral.gov</a>.

            With appreciation,<br /><br />

            The Green Choice Energy Team
        </td>
        @endif
    </tr>
</table>
@endsection
