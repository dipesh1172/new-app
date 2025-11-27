@extends('layouts.app')

@section('title')
Support: Contract Runner
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Support</li>
        <li class="breadcrumb-item active">Contract Runner</li>
    </ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Contract Runner
                </div>
                <div class="card-body">
                    <p>
                        Enter confirmation code to attempt to regenerate (and resend) contract documents. Separate multiple codes with commas.
                    </p>
                    @if($multiple === false)
                        <form method="POST">
                            {{ csrf_field() }}
                            <div class="row">
                                <div class="col-10">
                                    <input type="text" tabindex="1" class="form-control" name="code" placeholder="Confirmation Code(s)" @if(isset($code)) value="{{$code}}" @endif>
                                </div>
                                <div class="col-2">
                                    
                                    <button tabindex="3" class="btn btn-primary" type="submit">Run <span class="fa fa-arrow-right" ></span></button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-check">
                                        <input tabindex="2" class="form-check-input ml-auto" type="checkbox" @if(isset($preview)) checked @endif value="true" id="previewCheck" name="preview">
                                        <label class="form-check-label" for="previewCheck">
                                            Is Preview Contract?
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </form>
                        @if(isset($output))
                            <hr>
                            @if(trim($output) == '')
                                <pre class="bg-light p-2">No Command Output</pre>
                                <p class="text-muted">This result typically means the specified confirmation code is not configured to use contracts.</p>
                            @else
                                <h4>Command Output</h4>
                                <pre style="text-align: left;" class="bg-light p-2">{{$output}}</pre>
                                <p class="text-muted">
                                    A successful run will typically end with <em>Success!</em> followed by the URL of the generated contract document.
                                </p>
                            @endif
                        @endif
                    @else
                        <form method="POST">
                            {{ csrf_field() }}
                            <div class="row">
                                <div class="col-10">
                                    <input type="text" class="form-control" name="code" placeholder="Confirmation Code(s)">
                                </div>
                                <div class="col-2">
                                    <button id="runbtnmulti" class="btn btn-primary" type="submit">Run <span class="fa fa-arrow-right" ></span></button>
                                </div>
                            </div>
                        </form>
                        <hr>
                        @foreach($code as $ccode)
                            <div class="card mb-2">
                                <div class="card-header">
                                    {{ $ccode }}
                                </div>
                                <div class="card-body" id="code-{{$ccode}}">
                                    <span class="fa fa-spinner fa-spin"></span>
                                </div>
                            </div>
                        @endforeach
                        
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @if($multiple) 
        <script type="text/javascript" defer>
            // Async Function Delaration.  No return so await is not needed when calling this function.
            // Also, not a VUE component so it does not need "npm run dev" to be deployed
            const regenerateContract = async (codes) => {
                console.log(`Confirmation Codes:`, codes);

                if (!Array.isArray(codes)) {
                    return alert(`codes is not an array`);
                }

                options.running = true;
                
                let el = null;
                let payload = {};
                let response = null;

                for(let i = 0, len = codes.length; i < len; i += 1) {
                    const code = codes[i];
                    // Get the DOM Element
                    el = document.getElementById(`code-${code}`);

                    // NaN - Not a Number, make sure the confirmation code is a number, not asdf (already trimmed and parsed)
                    if (isNaN(codes[i])) {
                        alert(`ERROR: Confirmation Code: "${code}"" does not appear to be Numeric\n\nConfirmation Codes have to be numeric.\n\nSkippng this Confirmation Number`);
                        if (el){ 
                            el.parentElement.classList.add('regenwarn');
                            el.innerHTML = `Skipped, "${code}" is not a Number`;
                        }
                        continue;
                    }

                    // Payload Object to send via Axios
                    payload = {
                        _token: '{{ csrf_token() }}',
                        format: 'text',
                        code: code
                    };

                    try {
                        console.log(`Sending Confirmation Code ${code} for regeneration`);

                        // The Await here is used so each contract being regenerated is handled sequentially and does not overload the server
                        response = await window.axios.post('/support/contract_test', payload);

                        msg = (!response || !response.data || response.data.length == 0) ? 'No Data Returned' : response.data.trim();

                        // CSS class defined below, adds a green border
                        el.parentElement.classList.add('regencomplete');
                        // CSS class defined below, makes the component a smaller size with Scroll Bars and is resizable if needed
                        el.classList.add('regenresult');
                        el.innerHTML = `<pre>${msg}</pre>`;
                    }
                    catch(e) {
                        const data = response && response.data ? response.data : null;
                        console.error(`Error in Axios Call on Code: ${codes[i]}: ${e.message}`, data);

                        if(el) { 
                            // CSS class defined below, adds a red border
                            el.parentElement.classList.add('regenerror');
                            el.innerHTML = 'Error: ' + e.message; 
                        }
                    }
                }

                options.running = false;
            };

            const checkRunning = (e) => {
                if (options.running) {
                    e.preventDefault();
                    e.stopPropagation();
                    alert("Please wait for current tasks to complete, or reload page.\n\nWARNING: Reloading page will cancel unprocessed Confirmation Numbers.");
                    return false;
                }
            };

            document.getElementById('runbtnmulti').addEventListener('click', (e) => checkRunning(e));

            codes = {!! json_encode($code) !!};

            const options = { running : false };

            // This is an Async function so it WAITS for each contract to be regenerated before continuing to the next contract number
            // Since we do not expect any values to be returned, we do not need to call await on this function although it is an async function
            regenerateContract(codes);
        </script>
    @endif
@endsection
<style scoped>
    .regenresult {
        height: 150px;
        overflow-y: auto;
        resize: vertical;
    }
    .regencomplete {
        border: solid darkgreen 1px !important;
    }
    .regenwarn {
        border: solid yellow 1px !important;
    }
    .regenerror {
        border: solid red 1px !important;
    }
</style>