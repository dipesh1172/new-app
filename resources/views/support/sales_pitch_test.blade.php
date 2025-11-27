@extends('layouts.app')

@section('title')
Support: Sales Pitch Test
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Support</li>
        <li class="breadcrumb-item active">Sales Pitch Test</li>
    </ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Sales Pitch Test
                </div>
                <div class="card-body">
                    @if(Session::has('flash_message'))
                        <div class="alert alert-danger"><em> {!! session('flash_message') !!}</em></div>
                    @endif
                    @if(!empty($brands))
                    <form method="POST">
                        {{csrf_field()}}
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="brand" class="form-control-label">Brand</label>
                                    <select id="brand" name="brand" class="form-control">
                                    <option value=""></option>
                                        @foreach($brands as $brand)
                                        <option value="{{$brand->id}}">{{$brand->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="tsr" class="form-control-label">TSR ID</label> 
                                    <input type="text" name="tsr" id="tsr" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary pull-right">Submit</button>
                            </div>
                        </div>
                    </form>
                    @endif
                    @if(!empty($pitch)) 
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">
                            To begin your sales pitch you must now call <a href="tel:{{$phone}}">{{$phone_f}}</a> and enter your Sales Pitch Reference ID:
                            </p>
                            <pre class="lead bg-light">{{$pitch[0]}} {{$pitch[1]}} {{$pitch[2]}} - {{$pitch[3]}} {{$pitch[4]}} {{$pitch[5]}} - {{$pitch[6]}} {{$pitch[7]}} {{$pitch[8]}}</pre>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <hr>
                        </div>
                        <div class="col-12 text-center">
                            <a href="/support/sptest" class="btn btn-success">Submit Another</a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
                    
                
        </div>
    </div>
@endsection

