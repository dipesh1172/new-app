@extends('layouts.app')

@section('title')
Support: Send File Transfer
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Support</li>
        <li class="breadcrumb-item active">Send File Transfer</li>
    </ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Send File Transfer
                </div>
                <div class="card-body">
                    @if(Session::has('flash_message'))
                        <div class="alert alert-success">
                            <span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em>
                        </div>
                    @endif

                    <form method="POST" class="form">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="col-10">
                                <input
                                    type="text"
                                    class="form-control form-control-lg"
                                    name="code"
                                    placeholder="Enter Confirmation Code(s) (comma separated)"
                                    @if(!empty($code))
                                    value="{{ $code }}"
                                    @endif
                                >
                            </div>
                            <div class="col-2">
                                <button type="submit" class="btn btn-primary btn-lg">Submit</button>
                            </div>
                        </div>
                        @if(!empty($output))
                        <div class="row">
                            <div class="col-12">
                                <hr>
                                <pre class="bg-light p-2">{{$output}}</pre>
                            </div>
                        </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
