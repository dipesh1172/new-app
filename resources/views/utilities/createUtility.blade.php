@extends('layouts.app')

@section('content')
<div id="create-utility-index">
    <create-utility-index 
        :errors="{{ json_encode($errors->all()) }} || undefined" 
        :initial-values="{{ json_encode(is_array(old()) ? (object) old() : old() ) }} || undefined" 
        :countries="{{ json_encode($countries) }} || undefined" 
        :states="{{ json_encode($states) }} || undefined" 
        :utility-types="{{ json_encode($utility_types) }} || undefined" 
    />
</div>
@endsection
