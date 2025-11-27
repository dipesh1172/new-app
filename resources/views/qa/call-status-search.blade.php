@extends('layouts.app')

@section('title')
Call Status Search
@endsection

@section('content')
<div class="container-fluid mt-5">
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <form class="form form-inline">
        <select class="form-control" name="type">
            <option value="call" @if($type == 'call') selected @endif>Phone Call</option>
            <option value="sms" @if($type == 'sms') selected @endif>Text Message</option>
        </select>
        <input class="form-control" type="date" format="Y-m-d" value="{{$date}}" name="date">
        <input class="form-control" type="text" value="{{$phone}}" name="to">
        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Search</button>
    </form>
</div>
<div class="card-body p-0">
<table class="table pb-0 mb-0">
    <thead>
        <tr>
            <th>
                Identifier
            </th>
            <th>Date of 
            @if($type == 'call')
            Call
            @endif
            @if($type == 'sms')
            Message
            @endif
            </th>
            <th>From</th>
            <th>To</th>
            <th>@if($type == 'call')
            Call
            @endif
            @if($type == 'sms')
            Message
            @endif Status</th>
            <th>@if($type == 'call')
            Call Duration
            @endif
            @if($type == 'sms')
            &nbsp;
            @endif </th>
        </tr>
    </thead>
    <tbody>
        @if(count($calls) == 0 && $phone !== null)
            <tr><td colspan="6">No Results</td></tr>
        @endif
        @foreach($calls as $call)
            <tr>
                <td>
                    <pre>{{ $call->sid }}</pre>
                </td>
                <td>
                    {{ $call->dateCreated->format('m-d-Y g:i:s a')}} UTC
                    
                </td>
                <td>
                    {{ $call->from }}
                </td>
                <td>
                    {{ $call->to }}
                </td>
                @if($call instanceof \Twilio\Exceptions\TwilioException)
                    <td colspan="2">
                        Error: {{ $call->getCode() }}<br>
                        <pre>{{ $call->getMessage() }}</pre>
                    </td>
                @else
                    <td>
                        <strong>{{ ucfirst($call->status) }}</strong>
                        @if(!empty($call->errorCode) && !empty($call->errorMessage))
                            <div class="alert alert-warning">
                                <strong>Error Code:</strong> {{ $call->errorCode }} <br>
                                <strong>Error Msg:</strong> {{ $call->errorMessage }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($type == 'call')
                            {{ $call->duration > 0 ? sprintf('%01.3g',$call->duration / 60) : 0 }} minutes
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
</div>
</div>
</div>
</div>
</div>

@endsection
