@extends('layouts.app')

@section('title')
Clients
@endsection

@section('content')
<div id="clients">
    <clients 
        :flash-message="{{ json_encode(session('flash_message')) }}" 
        :column-parameter="{{ json_encode(request('column'))}}"
        :direction-parameter="{{ json_encode(request('direction'))}}"
        :page-parameter="{{ json_encode(request('page'))}}"
        :search-parameter="{{ json_encode(request('search'))}}"
    />
</div>
@endsection
