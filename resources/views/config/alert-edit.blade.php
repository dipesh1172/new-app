@extends('layouts.app')

@section('title')
{{ $alert == null ? 'Create' : 'Edit'}} Alert
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        
        @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="/config/site-alerts/store">
            {{ csrf_field() }}
            <input type="hidden" name="id" value="{{ $alert !== null ? $alert->id : '' }}">
            <div class="form-group">
                <label for="scope">Scope</label>
                <select class="form-control" id="scope" name="scope">
                    <option value="agents" {{ $alert !== null && $alert->scope == 'agents' || old('scope') == 'agents' ? 'selected' : ''}}>Agents</option>
                    <option value="clients" {{ $alert !== null && $alert->scope == 'clients' || old('scope') == 'clients' ? 'selected' : ''}}>Clients</option>
                    <option value="mgmt" {{ $alert !== null && $alert->scope == 'mgmt' || old('scope') == 'mgmt' ? 'selected' : ''}}>Managment</option>
                </select>
            </div>
            <div class="form-group">
                <label for="brand_id">Brand</label>
                <select class="form-control" id="brand_id" name="brand_id">
                    <option value="">None / Not Applicable</option>
                    @foreach($brands as $brand)
                        <option value="{{$brand->id}}" {{$alert !== null && $alert->brand_id == $brand->id || old('brand_id') == $brand->id ? 'selected' : ''}}>{{$brand->name}}</option>
                    @endforeach 
                </select>
            </div>
            <div class="form-group">
                <label for="title">Title</label>
                <input class="form-control" maxlength="100" type="text" name="title" id="title" value="{{$alert !== null ? $alert->title : old('title') }}">
            </div>
            <div class="form-group">
                <label for="alert">Message</label>
                <textarea class="form-control" name="alert" id="alert">{{ $alert !== null ? $alert->alert : old('alert') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-floppy-o" aria-hidden="true"></i> Save</button>
        </form>
    </div>
</div>
@endsection
