@extends('layouts.raw')

@section('title')
Recording Search
@endsection

@section('content')


@if(count($calls) == 0)
    <div class="alert alert-warning mt-4">
    No recordings found for this interaction
    @if($strict)
    <br>
        <a href="/qa/{{$dir}}recording-search/{{$dxcid}}/{{$date}}?interaction={{$interaction}}">Change to approximate search</a>
    @endif
    </div>
    
@else
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Time of Call</th>
                <th>Call Info</th>
                <th>Duration</th>
                <th>Recording(s)</th>
                <th>Tools</th>
            </tr>
        </thead>
        <tbody>
            @foreach($calls as $call)
                <tr>
                    <td>{{($call['dateCreated']->format('Y-m-d H:i:s'))}}</td>
                    <td>
                        ID: {{$call['sid']}}<br/>
                        From: {{ $call['fromFormatted'] }}<br />
                        To: {{ $call['toFormatted'] }}<br/>
                    </td>
                    <td>{{ $call['duration'] > 0 ? sprintf('%01.3g',$call['duration'] / 60) : 0}} minutes</td>
                    <td>
                        @foreach($call['recordings'] as $recording)
                            <audio controls src="https://api.twilio.com{{ str_replace('.json', '.wav', $recording['uri']) }}">No Audio Support</audio>
                        @endforeach
                    </td>
                    <td>
                    <form method="POST" action="/qa/interaction-update">
                        {{ csrf_field() }}
                        <input type="hidden" name="sid" value="{{$call['sid']}}">
                        <input type="hidden" name="interaction" value="{{$interaction}}">
                        <button type="submit" class="btn btn-success">This is it!</button>
                    </form>
                    <!-- <pre>
                    {!! json_encode($call, \JSON_PRETTY_PRINT) !!}
                    </pre> -->
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if($strict)
        <br>
        <a href="/qa/{{$dir}}recording-search/{{$dxcid}}/{{$date}}?interaction={{$interaction}}">Change to approximate search</a>
    @endif
@endif
@endsection
