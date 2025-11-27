@extends('layouts.app')

@section('title')
Support: Email Lookup Tool
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Support</li>
        <li class="breadcrumb-item active">Email Lookup Tool</li>
    </ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            @if(Session::has('flash_message'))
                <div class="alert alert-danger"><em> {!! session('flash_message') !!}</em></div>
            @endif
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Email Lookup Tool
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="text" class="form-control" id="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>&nbsp;</label><br>
                            <button type="button" class="btn btn-primary" id="lookup"><span class="fa fa-search"></span> Find</button>
                        </div>
                    </div>
                    <div class="row d-none" id="deliverable">
                        <div class="col-md-12">
                            <div class="alert alert-success">
                                The email address is deliverable
                            </div>
                        </div>
                    </div>
                    <div class="row d-none" id="undeliverable">
                        <div class="col-md-12">
                            <div class="alert alert-warning">
                                The email address is undeliverable
                                <br>
                                <button type="button" class="btn btn-danger" id="resetBtn">Reset</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
window.email_id = null;
<script>
(() => {
    const emailField = document.getElementById('email');
    const findBtn = document.getElementById('lookup');
    const deliverableMsg = document.getElementById('deliverable');
    const undeliverableMsg = document.getElementById('undeliverable');
    const resetBtn = document.getElementById('resetBtn');

    findBtn.addEventListener('click', () => {
        const email = emailField.value;
        axios.post('/support/email', {
            _token: window.csrf_token,
            email
        }).then((res) => {
            console.log(res.data);
            if(res.data.error) {
                alert('Email not located in system or an error occured');
                return;
            }
            if(res.data.deliverable) {
                deliverableMsg.classList.remove('d-none');
                undeliverableMsg.classList.add('d-none');
                window.email_id = null;
            } else {
                window.email_id = res.data.id;
                undeliverableMsg.classList.remove('d-none');
                deliverableMsg.classList.add('d-none');
            }
        }).catch((e) => {
            console.log(e);
            alert('Error while looking up email');
        });
    });

    resetBtn.addEventListener('click', () => {
        const email = emailField.value;
        axios.post('/support/email-reset', {
            _token: window.csrf_token,
            email
        }).then((res) => {
            if(res.data.error) {
                alert('Unable to reset email to deliverable, message: '+res.data.message);
            } else {
                alert('Email reset to deliverable');
                window.location.reload();
            }
        }).catch((e) => {
            alert('Error while resetting email');
            console.log(e);
        });
    });
})();
</script>
@endsection
