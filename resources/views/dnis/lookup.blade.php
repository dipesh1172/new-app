@extends('layouts.app')

@section('title')
DNIS Lookup
@endsection

@section('content')
<div id="lookup-dnis">
	<lookup-dnis 
		:errors="{{ json_encode($errors->all()) }} || undefined"
		:flash-message="{{ json_encode(session('flash_message')) }} || undefined"
	/>
</div>
@endsection