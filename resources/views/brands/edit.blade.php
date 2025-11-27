@extends('layouts.app')

@section('title')
Edit Brand {{ $brand->name }}
@endsection

@section('content')
	<div id="brands-edit">
		<brands-edit
			:brand="{{ json_encode($brand) }}"
			:clients="{{ json_encode($clients) }}"
			:states="{{ json_encode($states) }}"
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
