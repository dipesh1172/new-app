@extends('layouts.app')

@section('title')
Events
@endsection

@section('content')
    <div id="events-index">
        <events-index
            :search-parameter="{{ json_encode(request('search'))}}"
            :search-field-parameter="{{ json_encode(request('searchField'))}}"
            :start-date-parameter="{{ json_encode(request('startDate')) }}"
            :end-date-parameter="{{ json_encode(request('endDate')) }}"
            :channel-parameter="{{ json_encode(request('channel')) }}"
            :brand-parameter="{{ json_encode(request('brandId')) }}"
            :language-parameter="{{ json_encode(request('language')) }}"
            :vendor-parameter="{{ json_encode(request('vendor')) }}"
            :sale-type-parameter="{{ json_encode(request('saleType')) }}"
            :column-parameter="{{ json_encode(request('column'))}}"
            :direction-parameter="{{ json_encode(request('direction'))}}"
            :page-parameter="{{ json_encode(request('page'))}}"
            :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
            :flash-message="{{ json_encode(session('flash_message')) }}"
            :table-has-actions="{{ json_encode(in_array(Auth::user()->role_id, array(1, 2, 3))) }}"
            :show-export-button="{{ json_encode(in_array(Auth::user()->role_id, array(1, 2))) }}"
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
            languages: {!! $languages !!},
            commodities: {!! $commodities !!},
            vendors: {!! $vendors !!},
        };
    </script>
@endsection