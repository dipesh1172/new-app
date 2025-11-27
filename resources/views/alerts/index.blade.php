@extends('layouts.app')

@section('title')
Event management
@endsection

@section('content')
<!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
        <li class="breadcrumb-item">Configuration</li>
        <li class="breadcrumb-item active">Alerts</li>
    </ol>
<div class="container-fluid">
	<div class="row"></div>
	@if(session('status') != null)
	<div class="alert alert-success alert-dismissible fade show" role="alert">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close">
			<span aria-hidden="true">&times;</span>
		</button>
		<strong>{{session('status')}}</strong>
	</div>
	@endif
	<div class="row">
		<div class="col-12">
			<div class="card">
				<div class="card-header">
					<i class="fa fa-th-large"></i> Available Alerts
				</div>
				<div class="card-body">
					<table class="table table-bordered">
						<thead>
							<tr>
								<th>Id</th>
								<th>Name</th>
								<th># of Actions</th>
								<th># Subscribed</th>
								<th>Enabled</th>
								<th>Tools</th>
							</tr>
						</thead>
						<tbody>
							@if($events == null || count($events) == 0)
								<tr>
									<td colspan="6" class="text-center bg-white text-dark">No Alerts are available.</td>
								</tr>
							@else
								@foreach($events as $event)
									<tr>
										<td>{{$event->id}}</td>
										<td>{{$event->name}}</td>
										<td>{{$event->actions->count()}}</td>
										<td>{{$event->subscriptions->count()}}</td>
										<td>
										@if($event->enabled)
											<span class="fa fa-check"></span>
										@else
											<span class="fa fa-ban"></span>
										@endif
										</td>
										<td><a title="View/Edit" href="/admin/settings/events/view/{{$event->id}}" class="btn btn-success"><span class="fa fa-eye"></span></a></td>
									</tr>
									<tr>
										<td colspan="6">{{$event->description}}</td>
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
@endsection
