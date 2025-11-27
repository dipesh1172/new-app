@extends('layouts.app')

@section('title')
Edit Login Landing page
@endsection

@section('content')
<div id="login-landing">
    <login-landing
        :brand="{{ json_encode($brand) }}"
        :vendor="{{ json_encode($vendor) }}"
        :ips="{{ json_encode($ips) }}"
        :slug="{{ json_encode(@$portal->slug) }} || undefined"
        :env-tpv-clients="{{ json_encode(config('app.urls.clients')) }}"
        :errors="{{ json_encode($errors->all()) }}"
        :flash-message="{{ json_encode(session('flash_message')) }}"
    />
</div>
@endsection
