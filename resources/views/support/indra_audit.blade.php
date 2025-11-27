@extends('layouts.app')

@section('title')
Indra Audit
@endsection

@section('content')
    <div id="indra-audit">
        <indra-audit
            :search-parameter="{{ json_encode(request('search'))}}"
            :search-field-parameter="{{ json_encode(request('searchField'))}}"
            <?php if(!empty(request('startDate')) && !empty(request('endDate'))) { ?>
                :start-date-parameter="{{ json_encode(request('startDate') ?? null) }}"
                :end-date-parameter="{{ json_encode(request('endDate') ?? null) }}"
            <?php } ?>
            :brand-parameter="{{ json_encode(request('brandId')) }}"
            :column-parameter="{{ json_encode(request('column'))}}"
            :direction-parameter="{{ json_encode(request('direction'))}}"
            :page-parameter="{{ json_encode(request('page'))}}"
            :has-error-message="{{ json_encode(Session::has('error_message'))}}"
            :error-message="{{ json_encode(session('error_message')) }}"
            :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
            :flash-message="{{ json_encode(session('flash_message')) }}"
            :table-has-actions="{{ json_encode(in_array(Auth::user()->role_id, array(1, 2, 3))) }}"
            :show-export-button="false"
        />
    </div>
@endsection

@section('head')
<style>
.reviewed {
    width: 100%;
}
</style>
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
            saleTypes: [
                {
                    id: 'Sale',
                    name: 'Sale',
                    type: 'saleType',
                },
                {
                    id: 'No Sale',
                    name: 'No Sale',
                    type: 'saleType',
                },
                {
                    id: 'Closed',
                    name: 'Closed',
                    type: 'saleType'
                }
            ],
            brands: {!! $brands !!},
            languages: [],
            commodities: [],
            vendors: [],
        };
    </script>
@endsection
