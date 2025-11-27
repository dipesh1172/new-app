@extends('layouts.app')

@section('title')
Edit Client {{ $client->name }}
@endsection

@section('content')
<div id="edit-client">
    <edit-client 
        :errors="{{ json_encode($errors->all()) }} || undefined" 
        :flash-message="{{  json_encode(session('flash_message')) }} || undefined" 
        :states="{{ json_encode($states) }} || undefined" 
        :client="{{ json_encode($client) }}"
        :aws-cloud-front="{{ json_encode(config('services.aws.cloudfront.domain')) }}"
    />
</div>
@endsection
