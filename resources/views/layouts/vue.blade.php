@php
if(Auth::check()) {
$alerts = \App\Models\Alert::where('scope', 'mgmt')->orderBy('created_at', 'DESC')->get();
} else {
$alerts = [];
}
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Whether you are dealing with employee on boarding, property leasing, or loan closing, Third party verification (TPV) is your best option.">
    <link rel="shortcut icon" href="/img/favicon.ico">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        const csrf_token = '{{csrf_token()}}';
    </script>
    <script>
        window.user = @json(session('user'));
    </script>
    <title>TPV.com | @yield('title')</title>
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
    <style>
        body {
            background-image: none;
        }
    </style>
    @if(config('app.env') == 'production')
    <script type="text/javascript">
        var rumMOKey = 'd7f12188036866afa9cc9545e2064a2e';
        (function() {
            if (window.performance && window.performance.timing && window.performance.navigation) {
                var site24x7_rum_beacon = document.createElement('script');
                site24x7_rum_beacon.async = true;
                site24x7_rum_beacon.setAttribute('src', '//static.site24x7rum.com/beacon/site24x7rum-min.js?appKey=' + rumMOKey);
                document.getElementsByTagName('head')[0].appendChild(site24x7_rum_beacon);
            }
        })(window);
    </script>
    @endif
    @yield('head')
</head>

<body>
    <div id="app">
        @if(!isset($no_menu))
        <div class="app header-fixed sidebar-fixed aside-menu-fixed aside-menu-hidden">
            @include('header')
            <div class="app-body">
                @include('sidebar')
                <!-- Main content -->
                <main class="main">
                    @endif
                    @foreach($alerts as $alert)
                    <div class="alert alert-warning">
                        <strong>{{$alert->title}}</strong>
                        <br>
                        {!! $alert->alert !!}
                    </div>
                    @endforeach
                    <div id="main-content"></div>
                    @if(!isset($no_menu))
                </main>
                {{-- <div id="chat-app">
                        @if (runtime_setting('chat_enabled') == '1')
                            <chat-app :user="{{ auth()->user() }}"></chat-app>
                @else
                <span>Chat is currently offline.</span>
                @endif
            </div> --}}
        </div>
        @include('footer')
    </div>
    @endif
    </div>
    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}"></script>
    @if(isset($javascriptVariables) && count($javascriptVariables) > 0)
    <script>
        @foreach($javascriptVariables as $jsvar)
        window.{{ $jsvar['name'] }} = {!! json_encode($jsvar['value']) !!};
        @endforeach
    </script>
    @endif
    @if(isset($scriptFiles) && count($scriptFiles) > 0)
    @foreach ($scriptFiles as $f)
    <script src="{{ asset($f) }}"></script>
    @endforeach
    @endif
    <script src="{{ asset('js/apps/'.$appName.'/index.js') }}"></script>
</body>

</html>
