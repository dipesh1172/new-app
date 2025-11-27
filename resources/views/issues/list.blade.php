@extends('layouts.app')

@section('content')
<div id="issues-list">
    <issues-list 
        :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
        :flash-message="{{ json_encode(session('flash_message')) }}"
    />
</div>
@endsection

@section('scripts')
@endsection
