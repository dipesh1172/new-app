@extends('layouts.app')

@section('title')
Contract Pages
@endsection

@section('content')
    <div id="contract-pages">
        <contract-pages
            :contract-config="{{ json_encode($contractConfig) }}"
            :contract-config-pages="{{ json_encode($list) }}"
            :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
            :flash-message="{{ json_encode(session('flash_message')) }}"
            :errors="{{ json_encode($errors->all()) }} || undefined" 
        />
    </div>
@endsection

@section('head')

@endsection

@section('vuescripts')

@endsection
