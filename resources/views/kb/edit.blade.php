@extends('layouts.app')

@section('content')
<div id="kb-edit">
	<kb-edit />
</div>
@endsection

@section('scripts')
<script src="{{asset('plugins/tinymce/js/tinymce/tinymce.min.js')}}"></script>
<script src="{{asset('plugins/tinymce/js/tinymce/jquery.tinymce.min.js')}}"></script>
@endsection