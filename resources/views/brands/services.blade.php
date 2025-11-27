@extends('layouts.app')

@section('title')
Brand Services
@endsection

@section('content')
        <div id="brands-utilities-index">
            <brands-services-index
                :brand="{{ json_encode($brand) }}"
                :errors="{{ json_encode($errors->all()) }}"
                :services="{{ json_encode($services) }}"
                :service-types="{{ json_encode($service_types) }}"
                :flash-message="{{ json_encode(session('flash_message')) }}"
            />
        </div>
@endsection
