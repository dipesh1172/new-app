@extends('layouts.app')

@section('title')
Internal Server Error
@endsection

@php
    $e = \App\Http\Controllers\ErrorHandler::reportError(500, $exception);
    $errorId = $e['code'];
    $isMissingRecord = $e['is-missing-record'];
@endphp

@section('content')
    @breadcrumbs([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Error', 'url' => '#', 'active' => true],
    ])

    <div class="container-fluid">
    	<div class="animated fadeIn">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-warning lead">
                                There was an error attempting to open the requested page.
                            </div>
                            
                            <br>
                            <h3>Resolving this Issue</h3>
                            <ul>
                                <li class="lead">If you typed the URL in please check your input.</li>
                                <li class="lead">Click <a href="javascript:history.go(-1);">here</a> to go back and try again.</li>
                            </ul>
                            <br>
                            <p class="lead">
                                If you believe you have received this error in error please contact your support representative.
                                <br>
                                Please reference your error id: 
                                <pre class="lead">{{$errorId}}</pre>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
