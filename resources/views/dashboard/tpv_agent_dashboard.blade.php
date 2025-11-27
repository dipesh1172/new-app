@extends('layouts.app')

@section('title')
Sales Agents Dashboard
@endsection

@section('content')
    @breadcrumbs([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Dashboard', 'url' => '/sales_dashboard', 'active' => false],
        ['name' => 'Sales Agents Dashboard', 'url' => '/sales_dashboard/agents', 'active' => false],
        ['name' => 'TPV Agents Dashboard', 'url' => '/sales_dashboard/tpv_agents', 'active' => true],
    ])

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div id="tpv-agent-dashboard">
                <tpv-agent-dashboard
                    :start-date-parameter="{{ json_encode(request('startDate')) }} || undefined"
                    :end-date-parameter="{{ json_encode(request('endDate')) }} || undefined"
                    :column-parameter="{{ json_encode(request('column'))}}"
                    :direction-parameter="{{ json_encode(request('direction'))}}"
                    :brand-parameter="{{ json_encode(request('brand')) }}"
                    :channel-parameter="{{ json_encode(request('channel')) }} || undefined"
                    :market-parameter="{{ json_encode(request('market')) }} || undefined"
                    :brand-parameter="{{ json_encode(request('brand')) }}"
                    :language-parameter="{{ json_encode(request('language')) }}"
                    :commodity-parameter="{{ json_encode(request('commodity')) }}"
                    :state-parameter="{{ json_encode(request('state'))}}"
                />
            </div>
        </div>
    </div>
    <!-- /.conainer-fluid --> 
@endsection

@section('head')

@endsection

@section('scripts')

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
        brands: {!! $brands !!},
        languages: {!! $languages !!},
        commodities: {!! $commodities !!},
        states: {!! $states !!},
    };
</script>
@endsection
