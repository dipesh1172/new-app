@extends('layouts.app')

@section('content')
<div id="edit-tpvstaff">
	<edit-tpvstaff 
		:has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
		:flash-message="{{ json_encode(session('flash_message')) }}"
		:old="{{ json_encode(old()) }}"
		:errors="{{ json_encode($errors->all()) }}"
	/>
</div>
@endsection