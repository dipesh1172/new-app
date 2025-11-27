@extends('layouts.app')

@section('title')
Dnis
@endsection

@section('content')
    <div id="dnis-index">
        <dnis-index
            :column-parameter="{{ json_encode(request('column'))}}"
            :direction-parameter="{{ json_encode(request('direction'))}}"
            :page-parameter="{{ json_encode(request('page'))}}"
            :create-url="{{ json_encode(URL::route('dnis.create')) }}"
            :create-url-ext="{{ json_encode(URL::route('dnis.createExternal')) }}"
            :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
            :flash-message="{{ json_encode(session('flash_message')) }}"
            :search-brand-parameter="{{ json_encode(request('brand_id'))}}"
        />
    </div>
@endsection

@section('head')

@endsection

@section('scripts')

@endsection
