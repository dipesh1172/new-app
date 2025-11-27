@extends('layouts.app')

@section('title')
Edit Profile
@endsection

@section('content')
	<!-- Breadcrumb -->
	<ol class="breadcrumb">
		<li class="breadcrumb-item">Home</li>
		<li class="breadcrumb-item active">Edit Profile</li>
	</ol>

	<div class="container-fluid">
		<div class="animated fadeIn">
			<div class="card">
				<div class="card-header">
					<i class="fa fa-th-large"></i> Edit your Profile
				</div>
				<div class="card-body">
					{{ Form::open(array('url' => array('users/profileUpdate'), 'method' => 'post', 'autocomplete' => 'off', 'enctype' => 'multipart/form-data')) }}
						<div class="row">
							<div class="col-md-3 text-center">
								@if (session('avatar'))
									<img src="{{ config('services.aws.cloudfront.domain') }}/{{ session('avatar') }}" class="rounded img-thumbnail avatar" alt="{{ Auth::user()->email }}">
								@else
									<img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim(Auth::user()->email))) }}.jpg?d=mm&s=300" class="rounded img-thumbnail" alt="{{ Auth::user()->email }}">
								@endif

								<br /><br />

								<div class="form-group">
									{{ Form::file('avatar', ['style' => 'border: 1px solid #c2cfd6;padding: .5rem .75rem;width: 100%;']) }}
								</div>
							</div>
							<div class="col-md-9">
								{{ Html::ul($errors->all()) }}

								@if(Session::has('flash_message'))
			                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
			                    @endif
					
								<div class="form-group">
									{{ Form::label('first_name', 'First Name') }}
									{{ Form::text('first_name', $user->first_name, array('class' => 'form-control form-control-lg', 'placeholder' => 'Enter a First Name')) }}
								</div>

								<div class="form-group">
									{{ Form::label('last_name', 'Last Name') }}
									{{ Form::text('last_name', $user->last_name, array('class' => 'form-control form-control-lg', 'placeholder' => 'Enter a Last Name')) }}
								</div>

								<div class="form-group">
									{{ Form::label('email', 'Email Address') }}
									{{ Form::text('email', $user->email, array('class' => 'form-control form-control-lg', 'placeholder' => 'Enter an Email Address')) }}
								</div>

								<br />

								<table class="table table-responsive table-bordered" id="phones">
									<thead>
										<tr>
											<th>Phone</th>
											<th width="50"></th>
										</tr>
									</thead>
									<tbody>
										@foreach ($user->phones as $key => $phone)
										<tr>
											<td>
												<input type="hidden" name="phones[]" value="{{ $phone->phone }}" />
												<span class="phone_text">{{ $phone->phone }}</span>
											</td>
											<td class="text-right">
												<button class="btn btn-danger btn-sm" onClick="deleteRow(this)">delete</button>
											</td>
										</tr>
										@endforeach
										<tr>
											<td>
												<input type="text" class="form-control form-control-lg form-control form-control-lg-lg phone_us" name="phones[]" placeholder="Enter a Phone Number" value="" />
											</td>
											<td class="text-right">
											</td>
										</tr>
									</tbody>
								</table>

								<br />

								<hr />

								<br />

								<button type="submit" class="btn btn-primary pull-right">Submit</button>
							</div>
						</div>

					{{ Form::close() }}
				</div>
			</div>
		</div>
		<!--/.col-->
	</div>
@endsection

@section('head')
<style>
.phone_text {
	font-size: 14pt;
}
</style>
@endsection

@section('scripts')
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.12/jquery.mask.min.js"></script>
<script>
$(document).ready(function() {
	$('.phone_text').text(function(i, text) {
	    return text.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
	});

	$('.phone_us').mask('(000) 000-0000');
});

function deleteRow(btn) {
	var row = btn.parentNode.parentNode;
	row.parentNode.removeChild(row);
}
</script>
@endsection
