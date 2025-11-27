@extends('layouts.emails')

@section('title')
Unresovled Eztpv Contact Errors
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <p>There are {{$count}} unresolved EzTPV contact errors.</p>
            <p>Please run the following query to determine actions required.</p>
            <p>Soft-delete any which should be considered resolved to exempt them from this report going forward.</p>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            {{$query}}
        </div>
    </div>          
@endsection