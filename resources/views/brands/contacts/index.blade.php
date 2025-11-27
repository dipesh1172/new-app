@extends('layouts.app')

@section('title')
Brand Contacts
@endsection

@section('content')
	<div id="brands-contacts">
		<brands-contacts
			:brand="{{ json_encode($brand) }}"
		/>
	</div>
@endsection

@section('vuescripts')
<script>
	window.baseContent = {
        contact_types: {!! json_encode($contact_types) !!},
        phone_types: {!! json_encode($phone_types) !!},
	}
</script>
@endsection
