@extends('layouts.app')

@section('title')
DNIS Choose
@endsection

@section('content')
<div id="choose-dnis">
	<choose-dnis 
		:errors="{{ json_encode($errors->all()) }} || undefined"
		:brands="{{ json_encode($brands) }} || undefined"
		:flash-message="{{ json_encode(session('flash_message')) }} || undefined"
	/>
</div>
@endsection