@extends('layouts.app')

@section('title')
Disposition Shortcuts
@endsection

@section('content')
<div id="dispo-shortcuts">
	<dispo-shortcuts 
		:brand="{{ json_encode($brand) }}"
		:shortcuts="{{ json_encode($shortcuts) }}"
		:dispositions="{{ json_encode($dispositions) }}"
	/>
</div>
@endsection