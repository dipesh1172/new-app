@extends('layouts.app')

@section('content')
<div id="list-roles">
	<list-roles 
		:errors="{{ json_encode($errors->all()) }}"
		:old="{{ json_encode(is_object(old()) ? old() : app()->make('stdClass')) }}"
		:has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
		:flash-message="{{ json_encode(session('flash_message')) }}"
	/>
</div>
@endsection