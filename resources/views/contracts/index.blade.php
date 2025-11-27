@extends('layouts.app')

@section('title')
Contracts
@endsection

@section('content')
    <div id="contracts-index">
        <contracts-index
            :search-parameter="{{ json_encode(request('search'))}}"
            :search-field-parameter="{{ json_encode(request('searchField'))}}"
            :channel-parameter="{{ json_encode(request('channel')) }}"
            :brand-parameter="{{ json_encode(request('brandId')) }}"
            :language-parameter="{{ json_encode(request('language')) }}"
            :sale-type-parameter="{{ json_encode(request('saleType')) }}"
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

@section('vuescripts')

@endsection
