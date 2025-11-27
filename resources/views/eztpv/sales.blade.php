@extends('layouts.app')

@section('title')
EZTPV Sales
@endsection

@section('content')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">EZTPV Sales</li>
    </ol>

    <div class="container-fluid">
        <div id="sales-index">
            <sales-index
                :search-parameter="{{ json_encode(request('search'))}}"
                :column-parameter="{{ json_encode(request('column'))}}"
                :direction-parameter="{{ json_encode(request('direction'))}}"
                :page-parameter="{{ json_encode(request('page'))}}"
                :create-url="{{ json_encode(route('brands.create')) }}"
                :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
                :flash-message="{{ json_encode(session('flash_message')) }}"
            />
        </div>
    </div>
@endsection

@section('head')

@endsection

@section('scripts')

@endsection
