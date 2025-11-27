<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Country;
use App\Models\State;
use App\Models\Upload;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Image;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('clients.clients');
    }

    /**
     * Display a json list.
     *
     * @return \Illuminate\Http\Response
     */
    public function getClients(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $search = $request->get('search');

        $clients = Client::select(
            'clients.id',
            'clients.name',
            'clients.email',
            'clients.logo_path',
            'clients.active',
            'uploads.filename',
            'clients.phone',
            'clients.deleted_at'
        )->with(
            'brands'
        )->leftJoin(
            'uploads',
            'clients.logo_path',
            'uploads.id'
        );

        if($search){
            $clients = $clients->where(function($query) use ($search){
                return $query->where('clients.name', 'LIKE', "%$search%")
                    ->orWhere('clients.phone', 'LIKE', "%$search%")
                    ->orWhere('clients.email', 'LIKE', "%$search%");
            });
        }

        if ($column && $direction) {
            $clients = $clients->orderBy($column, $direction);
        } else {
            $clients = $clients->orderBy('clients.name', 'asc');
        }

        return $clients->paginate(30);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $states = State::select('id', 'name', 'country_id')->get();
        $countries = Country::select('id', 'country AS name')->get();
        return view(
            'clients.create', 
            [
                'states' => $states, 
                'countries' => $countries
            ]
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = array(
            'name' => 'required',
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('clients.create')
                ->withErrors($validator)
                ->withInput();
        } else {
            $client = new Client();
            $client->name = $request->name;

            if ($request->address) {
                $client->address = $request->address;
            }

            if ($request->city) {
                $client->city = $request->city;
            }

            if ($request->state) {
                $client->state = $request->state;
            }

            if ($request->zip) {
                $client->zip = $request->zip;
            }

            if ($request->phone) {
                $client->phone = "+1".preg_replace("/[^0-9]/", "", $request->phone);
            }

            if ($request->email) {
                $client->email = $request->email;
            }            

            if ($request->notes) {
                $client->notes = $request->notes;
            }

            $client->active = 1;
            $client->save();

            if ($request->file('logo_upload')) {
                $img = $request->file('logo_upload');
                $ext = strtolower($img->getClientOriginalExtension());
                $keyname = "uploads/clients/" . $client->id . "/logos/" . date('Y') . "/" . date('m') . "/" . date('d');
                $filename = md5($img->getRealPath()).".".$ext;
                $path = public_path('tmp/' . $filename);
                Image::make($img->getRealPath())->save($path);

                $optimizerChain = OptimizerChainFactory::create();
                $optimizerChain->optimize($path);

                $s3 = Storage::disk('s3')->put($keyname."/".$filename, file_get_contents($path), 'public');

                if ($client->logo_path && $client->logo_path > 0) {
                    // Disable the previous logo
                    $upload = Upload::find($client->logo_path)->delete();
                }

                $upload = new Upload(
                    [
                        'user_id' => Auth::user()->id,
                        'client_id' => $client->id,
                        'filename' => $keyname."/".$filename,
                        'size' => filesize($path),
                        'upload_type_id' => 2
                    ]
                );

                $upload->save();
                $client->logo_path = $upload->id;
                $client->save();

                unlink($path);
            }

            session()->flash('flash_message', 'Client was successfully added!');
            return redirect()->route('clients.index');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $client = Client::select(
            'clients.id', 
            'clients.name', 
            'clients.address',
            'clients.city',
            'clients.state',
            'clients.zip',
            'clients.phone',
            'clients.email',
            'clients.logo_path',
            'clients.active',
            'uploads.filename'
        )
        ->where('clients.id', '=', $id)
        ->leftJoin('uploads', 'clients.logo_path', '=', 'uploads.id')
        ->first();

        return view('clients.show', ['client' => $client]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $states = State::select('id', 'name', 'state_abbrev')->orderBy('name', 'asc')->get();
        $client = Client::select(
            'clients.id',
            'clients.name',
            'clients.address',
            'clients.city',
            'clients.state',
            'clients.zip',
            'clients.phone',
            'clients.email',
            'clients.logo_path',
            'clients.active',
            'clients.notes',
            'uploads.filename'
        )->where(
            'clients.id',
            $id
        )->leftJoin(
            'uploads',
            'clients.logo_path',
            'uploads.id'
        )->first();

        return view('clients.edit', ['client' => $client, 'states' => $states]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = array(
            'name' => 'required',
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('clients.edit', $id)
                ->withErrors($validator)
                ->withInput();
        } else {
            $client = Client::find($id);
            $client->name = $request->name;

            if ($request->address) {
                $client->address = $request->address;
            }

            if ($request->city) {
                $client->city = $request->city;
            }

            if ($request->state) {
                $client->state = $request->state;
            }

            if ($request->zip) {
                $client->zip = $request->zip;
            }

            if ($request->phone) {
                $client->phone = "+1".preg_replace("/[^0-9]/", "", $request->phone);
            }

            if ($request->email) {
                $client->email = $request->email;
            }

            if ($request->notes) {
                $client->notes = $request->notes;
            }

            $client->active = 1;
            $client->save();

            if ($request->file('logo_upload')) {
                $img = $request->file('logo_upload');
                $ext = strtolower($img->getClientOriginalExtension());
                $keyname = "uploads/clients/" . $client->id . "/logos/" . date('Y') . "/" . date('m') . "/" . date('d');
                $filename = md5($img->getRealPath()).".".$ext;
                $path = public_path('tmp/' . $filename);
                Image::make($img->getRealPath())->save($path);

                $optimizerChain = OptimizerChainFactory::create();
                $optimizerChain->optimize($path);

                $s3 = Storage::disk('s3')->put($keyname."/".$filename, file_get_contents($path), 'public');

                if ($client->logo_path && $client->logo_path > 0) {
                    // Disable the previous logo
                    $upload = Upload::find($client->logo_path)->delete();
                }

                $upload = new Upload([
                    'user_id' => Auth::user()->id,
                    'client_id' => $client->id,
                    'filename' => $keyname."/".$filename,
                    'size' => filesize($path),
                    'upload_type_id' => 2
                ]);

                $upload->save();
                $client->logo_path = $upload->id;
                $client->save();

                unlink($path);
            }

            session()->flash('flash_message', 'Client was successfully edited!');
            return redirect()->route('clients.index');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $client = Client::where('id', $id)->first();
        $client->delete();

        session()->flash('flash_message', 'Client was successfully deleted!');
        return redirect()->route('clients.index');
    }

    public function search(Request $request)
    {
        $search = trim($request->search);

        $clients = Client::select('id', 'name', 'active')
            ->search($search)
            ->orderBy('name', 'asc')
            ->paginate(30);

        return view('clients.clients', ['clients' => $clients, 'search' => $search]);
    }
}
