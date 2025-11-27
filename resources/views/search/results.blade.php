@extends('layouts.app')

@section('title')
Search
@endsection

@section('content')
<div id="search-results">
    <search-results 
        :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
        :flash-message="{{ json_encode(session('flash_message')) }}"
    />
</div>
@endsection
