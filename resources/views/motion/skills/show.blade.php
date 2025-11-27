@extends('layouts.app')

@section('title')
Motion Skills
@endsection

@section('content')
        <div id="motion-skills">
            <motion-skills
                :search-parameter="{{ json_encode(request('search'))}}"
                :column-parameter="{{ json_encode(request('column'))}}"
                :direction-parameter="{{ json_encode(request('direction'))}}"
                :page-parameter="{{ json_encode(request('page'))}}"
                :create-url="{{ json_encode(route('motion_skills.create')) }}"
                :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
                :flash-message="{{ json_encode(session('flash_message')) }}"
            />
        </div>
@endsection
