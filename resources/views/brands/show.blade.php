@extends('layouts.app')

@section('title')
Brand: {{ $brand->name }}
@endsection

@section('content')
	<!-- Breadcrumb -->
	<ol class="breadcrumb">
		<li class="breadcrumb-item">Home</li>
		<li class="breadcrumb-item active"><a href="{{ URL::route('brands.index') }}">Brands</a></li>
		<li class="breadcrumb-item active">Brand: {{ $brand->name }}</li>
	</ol>

	<div class="container-fluid">
		<div class="animated fadeIn">
            <p align="right">
              
            </p>
			<div class="card">
				<div class="card-header">
					Brand: {{ $brand->name }}
				</div>
				<div class="card-body">
					@if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Active</th>
                                <th></th>
                                <th>Name</th>
                                <th>Billing Name</th>
                                <th>Billing Address</th>
                                <th>Billing Phone</th>
                                <th>Billing Terms</th>
                                <th>Billing Frequency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    @if ($brand->active == 1)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!is_null($brand->logo_path))
                                        <img id="logo" src="{{ config('services.aws.cloudfront.domain') }}/{{ $brand->logo_path }}" height="80px" class="img-avatar" alt="Logo">
                                    @else
                                        <img id="logo" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mm&s=300" height="80px" class="img-avatar" alt="No logo">
                                    @endif
                                </td>
                                <td>{{ $brand->name }}</td>
                                <td>{{ $brand->billing_name }}</td>
                                <td>
                                    {{ $brand->billing_attn }}
                                    <br>
                                    {{ $brand->billing_address1 }}
                                    <br>
                                    {{ $brand->billing_address2 }}
                                    <br>
                                    @if ($brand->billing_city && $brand->billing_state)
                                        {{ $brand->billing_city }}, {{ $brand->billing_state }} {{ $brand->billing_zip }}
                                    @endif
                                </td>
                                <td>{{ $brand->phone_number }}</td>
                                <td>{{ $brand->billing_terms }}</td>
                                <td>{{ ucfirst($brand->frequency) }}</td>
                            </tr>
                        </tbody>
                    </table>
				</div>
			</div>
		</div>
		<!--/.col-->
	</div>
@endsection

@section('head')
<style>
	table.table.table-striped {
		margin-bottom: 50px;
	}
</style>
@endsection

@section('scripts')

@endsection
