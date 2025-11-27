@extends('layouts.app')

@section('title')
	TPV Compare Comfirmation Codes
@endsection

@section('content')
    <div class="container-fluid">
        <div class="animated fadeIn">
            <br />

            <h1>Katana - Compare Confirmation Codes</h1>

            {{ Form::open(['route' => 'katana.compareConfirmationCodes', 'method' => 'GET']) }}
            <div class="row w-100 p-3">
                <div class="col-md-4">
                    <input type="text" name="code1" class="form-control form-control-lg" value="{{ $code1 }}" autocomplete="off" />
                </div>
                <div class="col-md-4">
                    <input type="text" name="code2" class="form-control form-control-lg" value="{{ $code2 }}" autocomplete="off" />
                </div>
                <div class="col-md-4">
                    <input type="submit" value="Submit" class="btn btn-lg btn-success" />
                </div>
            </div>
            {{ Form::close() }}
            
            <hr />

            @if ($code1 && $code2)
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <center><b>{{ $code1 }}</b></center>
                        </div>
                        <div class="card-body">
<pre>
{{ print_r($results1, true) }}
</pre>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <center><b>{{ $code2 }}</b></center>
                        </div>
                        <div class="card-body">
<pre>
{{ print_r($results2, true) }}
</pre>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection

@section('head')

@endsection

@section('scripts')

@endsection