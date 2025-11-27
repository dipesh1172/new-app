@extends('layouts.app')

@section('title')
Alerts
@endsection

@section('content')
<alerts
    :errors="{{ json_encode($errors->all()) }}"
    :flash-message="{{ json_encode(session('flash_message')) }}"
/>
@endsection