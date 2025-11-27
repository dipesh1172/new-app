@extends('layouts.app')

@section('title')
Add DNIS
@endsection

@section('content')
<div id="create-dnis-external">
	<create-dnis-external 
		:errors="{{ json_encode($errors->all()) }} || undefined"
		:flash-message="{{ json_encode(session('flash_message')) }} || undefined"
	/>
</div>
@endsection
