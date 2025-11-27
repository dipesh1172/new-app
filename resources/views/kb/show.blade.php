@extends('layouts.app')

@section('content')
<div id="kb-show">
	<kb-show 
		:is-guest="{{ json_encode(Auth::guest()) }}"
        :kb="{{ json_encode($kb) }}"
	/>
</div>
@endsection
