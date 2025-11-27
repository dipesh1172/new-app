@extends('layouts.app')

@section('title')
Vendors
@endsection

@section('content')
<div id="brand-vendors">
    <brand-vendors 
        :brand="{{ json_encode($brand) }}" 
        :flash-message="{{ json_encode(session('flash_message')) }}" 
        :table-has-actions="{{ json_encode(in_array(session('user')->role_id, array(1, 2, 3))) }}" 
    />
</div>
@endsection
