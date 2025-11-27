@extends('layouts.app')

@section('title')
Contract Generations
@endsection

@section('content')
    <div id="contract-generations">
        <contract-generations
            :search-parameter="{{ json_encode(request('search'))}}"
            :column-parameter="{{ json_encode(request('column'))}}"
            :direction-parameter="{{ json_encode(request('direction'))}}"
            :page-parameter="{{ json_encode(request('page'))}}"
            :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
            :flash-message="{{ json_encode(session('flash_message')) }}"
        />
    </div>
@endsection

@section('head')

@endsection

@section('scripts')

@endsection
