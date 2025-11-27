@extends('layouts.app')

@section('title')
Test Voice Imprint
@endsection

@section('content')

<div class="container-fluid">
    <form method="POST">
        {{ csrf_field() }}
        <input type="text" placeholder="confirmation code" name="confirmation">
        <input type="text" placeholder="Phone Number to Call" name="to">
        <button type="submit">Submit</button>
    </form>
</div>
@endsection
