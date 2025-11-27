@extends('layouts.app')

@section('title')
Event {{ $event->event_id }}
@endsection

@section('content')
	<!-- Breadcrumb -->
	<ol class="breadcrumb">
		<li class="breadcrumb-item">Home</li>
		<li class="breadcrumb-item active"><a href="{{ URL::route('events.index') }}">Events</a></li>
		<li class="breadcrumb-item active">Event</li>
	</ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div class="card">
                <div class="card-header">
                    Event (#{{ $event->id }})
                </div>
                <div class="card-body">
                    @if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif

                    <div class="row">
                        <div class="col-md-2">
                            <strong>Created At:</strong> {{ $event->created_at->diffForHumans() }}<br />

                            @if ($event->confirmation_code)
                                <strong>Confirmation Code:</strong> {{ $event->confirmation_code }}<br />
                            @endif
                            
                            @if ($event->tsr_id)
                                <strong>TSR ID:</strong> {{ $event->tsr_id }}<br />
                            @endif

                            @if ($event->first_name && $event->last_name)
                                <strong>Sales Agent:</strong> {{ $event->first_name }} {{ $event->last_name }}<br
                                 />
                            @endif

                            @if ($event->brand_name)
                                <strong>Vendor:</strong> {{ $event->brand_name }}<br />
                            @endif
                            
                            <strong>Channel:</strong> {{ strtoupper($event->channel) }}<br />

                            @if ($event->phone_number)
                                <strong>Customer Phone:</strong> {{substr_replace(substr_replace(str_replace('+1', '', $event->phone_number),'-',3,0),'-',7,0)}}<br />
                            @endif

                            @if ($event->dxc_rec_id)
                                <strong>DXC Rec ID:</strong> {{ $event->dxc_rec_id }}<br />
                            @endif
                        </div>
                        <div class="col-md-10">
                            @if (!$interactions->isEmpty())
                                <table class="table table-striped table-hover table-bordered">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Direction</th>
                                        <th>TPV Agent</th>
                                        <th>Result</th>
                                        <th class="text-center">Duration</th>
                                        <th class="text-center">Recording</th>
                                        {{-- <th class="text-center">View Transcript</th> --}}
                                    </tr>
                                    @php $total_time = 0; @endphp
                                    @foreach ($interactions as $interaction)
                                    <tr>
                                        <td>{{ $interaction->created_at->diffForHumans() }}</td>
                                        <td>{{ $interaction->source }}</td>
                                        <td>{{ $interaction->name }}</td>
                                        <td>{{ $interaction->first_name }} {{ $interaction->last_name }}</td>
                                        <td>
                                            @if ($interaction->result == 'Sale')
                                                <span class="badge badge-success">{{ $interaction->result }}</span>
                                            @elseif ($interaction->result == 'No Sale')
                                                <span class="badge badge-warning">{{ $interaction->result }}</span>
                                            @elseif ($interaction->result == 'Closed')
                                                <span class="badge badge-danger">{{ $interaction->result }}</span>
                                            @else
                                                {{-- {{ $interaction->result }} --}}
                                                --
                                            @endif
                                        </td>
                                        <td class="text-center">{{ number_format($interaction->interaction_time, 2) }} min(s)</td>
                                        <td class="text-center">
                                            @if ($interaction->recording)
                                                <audio controls><source src="{{ config('services.aws.cloudfront.domain') }}/{{ $interaction->recording }}">Your browser does not support the audio element.</audio>
                                            @else
                                                --
                                            @endif
                                        </td>
{{--                                         <td class="text-center">
                                            <a class="btn btn-sm btn-info" href="/interaction/transcript/{{ $interaction->id }}">view</a>
                                        </td> --}}
                                    </tr>
                                    @php $total_time += $interaction->interaction_time; @endphp
                                    @endforeach
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td class="text-center">{{$total_time}} min(s)</td>
                                        <td></td>
                                    </tr>
                                </table>
                            @endif
                        </div>
                    </div>

                    <br /><br />

                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Identifier</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Provider</th>
                                <th>Product</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (count($event_products) == 0)
                                <tr>
                                    <td colspan="6" class="text-center">No products were found.</td>
                                </tr>
                            @else
                                @foreach ($event_products as $ep)
                                <tr>
                                    <td>{{ $ep['event_type'] }}</td>
                                    <td>{{ $ep['identifier'] }} ({{ $ep['account_type'] }})</td>

                                    @if ($ep['company_name'])
                                        <td>{{ $ep['company_name'] }}</td>
                                    @else
                                        <td>{{ $ep['bill_first_name'] }} {{ $ep['bill_last_name'] }} 
                                    @endif

                                    <td>
                                        @foreach ($ep['addresses'] as $address)
                                            @if ($address['id_type'] == 'e_p:billing')
                                                <strong>Billing:</strong>
                                            @else
                                                <strong>Service:</strong>
                                            @endif
                                            @if (!is_null($address['line_1']))
                                                 {{ $address['line_1'] }} <i>{{ $address['line_2'] }}</i> {{ $address['city'] }}, {{ $address['state_province'] }} {{ $address['zip'] }}
                                            @else
                                                N/A
                                            @endif
                                            <br />
                                        @endforeach
                                    </td>
                                    <td>
                                        {{ @$ep['rate']['utility_name'] }}
                                    </td>
                                    <td>
                                        @if (@$ep['rate']['name'])
                                            {{ $ep['rate']['name'] }} ({{ $ep['rate']['program_code'] 
                                        }})
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('head')
<style>
table.table.table-striped {
	margin-bottom: 50px;
}
</style>
@endsection

@section('scripts')

@endsection
