@extends('layouts.app')

@section('content')
<div id="errors">
    <errors 
        :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
        :flash-message="{{ json_encode(session('flash_message')) }}"
    />
</div>
@endsection
