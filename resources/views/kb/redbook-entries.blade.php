@extends('layouts.app')

@section('title')
Redbook Entries
@endsection

@section('content')
@breadcrumbs([
	['name' => 'Home', 'url' => '/'],
	['name' => 'KB', 'url' => '/kb'],
	['name' => 'Redbook Entries', 'active' => true]
])
	<div class="container-fluid">
		<div class="row">

		</div>
		<div class="row">
			<div class="col-12">
				<div class="card">
					<div class="card-header">
						<a class="btn btn-primary btn-sm pull-right" href="#" id="newEntryBtn">New Entry</a>
					</div>
					<div class="card-body p-1">
						<table id="redbookEntries" class="table table-striped">
							<thead>
								<tr>
									<th>ID</th>
									<th>Keyword</th>
									<th data-dynatable-column="url" data-dynatable-no-sort="1">Destination URI</th>
									<th data-dynatable-column="visibleOnIndex">Visible on Index</th>
									<th data-dynatable-column="created_at">Created At</th>
									<th data-dynatable-column="updated_at">Updated At</th>
									<th data-dynatable-no-sort="1">Tools</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>



	<div class="modal" id="editor-modal" tabindex="-1" role="dialog" aria-labelledby="editor-title">

		<div class="modal-dialog" role="document">
			<form class="modal-content form-horizontal" id="editor">
				<div class="modal-header">
					<h4 class="modal-title" id="editor-title">Add Row</h4>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				</div>
				<div class="modal-body">
					<input type="hidden" id="id" name="id" class="hidden"/>
					<div class="form-group required">
						<label for="keyword" class="col-sm-3 form-control-label">Keyword</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" id="keyword" name="keyword" placeholder="Search Keyword" required>
							<div id="keyword-errors"></div>
						</div>

					</div>

					<div class="form-group required">
						<label for="url" class="col-sm-3 form-control-label">URL</label>
						<div class="col-sm-9">
							<input type="url" class="form-control" id="url" name="url" placeholder="Destination URL" required>
							<div id="url-errors"></div>
						</div>

					</div>
					<div class="form-group">
						<label for="visibleOnIndex" class="col-sm-3 form-control-label">Visible on Index Page</label>
						<div class="col-sm-9">
							<select class="form-control" id="visibleOnIndex" name="visibleOnIndex">
								<option value="0">False</option>
								<option value="1">True</option>
							</select>
							<div id="visibleOnIndex-errors"></div>
						</div>

					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">Save changes</button>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>

	<div class="modal" id="processing" tabindex="-1" role="dialog" aria-labelledby="Processing">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-body">
	      	<h2>Loading</h2>
	      	<img src="/img/loading.gif" />
	      </div>
	    </div>
	  </div>
	</div>
@endsection

@section('head')
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<link href="{{ asset('css/jquery.dynatable.css') }}" rel="stylesheet">
	<style>
		/* provides a red astrix to denote required fields - this should be included in common stylesheet */
		.form-group.required .form-control-label:after {
			content:"*";
			color:red;
			margin-left: 4px;
		}
		#newEntryBtn {
			margin-left: 10px;
		}

		#processing .modal-body {
			text-align: center;
		}

	</style>
@endsection

@section('scripts')
<script src="{{ asset('js/jquery.dynatable.js') }}"></script>
<script>

var $modal = $('#editor-modal');
var $editor = $('#editor');
var $editorTitle = $('#editor-title');

function editRow(rowData){

	$editor.find('#id').val(rowData.id);
	$editor.find('#keyword').val(rowData.keyword);
	$editor.find('#url').val(rowData.url);

	$editor.find('#visibleOnIndex').val((rowData.visibleOnIndex ? '1' : '0'));

	$modal.data('row', rowData);
	$editorTitle.text('Edit row #' + rowData.id);
	$modal.modal('show');
}

function deleteRow(rowId) {
	if (confirm('Are you sure you want to delete this Redbook Entry?')){
		$.ajax('/redbook/entry/' + rowId, {
			method: 'DELETE',
			dateType: 'json',
			success: function(response){
				if(response.error !== undefined && response.error == null) {
					window.location.reload();
				} else {
					alert('Could not delete row!');
				}
			}
		});

	}
}

$(function(){
	$.ajaxSetup({
	    headers: {
	        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
	    }
	});


	var row = 0;
	var rowFormatter = function(rowIndex, record, columns, cellWriter) {
		row++;
		return '<tr class="' + (row % 2 ? 'striped' : '') + '"><td>' + record.id + '</td>' +
		'<td>' + record.keyword + '</td>' +
		'<td><a target="_blank" href="' + record.url + '">Link</a></td>' +
		'<td>' + (record.visibleOnIndex == '1' ? 'True' : 'False') + '</td>' +
		'<td>' + record.created_at + '</td>' +
		'<td>' + record.updated_at + '</td>' +
		'<td>' +
			'<button title="Edit" class="btn btn-default" onclick=\'editRow('+JSON.stringify(record)+');\'><span class="fa fa-pencil"></span></button> ' +
			'<button title="Delete" class="btn btn-default" onclick=\'deleteRow('+record.id+');\'><span class="fa fa-trash"></span></button>' +
			'</td>'
		'</tr>';
	};

	$('#redbookEntries').dynatable({
		dataset: {
			ajax: true,
			ajaxUrl: '/redbook/list',
			ajaxOnLoad: true,
			records: [],
			perPageOptions: [10,25,50]
		},
		features: {
			perPageSelect: false,
			sort: false
		},
		writers: {
			_rowWriter: rowFormatter
		}
	});

	$('#redbookEntries').on('dynatable:beforeUpdate', function(){
		$('#processing').modal({
			backdrop: 'static',
			keyboard: false
		});
	});

	$('#redbookEntries').on('dynatable:afterUpdate', function(){
		$('#processing').modal('hide');
	});

	//$('#newEntryBtn').insertBefore($('#redbookEntries'));

	$('#newEntryBtn').on('click',function(evt){
		evt.preventDefault();
		$modal.removeData('row');
		$editor[0].reset();
		$editorTitle.text('Add a new Redbook Entry');
		$modal.modal('show');
	});



	$editor.on('submit', function(e){
		if (this.checkValidity && !this.checkValidity()) return;
		e.preventDefault();
		var values = {
			id: $editor.find('#id').val(),
			keyword: $editor.find('#keyword').val().toLowerCase(),
			url: $editor.find('#url').val(),
			visibleOnIndex: $editor.find('#visibleOnIndex').val()
		};

		var url = '';
		var method = '';

		if(values.id !== null && values.id !== undefined && values.id !== ''){
			url = '/redbook/entry/'+values.id;
			method = 'PATCH';
		} else {
			url = '/redbook/save';
			method = 'POST';
		}

		$.ajax(url,{
			'method': method,
			data: values,
			dataType: 'json',
			statusCode: {
				'422': function(xhr,statusText,errorThrown){

					var data = $.parseJSON(xhr.responseText);
					$.each(data,function(name,items){
						console.log(items);
						var errItem = $('#'+name + '-errors');
						errItem.html('');
						$.each(items,function(index,value){
							console.log(value);
							errItem.append('<span class="help-block">'+value+'</span>');
						});

					});
				}
			},
			success: function(response){
				window.location.reload();
			}
		});
	});
});


</script>

@endsection