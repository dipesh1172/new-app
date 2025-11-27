@extends('layouts.emails')

@section('title')
Green Choice Welcome Letter
@endsection

@section('content')
<table width="100%" cellpadding="0" cellspacing="0" class="main content-wrap">
    <tr>
        <td class="content-block">
            <center><img alt="Green Choice Energy Logo" src="https://tpv-assets.s3.amazonaws.com/gce_logo.png" /></center><br />
        </td>
    </tr>

    <tr>
        @if ($languageId == 2)
        <td class="content-block">
            Querido/a {{ $firstName . ' ' . $lastName }}, <br />

            <br />

            ¡Bienvenido a la familia Green Choice Energy! Estamos encantados de tenerte como cliente y queremos expresarte nuestra más sincera gratitud por elegirnos. <br /><br />

            Aquí en Green Choice Energy, estamos comprometidos a proporcionarte servicios excepcionales y una experiencia sin contratiempos. Estamos aquí para asegurarnos de que tu experiencia con nosotros supere tus expectativas.<br /><br />

            Para ayudarte a empezar, aquí tienes algunos puntos clave para iniciar tu viaje con Green Choice Energy: <br /><br />
            <ol>
                <li><strong>Explora nuestros productos y servicios:</strong> Tómate un momento para navegar por nuestro sitio web y descubrir la gama de productos/servicios que ofrecemos. Desde plantar un árbol en tu honor hasta las robustas Recompensas de Energía Green Choice, tenemos algo para todos.</li>
                <li><strong>Mantente conectado:</strong> Síguenos en las redes sociales para estar al día de las últimas noticias. ¡Nos encanta conectar con nuestros clientes!</li>
                <li><strong>Necesita ayuda?</strong> Nuestro equipo de atención al cliente está aquí para ayudarle. Si tiene alguna pregunta, duda o simplemente quiere saludarnos, no dude en ponerse en contacto con nosotros llamando al <a href="tel:800-685-0960">800-685-0960</a>. Estaremos encantados de ayudarle.</li>
            </ol>
            <br />
            Nos sentimos honrados de tenerte como parte de la familia Green Choice Energy, estamos seguros de que disfrutarás tu experiencia con nosotros.  Gracias por confiarnos tu compromiso con la energía sostenible. Juntos, estamos marcando una diferencia positiva para nuestro planeta y las generaciones futuras. Su decisión de apostar por las energías renovables es un gran paso hacia un mundo más limpio y ecológico. Nos sentimos honrados de que formes parte de la familia Green Choice Energy y esperamos impulsar colectivamente un cambio positivo.
            <br /><br />
            Saludos cordiales,<br /><br />

            Green Choice Energy
        </td>
        @else
        <td class="content-block">
            Dear {{ $firstName . ' ' . $lastName }}, <br />

            <br />

            Welcome to the Green Choice Energy family! We are thrilled to have you as a customer and want to express our sincere gratitude for choosing us. <br /><br />

            Here at Green Choice Energy, we are committed to providing you with exceptional services and a seamless experience. We're here to ensure that your experience with us exceeds your expectations.<br /><br />
            To help you get started, here are a few key points to kick off your journey with Green Choice Energy: <br /><br />
            <ol>
                <li><strong>Explore Our Products/Services:</strong> Take a moment to browse through our website and discover the range of products/services we offer. From planting a tree in your honor or the robust Green Choice energy Rewards, we have something for everyone.</li>
                <li><strong>Stay Connected:</strong> Follow us on social media to stay updated on the latest news. We love connecting with our customers!</li>
                <li><strong>Need Assistance?</strong> Our customer care team is here to assist you. If you have any questions, concerns, or just want to say hello, feel free to reach out to us at <a href="tel:800-685-0960">800-685-0960</a>. We're always happy to help!</li>
            </ol>
            <br />
            We're honored to have you as part of the Green Choice Energy family, we are confident that you'll enjoy your experience with us.  Thank you for entrusting us with your commitment to sustainable energy. Together, we're making a positive difference for our planet and future generations. Your choice to embrace renewable energy is a powerful step toward a cleaner, greener world. We're honored to have you as part of the Green Choice Energy family, and we look forward to collectively driving positive change.
            <br /><br />
            Best regards,
            <br /><br />
            Green Choice Energy
            
        </td>
        @endif
    </tr>
</table>
@endsection
