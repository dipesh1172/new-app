@extends('layouts.app')

@section('title')
Add Skill
@endsection

@section('content')
<div id="motion-skills-edit">
	<motion-skills-edit
        :skill="{{ json_encode($skill) }}"
		:errors="{{ json_encode($errors->all()) }} || undefined" 
		:flash-message="{{  json_encode(session('flash_message')) }} || undefined" 
		:languages="{{ json_encode($languages) }} || undefined" 
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
