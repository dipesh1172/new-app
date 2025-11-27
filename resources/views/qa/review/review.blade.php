@extends('layouts.app')

@section('title')
QA - Call Followup List
@endsection

@section('content')
    <div id="qa-review">
        <qa-review
            :flash-message="{{ json_encode(session('flash_message')) }}"
        />
    </div>
@endsection