@extends('layouts.app')

@section('title')
Brand Service Types
@endsection

@section('content')
<div class="container">
<div class="row">
<div class="col-12">
<div class="card mt-4">
    <div class="card-header d-md-flex justify-content-between">
        <span><i class="fa fa-th-large"></i> Brand Service Types</span>
        <button type="button" id="add_btn" class="btn btn-primary btn-sm mb-0"><i class="fa fa-plus"></i> Add Brand Service Type</button>
    </div>
    <div class="card-body table-responsive p-0">
    @if(session('message'))
        <div class="alert alert-info">
        {{ session('message') }}
        </div>
    @endif
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Updated At</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Pricing Type</th>
                    <th>Tools</th>
                </tr>
            </thead>
            <tbody id="table_body">
                <tr><td colspan="5" class="text-center"><i class="fa fa-spinner fa-spin"></i></td></tr>
            </tbody>
        </table>
    </div>
</div>
</div>
</div>
</div>
<form action="/config/brand_service_type" method="POST">
@csrf
<input type="hidden" name="id" id="idInput">
<div id="bstEditor" class="modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">BST Editor</h5>
      </div>
      <div class="modal-body">
        
  <div class="form-group">
    <label for="nameInput">Name</label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" name="name" id="nameInput">
    @error('name')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
  </div>
  <div class="form-group">
    <label for="descInput">Description</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="descInput" name="description" rows="5">{{ old('description') }}</textarea>
    @error('description')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
  </div>
  <div class="form-group">
    <label for="ptypeInput">Pricing Type</label>
    <select class="form-control @error('pricing_type') is-invalid @enderror" name="pricing_type" id="ptypeInput">
      <option {{old('pricing_type') == 'fixed' ? 'selected' : ''}} value="fixed">Fixed</option>
      <option {{old('pricing_type') == 'per-use' ? 'selected' : ''}} value="per-use">Per Use</option>
      <option {{old('pricing_type') == 'other' ? 'selected' : ''}}value="other">Other</option>
    </select>
    @error('pricing_type')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
  </div>
  <div class="form-group">
    <label for="itypeInput">Invoiceable Type</label>
    <select class="form-control @error('invoiceable_type_id') is-invalid @enderror" name="invoiceable_type_id" id="itypeInput">
    <option {{old('invoiceable_type_id') == '' ? 'selected' : ''}} value="">None</option>
    @foreach($invoiceableTypes as $it)
        <option {{old('invoiceable_type_id') == $it->id ? 'selected' : ''}}  value="{{$it->id}}">{{$it->desc}}</option>
    @endforeach
    </select>
    @error('invoiceable_type_id')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
  </div>
  

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-remove"></i> Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
      </div>
    </div>
  </div>
</div>
</form>
<form id="delForm" action="/config/brand_service_type" method="POST">
@method('DELETE')
@csrf
<input type="hidden" id="delId" name="id" value="">
</form>
@endsection

@section('scripts')
    <script>
    window.items = [];
    window.editing = null;

    const invoiceableTypes = {!! json_encode($invoiceableTypes) !!};
        $(() => {
            $('#bstEditor').on('show.bs.modal', () => {
                if(window.editing !== null) {
                    const workingCopy = window.items[window.editing];
                    $('#idInput').val(workingCopy.id);
                    $('#nameInput').val(workingCopy.name);
                    $('#descInput').html(workingCopy.description);
                    $('#ptypeInput').val(workingCopy.pricing_type);
                    $('#itypeInput').val(workingCopy.invoiceable_type_id);
                }
            });
            $('#bstEditor').on('hide.bs.modal', () => {
                window.editing = null;
                $('#idInput').val('');
                $('#nameInput').val('');
                $('#descInput').html('');
                $('#ptypeInput').val('');
                $('#itypeInput').val('');
            });
            @if($errors->any())
                $('#bstEditor').modal();
            @endif
            $('#add_btn').on('click', () => {
                $('#bstEditor').modal();
            });
            axios.post('/config/brand_service_types?column=name&dir=ASC')
            .then((res) => {
                const lines = res.data.data;
                if(lines.length == 0) {
                    $('#table_body td').html('No Brand Service Types found.');
                    return;
                }
                const body = $('#table_body');
                body.empty();
                window.items = lines;
                for(let i = 0, len = lines.length; i < len; i += 1) {
                    const line = lines[i];
                    const row = $('<tr></tr>');
                    row.append($('<td>'+line.updated_at+'</td>'));
                    row.append($('<td>'+line.name+'</td>'));
                    row.append($('<td>'+ ( line.description.length > 100 ? line.description.slice(0, 100) : line.description) +'</td>'));
                    row.append($('<td>'+line.pricing_type+'</td>'));
                    const tools = $('<td></td>');
                    const editBtn = $('<button></button>');
                        editBtn.attr('type', 'button');
                        editBtn.addClass('btn');
                        editBtn.addClass('btn-primary');
                        editBtn.html('Edit');
                        editBtn.on('click', () => {
                            window.editing = i;
                            $('#bstEditor').modal();
                        });
                    tools.append(editBtn);

                     const delBtn = $('<button></button>');
                        delBtn.attr('type', 'button');
                        delBtn.addClass('btn');
                        delBtn.addClass('btn-danger');
                        delBtn.html('Delete');
                        delBtn.on('click', () => {
                            if(confirm('Are you sure you wish to remove this item?')) {
                                $('#delId').val(line.id);
                                $('#delForm').submit();
                            }
                        });
                    tools.append(delBtn);
                    row.append(tools);
                    body.append(row);
                }
            });
        });
    </script>
@endsection
