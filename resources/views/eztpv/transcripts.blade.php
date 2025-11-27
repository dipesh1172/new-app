@extends('layouts.transcripts')

@section('title')
Signature Page for Contract
@endsection

@section('content')
	<div id="index">
		<div class="row">
			<div class="col-md-8">
				@if (!empty($logo_path))	
					<span>
						<img id="logo" src="{{ $logo_path }}" class="logo" alt="Logo">
					</span>
				@else
					<span>
					<strong>{{ 'confirmation_code' }}</strong> {{ $summary['confirmation_code'] }}
					</span>
				@endif

			</div>
			<div class="col-md-4 text-right">
				<b>{{ $summary['brand_name'] }}</b><br>
				{{ $summary['brand_address'] }}<br>
				{{ $summary['brand_city'] }}, {{ $summary['brand_state'] }} {{ $summary['brand_zip'] }}<br>

				@if (!empty($summary['brand_email']))	
					<span>
					{{ $summary['brand_email'] }}<br>
					</span>
				@endif

				@if (!empty($summary['puct_license']))
				<span>
					PUCT# {{ $summary['puct_license'] }}<br>
				</span>
				@endif

				@if (!empty($summary['brand_service_number']))
				<span>
					{{ $summary['brand_service_number'] }}<br>
				</span>
				@endif
			</div>
		</div>

	  <hr><br>
	  <div class="row">
		<div class="col-md-7">
			<h4>Thank you for choosing {{ $summary['brand_name'] }}!</h4><br>
			@if ($summary['state'] === 'TX')
				This Third Party Verification (Letter of Authorization) signature page confirms your choice to enroll with <b>{{$summary['brand_name']}} </b> and provides a summary of your new service account.<br><br>
			@else
				This Third Party Verification signature page confirms your choice to enroll with <b>{{$summary['brand_name']}}</b> and provides a summary of your new service account.  The terms and conditions are also appended to this document for you to easily reference at any time.<br><br>

				<b>We are currently processing your enrollment</b><br><br>

				Your enrollment has been sent to your utility. Your utility will send you a confirmation notice confirming your selection of <b>{{$summary['brand_name']}}</b> as your supplier.<br><br>

				Your service will begin with your first meter read by your utility after your enrollment is accepted, which may take up to 1-2 billing cycles.<br><br>
			@endif
		</div>
		<div class="col-md-5">
			<div class="panel panel-default">
				<div class="panel-heading">
					Information
				</div>
				<div class="panel-body">
					<b>Date Created</b> {{ $summary['created_at'] }}<br>
					<b>Confirmation Code</b> {{ $summary['confirmation_code'] }}<br>
					<b>Sales Agent</b> {{ $summary['sales_agent_name'] }}<br>
					<b>Sales Agent ID</b> {{ $summary['sales_agent_id'] }}<br>
					<b>Authorizing Name</b> {{ $summary['auth_name'] }}<br>
					<b>Phone</b> {{ $summary['phone'] }}<br>

					@if (!empty($summary['email']))
						<span>
						<b>Email</b> {{ $summary['email'] }}<br>
						</span>
					@endif

					<b>Language</b> {{ $summary['language'] }}<br>
					@if (!empty($summary['ip_addr']))
						<span>
						<b>IP Address</b> {{ $summary['ip_addr'] }}<br>
						</span>
					@endif

					@if (!empty($summary['gps_coords']))
						<span>
						<b>GPS Coords</b> {{ $summary['gps_coords'] }}<br>
						</span>
					@endif
				</div>
			</div>
		</div>
		Below is a summary of your service account with <b>{{$summary['brand_name']}}</b><br><br>
		<div class="table-condensed">
            <table class="table table-striped table-bordered table-focus m-0 p-0">
            <tr>
	            <th>Type</th>
    	        <th>Identifier</th>
        	    <th>Billing Name</th>
            	<th>Address</th>
            	<th>Product</th>
            </tr>
			@foreach ($summary['product'] as $products)
           		<tr>
                	<td>
						@if ($products['event_type']['event_type'] === 'Electric')
                    		Electric
                  		@else
                   			Gas
					  	@endif
                	</td>
                	<td>
						@foreach ($products['identifiers'] as $identifiers)
							{{ $identifiers['identifier'] }}
					  
							@if ($identifiers['utility_account_type']['account_type'] === 'Account Number')
								(Account Number)
							@else
								({{ $identifiers['utility_account_type']['account_type'] }})
					  		@endif
						@endforeach
					</td>
	                <td>
    		            {{ $products['bill_first_name'] }} {{ $products['bill_last_name'] }}
            	    </td>
                	<td>
						@foreach ($products['addresses'] as $address)
							@if ($address['id_type'] === 'e_p:service')
	                    		<b>Service Address:</b>
    	                	@else
        	            		<b>Billing Address:</b>
            	        	@endif
                			{{ $address['address']['line_1'] }} <i>{{ $address['address']['line_2'] }}</i> {{ $address['address']['city'] }}, {{ $address['address']['state_province'] }} {{ $address['address']['zip'] }}
	                    	<br>
    	            	@endforeach
               		</td>
			   		<td>
						@if ($products['rate']['utility']['utility'])
							<b>Provider:</b> {{ $products['rate']['utility']['utility']['name'] }}<br>
						@endif

						@if ($products['rate']['product']['name'])
							<b>Product:</b> {{ $products['rate']['product']['name'] }}<br>
						@endif

						@if ($products['rate']['program_code'])
							<b>Program Code:</b> {{ $products['rate']['program_code'] }}<br>
						@endif
                		<!-- Rate Type: fixed, flex, flat fee -->
                		@if ($products['rate']['product']['rate_type']['rate_type'] == 'fixed' || $products['rate']['product']['rate_type']['rate_type'] == 'flex'  || $products['rate']['product']['rate_type']['rate_type'] == 'flat fee')
                    		@if ($products['rate']['rate_amount'])
                    			<b>Rate Amount:</b>
                        		@if ($products['rate']['rate_amount'] > 0 && $products['rate']['rate_currency']['currency'] == 'cents')
	                        		${{ $products['rate']['rate_amount'] / 100 }}
	                    		@else
                        			${{ $products['rate']['rate_amount'] }}
								@endif
							@endif
                    		per {{ $products['rate']['rate_uom']['uom'] }}<br>
						@endif
	                    @if ($products['rate']['product']['term'] > 0)
    		                <b>Term:</b> {{ $products['rate']['product']['term'] }} {{ $products['rate']['product']['term_type']['term_type'] }}<br>
	                    @else
    		                <b>Term:</b> Month to Month<br>
                    	@endif
		                <!-- Rate Type: variable -->
        		        @if ($products['rate']['product']['rate_type']['rate_type'] == 'variable')
                			<b>Rate Amount:</b> Variable<br>
						@endif
		                <!-- Rate Type: tiered -->
        		        @if ($products['rate']['product']['rate_type']['rate_type'] == 'tiered' || $products['rate']['product']['rate_type']['rate_type'] == 'step')
                		    @if ($products['rate']['rate_amount'] > 0 && $products['rate']['intro_rate_amount'] > 0)
                      			<!-- Rate type: fixed tiered -->
                	    		@if ($products['rate']['intro_rate_amount'])
                    	    		<b>Initial Rate Amount:</b>
                        			@if ($products['rate']['intro_rate_amount'] > 0 && $products['rate']['rate_currency']['currency'] == 'cents')
                          				${{ $products['rate']['intro_rate_amount'] / 100 }}
									@else
                          				${{ $products['rate']['intro_rate_amount'] }}
									@endif
                        		@endif
                        		per {{ $products['rate']['rate_uom']['uom'] }}<br>
 
	                      		@if ($products['rate']['product']['intro_term'])
    	                    		<b>Intro Term:</b> {{ $products['rate']['product']['intro_term'] }} {{ $products['rate']['product']['intro_term_type']['term_type'] }}<br>
        	            		@endif

	                    		@if ($products['rate']['rate_amount'])
    	                    		<b>Rate Amount:</b>
									@if ($products['rate']['rate_amount'] > 0 && $products['rate']['rate_currency']['currency'] == 'cents')
            		            		${{ $products['rate']['rate_amount'] / 100 }}
									@else
                    	     			${{ $products['rate']['rate_amount'] }}
                  	      			@endif
	                	        	per {{ $products['rate']['rate_uom']['uom'] }}<br>
    		           		 	@endif

	            	        	@if ($products['rate']['product']['term'] > 0)
    	            	        	<b>Term:</b> {{ $products['rate']['product']['term'] }} {{ $products['rate']['product']['term_type']['term_type'] }}<br>
								@else
    	                			<b>Term:</b> Month to Month<br>
    	               			@endif
							@else
    	                		<!-- Rate Type: variable tiered -->
        	            		<b>Rate Amount:</b> Variable<br>

	                    		@if ($products['rate']['intro_rate_amount'])
	                        		<b>Initial Rate Amount:</b>
		                        	@if ($products['rate']['intro_rate_amount'] > 0 && $products['rate']['rate_currency']['currency'] == 'cents')
 			                       		${{ $products['rate']['intro_rate_amount'] / 100 }}
 			                       	@else
	                        			${{ $products['rate']['intro_rate_amount'] }}
	                        		@endif
	                        		per {{ $products['rate']['rate_uom']['uom'] }}<br>
	                      		@endif
	                   			@if ($products['rate']['product']['intro_term'])
 		                       		<b>Intro Term:</b> {{ $products['rate']['product']['intro_term'] }} {{ $products['rate']['product']['intro_term_type']['term_type'] }}<br>
    	                  		@endif
    	                	@endif
						@endif
    		            @if ($products['rate']['product']['service_fee'])
            			    <b>Service Fee:</b> {{ $products['rate']['product']['service_fee'] }}<br>
                		@endif

                		@if ($products['rate']['product']['transaction_fee'])
                    		<b>Transaction Fee:</b> {{ $products['rate']['product']['transaction_fee'] }}<br>
                		@endif

                		@if ($products['rate']['cancellation_fee'])
                  			<b>Cancellation Fee:</b>  ${{ $products['rate']['cancellation_fee'] }}
                  			@if ($products['rate']['cancellation_fee_term_type'] && $products['rate']['cancellation_fee_term_type']['term_type'] !== null && $products['rate']['cancellation_fee_term_type']['term_type'] === 'month')
        	            		per month remaining on the contract
                  			@else
 					  			one time fee
                  			@endif
                    		<br>
                		@endif

                		@if ($products['rate']['product']['daily_fee'])
                  			<b>Daily Fee:</b> {{ $products['rate']['product']['daily_fee'] }}<br>
                		@endif

                		@if ($products['rate']['rate_monthly_fee'])
                  			<b>Monthly Fee:</b> ${{ $products['rate']['rate_monthly_fee'] }} per month<br>
                		@endif
					</td>
				</tr>
			@endforeach
            </table>
            <br><br>
			<div class="table-condensed">
				@if ($sections)
					<br>
					<h3>Transcript</h3>
					<table class="table table-striped table-bordered">
                        <tr>
							<th>Date</th>
							<th>Question</th>
							<th>Answer</th>
						</tr>
						@if (!empty($sections['preamble']))
							@foreach ($sections['preamble'] as $preambles) 
								<tr>
									<td>
										{!! $preambles['created_at'] !!}
									</td>
									<td>
										{!! $preambles['question'] !!}
									</td>
									<td>
										{!! $preambles['answer'] !!}
									</td>
								</tr>
							@endforeach
						@endif
						@if (!empty($sections['verify']))
							@foreach ($sections['verify'] as $verifys) 
								@if	(!empty($verifys))
									@foreach ($verifys as $verify)
										<tr>
										<td>
											{!! $verify['created_at'] !!}
										</td>
										<td>
											{!! $verify['question'] !!}
										</td>
										<td>
											{!! $verify['answer'] !!}
										</td>
										</tr>
									@endforeach
								@endif
							@endforeach
						@endif
						@if	(!empty($sections['postverify']))
							@foreach ($sections['postverify'] as $postverifys) 
								<tr>
									<td>
										{!! $postverifys['created_at'] !!}
									</td>
									<td>
										{!! $postverifys['question'] !!}
									</td>
									<td>
										{!! $postverifys['answer'] !!}
									</td>
								</tr>
							@endforeach
						@endif
					</table>
				@endif
			</div>
	
		</div>

	</div>

	
	</div>
@endsection 

@section('head')
<style>
.logo {
	max-height: 170px;
}

.img-fluid {
	max-height: 100px;
	min-height: 100px;
	margin: 10px;
}

.pagebreak {
	page-break-after: always;
}
</style>
@endsection
