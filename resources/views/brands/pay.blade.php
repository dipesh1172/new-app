@extends('layouts.app')

@section('title')
Pay Link - {{ $brand->name }}
@endsection

@section('content')
	<div id="pay-link">
		<pay-link
			:brand="{{ json_encode($brand) }}"
            :results="{{ json_encode($results) }}"
			:errors="{{ json_encode($errors->all()) }}"
			:flash-message="{{ json_encode(session('flash_message')) }}"
		/>
	</div>
@endsection

@section('vuescripts')
<script>
	window.baseContent = {
		AWS_CLOUDFRONT: "{{ config('services.aws.cloudfront.domain') }}",
	}
</script>
@endsection
