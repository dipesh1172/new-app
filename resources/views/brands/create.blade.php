@extends('layouts.app')

@section('title')
Add Brand
@endsection

@section('content')
<div id="create-brand-index">
	<create-brand-index 
		:errors="{{ json_encode($errors->all()) }} || undefined" 
		:flash-message="{{  json_encode(session('flash_message')) }} || undefined" 
		:clients="{{ json_encode($clients) }} || undefined" 
		:countries="{{ json_encode($countries) }} || undefined" 
		:states="{{ json_encode($states) }} || undefined" 
        :initial-values="{{ !empty(old()) ? json_encode(old(), true) : '{}' }}"
	/>
</div>
@endsection

@section('vuescripts')
<script>
/*
{!! json_encode(old(), true) !!}
*/
</script>
@endsection
