@extends('layouts.app')

@section('content')
<div id="add-tpv-staff">
	<add-tpv-staff 
		:errors="{{ json_encode($errors->all()) }} || undefined"
		:old="{{ json_encode(is_array(old()) ? (object) old() : old()) }} || undefined"
	/>
</div>
@endsection