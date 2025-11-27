@extends('layouts.emails')

@section('title')
    {{ $subject }}
@endsection

@section('content')
    <p>The following Outbound call details has been Requeued</p>
    <br>
    <p>First Name        : {{ $firstName }}</p>
    <p>Last Name         : {{ $lastName }}</p>
    <p>Utility           : {{ $phone }}</p>
    <br>
    Thanks,
    <br>
@endsection
