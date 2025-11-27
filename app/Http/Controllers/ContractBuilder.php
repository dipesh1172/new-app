<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ContractConfig;
use App\Models\ContractConfigCancellation;
use App\Models\ContractConfigPage;
use App\Models\ContractConfigTc;
use App\Models\State;
use App\Models\Upload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;

class ContractBuilder extends Controller
{
    /**
     * Contract Builder Routes.
     */
    public static function routes()
    {
        Route::group(
            ['middleware' => ['auth']],
            function () {
                Route::get('contracts', 'ContractBuilder@index')->name('contracts.index');
                Route::get('contracts/list', 'ContractBuilder@list')->name('contracts.list');
                Route::get('contracts/add', 'ContractBuilder@add')->name('contracts.upload');
                Route::get('contracts/{id}/show', 'ContractBuilder@show')->name('contracts.show');
                Route::get('contracts/{id}/editContract', 'ContractBuilder@editContract')->name('contracts.editContract');
                Route::get('contracts/{contract_id}/page/{page_id}/edit', 'ContractBuilder@editPage')->name('contracts.editPage');
                Route::get('contracts/{contract_id}/page/{page_id}/activate', 'ContractBuilder@activate')->name('contracts.activate');
                Route::post('contracts/{id}/update', 'ContractBuilder@update')->name('contracts.update');
                Route::post('contracts/{contract_id}/page/update', 'ContractBuilder@updatePage')->name('contracts.updatePage');
                Route::post('contracts/store', 'ContractBuilder@store')->name('contracts.store');
                Route::get('contracts/tclist/{brand_id}', 'ContractBuilder@tandcList')->name('contracts.tandcList');
                Route::get('contracts/tcs', 'ContractBuilder@tcs')->name('contracts.tcs');
                Route::get('contracts/tcs/list', 'ContractBuilder@listTCS')->name('contracts.listTCS');
                Route::get('contracts/cancellations', 'ContractBuilder@cancellations')->name('contracts.cancellations');
                Route::get('contracts/cancellations/list', 'ContractBuilder@listCancellations')->name('contracts.listCancellations');
                Route::post('contracts/uploadPdf', 'ContractBuilder@uploadPdf')->name('contracts.uploadPdf');
            }
        );
    }

    public function index()
    {
        return view('contracts.index');
    }

    public function tcs()
    {
        return view(
            'contracts.tcs',
            [
                'brands' => Brand::whereNotNull('client_id')->orderBy('name')->get(),
            ]
        );
    }

    public function cancellations()
    {
        return view('contracts.cancellations');
    }

    public function list(Request $request)
    {
        $column = $request->get('column') ?? 'brand_name';
        $direction = $request->get('direction') ?? 'asc';
        $search = $request->get('search');

        $contractConfig = ContractConfig::select(
            'contract_config.id',
            'contract_config.contract_name',
            'brands.name AS brand_name',
            'states.state_abbrev',
            'contract_config.channel',
            'contract_config.market',
            'languages.language',
            'contract_config.commodities',
            'rate_types.rate_type',
            'contract_config_tcs.name AS terms_and_conditions_name'
        )->leftJoin(
            'brands',
            'contract_config.brand_id',
            'brands.id'
        )->leftJoin(
            'states',
            'contract_config.state_id',
            'states.id'
        )->leftJoin(
            'languages',
            'contract_config.language_id',
            'languages.id'
        )->leftJoin(
            'contract_config_tcs',
            'contract_config.terms_and_conditions',
            'contract_config_tcs.id'
        )->leftJoin(
            'rate_types',
            'contract_config.rate_type',
            'rate_types.id'
        );

        $column = 'status' === $column ? 'active' : $column;

        $contractConfig = $contractConfig->orderBy($column, $direction);

        return $contractConfig->paginate(30);
    }

    public function listTCS(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $search = $request->get('search');
        $contractConfigTc = ContractConfigTc::select(
            'contract_config_tcs.id',
            'contract_config_tcs.name AS terms_and_conditions_name',
            'brands.name AS brand_name',
            'languages.language'
        )->leftJoin(
            'brands',
            'contract_config_tcs.brand_id',
            'brands.id'
        )->leftJoin(
            'languages',
            'contract_config_tcs.language_id',
            'languages.id'
        );

        $column = 'status' == $column ? 'active' : $column;

        if ($column && $direction) {
            $contractConfigTc = $contractConfigTc->orderBy($column, $direction);
        } else {
            $contractConfigTc = $contractConfigTc->orderBy('brand_name', 'asc');
        }

        return response()->json($contractConfigTc->paginate(30));
    }

    public function listCancellations(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $search = $request->get('search');
        $contractConfigCancellations = ContractConfigCancellation::select(
            'contract_config_cancellations.id',
            'contract_config_cancellations.name',
            'brands.name AS brand_name'
        )->leftJoin(
            'brands',
            'contract_config_cancellations.brand_id',
            'brands.id'
        );

        $column = 'status' == $column ? 'active' : $column;

        if ($column && $direction) {
            $contractConfigCancellations = $contractConfigCancellations->orderBy($column, $direction);
        } else {
            $contractConfigCancellations = $contractConfigCancellations->orderBy('brand_name', 'asc');
        }

        return response()->json($contractConfigCancellations->paginate(30));
    }

    public function editPage(Request $request, $contract_id, $page_id)
    {
        return view(
            'contracts.editPage',
            [
                'contractConfig' => ContractConfig::find($contract_id),
                'contractConfigPage' => ContractConfigPage::find($page_id),
            ]
        );
    }

    public function show(Request $request, $id)
    {
        $contractConfig = ContractConfig::select(
            'contract_config.*',
            'brands.name',
            'states.state_abbrev',
            'languages.language',
            'rate_types.rate_type',
            'contract_config_tcs.name AS terms_and_conditions_name'
        )->leftJoin(
            'brands',
            'contract_config.brand_id',
            'brands.id'
        )->leftJoin(
            'states',
            'contract_config.state_id',
            'states.id'
        )->leftJoin(
            'languages',
            'contract_config.language_id',
            'languages.id'
        )->leftJoin(
            'rate_types',
            'contract_config.rate_type',
            'rate_types.id'
        )->leftJoin(
            'contract_config_tcs',
            'contract_config.terms_and_conditions',
            'contract_config_tcs.id'
        )->find($id);
        if ($contractConfig && isset($contractConfig->page_intro)) {
            $contractConfig->page_intro = json_decode($contractConfig->page_intro);
        }

        $ContractConfigPages = ContractConfigPage::select(
            'sort',
            'label',
            'body'
        )->where(
            'contract_config_id',
            $contractConfig->id
        )->orderBy('sort')->get();

        // echo "<pre>";
        // print_r($ContractConfigPages->toArray());
        // exit();
        $rows = [];
        foreach ($ContractConfigPages as $ccp) {
            $array = [];
            $array['id'] = $ccp->sort;

            if (is_string($ccp->label)) {
                $ccp->label = json_decode($ccp->label, true);
            }

            if (is_string($ccp->body)) {
                $ccp->body = json_decode($ccp->body, true);
            }

            $array['english_label'] = @$ccp->label['english'];
            $array['spanish_label'] = @$ccp->label['spanish'];
            $array['english_body'] = @$ccp->body['english'];
            $array['spanish_body'] = @$ccp->body['spanish'];

            $rows[] = $array;
        }

        // echo "<pre>";
        // print_r($rows);
        // exit();

        return view(
            'contracts.show',
            [
                'contractConfig' => $contractConfig,
                'list' => $rows,
            ]
        );
    }

    public function add()
    {
        return view(
            'contracts.addContract',
            [
                'brands' => Brand::whereNotNull('client_id')->orderBy('name')->get(),
                'states' => State::select(
                    'id',
                    'name',
                    'state_abbrev'
                )->where(
                    'status',
                    1
                )->orderBy(
                    'name',
                    'asc'
                )->get(),
            ]
        );
    }

    public function editContract(Request $request, $id)
    {
        $contract = ContractConfig::select(
            'contract_config.*',
            'brands.name AS brand_name'
        )->leftJoin(
            'brands',
            'contract_config.brand_id',
            'brands.id'
        )->find($id);
        $contract->channel = explode('|', $contract->channel);
        $contract->market = explode('|', $contract->market);
        $contract->page_intro = json_decode($contract->page_intro);

        $tcs = $this->tandcList($contract->brand_id);

        $params = [
            'edit' => true,
            'brands' => Brand::whereNotNull('client_id')->orderBy('name')->get(),
            'states' => State::select(
                'id',
                'name',
                'state_abbrev'
            )->where(
                'status',
                1
            )->orderBy(
                'name',
                'asc'
            )->get(),
            'contract' => $contract,
            'tcs' => $tcs,
        ];

        return view(
            'contracts.editContract',
            $params
        );
    }

    public function activate(Request $request, $contract_id, $version_id)
    {
        $ccvs = ContractConfigVersion::where(
            'contract_config_id',
            $contract_id
        )->update(
            [
                'active' => 0,
            ]
        );

        $ccv = ContractConfigVersion::find($version_id);
        if ($ccv) {
            $ccv->active = 1;
            $ccv->save();
        }

        return redirect()->route('contracts.show', $contract_id);
    }

    public function updatePage(Request $request, $contract_id)
    {
        $rules = array(
            'english_label' => 'required',
        );

        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        } else {
            if ($request->english_intro || $request->spanish_intro) {
                $array = [];
                $array['english'] = $request->english_intro;
                $array['spanish'] = $request->spanish_intro;

                $cc = ContractConfig::find($contract_id);
                $cc->page_intro = json_encode($array);
                $cc->save();
            }

            // Delete existing rows.
            ContractConfigPage::where(
                'contract_config_id',
                $contract_id
            )->delete();

            if (($request->english_label) !== null) {
                foreach ($request->english_label as $key => $value) {
                    $label = [];
                    $body = [];

                    $ccp = ContractConfigPage::where(
                        'contract_config_id',
                        $contract_id
                    )->where(
                        'sort',
                        ($key + 1)
                    )->withTrashed()->first();
                    if (!$ccp) {
                        $ccp = new ContractConfigPage();
                    } else {
                        $ccp->restore();
                    }

                    $label['english'] = $request->english_label[$key];
                    $label['spanish'] = $request->spanish_label[$key];

                    $body['english'] = $request->english_body[$key];
                    $body['spanish'] = $request->spanish_body[$key];

                    $ccp->contract_config_id = $contract_id;
                    $ccp->sort = ($key + 1);
                    $ccp->label = json_encode($label);
                    $ccp->body = json_encode($body);
                    $ccp->save();
                }
            }

            // echo "<pre>";
            // print_r($request->all());
            // exit();

            return redirect()->route(
                'contracts.show',
                [
                    $ccp->contract_config_id,
                ]
            );
        }
    }

    public function uploadPdf(Request $request)
    {
        $file = $request->file('terms_conditions');
        $orig_filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $realpath = $file->getRealPath();

        if ($extension !== 'pdf') {
            session()->flash('flash_message', 'Uploaded T&C file must be a pdf.');

            return redirect()->route('contracts.uploadPdf');
        }

        $version = $this->pdfVersion($realpath);
        if ($version > 1.4 || empty($version)) {
            session()->flash('flash_message', 'Uploaded T&C file must be version 1.4 or lower.  Visit https://docupub.com/pdfconvert/ to convert it and re-upload.');

            return redirect()->route('contracts.uploadPdf');
        }

        $s3filename = md5($orig_filename);
        $keyname = 'uploads/pdfs/'.$request->brand.'/'.date('Y-m-d').'/'.$s3filename.'.pdf';

        try {
            Storage::disk('s3')->put(
                $keyname,
                file_get_contents($realpath),
                'public'
            );
        } catch (Aws\S3\Exception\S3Exception $e) {
            error('Error storing invoice on S3: '.$e);

            session()->flash('flash_message', 'Error storing invoice on S3: '.$e);

            return redirect()->route('contracts.uploadPdf');
        }

        $upload = new Upload();
        $upload->user_id = Auth::user()->id;
        $upload->brand_id = $request->brand;
        $upload->filename = $keyname;
        $upload->upload_type_id = 9;
        $upload->save();

        if ($upload) {
            $tcs = new ContractConfigTc();
            $tcs->brand_id = $request->brand;
            $tcs->name = $orig_filename;
            $tcs->language_id = $request->language;
            $tcs->upload_id = $upload->id;
            $tcs->save();
        }

        return redirect()->route('contracts.tcs');
    }

    public function update(Request $request, $id)
    {
        $cc = ContractConfig::find($id);
        if ($cc) {
            $cc->state_id = $request->state;
            $cc->channel = implode('|', $request->channel);
            $cc->market = implode('|', $request->market);
            $cc->commodities = $request->commodity;
            $cc->rate_type = $request->rate_type;
            $cc->terms_and_conditions = $request->terms_and_conditions;
            $cc->terms_and_conditions_spanish = $request->terms_and_conditions_spanish;
            $cc->save();
        }

        return redirect()->route('contracts.show', $cc->id);
    }

    public function pdfVersion($filename)
    {
        $fp = @fopen($filename, 'rb');

        if (!$fp) {
            return 0;
        }

        /* Reset file pointer to the start */
        fseek($fp, 0);

        /* Read 20 bytes from the start of the PDF */
        preg_match('/\d\.\d/', fread($fp, 20), $match);

        fclose($fp);

        if (isset($match[0])) {
            return $match[0];
        } else {
            return 0;
        }
    }

    public function tandcList($brand_id)
    {
        $tcs = ContractConfigTc::select(
            'id as terms_and_conditions',
            'name as terms_and_conditions_name'
        )->where(
            'brand_id',
            $brand_id
        )->withTrashed()->get();
        if ($tcs) {
            return $tcs;
        }

        return null;
    }

    public function store(Request $request)
    {
        $cc = new ContractConfig();
        $cc->brand_id = $request->brand;
        $cc->state_id = $request->state;
        $cc->channel = ($request->channel) ? implode('|', $request->channel) : null;
        $cc->market = ($request->market) ? implode('|', $request->market) : null;
        $cc->commodities = $request->commodity;
        $cc->rate_type = $request->rate_type;
        $cc->terms_and_conditions = $request->terms_and_conditions;
        $cc->terms_and_conditions_spanish = $request->terms_and_conditions_spanish;
        $cc->save();

        return redirect()->route('contracts.show', $cc->id);
    }
}
