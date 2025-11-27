@extends('layouts.app')

@section('title')
	TPV Support Queries
@endsection

@section('content')
    <div class="container-fluid">
        <div class="animated fadeIn">
            <br />

            {{ Form::open(['route' => 'katana.search']) }}
            <div class="row w-100 p-3">
                <div class="col-md-4">
                    <select name="mode" class="form-control form-control-lg">
                        <option value="events_confirmation_code" @if ($mode == 'events_confirmation_code') selected @endif>Events - Confirmation Code</option>
                        <option value="users_user_id" @if ($mode == 'users_user_id') selected @endif>Users - User ID</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="parameter" class="form-control form-control-lg" value="{{ $parameter }}" />
                </div>
                <div class="col-md-4">
                    <input type="submit" value="Submit" class="btn btn-lg btn-success" />
                </div>
            </div>
            {{ Form::close() }}
            
            <hr />

            @isset ($query)
                {{-- <pre>
                    {{ print_r($query) }}
                    <hr>
                    {{ print_r($relations) }}
                </pre> --}}
                <div class="card">
                    <div class="card-header">
                        <b>{{ $table }}</b>
                    </div>
                    <div class="card-body">
                        @foreach ($query as $key => $val)
                            <b>{{ $key }}</b>: {{ $val }}<br />
                        @endforeach
                    </div>
                </div>

                @foreach ($relations as $relation => $data)
                    @if (!is_array(current($data)))
                        <div class="card">
                            <div class="card-header">
                                <b>{{ $relation }}</b>
                            </div>
                            <div class="card-body">
                                @foreach ($data as $d_key => $d_val)
                                    @switch(gettype($d_val))
                                        @case('array')
                                            <pre>
                                                {{ print_r($d_val) }}
                                            </pre>
                                            @break
                                        @case('integer')
                                            <b>{{ $d_key }}</b>: {{ $d_val }}<br />
                                            @break
                                        @default
                                            @php
                                                json_decode($d_val);    
                                            @endphp
                                            @if (
                                                    (
                                                        (
                                                            substr($d_val, 0, 1) === '{'
                                                            || substr($d_val, 0, 1) === '['
                                                        ) 
                                                        && json_last_error() === JSON_ERROR_NONE
                                                    )
                                                    || substr($d_val, 0, 10) === 'data:image'
                                            )
                                                <b>{{ $d_key }}</b>: truncated <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal_{{ $d_key }}">
                                                        View
                                                      </button><br />
                                                <div class="modal fade" id="modal_{{ $d_key }}" tabindex="-1" role="dialog" aria-labelledby="modal_{{ $d_key }}_title" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg" role="document">
                                                        <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="modal_{{ $d_key }}_title">{{ $d_key }}</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body" style="overflow:scroll;">
                                                            @if (substr($d_val, 0, 10) === 'data:image')
                                                                {{ $d_val }}
                                                            @else
                                                                <pre>{{ print_r(json_decode($d_val, true)) }}</pre>    
                                                            @endif
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                        </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <b>{{ $d_key }}</b>: {{ $d_val }}<br />
                                            @endif
                                    @endswitch
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="row">
                            @foreach ($data as $row)
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <b>{{ $relation }}</b>
                                    </div>
                                    <div class="card-body">
                                        @foreach ($row as $r_key => $r_val)
                                            @switch(gettype($r_val))
                                                @case('array')
                                                    <pre>
                                                        {{ print_r($r_val) }}
                                                    </pre>
                                                    @break
                                                @case('integer')
                                                    <b>{{ $r_key }}</b>: {{ $r_val }}<br />
                                                    @break
                                                @default
                                                    @php
                                                        json_decode($r_val);    
                                                    @endphp
                                                    @if (
                                                            (
                                                                (
                                                                    substr($r_val, 0, 1) === '{'
                                                                    || substr($r_val, 0, 1) === '['
                                                                ) 
                                                                && json_last_error() === JSON_ERROR_NONE
                                                            )
                                                            || substr($r_val, 0, 10) === 'data:image'
                                                    )
                                                    <b>{{ $r_key }}</b>: truncated <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal_{{ $r_key }}">
                                                                View
                                                            </button><br />
                                                        <div class="modal fade" id="modal_{{ $r_key }}" tabindex="-1" role="dialog" aria-labelledby="modal_{{ $r_key }}_title" aria-hidden="true">
                                                            <div class="modal-dialog modal-lg" role="document">
                                                                <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="modal_{{ $r_key }}_title">{{ $r_key }}</h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body" style="overflow:scroll;">
                                                                        @if (substr($r_val, 0, 10) === 'data:image')
                                                                            {{ $r_val }}
                                                                        @else
                                                                            <pre>{{ print_r(json_decode($r_val, true)) }}</pre>    
                                                                        @endif
                                                                    </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <b>{{ $r_key }}</b>: {{ $r_val }}<br />
                                                    @endif
                                            @endswitch
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @else
                No records found in <strong>{{ $table }}</strong> table.
            @endisset
        </div>
    </div>
@endsection

@section('head')

@endsection

@section('scripts')
<script>
    $('.modal-dialog').parent().on('show.bs.modal', function(e){ $(e.relatedTarget.attributes['data-target'].value).appendTo('body'); })
</script>
@endsection