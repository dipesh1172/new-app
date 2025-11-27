@extends('layouts.app')

@section('title')
Edit Skill
@endsection

@section('content')
<div id="motion-skill-maps-edit">
	<motion-skill-maps-edit
        :skill-map="{{ json_encode($skillMap) }}"
		:errors="{{ json_encode($errors->all()) }} || undefined" 
		:flash-message="{{  json_encode(session('flash_message')) }} || undefined" 
		:brands="{{ json_encode($brands) }} || undefined"
		:dnis="{{ json_encode($dnis) }} || undefined"
        :languages="{{ json_encode($languages) }} || undefined" 
		:motion-skills="{{ json_encode($skills) }} || undefined"
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
