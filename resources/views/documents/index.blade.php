@extends('layouts.app')

@section('title')
Standard Documents
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">Standard Documents</li>
    </ol>

    <div class="container-fluid">
    	<div class="animated fadeIn">
            <div class="row page-buttons">
                <div class="col-md-12">
                    <div class="form-group pull-right m-0">
                        <a href="{{ URL::to('documents/add') }}" class="btn btn-success m-0"><b>+</b> Add Standard Document</a>
                    </div>
                </div>
            </div>

            <br />

            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Standard Documents
                </div>
                <div class="card-body">
                	<div class="table-responsive">
						<table class="table table-striped">
							<thead class="thead-inverse">
								<tr>
									<th>Status</th>
									<th>Title</th>
									<th>Created</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								@if ($documents->isEmpty())
								<tr>
									<td colspan="4" class="text-center">
										No documents were found.
									</td>
								</tr>
								@else
									@foreach ($documents AS $doc)
									<tr>
										<td>
	                                        @if ($doc->status == 1)
	                                            <span class="badge badge-success">enabled</span>
	                                        @else
	                                            <span class="badge badge-danger">disabled</span>
	                                        @endif
										</td>
										<td>{{ $doc->title }}</td>
										<td>{{ date('m-d-Y h:i:s A', strtotime($doc->created_at)) }}</td>
										<td class="text-right">
											<a href="/documents/edit/{{ $doc->id }}" class="btn btn-primary"><span class='fa fa-pencil'></span> Edit</a>
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
@endsection

@section('head')

@endsection

@section('scripts')

@endsection
