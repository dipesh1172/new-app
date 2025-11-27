@extends('layouts.app')

@section('title')
Edit Brand {{ $brand->name }}
@endsection

@section('content')
<div id="bgcheck">
    <bgcheck
        :errors="{{ json_encode($errors->all()) }} || undefined" 
        :brand="{{ json_encode($brand) }} || undefined"
        :creds="{{ json_encode($creds) }} || undefined"
        :providers="{{ json_encode($providers) }} || undefined"
        :flash-message="{{ json_encode(session('flash_message')) }}"
    />
</div>
@endsection