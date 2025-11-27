@extends('layouts.app')

@section('title')
@if($event == null)
@php
	$post = 'create';
@endphp
Creating New Event
@else
@php
	$post = 'save';
@endphp
Editing {{$event->name}}
@endif
@endsection

@section('content')
<div class="container">
	<div class="row"></div>
	<div class="row">
		<div class="col-2"></div>
		<div class="col-8">
			<div class="card">
				<div class="card-body">
					@if(count($templates) == 0)
					<div class="alert bg-danger">
						No Templates Exist, <a href="/admin/settings/events/templates">create them first.</a>
					</div>
					@endif
					<form method="POST" action="/admin/settings/events/{{$post}}">
						{{csrf_field()}}
						<input type="hidden" name="id" value="{{$event != null ? $event->id : ''}}" />
						<div class="form-group row">
							<div class="col-3">
								<label for="ename">Event Name</label>
							</div>
							<div class="col-9">
								@if ($errors->has('ename'))
	                                <span class="help-block text-danger">
	                                    <strong>{{ $errors->first('ename') }}</strong>
	                                </span>
	                            @endif
								<input type="text" class="form-control" name="ename" id="ename" value="{{old('ename') || $event != null ? $event->name : ''}}" />
							</div>
						</div>
						<div class="form-group row">
							<div class="col-3">
								<label for="edesc">Description</label>
							</div>
							<div class="col-9">
								@if ($errors->has('edesc'))
	                                <span class="help-block text-danger">
	                                    <strong>{{ $errors->first('edesc') }}</strong>
	                                </span>
	                            @endif
								<textarea class="form-control" rows="5" name="edesc" id="edesc">{{old('edesc') || $event != null ? $event->description : ''}}</textarea>
							</div>
						</div>
						<div class="row">
							<div class="col-3">
								Available Variables for Templates
							</div>
							<div class="col-9">
								@foreach($vars as $var)
									<span class="badge badge-secondary">{{$var}}</span>
								@endforeach
							</div>
						</div>
						<div class="form-group row">
							<div class="col-3">
								Supported Actions
							</div>
							<div class="col-9">
								@if($errors->count() > 0)
									<div class="alert">
										@foreach($errors->toArray() as $key => $value)
											<p><strong>{{var_dump($key)}}</strong> {{var_dump($value)}}</p>
										@endforeach
									</div>
								@endif
								@foreach($actions as $action)
									@php
										if($event != null) {
											$c = true;
											$c_tid = '';
											$c_enabled = '';
											$eactions = $event->actions;

											foreach($eactions as $eaction) {
												if($action->id == $eaction->action_type) {
													$c_tid = $eaction->template_id;
													$c_enabled = 'checked="checked"';
													break;
												}
											}
										} else {
											$c = false;
										}
									@endphp
									<div class="row">
										<div class="col-4">
											<input type="checkbox" value="{{$action->id}}" name="action-{{snake_case($action->name)}}" id="action-{{snake_case($action->name)}}" {{$c ? $c_enabled : ''}} />
											<label for="action-{{snake_case($action->name)}}">{{$action->name}}</label>
										</div>
										<div class="col-8">
											<label for="template-action-{{snake_case($action->name)}}">Template:</label>
											<select name="template-action-{{snake_case($action->name)}}" id="template-action-{{snake_case($action->name)}}">
												<option value="">None Selected</option>
												@foreach($templates as $template)
													<option {{$c ? ($template->id == $c_tid ? 'selected="selected"' : '') : ''}} value="{{$template->id}}">{{$template->name}}</option>
												@endforeach
											</select>
										</div>
									</div>
								@endforeach
							</div>
						</div>
						@if(count($templates) != 0)
							<div class="form-group row">
								<div class="col-12">
									<button type="submit" class="btn btn-primary float-right">Save</button>
								</div>
							</div>
						@endif
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
