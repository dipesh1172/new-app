@extends('layouts.app')

@section('title')
Add Utility
@endsection

@section('content')
<div id="create-brand-utility">
    <create-brand-utility 
        :all-utilities="{{ json_encode($all_utilities) }}"
        :brand="{{ json_encode($brand) }}"
        :errors="{{ json_encode($errors->all()) }} || undefined" 
    />
</div>
@endsection