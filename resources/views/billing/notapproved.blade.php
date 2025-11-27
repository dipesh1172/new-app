@extends('layouts.app')

@section('title')
Billing
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">Billing</li>
    </ol>

	<div class="container-fluid">
	    @include('billing.nav')

	    <div class="tab-content">
	        <div role="tabpanel" class="tab-pane active">
				<div class="card">
					<div class="card-header">
						<i class="fa fa-th-large"></i> Billing
					</div>
					<div class="card-body">
                        <div class="row">
                            @if(Session::has('flash_message'))
                                <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                            @endif

                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                    	<th>Invoice Number</th>
                                        <th>Brand</th>
                                        <th>Dates</th>
                                        <th>Due Date</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($invoices->isEmpty())
                                        <tr>
                                            <td 
                                                colspan="7" 
                                                class="text-center">
                                                No un-approved invoices were found.
                                            </td>
                                        </tr>
                                    @else
                                        @foreach ($invoices as $invoice)
                                            <tr>
                                                <td>{{ $invoice->invoice_number }}</td>
                                                <td>{{ $invoice->name }}</td>
                                                <td>
                                                    {{ $invoice->invoice_start_date->format('m/d/Y') }}
                                                    to
                                                    {{ $invoice->invoice_end_date->format('m/d/Y') }}
                                                </td>
                                                <td>{{ $invoice->invoice_due_date->format('m/d/Y') }}</td>
                                                <td>${{ number_format($invoice->total, 2, '.', ',') }}</td>
                                                <td class="text-right">
                                                    <a href="/invoice/{{ $invoice->id }}" class="btn btn-success">View Invoice</a>
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
        </div>
    </div>
    <!--/.col-->
@endsection

@section('head')

@endsection

@section('scripts')

@endsection
