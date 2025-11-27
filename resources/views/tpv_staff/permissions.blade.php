@extends('layouts.app')

@section('title')
Edit Tpv Staff
@endsection

@section('content')
@breadcrumbs([
	['name' => 'Home', 'url' => '/'],
	['name' => 'TPV Staff', 'url' => route('tpv_staff.index')],
	['name' => 'Edit TPV Staff', 'url' => route('tpv_staff.edit', ['tpv_staff' => $tpv_staff->id])],
	['name' => 'Edit Permissions', 'url' => '#', 'active' => true]
])

<div class="container-fluid">
	<div class="animated fadeIn">
		<div class="card">
			<div class="card-header">
				<i class="fa fa-th-large"></i> Edit Tpv Staff Permissions
			</div>
			<div class="card-body container-fluid">
				<div class="row">
					<form>
						<div class="form-group">
							<div class="col-12">
								@php
									$first = true;
									$lastSection = null;
								@endphp
								@if(count($allperms) == 0)
									<div class="alert alert-warning">
										There are no permissions setup yet.
									</div>
								@else
									@foreach($allperms as $perm)
										@php
										$permsects = explode('.',$perm->short_name);
										$sect = $permsects[0];
										@endphp

									@if($lastSection != $sect)
										@if(!$first)
											</div>
											</ul>

										@endif
										
										<div class="col-12">
											<br />
											<h4>{{ucfirst($sect)}}</h4>
											<ul class="list-group">
									@endif
										<li class="list-group-item
										@if(!has_perm($perm->short_name))
										disabled
										@endif
										@if($role->permissions->where('perm_id',$perm->id)->count() != 0 && has_perm($perm->short_name, $tpv_staff))
										list-group-item-success
										@else
											@if(has_perm($perm->short_name, $tpv_staff))
												list-group-item-danger
											@else
												list-group-item-info
											@endif
										@endif

										">
											{{$perm->description}} ({{$perm->short_name}})
											<span class="ml-2 pull-right">
												@if($role->permissions->where('perm_id',$perm->id)->count() != 0)
												Granted by Role
												@else
													@if(has_perm($perm->short_name, $tpv_staff))
														Has, Not in Role
													@else
														Does not have
													@endif
												@endif
											</span>
											<div class="pull-right">
												<label class="switch">
				                                    <input type="checkbox" id="{{$perm->short_name}}" name="{{$perm->short_name}}" {{(has_perm($perm->short_name, $tpv_staff) ? 'checked="checked"' : '')}} />
				                                    <span class="slider round"></span>
				                                </label>
											</div>
										</li>
						@php
							$first = false;
							$lastSection = $sect;
						@endphp
						@endforeach
					</ul>
					</div>
					</div>
				@endif
				</div>

			</form>

		</div>

		@if(count($allperms) != 0)
		<div class="row col-10">
			<button id='save-btn' class="btn btn-success float-right">Save</button><br /><br />
		</div>
		@endif

		<br /><br />
			</div>
		</div>
	</div>
</div>
@endsection

@section('scripts')
<script>
function do_save(evt) {
	evt.stopPropagation();
	evt.preventDefault();
	var perms = {};
	$('input[type=checkbox]').each(function(index,item){

		perms[item.id] = $(item).prop('checked');

	});
	$.ajax('{{route('tpv_staff.permissions', ['staff' => $tpv_staff->id])}}',{
		method: 'POST',
		data: {
			permissions: perms,
		},
		dataType: 'json',
		success: function(response){
			if(response.errors == null) {
				alert('Saved');
			} else {
				console.log(response.errors);
				alert('There was an error saving the permissions.');
			}
		},
		error: function(){
			alert('Problem communicating with the server.');
		}
	});
}

$(function(){
	$.ajaxSetup({
	    headers: {
	        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
	    }
	});
	$('#save-btn').on('click', do_save);
});
</script>
@endsection
