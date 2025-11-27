@extends('layouts.app')

@section('title')
Alerts
@endsection

@section('content')

@if(count($alerts) == 0) 
<div class="alert alert-info">
    There are currently no alerts configured.
    <a href="/config/site-alerts/new" class="btn btn-primary"><i class="fa fa-plus"></i> Add One Now</a>
</div>
@else
<div class="card">
    <div class="card-body">
        <a href="/config/site-alerts/new" class="btn btn-primary pull-right"><i class="fa fa-plus"></i> Add New Alert</a>
        @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors as $e)
                <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Scope</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alerts as $alert)
                    <tr>
                        <td>
                            {{$alert->scope}}
                            @if($alert->brand_id !== null)
                            <br>
                            <strong>Brand: </strong>{{ $brands->where('id', $alert->brand_id)->first()->name }}
                            @endif
                        </td>
                        <td>{{$alert->title}}</td>
                        <td>{!! $alert->alert !!}</td>
                        <td>
                            <div class="btn-group">
                                <a href="/config/site-alerts/{{$alert->id}}" class="btn btn-info"><i class="fa fa-edit"></i> Edit</a>
                                <a href="#" data-target="#deleteForm{{ $alert->id }}" data-url="/config/site-alerts/store" class="btn btn-delete btn-danger"><i class="fa fa-trash"></i> Delete</a>
                            </div>
                            
                            <form id="deleteForm{{ $alert->id }}" class="d-none" method="post" action="/config/site-alerts/store">
                                {{ csrf_field() }}
                                <input type="hidden" name="id" value="{{$alert->id}}">
                                <input type="hidden" name="command" value="delete">
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@section('scripts')

<script>
    $(function () {

        $(document)

            .on('click', '.btn-delete', function (e) {

                var formId = $(this).data('target');
                var form = $(formId);

                if (confirm('Are you sure you want to remove this alert?')) {
                    form.submit();
                }

            })

    });
</script>

@endsection
