@extends('layouts.app')
@section('content')
<div id="utilities-index">
            <utilities-index
                :create-url="{{ json_encode(URL::route('utilities.createUtility')) }}"
                :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
                :flash-message="{{ json_encode(session('flash_message')) }}"
                :states="{{ json_encode($states) }}"
            />
</div>
@endsection
