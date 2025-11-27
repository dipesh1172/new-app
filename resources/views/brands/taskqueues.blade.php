@extends('layouts.app')

@section('title')
Task Queues for {{ $brand->name }}
@endsection

@section('content')
<div id="taskqueues">
        <taskqueues 
            :brand="{{ json_encode($brand) }}"
            :taskqueues="{{ json_encode($taskqueues) }}"
            :flash-message="{{ json_encode(session('flash_message')) }}"
        />
</div>
@endsection