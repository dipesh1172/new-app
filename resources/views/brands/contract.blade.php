@extends('layouts.app')

@section('title')
Brand Contacts
@endsection

@section('content')
	<div id="brands-contract">
		<brands-contract
			:brand="{{ json_encode($brand) }}"
		/>
	</div>
@endsection

@section('vuescripts')
<script>
console.log('XDD')
	window.AWS_CLOUDFRONT_PATH = "{{ config('services.aws.cloudfront.domain') }}"
</script>
@endsection
