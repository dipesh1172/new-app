@extends('layouts.raw')

@section('title')
{{$kb->title}}
@endsection

@section('content')

	<div class="container-fluid">
	<div class="row knowledge-base knowledge-base-raw">
	<h1>{{$kb->title}}</h1>
		<div class="alerts">
		@foreach($alerts as $alert)
			<table class="alert-table">
			<tr>
			<td class="alert-table-image"><img src="/img/alert-@php
			echo(($alert->icon == 4 ? '4.gif' : $alert->icon.'.png'));
			@endphp" />
			</td>
			<td class="alert alert-tpv">
				{!! $alert->message !!}
			</td>
			</tr>
			</table>
		@endforeach
		</div>
		

	</div>
	<div class="knowledge-base-content">
		{!! $kb->content !!}
		</div>
	</div>
	<div class="container-fluid">
		<div class="footie row">
			<div class="col-4">VS-{{$kb->version}}.0-{{(new \DateTime($kb->updated_at))->format('m/Y')}}</div>
			<div class="col-4">&nbsp;</div>
			<div class="col-4"><span class="pull-right">&copy; 1997-{{date('Y')}} TPV.com</span></div>
            
		</div>
        <div class="row">
        <div class="col-12 text-small">
                Information contained within this system is confidential and the property of Data Exchange, Inc.
            </div>
        </div>
	</div>
@endsection

@section('extra-styles')
<style>
	.knowledge-base-content table, .knowledge-base-content td {
		border: 2px solid #333;
	}
	body {
		font-family: Tahoma,sans-serif;
		font-size: 16px;
		color: #333;
		line-height: 1.2em;
		background-color: #fff;
	}
	.table>tbody>tr>td, .table>tbody>tr>th, .table>tfoot>tr>td, .table>tfoot>tr>th, .table>thead>tr>td, .table>thead>tr>th {
		line-height: 1.2em;
	}

	h1,h2,h3,h4 {
		color: #4a86e8;
	}

	.footie {
		clear: both;
        padding-top: 20px;
		padding-bottom: 5px;
		border-bottom: 1px solid #4a86e8;
	}
    .text-small {
        font-size: 75%;
    }

</style>
@endsection


