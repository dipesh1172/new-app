<!doctype html>
<html lang="en">
<head>
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <meta charset="utf-8">

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

    <style>
        .invoice-title h2, .invoice-title h3 {
            display: inline-block;
        }

        .table > tbody > tr > .no-line {
            border-top: none;
        }

        .table > thead > tr > .no-line {
            border-bottom: none;
        }

        .table > tbody > tr > .thick-line {
            border-top: 2px solid;
        }
    </style>

    @if ($view === 'pdf')
        <style>
            body {
                font-size: 16px;
            }

            h3 {
                font-size: 30px;
            }

            h5 {
                font-size: 25px;
            }
        </style>
    @endif
</head>
<body>
    <div>
        <div class="container-fluid mt-2">
            <div class="animated fadeIn">
                <div class="card-body">
                    <div class="row p-2">
                        <div
                            class="col-md-12"
                        >
                            <div class="invoice-title">
                                <h2>
                                    <img
                                        crossorigin="anonymous"
                                        src="https://tpv-assets.s3.amazonaws.com/tpv-new-220x120.png"
                                    >
                                </h2>

                                <h3 class="pull-right text-right">
                                    <strong>Invoice for TPV Services</strong> <br>
                                    <small>
                                        Period: {{ date('m/d/Y', strtotime($invoice->invoice_start_date)) }} - {{ date('m/d/Y', strtotime($invoice->invoice_end_date)) }}
                                    </small>
                                </h3>
                            </div>

                            <br>

                            <div class="row">
                                <table style="width:100%;">
                                    <tr>
                                        <td style="width:70%;">
                                            <h5>
                                                <strong>Invoice Number: {{ $invoice->invoice_number }}</strong>
                                                @if($invoice->purchase_order_no !== null)
                                                    <br>
                                                    PO # {{ $invoice->purchase_order_no}}
                                                @endif
                                            </h5>
                                        </td>
                                        <td style="width:30%; text-align:right;">
                                            <h5>
                                                <strong>
                                                    Due Date: {{ date('M d, Y', strtotime($invoice->invoice_due_date)) }}
                                                </strong>
                                            </h5>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="row">
                                <div class="offset-md-1 col-md-6">
                                    <h5>
                                        <address>
                                            {{ $invoice->legal_name }} <br>
                                            {{ $invoice->address }} <br>
                                            {{ $invoice->city }}, {{ $invoice->state }} {{ $invoice->zip }}
                                        </address>
                                    </h5>
                                </div>
                            </div>

                            <br>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="panel panel-default">
                                        <div class="panel-body">
                                            <div class="table-responsive">
                                                <table class="table table-condensed table-bordered table-striped">
                                                    @foreach ($result as $commodity => $data)
                                                        {{-- <thead> --}}
                                                            @if ($commodity === 'electric' || $commodity === 'gas' || $commodity === 'total')
                                                                <tr>
                                                                    <td style="width:40%">
                                                                        <strong>{{ ucwords($commodity) }}</strong>
                                                                    </td>
                                                                    <td
                                                                        style="width:15%"
                                                                        class="text-center"
                                                                    >
                                                                        <strong>D2D</strong>
                                                                    </td>
                                                                    <td
                                                                        style="width:15%"
                                                                        class="text-center"
                                                                    >
                                                                        <strong>Tele-Sales</strong>
                                                                    </td>
                                                                    <td
                                                                        style="width:15%"
                                                                        class="text-center"
                                                                    >
                                                                        <strong>Retail</strong>
                                                                    </td>
                                                                    <td
                                                                        style="width:15%"
                                                                        class="text-center"
                                                                    >
                                                                        <strong>Customer Care</strong>
                                                                    </td>
                                                                </tr>
                                                            @endif

                                                            @if ($commodity === 'Live Minutes (Call Abandoned)')
                                                                <tr>
                                                                    <td style="width:40%">
                                                                        <strong>{{ $commodity }}</strong>
                                                                    </td>
                                                                    <td
                                                                        class="text-right"
                                                                    >
                                                                        <strong>
                                                                        ${{ $result['Live Minutes (Call Abandoned)'][1] }}
                                                                        </strong>
                                                                    </td>
                                                                </tr>
                                                            @endif

                                                            @if ($commodity === 'Total Live Minute Invoice Charges')
                                                                <tr>
                                                                    <td style="width:40%">
                                                                        <strong>{{ $commodity }}</strong>
                                                                    </td>
                                                                    <td
                                                                        class="text-right"
                                                                    >
                                                                        <strong>
                                                                        ${{ $result['Total Live Minute Invoice Charges'][1] }}
                                                                        </strong>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                        {{-- </thead>
                                                        <tbody> --}}
                                                            @if ($commodity === 'electric' || $commodity === 'gas' || $commodity === 'total')
                                                                <tr>
                                                                    <td>
                                                                        Total Good Sales
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[1]['total_good_sales'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[2]['total_good_sales'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[3]['total_good_sales'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[4]['total_good_sales'] }}
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        Total No Sales
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[1]['total_no_sales'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[2]['total_no_sales'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[3]['total_no_sales'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[4]['total_no_sales'] }}
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        TPV Good Sales Transaction Time
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[1]['tpv_good_sales_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[2]['tpv_good_sales_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[3]['tpv_good_sales_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[4]['tpv_good_sales_transaction_time'] }}
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        TPV No Sales Transaction Time
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[1]['tpv_no_sales_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[2]['tpv_no_sales_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[3]['tpv_no_sales_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[4]['tpv_no_sales_transaction_time'] }}
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        Total Transaction Time
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[1]['total_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[2]['total_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[3]['total_transaction_time'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        {{ $data[4]['total_transaction_time'] }}
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        Cost per Minute
                                                                    </td>
                                                                    <td class="text-right" >
                                                                        ${{ $data[1]['cost_per_minute'] }}
                                                                    </td>
                                                                    <td class="text-right" >
                                                                        ${{ $data[2]['cost_per_minute'] }}
                                                                    </td>
                                                                    <td class="text-right" >
                                                                        ${{ $data[3]['cost_per_minute'] }}
                                                                    </td>
                                                                    <td class="text-right" >
                                                                        ${{ $data[4]['cost_per_minute'] }}
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="width:40%">
                                                                        Total Costs
                                                                    </td>
                                                                    <td class="text-right">
                                                                        ${{ $data[1]['total_costs'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        ${{ $data[2]['total_costs'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        ${{ $data[3]['total_costs'] }}
                                                                    </td>
                                                                    <td class="text-right">
                                                                        ${{ $data[4]['total_costs'] }}
                                                                    </td>
                                                                </tr>
                                                            @endif

                                                        {{-- </tbody> --}}
                                                    @endforeach

                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <br>

                    <table>
                        <tr>
                            <td style="width:50%; border-right: 2px solid #DDDDDD;">
                                <center>
                                    <strong>
                                        Remittance for Electronic Payments<br />
                                        Financial Institution: Firstrust Bank<br />
                                        15 E. Ridge Pike; Conshohocken, PA 19248<br />
                                        Account name: AnswerNet TPV<br />
                                        ABA number: 236073801<br />
                                        Account number: 8000335722<br />
                                    </strong>
                                </center>
                            </td>
                            <td style="width:50%;">
                                <center>
                                    <h4>TPV.com</h4><br>
                                    Our address:<br>
                                    3930 Commerce Avenue<br>
                                    Willow Grove, PA 19090
                                    <br><br><br>
                                    For questions on your invoice, please contact TPV.com Client Services at <a href="mailto:accountmanagers@answernet.com">accountmanagers@answernet.com</a>.
                                </center>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
