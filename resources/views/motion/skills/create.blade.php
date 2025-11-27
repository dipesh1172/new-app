@extends('layouts.app')

@section('title')
Add Skill
@endsection

@section('content')
<div id="motion-skills-create">
	<motion-skills-create        
		:errors="{{ json_encode($errors->all()) }} || undefined" 
		:flash-message="{{  json_encode(session('flash_message')) }} || undefined" 
		:languages="{{ json_encode($languages) }} || undefined" 
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
