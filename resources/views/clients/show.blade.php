@extends('layouts.app')

@section('title')
Client: {{ $client->name }}
@endsection

@section('content')
	<!-- Breadcrumb -->
	<ol class="breadcrumb">
		<li class="breadcrumb-item">Home</li>
		<li class="breadcrumb-item active"><a href="{{ URL::route('clients.index') }}">Clients</a></li>
		<li class="breadcrumb-item active">Client: {{ $client->name }}</li>
	</ol>

	<div class="container-fluid">
		<div class="animated fadeIn">
            <p align="right">
              
            </p>
			<div class="card">
				<div class="card-header">
					Client: {{ $client->name }}
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
                                <th>Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    @if ($client->active == 1)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!is_null($client->filename))
                                        <img id="logo" src="{{ config('services.aws.cloudfront.domain') }}/{{ $client->filename }}" height="80px" class="img-avatar" alt="Logo">
                                    @else
                                        <img id="logo" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mm&s=300" height="80px" class="img-avatar" alt="No logo">
                                    @endif
                                </td>
                                <td>{{ $client->name }}</td>
                                <td>
                                    {{ $client->address }}
                                    <br>
                                    @if ($client->city && $client->state)
                                        {{ $client->city }}, {{ $client->state }} {{ $client->zip }}
                                    @endif
                                </td>
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
