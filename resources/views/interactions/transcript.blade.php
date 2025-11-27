@extends('layouts.raw')

@section('title')
Interaction Transcript (#{{$interaction->id}})
@endsection

@section('content')

    @php
    $getSampleTime = function($idate, $adate) {
        $adateCorrected = $adate;
        $adateCorrected->tz = 'America/Chicago';
        $diff = $idate->diffInSeconds($adateCorrected);
        if($diff > 18000) {
            $diff -= 18000;
        }
        return $diff;
    };

    $minAcceptF = 0.75;
    $minCorrectF = 0.95;

    $minAccept = config('app.ivr_minimum_accept', null);
    if ($minAccept === null) {
        $minAccept = runtime_setting('ivr_minimum_accept');
        
        if ($minAccept !== null) {
            $minAccept = intval($minAccept);
        }
    }
    if ($minAccept !== null && is_int($minAccept) && $minAccept >= 0 && $minAccept <= 100) {
        $minAcceptF = $minAccept / 100;
    }

    $minCorrect = config('app.ivr_minimum_correct', null);
    if ($minCorrect === null) {
        $minCorrect = runtime_setting('ivr_minimum_correct');
        if ($minCorrect !== null) {
            $minCorrect = intval($minCorrect);
        }
    }
    if ($minCorrect !== null && is_int($minCorrect) && $minCorrect >= 0 && $minCorrect <= 100) {
        $minCorrectF = $minCorrect / 100;
    }
    @endphp

    <script>
        function playSample(timestamp) {
            console.log('answer at '+timestamp);
            let ael = document.getElementById('wcr');
            if(ael) {
                ael.currentTime = (timestamp - 4);
                ael.ontimeupdate = () => {
                    if(ael.currentTime >= timestamp) {
                        ael.pause();
                        ael.ontimeupdate = null;
                    }
                };
                ael.play();
            }
        }
    </script>

    <div class="container-fluid">
        <h3>Call Recording</h3>
        @if (@$interaction->recording)
            <audio style="width: 100%;" preload="auto" id="wcr" controls><source src="{{ config('services.aws.cloudfront.domain') }}/{{ @$interaction->recording }}">Your browser does not support the audio element.</audio><br /><br />
        @else
            <div class="alert alert-warning">Recording is Not Available.</div>
        @endif
        
        <div class="row">
            <div class="col-md-12">
                <h3>{{ @$script->title }}</h3>
            </div>
        </div>

        <table class="table table-striped">
            @if (!@$answers->isEmpty())
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Question</th>
                        <th>Answer</th>
                    </tr>
                </thead>
            @endif
            <tbody>
                @if (@$answers->isEmpty())
                    <tr>
                        <td colspan="3" class="text-center">No transcript available.</td>
                    </tr>
                @else
                    @php
                        $last = false;
                    @endphp
                    @foreach (@$answers as $answer)
                        @if($last == false && $answer->is_summary)
                            <tr class="bg-light"><td colspan="3">--- BEGIN SUMMARY PAGE ---</td></tr>
                        @endif
                        <tr
                        @if($answer->is_summary)
                        class="bg-light"
                        @endif>
                            <td>{{$answer->section_id}}.{{$answer->subsection_id}}.{{$answer->sq_id}}</td>
                            @if($interaction->interaction_type_id === 20)
                            <td>
                                @foreach(@$answer->question[$interaction->language == 1 ? 'english' : 'spanish'] as $qt)
                                    {!! $hydrate($qt['text'], $data['products'][isset($answer->additional_data) && isset($answer->additional_data['product_index']) ? $answer->additional_data['product_index'] : 0]) !!}
                                @endforeach
                                <hr>
                                <span class="badge badge-secondary" title="Input Expectation Statement">I.E.S.</span> 
                                @foreach(@$answer->question['expectation']['actions'] as $action)
                                    <em>
                                        {{ $action['text'][$interaction->language == 1 ? 'english' : 'spanish']}}
                                    </em>
                                @endforeach
                            </td>
                            @else
                            <td>{{ @$answer->question[$interaction->language == 1 ? 'english' : 'spanish'] }}</td>
                            @endif
                            <td>
                                @if($interaction->interaction_type_id === 20)
                                    @if(@$answer->question['expectation']['type'] === 'record')
                                        @php
                                            $tStatus = isset($answer->additional_data) && isset($answer->additional_data['transcript_status']) ? ($answer->additional_data['transcript_status']) : null;
                                            $transcript = isset($answer->additional_data) && isset($answer->additional_data['transcript_text']) ? ($answer->additional_data['transcript_text']) : null;
                                            $updatedby = isset($answer->additional_data) && isset($answer->additional_data['transcript_updatedby']) ? ($answer->additional_data['transcript_updatedby']) : null;
                                        @endphp
                                        <audio controls>
                                            <source src="{{@$answer->answer}}">
                                            Your browser doesn't support audio elements
                                        </audio>
                                        @if($tStatus != null)
                                            <br>
                                            @if($tStatus == 'completed')
                                                <strong>Transcript:</strong>
                                                <pre class="mb-1 bg-light">{{$transcript}}</pre>
                                                @if($updatedby != null) 
                                                    <em class="small">Text updated by: {{$updatedby}}</em>
                                                @endif
                                            @else
                                                <span class="badge badge-danger" title="Transcription requires the recording to be >= 2 seconds">Auto-transcription Failed</span>
                                            @endif
                                        @else
                                            <span class="badge badge-danger" title="Transcription requires the recording to be >= 2 seconds">Auto-transcription Failed</span>
                                        @endif
                                        <button title="Update Transcription Text" type="button" class="btn btn-sm btn-secondary" onclick="updateTranscriptText('{{$transcript}}', '{{$answer->answer}}')">
                                            <span class="fa fa-pencil"></span>
                                        </button>
                                    @else
                                        {{ @$answer->answer }}
                                        @php
                                            $number = isset($answer->additional_data) && isset($answer->additional_data['numeric_input']) ? $answer->additional_data['numeric_input'] : null;
                                            $voice = isset($answer->additional_data) && isset($answer->additional_data['speech_input']) ? $answer->additional_data['speech_input'] : null;
                                            $confidence = isset($answer->additional_data) && isset($answer->additional_data['speech_confidence']) ? floatval($answer->additional_data['speech_confidence']) : null;
                                        @endphp
                                        @if($number != null)
                                            <span class="badge badge-info">Pressed "{{$number}}"</span>
                                        @endif
                                        @if($voice != null) 
                                            @if($confidence >= $minAcceptF)
                                                @if($confidence < $minCorrectF)
                                                    <span class="badge badge-warning" title="Min: {{$minAcceptF}} Correct: {{$minCorrectF}} Value: {{$confidence}}">Voice Input Accepted (Low Confidence)</span>
                                                @else
                                                    <span class="badge badge-success" title="{Min: {{$minAcceptF}} Correct: {{$minCorrectF}} Value: {{$confidence}}">Voice Input Accepted</span>
                                                @endif
                                            @else
                                                <span class="badge badge-danger" title="Min: {{$minAcceptF}} Correct: {{$minCorrectF}} Value: {{$confidence}}">Voice Input Rejected</span>
                                            @endif
                                            <button type="button" class="btn btn-light" onclick="playSample({{ $getSampleTime($interaction->created_at, $answer->created_at) }})">
                                                <span class="fa fa-play"></span>
                                            </button>
                                        @endif
                                        @php
                                            unset($number);
                                            unset($voice);
                                            unset($confidence);
                                        @endphp
                                    @endif
                                @else
                                    {{ @$answer->answer_type }}
                                    @if (@$answer->answer && $answer->answer !== null && $answer->answer !== 'null')
                                        : {{ @$answer->answer }}
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @php
                        $last = $answer->is_summary;
                        @endphp
                    @endforeach
                @endif
            </tbody>
        </table>
        <div class="modal fade" id="transcript-editor" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Update Text Transcription</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{route('qa.update-ivr-transcript')}}" class="container">
                            {{ csrf_field() }}
                            <input type="hidden" id="answer-id" name="answer">
                            <div class="row">
                                <div class="col-12">
                                    <audio style="width: 100%;" controls id="transcript-audio"><source id="transcript-audio-src" src="">Unsupported</audio>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <label for="transcript-text">Transcription Text:</label>
                                    <textarea class="form-control form-control-lg" id="transcript-text" name="new_text"></textarea>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <button class="btn btn-primary pull-right mt-2" type="submit">Save Transcript</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <script>
    function updateTranscriptText(transcriptText, transcriptAudio) {
        const answer = $('#answer-id');
        const text = $('#transcript-text');
        const dialog = $('#transcript-editor');
        const audio = $('#transcript-audio');
        console.log(transcriptAudio);
        audio.html(`<source src="${transcriptAudio}">`);
        answer.val(transcriptAudio);
        
        if(transcriptText != null) {
            text.html(transcriptText);
        } else {
            text.html('');
        }
        dialog.modal({show: true});
    }
    </script>
    <!--/.col-->
@endsection

@section('head')
    <style>
    body {
        background-image: none;
    }
    </style>
@endsection

@section('scripts')
@endsection