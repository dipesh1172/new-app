@extends('layouts.app')

@section('title')
Report: Pending Replaced
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Reports</li>
        <li class="breadcrumb-item active">Pending Replaced</li>
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
                            <i class="fa fa-th-large"></i> Pending Sales Replaced
                        </div>
                        <div class="card-body">
                            <table class="table table-hover">
                                @foreach ($data as $key => $value)
                                    <tr>
                                        <td>
                                            <strong>{{ $key }}</strong><br /><br />

                                            <table class="table table-hover table-striped table-bordered">
                                            <tr>
                                                <th>Date</th>
                                                <th>Confirmation</th>
                                                <th>Channel</th>
                                                <th>Result</th>
                                                <th>Reason</th>
                                                <th>Vendor</th>
                                                <th>Commodity</th>
                                                <th>Auth Name</th>
                                            </tr>
                                            @foreach ($value as $d)
                                            <tr>
                                                <td>{{ $d['interaction_created_at'] }}</td>
                                                <td>{{ $d['confirmation_code'] }}</td>
                                                <td>{{ $d['channel'] }}</td>
                                                <td>{{ $d['result'] }}</td>
                                                <td>{{ $d['disposition_reason'] }}</td>
                                                <td>{{ $d['vendor_name'] }}</td>
                                                <td>{{ $d['commodity'] }}</td>
                                                <td>{{ $d['auth_first_name'] }} {{ $d['auth_last_name'] }}</td>
                                            </tr>
                                            @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--/.col-->
@endsection

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<style>
    tr.row-brand, tr.row-channel {
        display: none;
    }
</style>
@endsection

@section('scripts')
<script src="http://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script>
    $(function() {
        $( ".datepicker" ).datepicker({
            changeMonth: true,
            changeYear: true
        });
    });
    $("[name='agent']").click(function() {
        if ($(this).hasClass('fa-plus-square')) {
            var staff_id = $(this).attr('id');
            $("[name='subrow']").hide();
            $("[name='agent']").removeClass('fa-minus-square').addClass('fa-plus-square');
            $("[name$='subrow'][id*='" + staff_id + "']").show();
            $(this).removeClass('fa-plus-square').addClass('fa-minus-square');
        } else {
            $("[name='subrow']").hide();
            $("[name='agent']").removeClass('fa-minus-square').addClass('fa-plus-square');
        }
    });
</script>
@endsection
