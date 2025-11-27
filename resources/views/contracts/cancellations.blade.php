@extends('layouts.app')

@section('title')
Contracts - Cancellations
@endsection

@section('content')
<div id="contract-cancellations">
    <contract-cancellations
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
