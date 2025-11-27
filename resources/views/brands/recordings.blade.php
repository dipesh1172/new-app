@extends('layouts.app')

@section('title')
Recordings
@endsection

@section('content')
<div id="recordings">
    <recordings 
        :brand="{{ json_encode($brand->makeVisible(['recording_transfer_config', 'recording_transfer_type', 'recording_transfer'])) }}"
    />
</div>
@endsection
