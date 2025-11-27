@extends('layouts.app')

@section('title')
Add Document
@endsection

@section('content')
	<!-- Breadcrumb -->
	<ol class="breadcrumb">
		<li class="breadcrumb-item">Home</li>
		<li class="breadcrumb-item active"><a href="{{ URL::route('documents.index') }}">Documents</a></li>
		<li class="breadcrumb-item active">Add Document</li>
	</ol>

	<div class="container-fluid">
		<div class="animated fadeIn">
			<div class="card">
				<div class="card-header">
					<i class="fa fa-th-large"></i> Add Document
				</div>
				<div class="card-body">
					@if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif

					{{ Html::ul($errors->all()) }}

					{{ Form::open(array('route' => array('documents.create'), 'method' => 'post', 'autocomplete' => 'off')) }}
						{{ csrf_field() }}
                    	<div class="row">
                    		<div class="col-md-11">
								<div class="form-group">
									<label for="inputTitle1">Title</label>
									<input type="text" class="form-control form-control-lg" name="title" id="inputTitle1" placeholder="Enter a Title">
								</div>
							</div>
							<div class="cold-md-1">
								<label for="inputStatus1">Status</label>
								<select name="status" class="form-control form-control-lg">
									<option value="1">Enabled</option>
									<option value="0">Disabled</option>
								</select>
							</div>
						</div>
                        <div class="form-group">
                        	<div class="row">
                        		<div class="col-md-8">
		                            <textarea name="doc"></textarea>
		                        </div>
		                        <div class="col-md-4">
									<div class="card">
										<div class="card-header">
											<i class="fa fa-th-large"></i> Available Variables
										</div>
										<div class="card-body">
											<div class="row">
												<div class="col-md-6">
													<div class="alert alert-secondary" role="alert">[company_name]</div>
												</div>
												<div class="col-md-6">
													<div class="alert alert-secondary" role="alert">[date]</div>
												</div>
												<div class="col-md-6">
													<div class="alert alert-secondary" role="alert">[printed_name]</div>
												</div>
												<div class="col-md-6">
													<div class="alert alert-secondary" role="alert">[signature]</div>
												</div>
											</div>
										</div>
									</div>
		                        </div>
		                    </div>
                        </div>
						<div id="button_submit">
							<button type="submit" class="btn btn-primary">Submit</button>
						</div>
					{{ Form::close() }}
				</div>
			</div>
		</div>
	</div>
@endsection

@section('head')

@endsection

@section('scripts')
<script src="/js/tinymce/js/tinymce/tinymce.min.js"></script>
<script>tinymce.init({
	selector: 'textarea',
	height: 700,
	menubar: false,
	plugins: [
		'advlist autolink lists link image charmap print preview anchor textcolor',
		'searchreplace visualblocks code fullscreen table',
		'insertdatetime media table contextmenu paste code help wordcount'
	],
	toolbar: 'insert | undo redo |  formatselect | bold italic backcolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | code | table | help'
});</script>
@endsection
