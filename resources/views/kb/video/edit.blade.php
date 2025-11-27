@extends('layouts.app')

@section('title')
	@if($video == null)
		New Upload
	@else
		Editing {{$video->title}}
	@endif
@endsection

@section('content')
@breadcrumbs([
	['name' => 'Home', 'url' => '/'],
	['name' => 'Video Knowledge Base', 'url' => '/kb/video'],
	['name' => ($video == null ? 'New Upload' : $video->title), 'active' => true]
])
<div class="container-fluid">
	<div  class="row"></div>
	<div class="row">
		<div class="col-12">
			@if ($errors->any())
			    <div class="alert alert-danger">
			        <ul>
			            @foreach ($errors->all() as $error)
			                <li>{{ $error }}</li>
			            @endforeach
			        </ul>
			    </div>
			@endif
			<div class="card">
				@if($video != null)
					<div class="card-header">
						<a href="/kb/video/play/{{$video->slug}}" class="btn btn-primary pull-right"><span class="fa fa-eye"></span> View</a>
					</div>
				@endif
				<div class="card-body">
					<form method="POST" id="create-form" enctype="multipart/form-data" class="">
						{{csrf_field()}}
						<input type="hidden" name="id" value="{{optional($video)->id}}" />
						<div class="form-group">
							<label class="form-control-label">Title</label>
							<input id="title" name="title" type="text" value="{{optional($video)->title}}" class="form-control">
						</div>
						<div class="form-group">
							<label class="form-control-label">Description</label>
							<textarea id="desc" class="form-control" name="desc">{{optional($video)->description}}</textarea>
						</div>
						<div class="form-group">

							@if($video != null && $video->path != '')
								<label class="form-control-label">Video PermaLink</label>
								<div class="input-group">
									<input type="text" id="permurl" value="https://afc.tpv.rocks/kb/video/{{$video->slug}}?format=.webm" readonly>
									<button type="button" class="btn btn-light input-group-addon" title="Copy to Clipboard" id="purlbtn"><i class="fa fa-copy"></i>
								</div>
							@else
							<label class="form-control-label">Video File</label>
								<input type="file" accept="video/*" class="form-control-file" id="file" name="videofile">
							@endif
						</div>
						<div class="form-group">
							<button type="submit" class="btn btn-primary pull-right">Save</button>
						</div>

					</form>
				</div>
			</div>
		</div>
	</div>
</div>

@endsection

@section('scripts')
<script>
	$(function(){
		$('#purlbtn').on('click', function(e) {
			e.preventDefault();
			$('#permurl')[0].select();
			document.execCommand('Copy');
		});
	});
</script>
@endsection