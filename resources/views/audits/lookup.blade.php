@extends('layouts.app')

@section('title')
Lookup Audits
@endsection

@section('content')
<div id="audit-lookup">
    <audit-lookup
        :confirmation-code="{{ json_encode($confirmation_code) }}"
        :errors="{{ json_encode($errors->all()) }}"
        :flash-message="{{ json_encode(session('flash_message')) }}"
    />
</div>
@endsection
