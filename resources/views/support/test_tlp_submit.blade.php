@extends('layouts.app')

@section('title')
Support: Clear System Cache
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Support</li>
        <li class="breadcrumb-item active">Test TLP Tablet Submission</li>
    </ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Test TLP Tablet Submission
                </div>
                <div class="card-body text-center">
                    @if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif
                    <form method="POST" action="{{config('app.urls.clients')}}/api/tlp/submit">
                       <input type="hidden" name="RefererUri" value="http://qasyncsperian.ontlp.com">
                       <input type="hidden" name="UserName" value="dxc_tlp">
                       <input type="hidden" name="Password" value="how it feels to chew 5 gum">
                       <input type="hidden" name="Method" value="SubmitTabletLead">
                       <div class="row">
                       <div class="col-md-4">
                       <label>Number of Products</label>
                        <select class="form-control" id="pcount">
                            @foreach([1,2,3,4] as $cnt)
                                <option value="{{$cnt}}" @if ($pcount == $cnt)
                                    selected
                                @endif>{{$cnt}}</option>
                            @endforeach
                        </select>
                       </div>
                       <div class="col-md-8">
                       <label>Client</label>
                       <select class="form-control" name="Client">
                        <option value="Sperian">Tomorrow Energy (Sperian)</option>
                       </select>
                       </div>
                       
                       </div>
                       <hr>
                       <input type="hidden" name="EnrollmentId" value="">
                       <div class="row">
                       <div class="col-md-4">
                       <label>Vendor Id</label>
                       <input type="text" class="form-control" name="VendorId" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Agent Id</label>
                       <input type="text" class="form-control" name="AgentId" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Channel</label>
                       <select class="form-control" name="ChannelType">
                       <option value="RDTD">Door To Door</option>
                       </select>
                       </div>
                       </div>
                       <input type="hidden" name="CreatedOn" value="2020-03-01T18:06:25.157">
                       <input type="hidden" name="DateOfSale" value="2020-03-01T18:06:25.857">
                       <input type="hidden" name="DeviceId" value="focusdummy">
                       <hr>
                       <div class="row">
                       <div class="col-md-6">
                       <label>Callback Number</label>
                       <input type="text" class="form-control" name="CallBackNumber" value="">
                       </div>
                       <div class="col-md-6">
                       <label>Sale ID</label>
                       <input type="text" name="SaleId" class="form-control" value="">
                       </div>
                       </div>

                       <input type="hidden" name="CallbackNumberMetadata[Status]" value="403">
                       <input type="hidden" name="CallbackNumberMetadata[CarrierName]" value="">
                       <input type="hidden" name="CallbackNumberMetadata[CarrierType]" value="">
                       <input type="hidden" name="CallbackNumberMetadata[CarrierIdName]" value="">
                       <input type="hidden" name="CallbackNumberMetadata[CarrierIdType]" value="">
                       <input type="hidden" name="BtnNumberMetadata" value="">
                       <input type="hidden" name="CallBackExtension" value="">
                       <input type="hidden" name="AccountNumber" value="">
                       <input type="hidden" name="ApprovedZip" value="false">
                       
                       <input type="hidden" name="RecordLocator" value="">

                        <hr>
                        <div class="d-none row">
                            <div class="col-md-6">
                                <label>Business Name</label>
                                <input type="text" class="form-control" name="Account[BusinessName]" value="">
                            </div>
                       </div>
                       <div class="row">
                        <div class="col-md-4">
                        <label>First Name</label>
                        <input type="text" class="form-control" name="Account[FirstName]" value="">
                        </div>
                       <div class="col-md-4">
                       <label>M Name</label>
                       <input type="text" class="form-control" name="Account[MiddleName]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Last Name</label>
                       <input type="text" class="form-control" name="Account[LastName]" value="">
                       </div>
                       </div>
                       
                       <input type="hidden" name="Account[Suffix]" value="">
                       <input type="hidden" name="Account[Email]" value="">
                       <input type="hidden" name="Account[Title]" value="">
                       <input type="hidden" name="Account[AuthorizingFirstName]" value="">
                       <input type="hidden" name="Account[AuthorizingLastName]" value="">
                       
                        <hr>
                        <strong>Billing Address</strong>
                       <input type="hidden" name="Billing[Attention]" value="">
                       <div class="row">
                       <div class="col-md-8">
                       <label>Address Line 1</label>
                       <input type="text" class="form-control" name="Billing[Address1]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Line 2</label>
                       <input type="text" class="form-control" name="Billing[Address2]" value="">
                       </div>
                       </div>
                       <div class="row">
                       <div class="col-md-4">
                       <label>City</label>
                       <input type="text" class="form-control" name="Billing[City]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>State (Abbreviation)</label>
                       <input type="text" class="form-control" name="Billing[State]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Zip</label>
                       <input type="text" class="form-control" name="Billing[Zip]" value="">
                       </div>
                       </div>
                       <input type="hidden" name="Billing[Zip4]" value="">
                       <div class="row">
                       <div class="col-md-8">
                       <label>Billing Phone</label>
                       <input type="text" class="form-control" name="Billing[Phone]" value="">
                       </div>
                       
                       <input type="hidden" name="Billing[PhoneExtension]" value="">
                       <div class="col-md-4">
                       <label>Bill Language</label>
                       <select class="form-control" name="Billing[Spanish]">
                       <option value="false">English</option>
                       <option value="true">Spanish</option>
                       </select>
                       </div>
                       </div>
                       @for($i = 0; $i < $pcount; $i += 1)
                       <hr>
                        <strong>Product {{$i + 1}}</strong>
                        <div class="row">
                        <div class="col-md-4">
                        <label>Program Code</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][ProductId]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Utility Label</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][Utility]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Fuel Type</label>
                       <select class="form-control" name="Meters[{{$i}}][FuelType]">
                       <option value="Electric">Electric</option>
                       <option value="Natural Gas">Natural Gas</option>
                       </select>
                       </div>
                       </div>
                       <input type="hidden" name="Meters[{{$i}}][Rate]" value="">
                       <input type="hidden" name="Meters[{{$i}}][RateUom]" value="¢/kWh">
                       <input type="hidden" name="Meters[{{$i}}][CancellationFee]" value="">
                       <div class="row">
                       <div class="col-md-8">
                       <label>Account Number</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][AccountNumber]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Account Number Label</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][AccountNumberLabel]" value="">
                       </div>
                       </div>
                       <input type="hidden" name="Meters[{{$i}}][NameKey]" value="">
                       <input type="hidden" name="Meters[{{$i}}][RateClass]" value="">
                       <input type="hidden" name="Meters[{{$i}}][BAccountNumber]" value="">
                       <input type="hidden" name="Meters[{{$i}}][Attention]" value="">
                       <div class="row">
                       <div class="col-md-8">
                       <label>Service Address Line 1</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][Address1]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Line 2</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][Address2]" value="">
                       </div>
                       </div>
                       <div class="row">
                       <div class="col-md-4">
                       <label>City</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][City]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>State (Abbreviation)</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][State]" value="">
                       </div>
                       <div class="col-md-4">
                       <label>Zip</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][Zip]" value="">
                       </div>
                       </div>
                       <input type="hidden" name="Meters[{{$i}}][Zip4]" value="">
                       <input type="hidden" name="Meters[{{$i}}][County]" value="">
                       <div class="row">
                       <div class="col-md-12">
                       <label>Phone number again</label>
                       <input type="text" class="form-control" name="Meters[{{$i}}][Phone]" value="">
                       </div>
                       </div>
                       <input type="hidden" name="Meters[{{$i}}][PhoneExtension]" value="">
                       <input type="hidden" name="Meters[{{$i}}][Contract]" value="">
                       <input type="hidden" name="Meters[{{$i}}][MeterNumber]" value="">
                       <input type="hidden" name="Meters[{{$i}}][Term]" value="">
                       <input type="hidden" name="Meters[{{$i}}][ServiceReferenceNumber]" value="">
                       <input type="hidden" name="Meters[{{$i}}][ProductType]" value="">
                       <input type="hidden" name="Meters[{{$i}}][Segment]" value="">
                       <input type="hidden" name="Meters[{{$i}}][StartDate]" value="">
                       <input type="hidden" name="Meters[{{$i}}][AggregateUsage]" value="">
                       <input type="hidden" name="Meters[{{$i}}][UtilityName]" value="">
                       <input type="hidden" name="Meters[{{$i}}][UtilityAlias]" value="">
                       @endfor

                       <input type="hidden" name="ITUSCOAccepted" value="">
                       <input type="hidden" name="QueueOutboundCall" value="false">
                       <input type="hidden" name="TransactionId" value="">
                       <input type="hidden" name="Timestamp" value="">
                       <input type="hidden" name="Mode" value="online">
                       <input type="hidden" name="IsTest" value="false">
                       <input type="hidden" name="SendPostBack" value="false">

                        <hr>
                       <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
                    
                
        </div>
    </div>
@endsection

@section('scripts')
<script>
function uuidv4() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}
$(() => {
    $('input[name="EnrollmentId"]').val(uuidv4());
    $('input[name="TransactionId"]').val(uuidv4());
    $('input[name="Timestamp"]').val((new Date()).getTime());
    $('#pcount').on('change', () => {
        window.location.href = `/support/tlp_test?count=${$('#pcount').val()}`;
    });
});
</script>
@endsection
