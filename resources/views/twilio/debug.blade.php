@extends('layouts.app')

@section('title')
Twilio: Debug
@endsection

@section('content')
<!-- Breadcrumb -->
<ol class="breadcrumb">
    <li class="breadcrumb-item">Home</li>
    <li class="breadcrumb-item">Twilio</li>
    <li class="breadcrumb-item active">Debug</li>
</ol>

<div class="container-fluid">
    <!-- navlist include goes here -->

    <div class="tab-content">
        <div role="tabpanel" class="tab-pane active">
            <div class="animated fadeIn">
                <div class="row page-buttons">
                    <div class="col-md-12"></div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-th-large"></i> Twilio: Debug
                    </div>
                    <div class="card-body">
                        <button class="accordion">Calls</button>
                        <div class="panel">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Created</th>
                                        <th>Direction</th>
                                        <th>Duration</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Forwarded From</th>
                                        <th>From</th>
                                        <th>Parent Call SID</th>
                                        <th>Price</th>
                                        <th>Price Unit</th>
                                        <th>SID</th>
                                        <th>Status</th>
                                        <th>To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($calls as $call)
                                    <tr>
                                        <td>{{ $call->dateCreated !== null ? $call->dateCreated->format('m-d-Y h:i:s A') : ''}}</td>
                                        <td>{{ $call->direction }}</td>
                                        <td>{{ $call->duration }}</td>
                                        <td>{{ $call->startTime !== null ? $call->startTime->format('m-d-Y h:i:s A') : ''}}</td>
                                        <td>{{ $call->endTime !== null ? $call->endTime->format('m-d-Y h:i:s A') : ''}}</td>
                                        <td>{{ $call->forwardedFrom }}</td>
                                        <td>{{ $call->from }}</td>
                                        <td>{{ $call->parentCallSid }}</td>
                                        <td>{{ $call->price }}</td>
                                        <td>{{ $call->priceUnit }}</td>
                                        <td>{{ $call->sid }}</td>
                                        <td>{{ $call->status }}</td>
                                        <td>{{ $call->to }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <button class="accordion">Stats</button>
                        <div class="panel">
                            {{ $stats }}
                        </div>

                        <button class="accordion">Tasks</button>
                        <div class="panel">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Age</th>
                                        <th>Assignment Status</th>
                                        <th>Date Created</th>
                                        <th>Task Queue Friendly Name</th>
                                        <th>Task Sid</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tasks as $task)
                                    <tr>
                                        <td>{{ $task->age }}</td>
                                        <td>{{ $task->assignmentStatus }}</td>
                                        <td>{{ $task->dateCreated !== null ? $task->dateCreated->format('m-d-Y h:i:s A') : ''}}</td>
                                        <td>{{ $task->taskQueueFriendlyName }}</td>
                                        <td>{{ $task->sid }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <button class="accordion">Task Queues</button>
                        <div class="panel">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Created</th>
                                        <th>Assigned</th>
                                        <th>Reserved</th>
                                        <th>Target Workers</th>
                                        <th>Task Order</th>
                                        <th>SID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($task_queues as $task_queue)
                                    <tr>
                                        <td>{{ $task_queue->friendlyName }}</td>
                                        <td>{{ $task_queue->dateCreated !== null ? $task_queue->dateCreated->format('m-d-Y h:i:s A') : ''}}</td>
                                        <td>{{ $task_queue->assignmentActivityName }}</td>
                                        <td>{{ $task_queue->reservationActivityName }}</td>
                                        <td>{{ $task_queue->targetWorkers }}</td>
                                        <td>{{ $task_queue->taskOrder }}</td>
                                        <td>{{ $task_queue->sid }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <button class="accordion">Workers</button>
                        <div class="panel">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Created</th>
                                        <th>Status Changed</th>
                                        <th>Available</th>
                                        <th>Activity</th>
                                        <th>Attributes</th>
                                        <th>SID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($workers as $worker)
                                    <tr>
                                        <td>{{ $worker->friendlyName }}</td>
                                        <td>{{ $worker->dateCreated !== null ? $worker->dateCreated->format('m-d-Y h:i:s A') : ''}}</td>
                                        <td>{{ $worker->dateStatusChanged !== null ? $worker->dateStatusChanged->format('m-d-Y h:i:s A') : ''}}</td>
                                        <td>{{ $worker->available }}</td>
                                        <td>{{ $worker->activityName }}</td>
                                        <td>{{ $worker->attributes }}</td>
                                        <td>{{ $worker->sid }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <button class="accordion">Activities</button>
                        <div class="panel">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Created</th>
                                        <th>Available</th>
                                        <th>SID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activities as $activity)
                                    <tr>
                                        <td>{{ $activity->friendlyName }}</td>
                                        <td>{{ $activity->dateCreated !== null ? $activity->dateCreated->format('m-d-Y h:i:s A') : ''}}</td>
                                        <td>{{ $activity->available }}</td>
                                        <td>{{ $activity->sid }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--/.col-->
@endsection

@section('head')
<style>
    /* Style the buttons that are used to open and close the accordion panel */
    .accordion {
        background-color: #eee;
        color: #444;
        cursor: pointer;
        padding: 18px;
        width: 100%;
        text-align: left;
        border: none;
        outline: none;
        transition: 0.4s;
    }

    /* Add a background color to the button if it is clicked on (add the .active class with JS), and when you move the mouse over it (hover) */
    .active_accordion,
    .accordion:hover {
        background-color: #ccc;
    }

    /* Style the accordion panel. Note: hidden by default */
    .panel {
        padding: 0 18px;
        background-color: white;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.2s ease-out;
    }

    .accordion:after {
        content: '\02795';
        /* Unicode character for "plus" sign (+) */
        font-size: 13px;
        color: #777;
        float: right;
        margin-left: 5px;
    }

    .active_accordion:after {
        content: "\2796";
        /* Unicode character for "minus" sign (-) */
    }

    .panel {
        overflow: scroll;
    }
</style>
@endsection

@section('scripts')
<script>
    var acc = document.getElementsByClassName("accordion");
    var i;

    for (i = 0; i < acc.length; i++) {
        acc[i].addEventListener("click", function() {
            this.classList.toggle("active_accordion");
            var panel = this.nextElementSibling;
            if (panel.style.maxHeight) {
                panel.style.maxHeight = null;
            } else {
                panel.style.maxHeight = panel.scrollHeight + "px";
            }
        });
    }
</script>
@endsection
