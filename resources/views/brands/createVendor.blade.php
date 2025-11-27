@extends('layouts.app')

@section('title')
Add Vendor
@endsection

@section('content')
<div id="add-brand-vendor"> 
    <add-brand-vendor 
        :errors="{{ json_encode($errors->all()) }}"
        :flash-message="{{  json_encode(session('flash_message')) }}"
        :vendors="{{ json_encode($vendors) }}"
        :brand="{{ json_encode($brand) }}"
        :states="{{ json_encode($states) }}"
    />
</div>
@endsection