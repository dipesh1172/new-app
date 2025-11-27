@extends('layouts.app')

@section('title')
Add Contract
@endsection

@section('content')
<div id="contract-add-edit-index">
    <contract-add-edit-index
        :brands="{{ json_encode($brands) }}"
        :states="{{ json_encode($states) }}"
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
