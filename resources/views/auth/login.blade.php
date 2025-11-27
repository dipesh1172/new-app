@php
$no_menu = true;
@endphp
@extends('layouts.app')

@section('title')
Login
@endsection

@section('content')
<div class="app flex-row align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card-group mb-0">
                    <div class="card">
                        <img class="card-img-top" src="img/logo.svg" alt="TPV.com Speech Bubble Logo" />
                        <div class="card-body">
                            <form method="POST" id="login-form" action="{{ route('login') }}" autocomplete="off">
                                <h2 class="text-center">Authorized Personnel Only</h2>
                                {{ csrf_field() }}
                                @if ($errors->has('username'))
                                    <div class="alert alert-danger">
                                        {{ $errors->first('username') }}
                                    </div>
                                @endif

                                <div class="input-group mb-3{{ $errors->has('username') ? ' has-danger' : '' }}">
                                    <label for="username" class="sr-only">Username</label>
                                    <span class="input-group-addon"><i class="fa fa-2x fa-user-circle"></i></span>
                                    <input type="text" id="username" autocomplete="username" name="username" value="{{ old('username') }}" class="form-control form-control-lg" placeholder="Username" required autofocus>
                                </div>

                                <div class="input-group mb-4{{ $errors->has('password') ? ' has-error' : '' }}">
                                    <label for="password" class="sr-only">Password</label>
                                    <span class="input-group-addon"><i class="fa fa-2x fa-key fa-rotate-90"></i></span>
                                    <input type="password" id="password" autocomplete="current-password" name="password" class="form-control form-control-lg" placeholder="Password">
                                </div>

                                <div class="row">
                                    <div class="col-6"></div>
                                    <div class="col-6">
                                        <button type="submit" class="btn btn-lg btn-primary px-4 pull-right">Login <i class="fa fa-sign-in"></i></button>
                                    </div>

                                </div>
                            </form>
                        </div>
                        <div class="card-footer text-center">
                            Information contained within this system is confidential and the property of TPV.com Powered by DataExchange.
                            Unauthorized access or use is prohibited.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('head')
<style>
body {
    background-image: url('../img/topography.png');
}
</style>
@endsection

@section('scripts')
<script>
    (() => {
        var form = document.getElementById('login-form');
        form.onsubmit = (e) => {
            var username = document.getElementById('username').value.trim();
            var pword = document.getElementById('password').value.trim();
            if(username == '' || pword == '') {
                e.preventDefault();
                if(username != '' && pword == '') {
                    document.getElementById('password').focus();
                }
                return false;
            }
        };
    })();
</script>
@endsection
