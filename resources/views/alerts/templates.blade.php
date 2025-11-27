@extends('layouts.app')

@section('title')
Templates for Events
@endsection

@section('content')
<ol class="breadcrumb">
	<li class="breadcrumb-item"> <a href="/dashboard">Home</a></li>
	<li class="breadcrumb-item">Configuration</li>
	<li class="breadcrumb-item active">Templates</li>
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
	<div class="card">
		<div class="card-header"><i class="fa fa-th-large"></i> Templates</div>
			<div class="card-body ">
				<div class="row">
					<div class="col-6 col-xs-12">

						<table class="table">
							<thead>
								<tr>
									<th>Name</th>
									<th>Tools</th>
								</tr>
							</thead>
							<tbody>
								@foreach($templates as $template)
									<tr>
										<td>{{$template->name}}</td>
										<td><button class="btn btn-success edit-btn" data-template-id="{{$template->id}}">Edit</button></td>
									</tr>
								@endforeach
							</tbody>
						</table>

					</div>
					<div class="col-6 col-xs-12" id="editor">
						<form action="{{route('alerts.templates.save')}}" method="POST">
							{{csrf_field()}}
							<input type="hidden" name="template-id" id="template-id" value="{{old('template-id')}}" />
							<div class="form-group">
								<label for="template-name">Template Name</label>
								@if ($errors->has('template-name'))
	                                <span class="help-block text-danger">
	                                    <strong>{{ $errors->first('template-name') }}</strong>
	                                </span>
	                            @endif
								<input type="text" class="form-control" name="template-name" id="template-name" value="{{old('template-name')}}" />
							</div>
							<div class="form-group">
								<label for="template-content">Content</label>
								<div class="text-muted">Enclose variables in brackets, e.g. <pre class="d-inline text-muted">[email]</pre></div>
								@if ($errors->has('template-content'))
	                                <span class="help-block text-danger">
	                                    <strong>{{ $errors->first('template-content') }}</strong>
	                                </span>
	                            @endif
								<textarea class="form-control" name="template-content" id="template-content" rows="6">{{old('template-content')}}</textarea>
							</div>
							<div class="form-group row">
								<div class="col-12">
									<button type="button" class="btn btn-danger" id="clear-template">Clear</button>
									<button type="submit" class="btn btn-primary float-right">Save</button>

								</div>
							</div>
						</form>

					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('extra-scripts')
	<script src="{{asset('js/tinymce/tinymce.min.js')}}"></script>
	<script>
	$(function(){
		tinymce.init({
			selector:'textarea',
			branding: false,
			theme: 'modern',
			menubar: false,
			resize: false,
			statusbar: false,
		});
		$('#clear-template').on('click', function(e) {
			e.preventDefault();
			$('#template-name').val('');
			$('#template-content').val('');
			tinymce.activeEditor.setContent('');
			$('#template-id').val('');
			return false;
		});
		$('.edit-btn').on('click', function(evt) {
			var target = $(evt.target);
			$.ajax('/admin/settings/events/template-info', {
				method: 'GET',
				data: {
					template: target.data('templateId'),
				}
			}).then(function(response){
				if(response.errors != null) {
					alert(response.errors);
				} else {
					$('#template-name').val(response.template.name);
					$('#template-content').val(response.template.template_content);
					tinymce.activeEditor.setContent(response.template.template_content);
					$('#template-id').val(response.template.id);
				}
			}).catch(function(){
				alert('Could not retrieve template!')
			});
		})
	});
	</script>
@endsection
