@extends('layouts.app')

@section('title')
Support: Client Alert Test
@endsection

@php
$messages = [
    'btn_not_approved' => 'The BTN provided is not part of the Approved Customers List.',
            'unknown_error' => 'An unexpected error has occured.',
            'end_call_with_disposition' => 'Thank You, we will mark this call as {disposition}. If you have any questions please call customer service at {cx_service_number}.',
            'end_call_no_disposition' => 'Thank You for your time, we will mark your account(s) as not enrolled at this time. If you have any questions please call customer service at {cx_service_number}',
            'btn_is_sales_agent' => 'The provided billing telephone number (BTN) is listed as a sales agent phone line, please provide an alternate number or contact your supervisor for assistance.',
            'acct_prev_enrolled' => 'The account identifier(s) {identifier} has been previously enrolled. Please enter a valid account identifier or contact your supervisor for assistance.',
            'btn_reuse' => 'This billing telephone number has been used to verify multiple accounts recently. Please check number entry, provide an alternate number, or contact your supervisor for assistance.',
            'svc_addr_reuse' => 'This service address has been used for another sale. Please provide a valid service address or contact your supervisor for assistance.',
            'cust_prev_enrolled' => 'Our records indicate the authorizing name and billing telephone number have been previously enrolled. Please enter a valid name and BTN or contact your supervisor for assistance.',
            'cust_email_prev_enrolled' => 'Our records indicate the Email Address has been previously enrolled with a different authorizing name. Please enter a valid email address or contact your supervisor for assistance.',
            'existing_acct' => 'Our records indicate the authorizing name and billing telephone number have been previously enrolled. Please enter a valid name and BTN or contact your supervisor for assistance.',
            'voip' => 'The provided BTN appears to be a VOIP phone. Please enter a valid BTN or contact your supervisor for assistance.',
            'birthday_1' => 'I apologize, the birthdate you provided is less than 18 years old.',
            'birthday_2' => 'Please enter the birthday as MMDDYYYY',
            'sales_limit' => 'I apologize agent, you have reached your sales limit for the day. If you have any questions please contact your supervisor because we will not be able to proceed. Thanks, have a great day!',
            'after_curfew' => 'I apologize agent, you are not allowed to perform sales past the curfew time. If you have any questions please contact your supervisor. Thanks, have a great day!',
            'multi_tpv' => 'Our records indicate the authorizing name and billing telephone number have been previously enrolled. Please enter a valid name and BTN or contact your supervisor for assistance.',
            'account_number_no_sale' => 'We show this account has been verified too many times. Please have the customer call {brand} Customer Service {cx_service_number} to discuss their account options.',
            'btn_no_sale_dispositions' => 'I\'m sorry. We cannot enroll your account at this time due to multiple failed attempts on the premise. If you have any questions, please contact {brand} at {cx_service_number}.',
            'account_number_good_sale' => 'We show this account has been verified too many times. Please have the customer call {brand} at {cx_service_number} to discuss their account options.',
            'btn_no_sale' => 'Our records indicate this billing telephone number is associated with a previous no sale. Please enter a valid BTN or contact your supervisor for assistance.',
            'email_reuse' => 'This Email address has been used previously. Please provide an alternate email address or contact your supervisor for assistance.',
            'record_id_not_found' => 'I"m sorry, this Record ID could not be located.  We can attempt the import again or we can proceed manually.',
            'record_id_existing' => 'I"m sorry, this confirmation code has been used for a previous good sale please enter a valid confirmation number or we can proceed manually.',
            'record_id_existing_no_manual' => 'I"m sorry, this confirmation code has used for a previous good sale. Please submit a new enrollment via EzTPV or contact your supervisor with any questions.',
            'record_id_wrong_state' => 'I"m sorry, this confirmation code is only valid in {needstate} but you called to verify for {instate}.',
            'record_id_wrong_channel' => 'This confirmation code is associated with a {event_channel} enrollment, however, this call is for {called_channel}.',
            'blacklist' => 'I\’m sorry. This account ({ident}) has been enrolled multiple time and can no longer be processed with {client}. If you have any questions, please contact your supervisor. Thank you. Goodbye.',
            'active_customer' => 'This account ({ident}) is for an existing {client} customer. Please have the customer call {client} Customer Service at {client_phone} to discuss their account options.',
            'language_mismatch' => 'Our records indicate this enrollment was performed in another language. In order to continue you must select the correct language from the phone menu. If you have any questions please direct them to your supervisor.',
            'rep_language_mismatch' => 'You are not currently configured to permit sales in the selected language.  Please contact your supervisor if you have any questions. Thank you, have a great day!',
            'invalid-date-simple' => 'The selected date is invalid for selection.',
            'invalid-date-more' => 'The selected date is invalid for selection. Please choose a different date or contact your supervisor for assistance.',
            'invalid-value' => 'The input value is invalid',
            'call-busy' => 'The call failed because the line is busy.',
            'call-no-answer' => 'The call failed because there was no answer.',
            'call-failed' => 'The call failed, the number dialed may not be a valid number.',
            'call-canceled' => 'The call failed because it was cancelled.',
            'invalid-first-name' => 'You must provide a First Name',
            'invalid-last-name' => 'You must provide a Last Name',
            'invalid-contact-number' => 'Please provide the contact phone number.',
            'invalid-birth-date' => 'Please provide the birthdate in the format MMDDYYYY',
            'invalid-phone' => 'Please enter a valid telephone number',
            'invalid-email' => 'Invalid email address',
            'invalid-custom-fields' => 'Please check all inputs are valid, one or more fields are not valid.',
            'invalid-enroll-type' => 'Please select how many service addresses will be enrolled today.',
            'invalid-identification' => 'The entered identification does not validate and cannot be saved.',
];
@endphp

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Support</li>
        <li class="breadcrumb-item active">Client Alert Test</li>
    </ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Client Alert Test
                </div>
                <div class="card-body">
                    @if(Session::has('flash_message'))
                        <div class="alert alert-danger"><em> {!! session('flash_message') !!}</em></div>
                    @endif
                    <div class="row">
                        <div class="col-12">
                            <form id="mainform" method="GET">
                            {{ csrf_field() }}
                                <div class="row">
                                    <div class="col-4">
                                        <div id="conf_code_field" class="form-group">
                                            <label for="confcode" class="form-control-label">Confirmation Code</label>
                                            <input type="text" value="{{$confcode}}" class="form-control" name="confcode" id="confcode">
                                        </div>
                                        <div id="raw_input_field" class="form-group d-none">
                                            <label for="rawinput" class="form-control-label">Raw Data Input</label>
                                            <textarea name="rawinput" id="rawinput" class="form-control">{{ $rawinput }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-group">
                                            <label for="mode" class="form-control-label">Mode</label> 
                                            <select name="mode" class="form-control" id="mode">
                                                <optgroup label="Normal">
                                                    <option value="check" @if($mode == 'check') selected @endif>Run Alerts</option>
                                                </optgroup>
                                                <optgroup label="Advanced">
                                                    <option value="funcs" @if($mode == 'funcs') selected @endif>Configured Alerts</option>
                                                    <option value="pdata" @if($mode == 'pdata') selected @endif>Inspect Data Standardization with Raw Data</option>
                                                </optgroup>
                                                
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-group">
                                            <label for="category" class="form-control-label">Alert Category</label>
                                            <select name="category" id="category" class="form-control">
                                                <option value="1" @if($category == 1) selected @endif>CALL START</option>
                                                <option value="2" @if($category == 2) selected @endif>CUST INFO PROVIDED</option>
                                                <option value="3" @if($category == 3) selected @endif>ACCT INFO PROVIDED</option>
                                                <option value="4" @if($category == 4) selected @endif>DISPOSITIONED</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-4">
                                        <div class="form-group">
                                            <label for="ani" class="form-control-label">ANI (if provided, override BTN)</label>
                                            <input type="text" value="{{$ani}}" class="form-control" name="ani" id="ani">
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        
                                        @if($mode == 'check' && $pcount > 1)
                                            <div class="form-group">
                                                <label for="product_index" class="form-control-label">Product Index</label>
                                                <select name="product_index" id="product_index" class="form-control">
                                                    @for($i = 0; $i < $pcount; $i += 1)
                                                        <option value="{{ $i }}" @if($product_index == $i) selected @endif>{{ $i + 1 }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-4">
                                        <div class="form-check pull-right">
                                            <input type="checkbox" value="on" class="form-check-input ml-0" id="write_entries" name="write" @if($writeEntries) checked @endif >
                                            <label for="write_entries" class="form-check-label" title="When enabled will write alert entries and send emails (in Run Alerts mode)">Perform normal operations</label>
                                        </div>
                                        <br><br>
                                        <button type="submit" class="btn btn-primary pull-right">Submit</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <p class="text-muted">
                                Running alerts through this tool will not create any alert entries for that event (unless Perform normal operations is checked). However any alert that has side effects 
                                like VOIP checks doing a lookup or Sending a SMS with the disposition will still occur including creating invoiceable artifacts.
                                If a message is given then the alert is considered to have triggered and in normal operation would've created the alert entries on 
                                event whether it stops the call or not.
                            </p>
                        </div>
                    </div>
                    @if($mode == 'funcs')
                        <hr>
                        @if(empty($funcs) || count($funcs) == 0)
                            <div class="row mt-4">
                                <div class="col-12">There are no alerts enabled for this brand/category</div>
                            </div>
                        @else
                            <div class="row mt-4">
                                <div class="col-12">The following alerts are enabled and would run for this event/category</div>
                                @foreach($funcs as $func)
                                    <div class="col-6">
                                        <div class="card">
                                            <div class="card-header">
                                                {{ $func['title'] }}
                                                @if($func['category_id'] < 4)
                                                    @if($func['stop_call'])
                                                        <span class="badge badge-danger pull-right">Would Stop Call</span>
                                                    @else
                                                        <span class="badge badge-success pull-right">Would NOT Stop Call</span>
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="card-body">
                                                {{ $func['description'] }} 
                                            </div>
                                            @if(isset($func['threshold']) && $func['threshold'] !== null)
                                                <div class="card-footer">
                                                    <span title="Some alerts have a threshold set but do not use it.">
                                                        Threshold: <span class="badge badge-info">{{ $func['threshold'] }}</span>
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    @if($mode == 'check')
                        <div class="row">
                            @if($pcount == 0 && $category == 3)
                                <div class="col-12">
                                    <div class="alert alert-danger">
                                        There are no products associated with this event.
                                    </div>
                                </div>
                            @endif
                            <div class="col-12">
                                <div id="accordion">
                                    <div class="card mb-0">
                                        <div class="card-header" id="alert-result-header">
                                            <h5 class="mb-0">
                                                <button class="btn btn-link" data-toggle="collapse" data-target="#alertresult" aria-expanded="true" aria-controls="alertresult">
                                                Alert Result
                                                </button>
                                            </h5>
                                        </div>
                                        <div id="alertresult" class="collapse show" aria-labelledby="alert-result-header" data-parent="#accordion">
                                            <div class="card-body">
                                                <ol>
                                                    <li>Would stop call? @if($response['stop-call']) <strong>Yes</strong> @else <strong>No</strong> @endif </li>
                                                    <li>Errors? @if(empty($response['errors'])) <strong>None</strong> @else <strong>{{ $response['errors'] }}</strong> @endif </li>
                                                    @if($response['disposition'] !== null)
                                                        <li>Would set disposition</li>
                                                    @endif
                                                    <li>Messages: 
                                                        @if($response['message'] === null) 
                                                            <strong>None</strong> 
                                                        @else 
                                                            @if(is_string($response['message']))
                                                                @if(isset($messages[$response['message']]))
                                                                    <strong>{{ $messages[$response['message']] }}</strong> 
                                                                @else
                                                                    @if($response['message'] == 'indra_active_api_fail' && !empty($response['extra']['msg']))
                                                                        <span class="badge badge-warning">Indra Message</span> <strong>{{ $response['extra']['msg'] }}</strong>
                                                                    @else
                                                                        <strong>{{ $response['message'] }}</strong> 
                                                                    @endif
                                                                @endif
                                                            @else
                                                                @foreach($response['message'] as $msg)
                                                                    <p>
                                                                    @if(isset($messages[$msg]))
                                                                        <strong>{{ $messages[$msg] }}</strong> 
                                                                    @else
                                                                        @if($msg == 'indra_active_api_fail' && !empty($response['extra']['msg']))
                                                                            <span class="badge badge-warning">Indra Message</span> <strong>{{ $response['extra']['msg'] }}</strong>
                                                                        @else
                                                                            <strong>{{ $msg }}</strong> 
                                                                        @endif
                                                                    @endif
                                                                    </p>
                                                                @endforeach
                                                            @endif
                                                        @endif 
                                                    </li>
                                                    <li>Time to process: <strong>{{ $ttp }} ms</strong> <p class="text-muted">Note this time will be slightly lower than when called from Agents/EzTPV due to extra overhead</p></li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-0">
                                        <div class="card-header" id="alert-odata-header">
                                            <h5 class="mb-0 pull-left">
                                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#alertodata" aria-expanded="false" aria-controls="alertodata">
                                                Alert Data 
                                                </button>
                                            </h5>
                                            <span class="badge badge-light pull-right">
                                                This data is what would be passed from Agents/EzTPV
                                            </span>
                                        </div>
                                        <div id="alertodata" class="collapse" aria-labelledby="alert-odata-header" data-parent="#accordion">
                                            <div class="card-body">
                                                <pre class="bg-light">{{ json_encode($originalData, \JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-0">
                                        <div class="card-header" id="alert-fdata-header">
                                            <h5 class="mb-0 pull-left">
                                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#alertfdata" aria-expanded="false" aria-controls="alertfdata">
                                                &quot;Standardized&quot; Alert Data
                                                </button>
                                            </h5>
                                            <span class="badge badge-light pull-right">
                                                This data is what would be passed to the individual alert functions after cleaning.
                                            </span>
                                        </div>
                                        <div id="alertfdata" class="collapse" aria-labelledby="alert-fdata-header" data-parent="#accordion">
                                            <div class="card-body">
                                                <pre class="bg-light">{{ json_encode($formattedData, \JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-0">
                                        <div class="card-header" id="alert-fulldata-header">
                                            <h5 class="mb-0">
                                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#alertfulldata" aria-expanded="false" aria-controls="alertfulldata">
                                                Full Event Data
                                                </button>
                                            </h5>
                                        </div>
                                        <div id="alertfulldata" class="collapse" aria-labelledby="alert-fulldata-header" data-parent="#accordion">
                                            <div class="card-body">
                                                <pre class="bg-light">{{ json_encode($fullData, \JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-0">
                                        <div class="card-header" id="alert-response-header">
                                            <h5 class="mb-0">
                                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#alertresponsedata" aria-expanded="false" aria-controls="alertresponsedata">
                                                Raw Response
                                                </button>
                                            </h5>
                                        </div>
                                        <div id="alertresponsedata" class="collapse" aria-labelledby="alert-response-header" data-parent="#accordion">
                                            <div class="card-body">
                                                <pre class="bg-light">{{ json_encode($rawResponse, \JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if($mode == 'pdata')
                        @if($dataIsEqual)
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        Data is unchanged after standardization
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-6">
                                <div class="card">
                                    <div class="card-header">Input Data</div>
                                    <div class="card-body p-0">
                                        <pre class="">{{ json_encode($rawData, \JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card">
                                    <div class="card-header">Standardized Data</div>
                                    <div class="card-body p-0">
                                        <pre class="">{{ json_encode($standardizedData, \JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    
@endsection

@section('scripts')
<script>
    window.removeClass = function(element, $class) {
        if(element.classList.contains($class)) {
            element.classList.remove($class);
        }
    };
    window.addClass = function(element, $class) {
        if(!element.classList.contains($class)) {
            element.classList.add($class);
        }
    };

    window.updateDisplay = function(mode) {
        var confCodeField = document.getElementById('conf_code_field');
        var rawInputField = document.getElementById('raw_input_field');
        var realRawInputField = document.getElementById('rawinput');
        var mainForm = document.getElementById('mainform');
        var tokenField = document.getElementsByName('_token');
        if(tokenField.length > 0) {
            tokenField = tokenField[0];
            tokenField.id = '_token';
        } else {
            tokenField = document.getElementById('_token');
        }
        
        if(mode === 'pdata') {
            realRawInputField.name = 'rawinput';
            tokenField.name = '_token';
            removeClass(rawInputField, 'd-none');
            addClass(confCodeField, 'd-none');
            mainForm.method = 'POST';
        } else {
            realRawInputField.name = '';
            tokenField.name = '';
            addClass(rawInputField, 'd-none');
            removeClass(confCodeField, 'd-none');
            mainForm.method = 'GET';
        }
    };

    (function() {
        var modeSelector = document.getElementById('mode');
        modeSelector.addEventListener('change', function(event) {
            updateDisplay(event.target.value);
        });

        updateDisplay('{{ $mode }}');
    })();
</script>
@endsection

