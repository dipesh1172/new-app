@extends('layouts.app')

@section('title')
{{ $title }}
@endsection

@section('content')
    <div id="generic-report">
        <generic-report
            title="{{$title}}"
            report-url="{{$url}}"
            main-url="{{$mainUrl}}"
            :search-parameter="{{ json_encode(request('search'))}}"
            :start-date-parameter="{{ json_encode(isset($startDate) ? $startDate : request('startDate')) }} || undefined"
            :end-date-parameter="{{ json_encode(isset($endDate) ? $endDate : request('endDate')) }} || undefined"
            :column-parameter="{{ json_encode(request('column'))}}"
            :direction-parameter="{{ json_encode(request('direction'))}}"
            :page-parameter="{{ json_encode(request('page'))}}"
            :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
            :flash-message="{{ json_encode(session('flash_message')) }}"
            :table-has-actions="{{ isset($hasActions) && $hasActions ? 'true' : 'false' }}"
            :show-export-button="{{ isset($showExportButton) && $showExportButton ? 'true' : 'false' }}"
            :language-parameter="{{ json_encode(request('language')) }}"
            :commodity-parameter="{{ json_encode(request('commodity')) }}"
            :vendor-parameter="{{ json_encode(request('vendor')) }}"
            @if(!empty($hiddenColumns))
            :hidden-columns="{{ json_encode($hiddenColumns) }}"
            @endif
            @if(!empty($searchOptions))
            :search-options="{{ json_encode($searchOptions) }}"
            @endif
            @if(!empty($viewLink))
                view-link="{{ $viewLink }}"
            @endif
        />
    </div>
@endsection

@section('vuescripts')
    <script type="text/javascript">
        window.baseContent = {
            languages: {!! isset($languages) ? $languages : '[]' !!},
            commodities: {!! isset($commodities) ? $commodities : '[]' !!},
            vendors: {!! isset($vendors) ? $vendors : '[]' !!},
        };
    </script>
@endsection
