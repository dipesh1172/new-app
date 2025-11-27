@extends('layouts.app')

@section('title')
Live Minutes
@endsection

@section('content')
    @if (session('message'))
        <div class="alert alert-warning">
            {{ session('message') }}
        </div>
    @endif
    <div id="invoice-live-minutes-index">
      <invoice-live-minutes-index
        :items="{{ json_encode($data['data']) }}"
        :invoice="{{ json_encode($invoice) }}"
        :active-page="{{ json_encode($data['current_page']) }}"
        :number-pages="{{ json_encode($data['last_page']) }}"
        :total-records="{{ json_encode($data['total']) }}"
        :sort-by="{{ json_encode($sortBy) }}"
        :sort-dir="{{ json_encode($sortDir) }}"
      />
    </div>
@endsection