@extends('layouts.app')

@section('title')
Contracts - Terms & Conditions
@endsection

@section('content')
<div id="contract-terms-and-conditions">
    <contract-terms-and-conditions
        :brands="{{ json_encode($brands) }}"
        :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
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
