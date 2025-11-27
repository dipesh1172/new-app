@extends('layouts.app')

@section('content')
<div id="edit-utility">
        <edit-utility 
            :errors="{{ json_encode($errors->all()) }} || undefined"
        />
</div>
@endsection
