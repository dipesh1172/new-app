@extends('layouts.app')

@section('content')
<div id="runtime-settings">
    <runtime-settings
        :status="{{ json_encode(session('status')) }}" 
        :settings="{{ json_encode($settings) }}" 
    />
</div>
@endsection
