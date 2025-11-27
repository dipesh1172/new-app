@extends('layouts.app')

@section('title')
Event {{ $event->event_id }}
@endsection

@section('content')
    <div id="events-show">
        <events-show
            :flash-message="{{ json_encode(session('flash_message')) }}"
            :flash-error-message="{{ json_encode(session('flash_message_error')) }}"
            :qa-review="{{ json_encode($fromQaReview) }}"
            :errors="{{ json_encode($errors->all()) }}"
            :event="{{ json_encode($event) }}"
            :tracking="{{ json_encode($tracking) }}"
            :dispositions="{{ json_encode($dispositions) }}"
            :call-review-types="{{ json_encode($call_review_types) }}"
            :role-id="{{ Auth::user()->role_id }}"
            :alerts="{{ json_encode($alerts) }}"
            review-interaction="{{ $focusOn }}"
        />
    </div>
@endsection

@section('head')
<style>
table.table.table-striped {
	margin-bottom: 50px;
}
</style>
@endsection

@section('vuescripts')
    <script type="text/javascript">
        window.MOTION_FILE_URL = "{{ config('services.motion.file_url') }}";
        window.MOTION_S3_BUCKET = "{{ config('services.motion.s3_bucket') }}";
        window.MOTION_SIGNED_URL = "{{ config('services.motion.signed_url') }}";
        window.AWS_CLOUDFRONT = "{{ config('services.aws.cloudfront.domain') }}";
        window.baseContent = {
            AWS_CLOUDFRONT: "{{ config('services.aws.cloudfront.domain') }}",
            states: {!! json_encode($states) !!},
            
        };
        @if($focusOn !== null)
            window.focusOnInteraction = "{{ $focusOn }}";
            @else
            window.focusOnInteraction = null;
            @endif
            @if($fromQaReview)
            window.fromQaReview = true;
            @else
            window.fromQaReview = false;
            @endif
    </script>
@endsection
