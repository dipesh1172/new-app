@extends('layouts.app')

@section('title')
Interactions
@endsection

@section('content')
    <div id="interactions-index">
        <interactions-index
            :search-parameter="{{ json_encode(request('search'))}}"
            :column-parameter="{{ json_encode(request('column'))}}"
            :direction-parameter="{{ json_encode(request('direction'))}}"
            :page-parameter="{{ json_encode(request('page'))}}"
            :has-flash-message="{{ json_encode(Session::has('flash_message'))}}"
            :flash-message="{{ json_encode(session('flash_message')) }}"
        />
    </div>

    <div class="modal fade" id="myModal" role="dialog">
        <div class="modal-dialog modal-lg">

            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('head')
<style>
.overflow-scroll {
    overflow: scroll;
}

.modal-lg {
    max-width: 80% !important;
}
</style>
@endsection

@section('scripts')
<script>
function openTranscript(id1, id2) {
    $('.modal-body').load(`/interactions/transcript/${id1}/${id2}`, () => {
        $('#myModal').modal({ show: true });
    });
}
</script>
@endsection