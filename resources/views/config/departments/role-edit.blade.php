@extends('layouts.app')

@section('title')
Editing Role: {{$role->name}}
@endsection

@section('content')
	@breadcrumbs([
		['name' => 'Home', 'url' => '/'],
		['name' => 'Company Configuration', 'url' => '/config'],
		['name' => 'Departments', 'url' => '/config/departments'],
		['name' => $dept->name . ' Roles', 'url' => "/config/departments/{$dept->id}/"],
		['name' => 'Editing ' . $role->name, 'active' => true]
	])

<div class="container-fluid">

	<div class="row">
		<div class="col-md-12">
			<div class="card">
				<div class="card-header"><i class="fa fa-th-large"></i> Editing {{$role->name}}</div>
				<div class="card-body">

					<form method="POST" id="create-form" enctype="multipart/form-data" class="">
                        <input type="hidden" name="_method" value="PATCH">
                        {{ csrf_field() }}
						<div class="form-group row required">
							<label for="rolename" class="form-control-label">Role Name</label>
							<input type="text" class="form-control" id="rolename" name="rolename" placeholder="" value="{{$role->name}}" required>
							<div class="d-none alert alert-warning" id="rolename-errors"></div>
						</div>

						<div class="form-group row">
							<label for="jobdesc" class=" form-control-label">Job Description</label>
							<div class="d-none alert alert-warning" id="jobdesc-errors"></div>
							<textarea id="jobdesc" class="form-control" rows="8" name="jobdesc">{!! $role->job_description !!}</textarea>
						</div>

						<div class="form-group row">
							<h3 class=" form-control-label">Permissions</h3>
						</div>
						<div class="form-group row">
							@php
								$first = true;
								$lastSection = null;
							@endphp
							@foreach($allperms as $perm)
								@php
									$permsects = explode('.',$perm->short_name);
									$sect = $permsects[0];
								@endphp
								@if($lastSection != $sect)
									@if(!$first)

										</ul>
									</div>
								</div>
							</div>
									@endif
									<div class="col-md-12">
									<div class="card card-primary">
									<div class="card-header">{{ucfirst($sect)}}</div>
									<div class="card-body">
									<ul class="list-group">
								@endif
								<li class="list-group-item">
									{{$perm->description}} ({{$perm->short_name}})
									<div class="material-switch float-right">
										<input type="checkbox" id="{{$perm->short_name}}" name="permissions[{{$perm->short_name}}]" {{($role->permissions->where('perm_id',$perm->id)->count() == 0 ? '' : 'checked="checked"')}} />
										<label for="{{$perm->short_name}}" class="bg-primary"></label>
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
				</div>
			</div>
					<div class="form-group row">
						<div class="col-md-12">
							<label for="submit" class="form-control-label">&nbsp;</label>
							<button class="btn btn-primary pull-right" id="submit"><i class="fa fa-floppy-o" aria-hidden="true"></i> Save</button>
						</div>
					</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('scripts')
<script src="{{asset('js/tinymce/tinymce.min.js')}}"></script>
<script src="{{asset('js/tinymce/jquery.tinymce.min.js')}}"></script>
<script src="{{asset('js/bootstrap.combobox.js')}}"></script>

<script>

var insave = false;
	function do_save(evt){
		if(insave) return;
		insave = true;
		if ($('#create-form').checkValidity && !$('#create-form').checkValidity()) return;
		evt.preventDefault();
		var perms = [];
		$('input[type=checkbox]:checked').each(function(index,item){perms.push(item.id);});
		var values = {
			rolename: $('#rolename').val(),
			jobdesc: tinymce.activeEditor.getContent(),
			permissions: perms,

		};
		$('#submit').addClass('disabled');
		$.ajax(window.location.toString(),{
			method: 'PATCH',
			data: values,
			dataType: 'json',
			statusCode: {
				'422': function(xhr,statusText,errorThrown){

					var data = $.parseJSON(xhr.responseText);
					$.each(data,function(name,items){
						console.log(items);
						var errItem = $('#'+name + '-errors');
						$('#'+name+'-errors').removeClass('d-none');
						errItem.html('');
						$.each(items,function(index,value){
							console.log(value);
							errItem.append('<span class="help-block">'+value+'</span>');
						});

					});
					$('#submit').removeClass('disabled');
					insave = false;
				}
			},
			success: function(response){
				if(response.errors == null) {
					alert('Saved');
				} else {
					console.log(response.errors);
					alert('There was an error saving the role.');
				}
				$('#submit').removeClass('disabled');
				insave = false;
			}
		});
	}



	$(function(){


		$.ajaxSetup({
		    headers: {
		        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		    }
		});

		tinymce.init({
			selector: 'textarea',
			branding: false,
			valid_elements : '+*[*]',
			height: 450,
			theme: 'modern',
			plugins: [
				'advlist autolink lists link image charmap print preview hr anchor pagebreak',
				'searchreplace wordcount visualblocks visualchars code fullscreen',
				'insertdatetime media nonbreaking save table contextmenu directionality',
				'emoticons template paste textcolor colorpicker textpattern imagetools noneditable'
			],
			toolbar1: 'undo redo | insert | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | forecolor backcolor emoticons',

			image_advtab: true,


 		});

 		$('#create-form').on('submit', do_save);
 		$('#submit').on('click', do_save);
	});
</script>
@endsection

@section('head')
<style>
	#preview {
		background-color: #fff;
		padding: 10px;
	}

	#preview p {
		background-color: #{{$role->bgcolor}};
		color: #{{$role->fgcolor}};
		max-width: 400px;
	}
	.material-switch > input[type="checkbox"] {
	    display: none;
	}

	.material-switch > label {
	    cursor: pointer;
	    height: 0px;
	    position: relative;
	    width: 40px;
	}

	.material-switch > label::before {
	    background: rgb(0, 0, 0);
	    box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
	    border-radius: 8px;
	    content: '';
	    height: 16px;
	    margin-top: -8px;
	    position:absolute;
	    opacity: 0.3;
	    transition: all 0.4s ease-in-out;
	    width: 40px;
	}
	.material-switch > label::after {
	    background: rgb(255, 255, 255);
	    border-radius: 16px;
	    box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
	    content: '';
	    height: 24px;
	    left: -4px;
	    margin-top: -8px;
	    position: absolute;
	    top: -4px;
	    transition: all 0.3s ease-in-out;
	    width: 24px;
	}
	.material-switch > input[type="checkbox"]:checked + label::before {
	    background: inherit;
	    opacity: 0.5;
	}
	.material-switch > input[type="checkbox"]:checked + label::after {
	    background: inherit;
	    left: 20px;
	}
</style>
@endsection
