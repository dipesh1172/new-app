@extends('layouts.app')

@section('title')
QA - Call Followups
@endsection

@section('content')
	@breadcrumbs([
		['name' => 'Home', 'url' => '/'],
		['name' => 'QA - Call Followups', 'url' => URL::route('qa_review.index')],
		['name' => 'QA - Call Followups', 'url' => '#']
    ])
    <div id="qa-review-show">
        <qa-review-show
            :flash-message="{{ json_encode(session('flash_message')) }}"
            :flash-error-message="{{ json_encode(session('flash_message_error')) }}"
            :errors="{{ json_encode($errors->all()) }}"
            :flag="{{ json_encode($flag) }}"
            :dispositions="{{ json_encode($dispositions) }}"
            :call-review-types="{{ json_encode($call_reviews) }}"
            :event-products="{{ json_encode($event_products) }}"
            :qa-review="{{ json_encode(request('qa_review') == 'true') }}"
            :role-id="{{ Auth::user()->role_id }}"
    />
    </div>
@endsection

@section('vuescripts')
    <script type="text/javascript">
        window.baseContent = {
            AWS_CLOUDFRONT: "{{ config('services.aws.cloudfront.domain') }}"
        }
    </script>
@endsection
