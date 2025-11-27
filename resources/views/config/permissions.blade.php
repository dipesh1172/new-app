@extends('layouts.app')

@section('title')
Available Permissions
@endsection

@section('content')
@breadcrumbs([
	['name' => 'Home', 'url' => '/'],
	['name' => 'Company Configuration', 'url' => '/config'],
	['name' => 'Permissions', 'url' => '/config/permissions', 'active' => true],
])

<div class="container-fluid">
	<div class="row clearfix">
		<div class="col-md-12">
			<button type="button" data-toggle="modal" data-target="#permission-add" class="btn btn-success pull-right mb-3">@fa(['icon' => 'plus']) Add permission</button>
		</div>
	</div>
	<div class="row clearfix">
		<div class="col-md-12">
			<div class="card">
				<div class="card-header">
					<i class="fa fa-th-large"></i> Available Permissions
				</div>
				<div class="card-body table-responsive p-0">
					@if(count($permissions) == 0)
						<div class="alert alert-warning">There are no permissions defined.</div>
					@else
						<table class="table table-striped">
							<thead>
								<tr>
									<th>Short Name</th>
									<th>Friendly Name</th>
									<th>Description</th>
								</tr>
							</thead>
							<tbody>
								@foreach($permissions as $perm)
									<tr>
										<td>{{$perm->short_name}}</td>
										<td>{{$perm->friendly_name}}</td>
										<td>{{$perm->description}}</td>
									</tr>
								@endforeach
							</tbody>

						</table>
					@endif
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="permission-add" tabindex="-1" role="dialog" aria-labelledby="permission-add-label" aria-hidden="true">
	<div class="modal-dialog" role="document">
    	<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="permission-add-label">Add Permission</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<div class="modal-body">
				<form id="perms" method="POST">
					{{ csrf_field() }}
					<div class="form-group">
						<label for="short_name">Short Name (period seperated)</label>
						<input class="form-control" type="text" id="short_name" name="short_name" />
					</div>
	
					<div class="form-group">
						<label for="friendly_name">Friendly Name</label>
						<input class="form-control" type="text" id="friendly_name" name="friendly_name" />
					</div>

					<div class="form-group">
						<label for="description">Description</label>
						<textarea class="form-control" id="description" name="description"></textarea>
					</div>
				</form>
			</div>

			<div class="modal-footer">
				<button type="button" id="save-btn" class="btn btn-primary">@fa(['icon' => 'save']) Save</button>
			</div>
		</div>
	</div>
</div>
@endsection

@section('scripts')
<script>
$(function(){
	$('#save-btn').on('click', function() {
		$('#perms').submit();
	});
});
</script>
@endsection
