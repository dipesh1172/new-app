@extends('layouts.app')

@section('title')
Add Business Rule
@endsection

@section('content')
	<!-- Breadcrumb -->
	<ol class="breadcrumb">
		<li class="breadcrumb-item">Home</li>
		<li class="breadcrumb-item active"><a href="{{ URL::route('rules.index') }}">Business Rules</a></li>
		<li class="breadcrumb-item active">Edit Business Rule</li>
	</ol>

	<div class="container-fluid">
		<div class="animated fadeIn">
			<div class="card">
				<div class="card-header">
					<i class="fa fa-th-large"></i> Edit Business Rule
				</div>
				<div class="card-body">
					@if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif

					{{ Html::ul($errors->all()) }}

					{{ Form::open(array('route' => array('rules.update', $rule->id), 'method' => 'put', 'autocomplete' => 'off')) }}
						<div class="form-group">
							{{ Form::label('business_rule', 'Business Rule') }}
							{{ Form::textarea('business_rule', $rule->business_rule, array('class' => 'form-control', 'placeholder' => 'Enter an Business Rule')) }}
						</div>
						<div class="form-group">
							{{ Form::label('answers', 'Answers') }}
							<br>
							{!! $rule->answers_html !!}
							{{ Form::hidden('answer_type', $rule->answer_type) }}
						</div>
						<button type="submit" class="btn btn-primary">Submit</button>
					{{ Form::close() }}
					<br>
					{{ Form::open(['method' => 'DELETE', 'onsubmit' => 'return ConfirmDelete()', 'class' => 'inline', 'route' => ['rules.destroy', $rule->id] ]) }}
	                {{ Form::hidden('id', $rule->id) }}
	                {{ Form::submit('Delete this Business Rule', ['class' => 'btn btn-sm btn-danger']) }}
	            {{ Form::close() }}
				</div>
			</div>
		</div>
		<!--/.col-->
	</div>
@endsection

@section('head')

@endsection

@section('scripts')

@endsection
