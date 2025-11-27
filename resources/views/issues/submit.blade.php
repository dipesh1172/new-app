@extends('layouts.app')

@section('title')
Report New Issue
@endsection

@section('content')
<div id="new-issue">
	<new-issue
		:has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
		:flash-message="{{ json_encode(session('flash_message')) }}"
		:add-to="'{!! $add_to ?? '' !!}'"
	/>
</div>
@endsection

@section('scripts')
@endsection