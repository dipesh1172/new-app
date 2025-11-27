<?php
namespace App\Http\Controllers;

use App\Models\Document;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $docs = Document::whereNull('brand_id')->get();

        return view('documents.index', [
            'documents' => $docs
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function add()
    {
        return view('documents.add');
    }

    public function edit($id)
    {
        $doc = Document::where('id', $id)->first();

        return view('documents.edit', [
            'doc' => $doc
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $rules = array(
            'title' => 'required',
            'doc' => 'required'
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect('documents.add')
                ->withErrors($validator)
                ->withInput();
        } else {
            $doc = new Document;
            $doc->title = $request->title;
            $doc->document = $request->doc;
            $doc->status = $request->status;
            $doc->save();

            session()->flash('flash_message', 'The document was successfully added!');
            return redirect()->route('documents.edit', [$doc->id]);
        }
    }

    public function update(Request $request, $id)
    {
        $rules = array(
            'title' => 'required',
            'doc' => 'required'
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect('documents.edit', ['doc' => $doc])
                ->withErrors($validator)
                ->withInput();
        } else {
            $d = Document::find($id);
            $d->title = $request->title;
            $d->document = $request->doc;
            $d->status = $request->status;
            $d->save();

            session()->flash('flash_message', 'The document was successfully saved!');
            return redirect()->route('documents.edit', [$id]);
        }
    }
}