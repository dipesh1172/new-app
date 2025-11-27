@extends('layouts.app')

@section('title')
Enrollment Files
@endsection

@section('content')
<div id="enrollments">
        <enrollments
            :brand="{{ json_encode($brand) }}"
            :bef="{{ json_encode($bef) }}"
            :lefs="{{ json_encode($lefs) }}"
            :logs="{{ json_encode($logs) }}"
            :aws-cloud-front="{{ json_encode(config('services.aws.cloudfront.domain')) }}"
        />
</div>
@endsection

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<style>
    .center {
        text-align: center;
    }
</style>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script>
    $(function() {
        $( ".datepicker" ).datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'yy-mm-dd',
        });
    });
</script>
@endsection
