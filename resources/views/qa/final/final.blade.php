@extends('layouts.app')

@section('title')
QA - Final Disposition List
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">Final Disposition List</li>
    </ol>

    <div class="container-fluid">
    	<div class="animated fadeIn">
            <div class="row page-buttons">
                <div class="col-md-6"></div>
                <div class="col-md-6">
                    
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Final Disposition List
                </div>
                <div class="card-body">
                    @if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif

                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Channel</th>
                                <th>Vendor</th>
                                <th>Sales Agent</th>
                                <th>Length</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(empty($events))
                                <tr><td colspan="3" class="text-center">No events were found.</td></tr>
                            @else
                                @foreach ($events as $event)
                                    <tr>
                                        <td>{{ date('F j, Y, g:i a', strtotime($event->created_at)) }}</td>
                                        <td>{{ strtoupper($event->channel) }}</td>
                                        <td>{{ $event->brand_name }}</td>
                                        <td>
                                            @if ($event->last_name && $event->first_name)
                                                {{ $event->first_name }} {{ $event->last_name }}
                                            @elseif ($event->last_name)
                                                {{ $event->last_name }}
                                            @elseif ($event->first_name)
                                                {{ $event->first_name }}
                                            @endif
                                        </td>
                                        <td>{{ $event->event_length }}</td>
                                        <td class="text-right" nowrap="nowrap">
                                            <!-- @//if (in_array(Auth::user()->brands[0]->role_id, array(1,2,3))) -->
                                                <a href="{{ URL::route('qa_final.show', [$event->id]) }}" class="btn btn-sm btn-primary"> view</a> 
                                            <!-- @//endif -->
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                    {{ $events->links() }}
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
