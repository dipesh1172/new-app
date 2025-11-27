@extends('layouts.app')

@section('title')
Utilities
@endsection

@section('content')
        <div id="brands-utilities-index">
            <brands-utilities-index
                :brand="{{ json_encode($brand) }}"
                :column-parameter="{{ json_encode(request('column'))}}"
                :direction-parameter="{{ json_encode(request('direction'))}}"
                :page-parameter="{{ json_encode(request('page'))}}"
                :create-url="{{ json_encode(URL::route('brands.createUtilityForBrand', [$brand])) }}"
                :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
                :flash-message="{{ json_encode(session('flash_message')) }}"
            />
        </div>
@endsection