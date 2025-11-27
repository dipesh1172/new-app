@extends('layouts.app')

@section('title')
Billing
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">Billing</li>
    </ol>

	<div class="container-fluid">
	    @include('billing.nav')

	    <div class="tab-content">
	        <div role="tabpanel" class="tab-pane active">
				<div class="card">
					<div class="card-header">
						<i class="fa fa-th-large"></i> Billing
					</div>
					<div class="card-body">
                        <div class="row">
                            @if(Session::has('flash_message'))
                                <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                            @endif

                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Brand Name</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($brands->isEmpty())
                                        <tr><td colspan="3" class="text-center">No brands were found.</td></tr>
                                    @else
                                        @foreach ($brands as $brand)
                                            <tr>
                                                <td>{{ $brand->brand_name }}</td>
                                                <td class="text-right">
                                                    <a href="/billing/{{ $brand->brand_id }}/invoices" class="btn btn-success">View Invoices</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--/.col-->
@endsection

@section('head')

@endsection

@section('scripts')

@endsection
