@extends('layouts.app')

@section('title')
Add Skill Mapping
@endsection

@section('content')
<div id="motion-skill-maps-create">
	<motion-skill-maps-create
		:errors="{{ json_encode($errors->all()) }} || undefined" 
		:flash-message="{{  json_encode(session('flash_message')) }} || undefined" 
        :brands="{{ json_encode($brands) }} || undefined"
		:dnis="{{ json_encode($dnis) }} || undefined"
        :languages="{{ json_encode($languages) }} || undefined" 
        :motion-skills="{{ json_encode($skills) }} || undefined"
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
