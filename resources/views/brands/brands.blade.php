@extends('layouts.app')

@section('title')
Brands
@endsection

@section('content')
        <div id="brands-index">
            <brands-index
                :search-parameter="{{ json_encode(request('search'))}}"
                :column-parameter="{{ json_encode(request('column'))}}"
                :direction-parameter="{{ json_encode(request('direction'))}}"
                :page-parameter="{{ json_encode(request('page'))}}"
                :create-url="{{ json_encode(route('brands.create')) }}"
                :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
                :flash-message="{{ json_encode(session('flash_message')) }}"
            />
        </div>
@endsection