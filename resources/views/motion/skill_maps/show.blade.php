@extends('layouts.app')

@section('title')
Motion Skills Mapping
@endsection

@section('content')
        <div id="motion-skill-maps">
            <motion-skill-maps
                :search-parameter="{{ json_encode(request('search'))}}"
                :column-parameter="{{ json_encode(request('column'))}}"
                :direction-parameter="{{ json_encode(request('direction'))}}"
                :page-parameter="{{ json_encode(request('page'))}}"
                :create-url="{{ json_encode(route('motion_skill_maps.create')) }}"
                :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
                :flash-message="{{ json_encode(session('flash_message')) }}"
            />
        </div>
@endsection
