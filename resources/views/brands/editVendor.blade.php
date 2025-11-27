@extends('layouts.app')

@section('title')
Edit Vendor
@endsection

@section('content')
<div id="edit-brand-vendor">
		<edit-brand-vendor 
			:brand="{{ json_encode($brand) }}"
			:vendor="{{ json_encode($vendor) }}"
			:states="{{ json_encode($states) }}"
			:errors="{{ json_encode($errors->all()) }}"
			:flash-message="{{ json_encode(session('flash_message')) }}"
			:http-post-password="{{ json_encode(session('http_post_password')) }}"
		/>
</div>
@endsection