@extends('layouts.app')

@section('title')
Add Client
@endsection

@section('content')
<div id="create-client-index">
	<create-client-index 
		:errors="{{ json_encode($errors->all()) }} || undefined" 
		:flash-message="{{  json_encode(session('flash_message')) }} || undefined" 
		:countries="{{ json_encode($countries) }} || undefined" 
		:states="{{ json_encode($states) }} || undefined" 
	/>
</div>
@endsection
