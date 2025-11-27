<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Traits\SearchFormTrait;
use App\Models\State;
use App\Models\RateType;
use App\Models\Rate;
use App\Models\Product;
use App\Models\Market;
use App\Models\Language;
use App\Models\Channel;
use App\Models\BrandEztpvContract;
use App\Models\Brand;

class ContractController extends Controller
{
    use SearchFormTrait;

    public static function choose_contract(
        string $brand_id,
        int $state_id,
        int $channel_id,
        int $market_id,
        int $language_id,
        $rate_id_or_obj,
        string $commodity,
        int $document_type_id = 1,
        bool $api_submission = false
    ) {
        if (is_string($rate_id_or_obj)) {
            $rate_info = Rate::find($rate_id_or_obj);
            $rate_id = $rate_id_or_obj;
        } else {
            $rate_info = $rate_id_or_obj; // hack to allow passing in and existing rate obj
            $rate_id = $rate_info->id;
        }
        if (empty($rate_info)) {
            info('Invalid rate id for contract selection: ', ['rate_id' => $rate_id]);
            return null;
        }

        $isGreen = !empty($rate_info->green_percentage);
        $product_id = $rate_info->product_id;
        $rate_amount = $rate_info->rate_amount;
        $rate_type = intval($rate_info->product->rate_type_id, 10);

        info('Contract Selection in progress', [
            'brand_id' => $brand_id,
            'state_id' => $state_id,
            'channel_id' => $channel_id,
            'market_id' => $market_id,
            'lang_id' => $language_id,
            'rate_id' => $rate_id,
            'commodity' => $commodity,
            'document_type' => $document_type_id,
            'api_submission' => $api_submission,
            'is_green' => $isGreen,
            'product_id' => $product_id,
            'rate_amount' => $rate_amount,
            'rate_type' => $rate_type,
        ]);

        $pdf_info = BrandEztpvContract::where('brand_id', $brand_id)
            ->where('document_type_id', $document_type_id)
            ->where('state_id', $state_id)
            ->where('channel_id', $channel_id)
            ->where('market_id', $market_id)
            ->where('language_id', $language_id);

        if ($document_type_id == 1) {
            $pdf_info = $pdf_info->where('commodity', $commodity);

            switch ($brand_id) {
                default:
                    break;

                case '0e80edba-dd3f-4761-9b67-3d4a15914adb': // Residents Energy
                case '77c6df91-8384-45a5-8a17-3d6c67ed78bf': // IDT Energy
                    $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                    if ($isGreen) {
                        $pdf_info = $pdf_info->where('product_type', 1);
                    } else {
                        $pdf_info = $pdf_info->where('product_type', 0);
                    }

                    // tiered: Fixed Tiered and Tiered Variable
                    if ($rate_type == 3) {
                        if (
                            $rate_amount > 0
                        ) {
                            $pdf_info->where(
                                'contract_pdf',
                                'LIKE',
                                '%fixed-tiered%'
                            );
                        } else {
                            $pdf_info->where(
                                'contract_pdf',
                                'LIKE',
                                '%tiered-variable%'
                            );
                        }
                    }
                    break;

                case 'bc90e0f2-2b82-46d8-8c6d-ce491fb1f227':
                case 'ec6e7aad-3d65-42c5-b242-145fb61c6c99':
                    // Waste Management
                    $pdf_info = $pdf_info->where(
                        'product_id',
                        $product_id
                    );
                    break;

                case '1f402ff3-dace-4aea-a6b2-a96bbdf82fee':
                case 'd758c445-6144-4b9c-b683-717aadec83aa':
                    // Spring
                    $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                    if ($rate_type == 3) {
                        // tiered: Fixed Tiered and Tiered Variable
                        if (
                            $rate_amount > 0
                        ) {
                            $pdf_info->where(
                                'contract_pdf',
                                'LIKE',
                                'spring%fixed-tiered%'
                            );
                        } else {
                            $pdf_info->where(
                                'contract_pdf',
                                'LIKE',
                                'spring%tiered-variable%'
                            );
                        }
                    }

                    // product-specific hard-coding
                    $specificProducts = [
                        'aa4bdefd-b08d-4af7-af64-567db52e6c30',
                        '29bc91d6-f61c-4772-bf17-1199f8a47175',
                        '2a11432a-fed5-4f0f-a583-eb5c4c581311',
                        'e6cb63cc-0cd5-4070-b86a-e56d37bdd181'
                    ];

                    if (
                        isset($product_id)
                        && in_array($product_id, $specificProducts)
                    ) {
                        // select by products.id
                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $product_id
                        );
                    } else {
                        // do not select by product_id
                        $pdf_info = $pdf_info->whereNull(
                            'product_id'
                        );
                    }
                    break;

                case 'd3970a96-e933-4cae-a923-e0daa7a59b4d':
                case '31b177d0-33d6-4c51-9907-5b57f68a9526':
                    // Kiwi
                    $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                    if ($rate_type == 3) {
                        // tiered: Fixed Tiered and Tiered Variable
                        if (
                            $rate_amount > 0
                        ) {
                            $pdf_info->where(
                                'contract_pdf',
                                'LIKE',
                                'kiwi%fixed-tiered%'
                            );
                        } else {
                            $pdf_info->where(
                                'contract_pdf',
                                'LIKE',
                                'kiwi%tiered-variable%'
                            );
                        }
                    }

                    // select by product for some states
                    switch ($state_id) {
                        default:
                            break;

                        case 33:
                            $pdf_info = $pdf_info->where(
                                'product_id',
                                $product_id
                            );

                            break;
                    }
                    break;

                case '4e65aab8-4dae-48ef-98ee-dd97e16cbce6':
                case 'eb35e952-04fc-42a9-a47d-715a328125c0':
                    // Indra
                    $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                    switch ($state_id) {
                        case 14: // Illinois
                            $pdf_info = $pdf_info->where(
                                'product_id',
                                $product_id
                            );
                            break;

                        default:
                            // everywhere else
                            if ($rate_type == 3) {
                                // tiered: Fixed Tiered and Tiered Variable
                                if (
                                    $rate_amount > 0
                                ) {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        'indra%fixed-tiered%'
                                    );
                                } else {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        'indra%tiered-variable%'
                                    );
                                }
                            }

                            break;
                    }
                    break;

                case 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e':
                case '7b08b19d-32a5-4906-a320-6a2c5d6d6372':
                    // RPA Energy
                    $pdf_info = $pdf_info->where('rate_type_id', $rate_type);
                    break;

                case 'c03d58ed-1bb0-4e35-9e11-94c1e3bd59cc':
                    // Clearview Energy
                    $pdf_info = $pdf_info->where(
                        'product_id',
                        $product_id
                    );
                    break;

                case '52f9b7cd-2395-48e9-a534-31f15eebc9d4':
                case 'faeb80e2-16ce-431c-bb54-1ade365eec16':
                    // Rushmore Energy
                    if ($api_submission === true) {
                        // select by products.id
                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $product_id
                        )
                            ->where(
                                'rate_id',
                                $rate_info->id
                            );
                        break;
                    }

                    // on 10-20 Paul and Lauren requested product-specific contract selection for specific product ids, 
                    // having been informed at the time that this code will break when IDs/products are updated
                    //
                    // on 11-13 Paul requested product/rate-specific contract selection for specific prodcuts/rates, as above
                    $giftCardProducts = [
                        '35fe3857-13ae-402b-ac3b-57ac3d383a8e',
                        '01349271-3b0d-4856-ac0e-fb417dd2f084',
                        '3163144d-7c73-4d6f-9671-96deec6230d7',
                        'df0fb122-bc8e-4b79-944b-94eabd9dfc48',
                        '3d5719a3-7625-4c44-8d72-19e745f053f4',
                        'f58328ae-5245-47cd-91d8-d299a6eeb83d',
                        'cb4d3e7c-7d73-48c4-a681-f7fca68905cd',
                        'f5726206-98c4-4757-80d4-6780d33a462e'
                    ];

                    $rateSpecificProducts = [
                        '9cc79fd7-3581-4347-8b6b-46f0e07e2921',
                        'cea2c2e4-7175-497e-a8b6-dacde0abcc69'
                    ];

                    if (in_array($product_id, $giftCardProducts)) {
                        // select by products.id
                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $product_id
                        );
                    } elseif (in_array($product_id, $rateSpecificProducts)) {
                        // select by products.id and rates.id
                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $product_id
                        )
                            ->where(
                                'rate_id',
                                $rate_info->id
                            );
                    } else {
                        // do not select by product_id
                        $pdf_info = $pdf_info->whereNull(
                            'product_id'
                        );
                    }

                    break;
            }
        }
        $pdf_info = $pdf_info->first();

        return $pdf_info;
    }

    public function get_file()
    {
        if (file_exists(base_path() . '\resources\assets\documents\\' . request()->file_name)) {
            return response()->file(base_path() . '\resources\assets\documents\\' . request()->file_name);
        }
        session()->flash('flash_message', 'The requested file was not found.');

        return back();
    }

    public function edit($id)
    {
        $c = BrandEztpvContract::select(
            'brand_eztpv_contracts.id',
            'brands.name as brand_name',
            'brand_eztpv_contracts.rate_type_id',
            'brand_eztpv_contracts.brand_id',
            'brand_eztpv_contracts.state_id',
            'brand_eztpv_contracts.market_id',
            'brand_eztpv_contracts.commodity',
            'brand_eztpv_contracts.language_id',
            'brand_eztpv_contracts.channel_id',
            'brand_eztpv_contracts.contract_pdf',
            'brand_eztpv_contracts.product_id',
            'brand_eztpv_contracts.signature_required_customer',
            'brand_eztpv_contracts.signature_required_agent',
            'brand_eztpv_contracts.rate_id',
            'brand_eztpv_contracts.original_contract',
            'brand_eztpv_contracts.file_name',
            'brand_eztpv_contracts.created_at',
            'brand_eztpv_contracts.document_type_id',
            'brand_eztpv_contracts.utility_id',
            DB::raw("IF(tpv_staff.middle_name IS NOT NULL, CONCAT(tpv_staff.first_name, ' ', tpv_staff.middle_name, ' ',tpv_staff.last_name), CONCAT(tpv_staff.first_name, ' ',tpv_staff.last_name)) as uploaded_by_name"),
            'document_types.type',
            'brand_eztpv_contracts.product_type'
        )->leftJoin(
            'tpv_staff',
            'brand_eztpv_contracts.uploaded_by',
            'tpv_staff.id'
        )->join(
            'brands',
            'brands.id',
            'brand_eztpv_contracts.brand_id'
        )->leftJoin(
            'document_types',
            'brand_eztpv_contracts.document_type_id',
            'document_types.id'
        )->where(
            'brand_eztpv_contracts.id',
            $id
        )->get()->first();

        if ($c) {
            if (
                strpos($c->contract_pdf, 'fixed-tiered')
            ) {
                $c->expanded_rate_type = 'fixed-tiered';
            } elseif (
                strpos($c->contract_pdf, 'tiered-variable')
            ) {
                $c->expanded_rate_type = 'tiered-variable';
            } else {
                $c->expanded_rate_type = null;
            }

            $configurations = BrandEztpvContract::select(
                'rate_type_id',
                'state_id',
                'market_id',
                'commodity',
                'language_id',
                'channel_id',
                'product_id',
                'rate_id',
                'signature_required_customer',
                'signature_required_agent',
                'document_type_id',
                'contract_pdf',
                'product_type'
            )->where(
                'original_contract',
                $c->original_contract
            )->where(
                'id',
                '!=',
                $c->id
            )->get();

            $brand_states = $this->get_brand_states($c->brand_id);
            $products = Product::select(
                'id',
                'name'
            )->where(
                'brand_id',
                $c->brand_id
            )->orderBy('products.name')->get();
            $rates = Rate::select(
                'rates.id',
                'rates.program_code'
            )->leftJoin(
                'products',
                'rates.product_id',
                'products.id'
            )->where(
                'products.brand_id',
                $c->brand_id
            )->orderBy('rates.program_code')->get();
            $rateTypes = $this->get_rates();

            foreach ($rateTypes as $key => $value) {
                // unset flex, step and flat fee if its not spark
                if (!in_array($c->brand_id, ['c72feb62-44e7-4c46-9cda-a04bd0c58275','7845a318-09ff-42fa-8072-9b0146b174a5'])) {
                    if ($value['name'] == 'flex') {
                        unset($rateTypes[$key]);
                    }
                    if ($value['name'] == 'step') {
                        unset($rateTypes[$key]);
                    }
                    if ($value['name'] == 'flat fee') {
                        unset($rateTypes[$key]);
                    }
                }
            }

            return view('generic-vue')->with(
                [
                    'componentName' => 'contract-brand-edit',
                    'title' => 'Edit Contract',
                    'parameters' => [
                        'contract' => $c,
                        'channels' => $this->get_channels(),
                        'languages' => $this->get_languages(),
                        'states' => isset($brand_states) ? $brand_states : json_encode([]),
                        'products' => isset($products) ? $products : json_encode([]),
                        'rates' => isset($rates) ? $rates : json_encode([]),
                        'rate-types' => $rateTypes,
                        'utilities' => json_encode($this->get_utilities($c->brand_id)),
                        'configurations' => isset($configurations) ? $configurations : json_encode([]),
                        'previous-versions' => $this->previousContractVersions($c) ?? json_encode([]),
                        'aws-cloud-front' => json_encode(config('services.aws.cloudfront.domain')),
                    ],
                ]
            );
        } else {
            return redirect()->back()->with('error', 'Unable to find specified contract');
        }
    }

    private function get_rates()
    {
        return Cache::remember('rates_types', 3600, function () {
            return RateType::select('id', 'rate_type AS name')->get();
        });
    }

    private function previousContractVersions($c)
    {
        $result = function ($original_contract, $debug = false) use ($c) {
            $query = BrandEztpvContract::select(
                'brand_eztpv_contracts.id',
                'brand_eztpv_contracts.created_at',
                'brand_eztpv_contracts.file_name',
                'brand_eztpv_contracts.uploaded_by',
                DB::raw("IF(tpv_staff.middle_name IS NOT NULL, CONCAT(tpv_staff.first_name, ' ', tpv_staff.middle_name, ' ',tpv_staff.last_name), CONCAT(tpv_staff.first_name, ' ',tpv_staff.last_name)) as uploaded_by_name")
            )
                ->onlyTrashed()
                ->where('original_contract', $original_contract)
                ->leftJoin('tpv_staff', 'brand_eztpv_contracts.uploaded_by', 'tpv_staff.id')
                ->orderBy('brand_eztpv_contracts.created_at', 'DESC');

            if ($debug) {
                dd(['original_contract' => $original_contract, 'query' => $query->toSql()]);
            }
            return $query->get();
        };
        return ($c->original_contract)
            ? $result($c->original_contract)
            : null;
    }

    public function update(Request $request, $id)
    {
        $c = BrandEztpvContract::find($id);
        if ($c) {
            $copy = $c->replicate();
            $date = Carbon::now()->timestamp;
            $file = $request->file('contract_doc');
            $was_file_uploded = false;
            $extension = null;
            if ($file && $file->isValid()) {
                $path = 'contracts';
                $extension = strtolower($file->getClientOriginalExtension());
                $name = $this->name_contract($request, $c, $date) . '.' . $extension;
                $file_name = md5($name) . '.' . $extension;
                try {
                    Storage::disk('s3')->put(
                        $path . '/' . $file_name,
                        $file->get(),
                        'public'
                    );
                } catch (\Aws\S3\Exception\S3Exception $e) {
                    session()->flash('flash_message', 'There has been an error uploading the file to the cloud.');

                    return redirect('/brands');
                }
                $copy->file_name = $file_name;
                $copy->contract_pdf = $name;
                $copy->uploaded_by = Auth::id();
                $was_file_uploded = true;
            } else {
                $contract_pdf_pieces = explode('.', $c->contract_pdf);
                $name = $this->name_contract($request, $c, $date) . '.' . $contract_pdf_pieces[1];
                $copy->contract_pdf = $name;
            }
            $c->delete();

            $copy->original_contract = ($copy->original_contract) ? $copy->original_contract : $c->id;
            $copy->product_type = $request->product_type;
            $copy->state_id = $request->state_id;
            $copy->market_id = $request->market_id;
            $copy->commodity = $request->commodity;
            $copy->utility_id = isset($request->utility_id) ? $request->utility_id : null;
            $copy->language_id = $request->language_id;
            $copy->rate_type_id = !empty($request->rate_type_id) ? $request->rate_type_id : null;
            $copy->channel_id = $request->channel_id;
            $copy->product_id = !empty($request->product_id) ? $request->product_id : null;
            $copy->rate_id = !empty($request->rate_id) ? $request->rate_id : null;
            $copy->signature_info = 'none';
            $copy->signature_info_customer = 'none';
            $copy->signature_info_agent = 'none';
            $copy->signature_required_customer = $request->signature_required_customer;
            $copy->signature_required_agent = $request->signature_required_agent;
            $copy->document_type_id = $request->document_type_id;
            switch ($extension) {
                case 'docx':
                    $copy->document_file_type_id = 2;
                    break;

                case 'pdf':
                    $copy->document_file_type_id = 1;
                    break;
            }
            $copy->contract_fdf = 'None';
            $copy->save();
            $this->update_contract_pdf_on_siblings($c, $date, $was_file_uploded);
        } else {
            session()->flash('flash_message', 'We were unable to find the requested contract on the DB. Please double check the submited information.');

            return redirect('/brands');
        }
        session()->flash('flash_message', 'The contract was successfully edited.');

        return redirect('/brands/' . $copy->brand_id . '/get_contracts');
    }

    public function restore($id)
    {
        $c = BrandEztpvContract::onlyTrashed()->find($id);
        $c->restore();
        $this->restore_version_file_name_on_siblings($c);

        session()->flash('flash_message', 'The contract was restored successfully.');

        return redirect('/brands/' . $c->brand_id . '/get_contracts');
    }

    private function restore_version_file_name_on_siblings($c)
    {
        BrandEztpvContract::where('original_contract', $c->original_contract)->update(['file_name' => $c->file_name]);
    }

    public function name_contract($request, $c, $date): string
    {
        $brand_name = $c->brand->name ?? '';
        if (isset($brand_name)) {
            // sanitize brand name for use in file systems
            $brand_name = strtolower($brand_name);
            $brand_name = str_replace(' ', '_', $brand_name);
            $brand_name = htmlspecialchars($brand_name);
        }
        $document_type = $c->documentType->type ?? '';
        if (isset($document_type)) {
            // sanitize document type for use in file systems
            $document_type = strtolower($document_type);
            $document_type = str_replace(' ', '_', $document_type);
            $document_type = htmlspecialchars($document_type);
        }
        $market = $request->market_id ?? '';
        if ($market) {
            $market = Market::find($market)->market;
        }
        $state = $request->state_id ?? '';
        if ($state) {
            $state = State::find($state)->state_abbrev;
        }
        $commodity = $request->commodity ?? '';
        $language = $request->language_id ?? '';
        if ($language) {
            $language = Language::find($language)->language;
            // sanitize language for use in file systems
            $language = strtolower($language);
            $language = str_replace(' ', '_', $language);
            $language = htmlspecialchars($language);
        }
        $rate_type = $request->rate_type_id ?? '';

        if ($rate_type) {
            if (
                $rate_type == 3
                && isset($request->expanded_rate_type)
            ) {
                $rate_type = $request->expanded_rate_type;
            } else {
                $rate_type = RateType::find($rate_type)->rate_type;
            }
        }

        $channel = $request->channel_id ?? '';
        if ($channel) {
            $channel = Channel::find($channel)->channel;
        }
        $name = '';
        $name .= $brand_name;
        $name .= '_' . $state;
        $name .= '_' . $market;
        $name .= '_' . $channel;
        $name .= '_' . $commodity;
        $name .= '_' . $rate_type;
        $name .= '_' . $language;
        $name .= '_' . $document_type;
        $name .= '_' . $date;

        return trim($name);
    }

    private function update_contract_pdf_on_siblings($c, $date, $was_file_uploded): void
    {
        if ($c->original_contract) {
            BrandEztpvContract::select('contract_pdf', 'id')
                ->where('original_contract', $c->original_contract)
                ->get()
                ->each(function ($contract) use ($date, $was_file_uploded) {
                    $regex = '/(.+_)(.+)(\..+)/';
                    $replace = '${1}' . $date . '${3}';
                    $contract->contract_pdf = preg_replace($regex, $replace, $contract->contract_pdf);
                    if ($was_file_uploded) {
                        $contract->uploaded_by = Auth::id();
                    }
                    $contract->save();
                });
        }
    }

    public function add_contract(Request $request)
    {
        $brand_states = $this->get_brand_states($request->brand_id);
        $brand = Brand::find($request->brand_id);
        $products = Product::select(
            'id',
            'name'
        )->where(
            'brand_id',
            $brand->id
        )->orderBy('products.name')->get();
        $rates = Rate::select(
            'rates.id',
            'rates.program_code'
        )->leftJoin(
            'products',
            'rates.product_id',
            'products.id'
        )->where(
            'products.brand_id',
            $brand->id
        )->orderBy('rates.program_code')->get();

        $rateTypes = $this->get_rates();

        foreach ($rateTypes as $key => $value) {
            // unset flex, step and flat fee if its not spark
            if (!in_array($brand->id, ['c72feb62-44e7-4c46-9cda-a04bd0c58275','7845a318-09ff-42fa-8072-9b0146b174a5'])) {
                if ($value['name'] == 'flex') {
                    unset($rateTypes[$key]);
                }
                if ($value['name'] == 'step') {
                    unset($rateTypes[$key]);
                }
                if ($value['name'] == 'flat fee') {
                    unset($rateTypes[$key]);
                }
            }
        }

        return view('generic-vue')->with(
            [
                'componentName' => 'contract-brand-add',
                'title' => 'Add Contract',
                'parameters' => [
                    'channels' => $this->get_channels(),
                    'languages' => $this->get_languages(),
                    'states' => isset($brand_states) ? $brand_states : json_encode([]),
                    'products' => isset($products) ? $products : json_encode([]),
                    'rates' => isset($rates) ? $rates : json_encode([]),
                    'rate-types' => $rateTypes,
                    'utilities' => json_encode($this->get_utilities($request->brand_id)),
                    'brand' => $brand ?? json_encode(app()->make('stdClass')),
                ],
            ]
        );
    }


    /**
     * @param $brand_id
     * @return array
     *
     */
    private function get_utilities($brand_id): array
    {
        return DB::select("
                    select 
                        s.state_abbrev,
                        s.id as state_id,
                        usf.id as utilit_id,
                        concat(u.name, ' (', (case when usf.utility_fuel_type_id = 1 then 'electric' else 'gas' end), ')') as name,
                        bu.utility_label,
                        bu.utility_external_id,
                        (case when usf.utility_fuel_type_id = 1 then 'electric' else 'gas' end) as commodity
                    from brand_utilities bu
                    join utilities u on bu.utility_id = u.id and u.deleted_at is null
                    join states s on u.state_id = s.id
                    join utility_supported_fuels usf on u.id = usf.utility_id
                    where
                        bu.brand_id = ?
                        and bu.deleted_at is null
                    order by bu.utility_label
                ", [$brand_id]);
    }

    private function cartesian_product($arg)
    {
        $r = [];
        $max = count($arg) - 1;
        $cartesian = function ($arr, $i) use (&$r, $arg, $max, &$cartesian) {
            $checkCount = is_array($arg[$i]) ? count($arg[$i]) : 1;
            for ($j = 0; $l = $checkCount, $j < $l; ++$j) {
                $a = $arr;
                $a[] = $arg[$i][$j];
                if ($i == $max) {
                    $r[] = $a;
                } else {
                    $cartesian($a, $i + 1);
                }
            }
        };
        $cartesian([], 0);

        return $r;
    }

    private function fakeRequest($request)
    {
        $r = app()->make('stdClass');
        $r->market_id = ($request->market_id) ? $request->market_id[0] : null;
        $r->language_id = ($request->language_id) ? $request->language_id[0] : null;
        $r->state_id = ($request->state_id) ? $request->state_id[0] : null;
        $r->commodity = ($request->commodity) ? $request->commodity[0] : null;
        $r->rate_type_id = ($request->rate_type_id) ? $request->rate_type_id : null;
        $r->channel_id = ($request->channel_id) ? $request->channel_id[0] : null;
        $r->expanded_rate_type = ($request->expanded_rate_type) ? $request->expanded_rate_type : null;
        $r->product_type = ($request->product_type) ? $request->product_type : null;

        return $r;
    }

    private function merge_array_values_to_keys_for_another_array($arr1, $arr2)
    {
        $arr = [];
        for ($i = 0; $i < count($arr1); ++$i) {
            if (isset($arr2[$i])) {
                $arr[$arr1[$i]] = $arr2[$i];
            }
        }

        return $arr;
    }

    public function deleteContract(BrandEztpvContract $bec)
    {
        $bec->delete();
        session()->flash('flash_message', 'The contract has been deleted');
        return response('', 200);
    }

    public function store(Request $request)
    {
        $rules = [
            'expanded_rate_type' => 'required_if:rate_type_id,3',
        ];

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        } else {
            $file = $request->file('contract_doc');
            if ($file && $file->isValid()) {
                $c_helper = new BrandEztpvContract();
                $c_helper->brand_id = $request->brand_id;
                $date = Carbon::now()->timestamp;
                $path = 'contracts';
                $extension = strtolower($file->getClientOriginalExtension());
                $r = $this->fakeRequest($request);
                $name = $this->name_contract($r, $c_helper, $date) . '.' . $extension;
                $file_name = md5($name) . '.' . $extension;
                try {
                    Storage::disk('s3')->put(
                        $path . '/' . $file_name,
                        $file->get(),
                        'public'
                    );
                } catch (\Aws\S3\Exception\S3Exception $e) {
                    session()->flash('flash_message', 'There has been an error uploading the file to the cloud.');

                    return redirect('/brands');
                }

                $fields = ['state_id', 'language_id', 'channel_id', 'commodity', 'market_id', 'product_type'];
                $args = [];
                $fields = array_values(
                    array_filter($fields, function ($f) use ($request, &$args) {
                        if ($request->get($f)) {
                            $args[] = $request->get($f);
                        }

                        return $request->get($f);
                    })
                );

                $fields[] = 'rate_type_id';
                if (isset($request->expanded_rate_type)) {
                    $fields[] = 'expanded_rate_type';
                }

                $configs = $this->cartesian_product($args);

                foreach ($configs as $k => $c) {
                    $configs[$k][] = $request->rate_type_id;
                    if (isset($request->expanded_rate_type)) {
                        $configs[$k][] = $request->expanded_rate_type;
                    }
                }

                $utility_id = null;
                if (isset($request->utility_id)) {
                    $utility_id = $request->utility_id;
                }

                $original_contract = null;
                $first = true;
                foreach ($configs as $config) {
                    $contract = new BrandEztpvContract();
                    $contract->brand_id = $request->brand_id;
                    $contract->document_type_id = $request->document_type_id;
                    $contract->document_file_type_id = 2;
                    $contract->file_name = $file_name;
                    foreach ($fields as $key => $f) {
                        if ($f !== 'expanded_rate_type') {
                            $contract->{$f} = $config[$key];
                        }
                    }

                    $contract->product_type = $request->product_type;
                    $contract->original_contract = $original_contract;
                    $contract->utility_id = $utility_id;
                    $contract->uploaded_by = Auth::id();
                    $contract->product_id = !empty($request->product_id) ? $request->product_id : null;
                    $contract->rate_id = !empty($request->rate_id) ? $request->rate_id : null;
                    $r = (object) $this->merge_array_values_to_keys_for_another_array($fields, $config);
                    $contract->contract_pdf = $this->name_contract($r, $contract, $date) . '.' . $extension;
                    $contract->signature_info = 'none';
                    $contract->signature_info_customer = 'none';
                    $contract->signature_info_agent = 'none';
                    $contract->signature_required_customer = $request->signature_required_customer;
                    $contract->signature_required_agent = $request->signature_required_agent;
                    $contract->contract_fdf = 'None';
                    $contract->save();
                    if ($first) {
                        $contract->original_contract = $contract->id;
                        $contract->save();
                        $original_contract = $contract->id;
                        $first = false;
                    }
                }

                return redirect('/brands/' . $request->brand_id . '/get_contracts');
            }
            if (!$file) {
                session()->flash('flash_message', 'There was no contract file selected.');

                return redirect('/brands');
            }
            if (!$file->isValid()) {
                session()->flash('flash_message', 'The uploaded file is corrupted.');

                return redirect('/brands');
            }
            session()->flash('flash_message', 'There was no contract file selected or the file is corrupted.');

            return redirect('/brands');
        }
    }
}
