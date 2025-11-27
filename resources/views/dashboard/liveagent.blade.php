@extends('layouts.app')

@section('title')
Live Agent
@endsection

@section('content')
<live-agent :call-centers="{{ json_encode($callCenters) }}" />
@endsection
