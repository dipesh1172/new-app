@extends('layouts.app')

@section('title')
Edit Fee Schedule for {{ $brand->name }}
@endsection

@section('content')
<div id="feeschedule">
	<feeschedule 
		:errors="{{ json_encode($errors->all()) }}" 
		:flash-message="{{ json_encode(session('flash_message')) }}"
		:old="{{ json_encode(old()) }}"
		:brand="{{ json_encode($brand) }}"
		:bill-frequencies="{{ json_encode($bill_frequencies) }}"
		:invoice-rate-card="{{ json_encode($invoice_rate_card) }}"
		:bill-methodologies="{{ json_encode($bill_methodologies) }}"
	/>
</div>
@endsection