@extends('layouts.app')

@section('title')
Edit DNIS {{ $dnis->dnis }}
@endsection

@section('content')

	<div id="edit-dni-index">
		<edit-dni-index
			:action="{{ json_encode(route('dnis.update', $dnis->id)) }} || undefined"
			:brands="{{ json_encode($brands) }} || undefined"
			:countries="{{ json_encode($countries) }} || undefined"
			:states="{{ json_encode($states) }} || undefined"
			:initial-values="{{ json_encode($dnis) }} || undefined"
			:flash-message="{{ json_encode(session('flash_message')) }} || undefined"
			:brand-states="{{json_encode($brand_states)}}"
			:markets="{{ json_encode($markets) }}"
			:channels="{{ json_encode($channels) }}"
			:errors="{{ json_encode($errors->all()) }} || undefined"
		/>
	</div>
@endsection