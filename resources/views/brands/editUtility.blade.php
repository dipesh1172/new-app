@extends('layouts.app')

@section('title')
Edit Utility
@endsection

@section('content')
<div id="edit-brand-utility">
    {{-- {{dd($brand)}} --}}
    <edit-brand-utility 
        :brand="{{ json_encode($brand) }}"
        :utility="{{ json_encode($utility) }}"
        :errors="{{ json_encode($errors->all()) }}"
    />
</div>
@endsection