@extends('layouts.app')

@section('title')
EZTPV Contracts
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">EZTPV Contracts</li>
    </ol>

    <div class="container-fluid">
    	<div class="animated fadeIn">
            <p align="right">
                {{ Form::open(['method' => 'GET', 'route' => ['eztpv.contracts'], 'class' => 'form-inline pull-right']) }}
                    {{ Form::text('search', old('search'), ['placeholder' => 'Search', 'class' => 'form-control']) }}
                    {{ Form::button('<i class="fa fa-search"></i>', ['type' => 'submit', 'class' => 'btn btn-primary']) }}
                {{ Form::close() }}
            </p>
            
            <br /><br />

            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> EZTPV Contracts
                </div>
                <div class="card-body">
                	<div class="table-responsive">
						<table class="table table-striped">
							<thead>
								<tr>
									<th>Date</th>
									<th>Confirmation Code</th>
									<th>Brand</th>
									<th></th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								@if ($contracts->isEmpty())
								<tr>
									<td colspan="4" class="text-center">
										No contracts were found.
									</td>
								</tr>
								@else
									@foreach ($contracts AS $c)
									<tr>
										<td>{{ \Carbon\Carbon::parse($c->created_at, 'America/Chicago') }}</td>
										<td>{{ $c->confirmation_code }}</td>
										<td>{{ $c->name }}</td>
										<th>
											@foreach ($c->event->interactions as $interactions)
												@if (isset($interactions->recordings) && isset($interactions->recordings[0]))
													<audio controls><source src="{{ config('services.aws.cloudfront.domain') }}/{{ $interactions->recordings[0]->recording }}">Your browser does not support the audio element.</audio><br />
												@endif
											@endforeach
										</th>
										<td class="text-right">
											<a target="_blank" href="{{ config('services.aws.cloudfront.domain') }}/{{ $c->filename }}" class="btn btn-primary">View</a>
										</td>
									</tr>
									@endforeach
								@endif
							</tbody>
						</table>

                        {{ $contracts->links() }}
					</div>
				</div>
			</div>
    	</div>
    </div>
@endsection

@section('head')

@endsection

@section('scripts')

@endsection
