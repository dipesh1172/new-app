@extends('layouts.app')

@section('content')
<div id="kb-create">
	<kb-create />
</div>
@endsection
@section('scripts')
<script src="{{asset('plugins/tinymce/js/tinymce/tinymce.min.js')}}"></script>
<script src="{{asset('plugins/tinymce/js/tinymce/jquery.tinymce.min.js')}}"></script>
@endsection