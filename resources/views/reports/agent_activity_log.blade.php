@extends('layouts.app')

@section('title')
Live Agent Dashboard: Agent Activity Log
@endsection

@section('content')
@breadcrumbs([
['name' => 'Home', 'url' => '/'],
['name' => 'Live Agent Dashboard', 'url' => '/live-agent', 'active' => false],
['name' => 'Agent Activity Log', 'url' => '/agent-activity-log', 'active' => true]
])

<div class="container-fluid">
    <!-- navlist include goes here -->

    <div class="tab-content">
        <div role="tabpanel" class="tab-pane active">
            <div class="animated fadeIn">
                <div class="row page-buttons">
                    <div class="col-md-12"></div>
                </div>
                @if (session('message'))
                    <div class="alert alert-warning">
                        {{ session('message') }}
                    </div>
                @endif
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-th-large"></i> Agent Activity Log
                        <div class="pull-right">
                            {{ Form::open(['method' => 'POST', 'route' => ['live_agent.activity_log'], 'class' => 'form-inline pull-right']) }}
                            {{ Form::hidden('id', @$id) }}
                            {{ Form::submit('Export CSV', ['class' => 'btn btn-success', 'name' => 'submitbutton', 'value' => 'export']) }}
                            {{ Form::close() }}
                        </div>
                        <div class="card-body">
                            @if(sizeof(@$statuses) > 0)
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>TPV Agent Name</th>
                                        <th>TPV Agent ID</th>
                                        <th>TPV Agent Role</th>
                                        <th>Status</th>
                                        <th>Brand</th>
                                        <th>Time</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($statuses as $status)
                                    <tr>
                                        <td>{{ $status['tpv_agent_name'] }}</td>
                                        <td>{{ $status['tpv_agent_id'] }}</td>
                                        <td>{{ $status['tpv_agent_role'] }}</td>
                                        <td>{{ $status['status'] }}</td>
                                        <td>{{ $status['brand'] }}</td>
                                        <td>{{ $status['timestamp'] }}</td>
                                        <td>{{ $status['duration']  }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-secondary">That TPV agent doesn't have any data for today.</div>
                        @endif
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
