@extends('layouts.app')

@section('title')
Dashboard
@endsection

@section('content')
    @breadcrumbs([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Dashboard', 'url' => '/dashboard', 'active' => true],
    ])

    <div class="container-fluid">
        <div class="animated fadeIn">
            @if (@$nodata)
                <div class="alert alert-info">There is currently no call data.</div>

                @if (@$nousers)
                    <div class="alert alert-warning">No users exist for this brand.  Click <a href="/users/create">here</a> to add one.</div>
                @endif

                @if (@$noproducts)
                    <div class="alert alert-warning">No products exist for this brand.  Click <a href="/products/create">here</a> to add one.</div>
                @endif
            @else
                <div class="row">
                    <div class="col-md-5">
                        <h2>Sales Dashboard</h2>
                    </div>
                    <div class="col-md-7">
                        <div id="dashboard-index">
                            <dashboard-index
                                :start-date-parameter="{{ json_encode(request('startDate')) }} || undefined"
                                :end-date-parameter="{{ json_encode(request('endDate')) }} || undefined"
                                :channel-parameter="{{ json_encode(request('channel')) }} || undefined"
                                :market-parameter="{{ json_encode(request('market')) }} || undefined"
                            />
                        </div>
                    </div>
                </div>

                <br class="clearfix" />

                <div class="card">
                    <div class="card-body">
                        <br />

                        <div class="row">
                            <div class="col-md-4">
                                @if (!empty($good_sale_no_sale_chart))
                                    <h3 class="text-center">Good Sale vs No Sale</h3>
                                    {!! $good_sale_no_sale_chart->render() !!}
                                @endif

                                <br /><hr /><br />

                                <div class="row">
                                    <div class="col-md-6 text-center">
                                        <div class="card p-3">
                                            <strong>SALE</strong>
                                            {{ $sales_no_sales['sales'] }} ({{ $sales_no_sales['sale_percentage'] }}%)
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-center">
                                        <div class="card p-3">
                                            <strong>NO SALE</strong>
                                            {{ $sales_no_sales['nosales'] }} ({{ $sales_no_sales['no_sale_percentage'] }}%)
                                        </div>
                                    </div>
                                </div> 

                                {{--
                                <div class="row">
                                    <div class="col-md-6 text-center">
                                        <div class="card p-3">
                                            <strong>EZTPV</strong>
                                            {{ $eztpvs['eztpv'] }} ({{ $eztpvs['eztpv_percentage'] }}%)
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-center">
                                        <div class="card p-3">
                                            <strong>WITHOUT EZTPV</strong>
                                            {{ $eztpvs['noeztpv'] }} ({{ $eztpvs['no_eztpv_percentage'] }}%)
                                        </div>
                                    </div>
                                </div>
                                --}}
                            </div>
                            <div class="col-md-8">
                                @if (!empty($sales_no_sales_chart))
                                    <h3 class="text-center">Sales/No Sales</h3>
                                    {!! $sales_no_sales_chart->render() !!}
                                @endif
                            </div>
                        </div>

                        <br /><hr /><br />

                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Sales by Vendor</strong>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-striped">
                                            <thead>
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Vendor</th>
                                                <th scope="col">Sales</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($sales_by_vendor as $k => $vendor)
                                                    <tr>
                                                        <th scope="row">{{ $k + 1 }}</th>
                                                        <td>{{ $vendor->name }}</td>
                                                        <td>{{ $vendor->sales_num }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Top Sales Agents</strong>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th scope="col">#</th>
                                                    <th scope="col">Agent</th>
                                                    <th scope="col">Vendor</th>
                                                    <th scope="col">Sales</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($top_sale_agents as $k => $sa)
                                                    <tr>
                                                        <th scope="row">{{ $k + 1 }}</th>
                                                        <td>{{ $sa->sales_agent }}</td>
                                                        <td>{{ $sa->vendor }}</td>
                                                        <td>{{ $sa->sales_num }}</td>
                                                    </tr>
                                                @endforeach

                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Top Sold Products</strong>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-striped">
                                            <thead>
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Product</th>
                                                <th scope="col">Sales</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($top_sold_products as $k => $product)
                                                    <tr>
                                                        <th scope="row">{{ $k + 1 }}</th>
                                                        <td>{{ $product->name }}</td>
                                                        <td>{{ $product->sales_num }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <br /><hr /><br />

                        <div class="row">
                            <div class="col-md-7">
                                @if (!empty($sales_per_hour_chart))
                                    <h3 class="text-center">Calls vs. Sales (per hour)</h3>
                                    {!! $sales_per_hour_chart->render() !!}
                                @endif
                            </div>                        
                            <div class="col-md-5">
                                @if (!empty($no_sale_dispositions_chart))
                                    <h3 class="text-center">No Sales Dispositions</h3>
                                    {!! $no_sale_dispositions_chart->render() !!}
                                @endif
                            </div>
                        </div>

                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- /.conainer-fluid --> 
@endsection

@section('head')

@endsection

@section('scripts')
<script src="//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.min.js"></script>
@endsection

@section('vuescripts')
    <script type="text/javascript">
        window.baseContent = {
            channels: [
                {
                    id: 1,
                    name: 'DTD',
                },
                {
                    id: 2,
                    name: 'TM',
                },
                {
                    id: 3,
                    name: 'Retail',
                },
            ],
            markets: [
                {
                    id: 1,
                    name: 'Residential',
                },
                {
                    id: 2,
                    name: 'Commercial',
                },
            ],
            sales: [
                {
                    id: 1,
                    name: 'Good Sale',
                },
                {
                    id: 2,
                    name: 'No Sale',
                },
            ],
        };
    </script>
@endsection