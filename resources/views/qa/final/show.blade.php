@extends('layouts.app')

@section('title')
QA - Final Disposition
@endsection

@section('content')
	<!-- Breadcrumb -->
	<ol class="breadcrumb">
		<li class="breadcrumb-item">Home</li>
		<li class="breadcrumb-item active"><a href="{{ URL::route('qa_final.index') }}">Final Disposition List</a></li>
		<li class="breadcrumb-item active">Final Disposition</li>
	</ol>

	<div class="container-fluid">
		<div class="animated fadeIn">
			<div class="card">
				<div class="card-header">
					Final Disposition
				</div>
				<div class="card-body">
					@if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif

                    <div class="row">
                    	<div class="col-md-3 text-center">
                            <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($event->email_address))) }}.jpg?d=mm&s=150" class="img-avatar" alt="{{Auth::user()->email}}">
                        </div>
                    	<div class="col-md-3">
                    		<strong>Created At:</strong> {{ date('F j, Y, g:i a', strtotime($event->created_at)) }}<br />
                    		<strong>Source:</strong> {{ $event->source }}<br />
                    		<strong>Sales Agent:</strong> {{ $event->sales_first_name }} {{ $event->sales_last_name }}<br />
                    		<strong>Vendor:</strong> {{ $event->brand_name }}<br />
                    		<strong>Channel:</strong> {{ strtoupper($event->channel) }}<br />
                    	</div>
                    	<div class="col-md-3">
                    		@if ($event->phone_number)
                    			<strong>Customer Phone:</strong> {{ $event->phone_number }}<br />
                    		@endif

                    		@if ($event->email_address)
	                    		<strong>Customer Email:</strong> {{ $event->email_address }}<br />
	                    	@endif

							<strong>Event Length:</strong> {{ $event->event_length }} ({{ $event->inbound_call_time }} in / {{ $event->outbound_call_time }} out)<br />
							<strong>TPV Agent:</strong> 
                            @if ($event->tpv_staff_last_name && $event->tpv_staff_first_name)
                                {{ $event->tpv_staff_first_name }} {{ $event->tpv_staff_last_name }}
                            @elseif ($event->tpv_staff_last_name)
                                {{ $event->tpv_staff_last_name }}
                            @elseif ($event->tpv_staff_first_name)
                                {{ $event->tpv_staff_first_name }}
                            @endif
                             @ {{ $event->station_id }}
							<br />
                    	</div>
                    	<div class="col-md-3">
                    		{{ Form::open(array('route' => array('qa_final.update', $event->id), 'method' => 'put', 'autocomplete' => 'off')) }}
                    		@if ($recording)
								<audio controls><source src="{{ $recording->recording }}">Your browser does not support the audio element.</audio>
							@else
								No audio recorded for this event
								<hr>
							@endif
                    		<div class="form-group">
								{{ Form::label('event_results_id', 'Result') }}
								<select name="event_results_id" id="event_results_id" class="form-control">
									<option value="">Select a Result</option>
									@foreach ($results as $result)
										@if ($result->id == $event->event_results_id)
											<option value="{{ $result->id }}" selected="selected">{{ $result->result }}</option>
										@else
											<option value="{{ $result->id }}">{{ $result->result }}</option>
										@endif
									@endforeach
								</select>
							</div>
							<div class="form-group" id="disposition">
								{{ Form::label('disposition_id', 'Disposition') }}
								<select name="disposition_id" id="disposition_id" class="form-control">
									<option value="">Select a Disposition</option>
									@foreach ($dispositions as $disposition)
										@if ($disposition->id == $event->disposition_id)
											<option value="{{ $disposition->id }}" selected="selected">{{ $disposition->reason }}</option>
										@else
											<option value="{{ $disposition->id }}">{{ $disposition->reason }}</option>
										@endif
									@endforeach
								</select>
							</div>
							<button type="submit" class="btn btn-primary">Submit</button>
							{{ Form::hidden('tracking_id', $tracking_id) }}
							{{ Form::close() }}
                    	</div>
                    </div>

                    <br /><br />

                    <table class="table table-striped">
                    	<thead>
                    		<tr>
                    			<th>Type</th>
                                <th>Account Number</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Product</th>
                            </tr>
                    	</thead>
                    	<tbody>
                    		@foreach ($event_products as $ep)
                    		<tr>
                    			<td>{{ $ep->event_type }}</td>
	                            <td>{{ $ep->account_number }}</td>
	                            <td>{{ $ep->bill_first_name }} {{ $ep->bill_last_name }}</td>
	                            <td>{{ $ep->bill_address1 }}, {{ $ep->bill_city }}, {{ $ep->bill_state }} {{ $ep->bill_zip }}</td>
	                            <td>{{ $ep->rate_name }}</td>
	                        </tr>
	                        @endforeach
                    	</tbody>
                    </table>
				</div>
			</div>
		</div>
		<!--/.col-->
	</div>
@endsection

@section('head')
<style>
	table.table.table-striped {
		margin-bottom: 50px;
	}
	div#disposition {
		display: none;
	}
</style>
@endsection

@section('scripts')
<script>
	$(document).ready(function() {
		if ($('#event_results_id').val() == 2) {
			$('#disposition').show();
		}
		$('#event_results_id').change(function() {
			if ($(this).val() == 2) {
				$('#disposition').show();
			} else {
				$('#disposition').hide();
			}
		})
	});
</script>
@endsection