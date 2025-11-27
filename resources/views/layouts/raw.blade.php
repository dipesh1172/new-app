<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') | {{ config('app.name', 'Laravel') }}</title>
    <link rel="apple-touch-icon" href="/img/apple-touch-icon.png" />
        <link rel="apple-touch-icon" sizes="57x57" href="/img/apple-touch-icon-57x57.png" />
        <link rel="apple-touch-icon" sizes="72x72" href="/img/apple-touch-icon-72x72.png" />
        <link rel="apple-touch-icon" sizes="76x76" href="/img/apple-touch-icon-76x76.png" />
        <link rel="apple-touch-icon" sizes="114x114" href="/img/apple-touch-icon-114x114.png" />
        <link rel="apple-touch-icon" sizes="120x120" href="/img/apple-touch-icon-120x120.png" />
        <link rel="apple-touch-icon" sizes="144x144" href="/img/apple-touch-icon-144x144.png" />
        <link rel="apple-touch-icon" sizes="152x152" href="/img/apple-touch-icon-152x152.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon-180x180.png" />

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @yield('extra-styles')

    <!-- Scripts -->
    <script>
        window.Laravel = {!! json_encode([
            'csrfToken' => csrf_token(),
        ]) !!};

        window.user = @json(session('user'));

    </script>

    <style>
    body {
        background-image: none;
        background-color: #FFFFFF;
    }
    </style>
    @if(config('app.env') == 'production')
        <script type="text/javascript">
            var rumMOKey='d7f12188036866afa9cc9545e2064a2e';
            (function(){
                if(window.performance && window.performance.timing && window.performance.navigation) {
                    var site24x7_rum_beacon=document.createElement('script');
                    site24x7_rum_beacon.async=true;
                    site24x7_rum_beacon.setAttribute('src','//static.site24x7rum.com/beacon/site24x7rum-min.js?appKey='+rumMOKey);
                    document.getElementsByTagName('head')[0].appendChild(site24x7_rum_beacon);
                }
            })(window);
        </script>
        @endif
</head>
<body>
    <div id="app" class="container">
        @yield('content')
    </div>

    <!-- Scripts -->
    <!-- <script src="{{ asset('js/app.js') }}"></script> -->

    @yield('extra-scripts')
</body>
</html>
