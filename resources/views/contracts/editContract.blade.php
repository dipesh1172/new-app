@extends('layouts.app')

@section('title')
Edit Contract
@endsection

@section('content')
    <div id="contract-add-edit-index">
        <contract-add-edit-index
            :tcs="{{ json_encode($tcs) }}"
            :brands="{{ json_encode($brands) }}"
            :states="{{ json_encode($states) }}"
            :contract="{{ json_encode($contract) }}"
            :edit="{{ json_encode($edit) }}"
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
