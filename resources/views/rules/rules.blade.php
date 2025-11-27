@extends('layouts.app')

@section('title')
Business Rules
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">Business Rules</li>
    </ol>

    <div class="container-fluid">
    	<div class="animated fadeIn">
     		<div class="row page-buttons">
                <div class="col-md-6"></div>
                <div class="col-md-6">
                    <div class="form-group pull-right ml-1">
                        <a href="{{ URL::route('rules.create') }}" class="btn btn-success">Add Business Rule</a>
                    </div>
                </div>
            </div>
	        <div class="card">
	            <div class="card-header">
	                <i class="fa fa-th-large"></i> Business Rules
	            </div>
	            <div class="card-body">
	            	<table class="table table-responsive" id="rules">
	            		<tbody>
	            			@if($rules->isEmpty())
                                <tr><td colspan="2" class="text-center">No business rules were found.</td></tr>
                            @else
                            	@foreach ($rules as $rule)
                                    <tr>
                                        <td>{{ $rule->business_rule }}</td>
                                        <td>{!! $rule->answers_html !!}</td>
                                        <td class="text-right" nowrap="nowrap">
                                            <a href="{{ URL::route('rules.edit', $rule->id) }}" class="btn btn-sm btn-primary">edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
	            		</tbody>
	            	</table>
	            	{{ $rules->links() }}
	            </div>
	        </div>
	        <br /><br />
    	</div>
    </div>
@endsection

@section('head')
<style>
#rules td {
	font-size: 17pt;
}
</style>
@endsection

@section('scripts')

@endsection
