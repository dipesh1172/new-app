@extends('layouts.app')

@section('content')
<div id="calls-by-channel">
    <calls-by-channel/>
</div>
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
                {
                    id: 4,
                    name: 'Care',
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