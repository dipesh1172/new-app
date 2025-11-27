@extends('layouts.app')

@section('content')
<div id="video-index">
	<video-index 
		:create-permit="{{ json_encode(has_perm('kb.create')) }}"
		:modify-permit="{{ json_encode(has_perm('kb.modify-all')) }}"
		:logged-user-id="{{ json_encode(Auth::user()->id) }}"
	/>
</div>
@endsection
