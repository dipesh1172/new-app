@extends('layouts.app')

@isset($title)
    @section('title')
    {{ $title }}
    @endsection
@endisset

@section('content')
<div id="{{$componentName}}">
            <{{ $componentName }}
            @if(isset($parameters) && count($parameters) > 0)
                @foreach($parameters as $name => $value)
                    :{{$name}}="{{ $value }}"
                @endforeach
            @endif
             />
</div>
@endsection

@section('scripts')
    @if(isset($extraScripts))
        @foreach($extraScripts as $script)
            <script src="{{ $script }}"></script>
        @endforeach
    @endif
@endsection
