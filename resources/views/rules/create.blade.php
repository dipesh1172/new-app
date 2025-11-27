@extends('layouts.app')

@section('title')
Add Business Rule
@endsection

@section('content')
	<!-- Breadcrumb -->
	<ol class="breadcrumb">
		<li class="breadcrumb-item">Home</li>
		<li class="breadcrumb-item active"><a href="{{ URL::route('rules.index') }}">Business Rules</a></li>
		<li class="breadcrumb-item active">Add Business Rule</li>
	</ol>

	<div class="container-fluid">
		<div class="animated fadeIn">
			<div class="card">
				<div class="card-header">
					<i class="fa fa-th-large"></i> Add a Business Rule
				</div>
				<div class="card-body">
					@if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif

					{{ Html::ul($errors->all()) }}

					{{ Form::open(array('action' => 'BusinessRuleController@store', 'method' => 'post', 'autocomplete' => 'off')) }}
						<div class="form-group">
							{{ Form::label('business_rule', 'Business Rule') }}
							{{ Form::textarea('business_rule', old('business_rule'), array('class' => 'form-control', 'placeholder' => 'Enter an Business Rule')) }}
						</div>
						<div class="form-group">
							{{ Form::label('answers', 'Answers') }}
							<br>
							<div class="answer_type_select">
								<ul class="ul_inline">
									<li class="li_inline">
										<input type="radio" name="answer_type" value="timer" class="timer-selected"> Timer
									</li>
									<li class="li_inline">
										<input type="radio" name="answer_type" value="switch" class="timer-selected"> Switch
									</li>
									<!-- <li class="li_inline">
										<input type="radio" name="answer_type" value="hours_of_operation" class="timer-selected"> Hours of Operation
									</li> -->
									<li class="li_inline">
										<input type="radio" name="answer_type" value="textbox" class="timer-selected"> Textbox
									</li>
								</ul>
							</div>
							<div class="answer_type answer_type_timer">
								<label for="timer_from">From</label>
								<input type="number" name="timer_from" min="5" max="300" step="5">
								<label for="timer_to">To</label>
								<input type="number" name="timer_to" min="5" max="300" step="5">
								<label for="timer_step">Step</label>
								<input type="number" name="timer_step" min="1" max="10">
								<label for="timer_default">Default</label>
								<input type="number" name="timer_default" min="5" max="300" step="5">
								<select name="timer_unit" id="timer_unit">
									<option value="seconds">Seconds</option>
									<option value="minutes">Minutes</option>
									<option value="days">Days</option>
								</select>
							</div>
							<div class="answer_type answer_type_switch">
								<label for="switch_on">On</label>
								<input type="text" name="switch_on" value="Enabled" class="form-control">
								<label for="switch_off">Off</label>
								<input type="text" name="switch_off" value="Not Enabled" class="form-control">
								<label for="switch_default">Default</label>
								<select name="switch_default" id="switch_default" class="form-control">
									<option value="on">On</option>
									<option value="off">Off</option>
								</select>
							</div>
							<!-- <div class="answer_type answer_type_hours_of_operation">
								<table>
									<tr>
										<th></th>
										<th>From</th>
										<th>To</th>
									</tr>
									<tr>
										<td><label for="hoo_sunday_from">Sunday</label></td>
										<td><input type="text" name="hoo_sunday_from"></td>
										<td><input type="text" name="hoo_sunday_to"></td>
									</tr>
									<tr>
										<td><label for="hoo_monday_from">Monday</label></td>
										<td><input type="text" name="hoo_monday_from"></td>
										<td><input type="text" name="hoo_monday_to"></td>
									</tr>
									<tr>
										<td><label for="hoo_tuesday_from">Tuesday</label></td>
										<td><input type="text" name="hoo_tuesday_from"></td>
										<td><input type="text" name="hoo_tuesday_to"></td>
									</tr>
									<tr>
										<td><label for="hoo_wednesday_from">Wednesday</label></td>
										<td><input type="text" name="hoo_wednesday_from"></td>
										<td><input type="text" name="hoo_wednesday_to"></td>
									</tr>
									<tr>
										<td><label for="hoo_thursday_from">Thursday</label></td>
										<td><input type="text" name="hoo_thursday_from"></td>
										<td><input type="text" name="hoo_thursday_to"></td>
									</tr>
									<tr>
										<td><label for="hoo_friday_from">Friday</label></td>
										<td><input type="text" name="hoo_friday_from"></td>
										<td><input type="text" name="hoo_friday_to"></td>
									</tr>
									<tr>
										<td><label for="hoo_saturday_from">Saturday</label></td>
										<td><input type="text" name="hoo_saturday_from"></td>
										<td><input type="text" name="hoo_saturday_to"></td>
									</tr>
								</table>
							</div> -->
							<div class="answer_type answer_type_textbox">
								<textarea name="textbox_text" id="textbox_text" cols="30" rows="10" class="form-control"></textarea>
							</div>
						</div>
						<button type="submit" class="btn btn-primary">Submit</button>
					{{ Form::close() }}
				</div>
			</div>
		</div>
		<!--/.col-->
	</div>
@endsection

@section('head')

	<style>
		div.answer_type {
			display: none;
		}
		ul.ul_inline {
			list-style-type: none;
			padding: 0;
		}
		li.li_inline {
			display: inline;
			margin-left: 0;
			margin-right: 10px;
		}
	</style>

@endsection

@section('scripts')

	<script>
		$(document).ready(function() {
			$('input:radio[name="answer_type"]').change(function() {
		        if ($(this).val() == "timer") {
		           	$('.answer_type_timer').show();
		           	$('.answer_type_switch').hide();
		           	$('.answer_type_hours_of_operation').hide();
		           	$('.answer_type_textbox').hide();
		        }
		        if ($(this).val() == "switch") {
		        	$('.answer_type_switch').show();
		        	$('.answer_type_timer').hide();
		           	$('.answer_type_hours_of_operation').hide();
		           	$('.answer_type_textbox').hide();
		        }
		        if ($(this).val() == "hours_of_operation") {
		           	$('.answer_type_hours_of_operation').show();
		           	$('.answer_type_timer').hide();
		           	$('.answer_type_switch').hide();
		           	$('.answer_type_textbox').hide();
		        }
		        if ($(this).val() == "textbox") {
		           	$('.answer_type_textbox').show();
		           	$('.answer_type_timer').hide();
		           	$('.answer_type_switch').hide();
		           	$('.answer_type_hours_of_operation').hide();
		        }
		    });
		});
	</script>

@endsection
