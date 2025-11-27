@extends('layouts.emails')

@section('title')
{{ $subject }}
@endsection

@section('content')
{!! nl2br($content) !!}
@endsection
