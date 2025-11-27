@php
if(Auth::check()) {
    $alerts = \App\Models\Alert::where('scope', 'mgmt')->orderBy('created_at', 'DESC')->get();
} else {
    $alerts = []; //session()->all()
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
        <script>window.csrf_token = '{{csrf_token()}}';</script>
        <title>{{ config('app.name', 'Laravel') }} | MGMT | @yield('title')</title>
        <!-- Styles -->
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        <style>
        body {
            background-image: none;
        }
        </style>
        <script>
            window.user = @json(session('user'));
            window.baseContent = {
                user: window.user,
                session: {!! json_encode(Illuminate\Support\Arr::except(session()->all(), ['user'])) !!}
            };
        </script>
        <link rel="apple-touch-icon" href="/img/apple-touch-icon.png" />
        <link rel="apple-touch-icon" sizes="57x57" href="/img/apple-touch-icon-57x57.png" />
        <link rel="apple-touch-icon" sizes="72x72" href="/img/apple-touch-icon-72x72.png" />
        <link rel="apple-touch-icon" sizes="76x76" href="/img/apple-touch-icon-76x76.png" />
        <link rel="apple-touch-icon" sizes="114x114" href="/img/apple-touch-icon-114x114.png" />
        <link rel="apple-touch-icon" sizes="120x120" href="/img/apple-touch-icon-120x120.png" />
        <link rel="apple-touch-icon" sizes="144x144" href="/img/apple-touch-icon-144x144.png" />
        <link rel="apple-touch-icon" sizes="152x152" href="/img/apple-touch-icon-152x152.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon-180x180.png" />
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
                        @yield('content')
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
        @yield('vuescripts')
        <script src="{{ asset('js/app.js') }}"></script>
        @yield('scripts')
    </body>
</html>
