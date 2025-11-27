<?php

namespace App\Http\Controllers;

use Spatie\ImageOptimizer\OptimizerChainFactory;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Traits\SearchFormTrait;
use App\Traits\CSVResponseTrait;
use App\Models\Vendor;
use App\Models\UserFavoriteBrand;
use App\Models\User;
use App\Models\Upload;
use App\Models\State;
use App\Models\Script;
use App\Models\Product;
use App\Models\LogEnrollmentFile;
use App\Models\InvoiceRateCard;
use App\Models\EventType;
use App\Models\Disposition;
use App\Models\Country;
use App\Models\ClientAlert;
use App\Models\Client;
use App\Models\BrandUsersProfileSection;
use App\Models\BrandUsersProfileField;
use App\Models\BrandUser;
use App\Models\BrandTaskQueue;
use App\Models\BrandServiceType;
use App\Models\BrandService;
use App\Models\BrandPay;
use App\Models\BrandEztpvContract;
use App\Models\BrandEnrollmentFile;
use App\Models\BrandContract;
use App\Models\BrandConfig;
use App\Models\BrandClientAlert;
use App\Models\Brand;
use App\Models\BillMethodology;
use App\Models\BillFrequency;
use App\Models\BgchkProvider;
use App\Models\BgchkCredential;
use App\Models\RateLevelBrand;

class BrandController extends Controller
{
    use SearchFormTrait;
    use CSVResponseTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('brands.brands');
    }

    public function contract($brandId)
    {
        return view('brands.contract', [
            'brand' => Brand::find($brandId),
        ]);
    }

    public function pay($brandId)
    {
        $results = BrandPay::where(
            'brand_id',
            $brandId
        )->first();

        return view(
            'brands.pay',
            [
                'brand' => Brand::find($brandId),
                'results' => $results,
            ]
        );
    }

    public function list_brand_scripts($brand)
    {
        return Script::select('id', 'title')->where('brand_id', $brand)->orderBy('title', 'ASC')->get()->toArray();
    }

    public function payUpdate(Request $request, Brand $brand)
    {
        $bp = BrandPay::where(
            'brand_id',
            $brand->id
        )->first();
        if ($bp) {
            $bp->delete();
        }

        $bp = new BrandPay();
        $bp->brand_id = $brand->id;
        $bp->body = $request->body;
        $bp->save();

        session()->flash('flash_message', 'Pay Link was successfully updated!');

        return redirect('/brands/' . $brand->id . '/pay');
    }

    private function get_brand_products_rates($id)
    {
        return Cache::remember($id . '_brand_products_rates', 1800, function () use ($id) {
            return Product::select(
                'products.name as product_name',
                'products.id as product_id',
                'rates.id as rate_id',
                'rates.program_code'
            )->leftJoin(
                'rates',
                'rates.product_id',
                'products.id'
            )->where(
                'products.brand_id',
                $id
            )->whereNotNull('rates.program_code')
                ->whereNotNull('products.name')
                ->whereNull('rates.deleted_at')
                ->orderBy('products.name', 'ASC')
                ->get();
        });
    }

    // public function get_brand_rates($id)
    // {
    //     return Cache::remember($id . '_brand_rates', 1800, function () use ($id) {
    //         return Rate::select('program_code', 'id')->where('brand_id', $id)->get();
    //     });
    // }

    public function show_rate_level_brands(Brand $brand)
    {
        $user_id = Auth::user()->id;

        return view('generic-vue')->with([
            'componentName' => 'rate-level-brands',
            'title' => 'Rate-Level Brands',
            'parameters' => [
                'brand' => json_encode($brand),
                'user_id' => json_encode(strtolower($user_id))
            ],
        ]);
    }

    public function load_rate_level_brands(Request $request)
    {
        $brands = RateLevelBrand::select(
            'rate_level_brands.id',
            'rate_level_brands.created_at',
            DB::raw('CONCAT(tpv_staff.first_name, " ", tpv_staff.last_name) AS created_by'),
            'rate_level_brands.brand_id',
            'rate_level_brands.brand_names'
        )->leftJoin(
            'tpv_staff',
            'rate_level_brands.created_by',
            'tpv_staff.id'
        )->where(
            'rate_level_brands.brand_id',
            $request->brand_id
        )->first();

        if ($brands) {
            $brands->created_at = $brands->created_at->setTimezone("America/Chicago");
        } else {
            // Create an empty object for use by the Vue component
            $brands = [
                "id" => null,
                "created_at" => null,
                "created_by" => null,
                "brand_id" => $request->brand_id,
                "brand_names" => "[]"
            ];
        }

        return response()->json(['error' => false, 'data' => $brands]);
    }

    public function store_rate_level_brands(Request $request)
    {
        $brand = $request->brand;
        $brandsList = $request->brandsList;
        $user_id = $request->userId;

        // return response()->json(['error' => true, 'id' => null]);

        // $brandsList->brand_names is an object array. Convert to simple array by taking in only the brand names.
        // While we're at it, ignore any records in the array tagged with 'deleted-brand'
        $brand_names = [];

        foreach ($brandsList['brand_names'] as $curBrand) {
            if (strtolower($curBrand['tag']) != 'deleted-brand') {
                $brand_names[] = $curBrand['name'];
            }
        }

        // Check for an existing record. 
        // If found, updated the deleted_by field and then soft-delete the record.
        if ($brandsList['id'] != null) {
            $brands = RateLevelBrand::find($brandsList['id']);

            if ($brands) {
                $brands->deleted_by = $user_id;
                $brands->save();

                $brands->delete();
            }
        }

        // Create a new record (or replacement record if this was an update)
        $brands = new RateLevelBrand();
        $brands->created_by = $user_id;
        $brands->brand_id = $brand['id'];
        $brands->brand_names = json_encode($brand_names);
        $brands->save();

        return response()->json(['error' => false, 'id' => $brands->id]);
    }

    public function show_brand_user_profile_fields(Brand $brand)
    {
        $sections = BrandUsersProfileSection::where('brand_id', $brand->id)->orderBy('sort', 'ASC')->get();
        if (count($sections) < 3) {
            // create default section
            if ($sections->where('name', 'Basic Information')->first() === null) {
                $section = new BrandUsersProfileSection();
                $section->brand_id = $brand->id;
                $section->name = 'Basic Information';
                $section->sort = 0;
                $section->save();
            }
            if ($sections->where('name', 'Quick Information')->first() === null) {
                $section = new BrandUsersProfileSection();
                $section->brand_id = $brand->id;
                $section->name = 'Quick Information';
                $section->sort = 0;
                $section->save();
            }
            if ($sections->where('name', 'Extended Information')->first() === null) {
                $section = new BrandUsersProfileSection();
                $section->brand_id = $brand->id;
                $section->name = 'Extended Information';
                $section->sort = 0;
                $section->save();
            }

            $sections = BrandUsersProfileSection::where('brand_id', $brand->id)->orderBy('sort', 'ASC')->get();
        }

        $fields = BrandUsersProfileField::whereIn('section_id', $sections->pluck('id'))->get();

        return view('generic-vue')->with([
            'componentName' => 'brands-user-profile-fields',
            'title' => 'User Profile Fields',
            'parameters' => [
                'brand' => json_encode($brand),
                'sections' => json_encode($sections),
                'fields' => json_encode($fields),
            ],
        ]);
    }

    public function save_brand_user_profile_section()
    {
        $id = request()->input('id');
        $brand_id = request()->input('brand_id');
        $name = request()->input('name');
        $sort = request()->input('sort');
        $command = request()->input('command');

        if ($id !== null) {
            $section = BrandUsersProfileSection::find($id);
            if ($command === 'disable') {
                $section->delete();

                return response()->json(['error' => false]);
            }
        } else {
            $section = new BrandUsersProfileSection();
        }

        $section->brand_id = $brand_id;
        $section->name = $name;
        $section->sort = $sort;
        $section->save();

        return response()->json(['error' => false, 'id' => $section->id]);
    }

    public function save_brand_user_profile_field()
    {
        $id = request()->input('id');
        $name = request()->input('name');
        $section = request()->input('section_id');
        $desc = request()->input('desc');
        $sort = request()->input('sort');
        $type = request()->input('type');
        $required = request()->input('required');
        $properties = request()->input('properties');
        $command = request()->input('command');

        if ($id !== null) {
            $field = BrandUsersProfileField::find($id);
            if ($command === 'disable') {
                $field->delete();

                return response()->json(['error' => false]);
            }
        } else {
            $field = new BrandUsersProfileField();
        }

        $field->name = $name;
        $field->section_id = $section;
        $field->desc = $desc;
        $field->sort = $sort;
        $field->type = $type;
        $field->required = $required !== null ? $required : false;
        $field->properties = $properties;

        $field->save();

        return response()->json(['error' => false, 'id' => $field->id]);
    }

    public function get_contracts($brandId)
    {
        $commodities = Cache::remember('brand_controller_commodities', 3600, function () {
            return EventType::select('id', 'event_type AS name')
                ->whereNull('deleted_at')
                ->whereIn('id', [1, 2, 4])
                ->get();
        });

        $brand = Brand::find($brandId);

        return view('generic-vue')->with(
            [
                'componentName' => 'brands-contracts',
                'title' => $brand->name . ' Contracts',
                'parameters' => [
                    'states' => json_encode($this->get_states()),
                    'channels' => json_encode($this->get_channels()),
                    'brand' => json_encode(Brand::find($brandId)),
                    'languages' => json_encode($this->get_languages()),
                    'commodities' => json_encode($commodities),
                    'products-and-rates' => $this->get_brand_products_rates($brandId),
                    'aws-cloud-front' => json_encode(config('services.aws.cloudfront.domain')),
                ],
            ]
        );
    }

    public function list_contracts(Request $request, $brand)
    {
        $market = $request->market;
        $language = $request->language;
        $commodity = $request->commodity;
        $state = $request->state;
        $channel = $request->channel;
        $dtype = $request->dtype;
        $rtype = $request->rtype;
        $direction = $request->direction ?? 'desc';
        $column = $request->column ?? 'brand_eztpv_contracts.created_at';
        $brand = Brand::find($brand);

        $contracts = BrandEztpvContract::select(
            'brand_eztpv_contracts.created_at',
            'markets.market',
            'states.state_abbrev',
            'channels.channel',
            'brand_eztpv_contracts.commodity',
            'languages.language',
            'brand_eztpv_contracts.file_name',
            'products.name as product_name',
            'products.deleted_at as product_deleted',
            'document_types.type',
            'brand_eztpv_contracts.product_type',
            'brand_eztpv_contracts.document_type_id',
            'brand_eztpv_contracts.rate_type_id',
            'brand_eztpv_contracts.contract_pdf'
        )->leftJoin(
            'products',
            'products.id',
            'brand_eztpv_contracts.product_id'
        )->leftJoin(
            'document_types',
            'brand_eztpv_contracts.document_type_id',
            'document_types.id'
        );

        if (!$request->csv) {
            $contracts = $contracts->addSelect('brand_eztpv_contracts.id');
        }

        $contracts = $contracts->leftJoin(
            'states',
            'states.id',
            'brand_eztpv_contracts.state_id'
        )->leftJoin(
            'channels',
            'channels.id',
            'brand_eztpv_contracts.channel_id'
        )->leftJoin(
            'markets',
            'markets.id',
            'brand_eztpv_contracts.market_id'
        )->leftJoin(
            'languages',
            'languages.id',
            'brand_eztpv_contracts.language_id'
        );

        if (!empty($dtype)) {
            $contracts = $contracts->where('brand_eztpv_contracts.document_type_id', $dtype);
        } else {
            $contracts = $contracts->whereIn(
                'brand_eztpv_contracts.document_type_id',
                [
                    1,
                    3,
                ]
            );
        }

        if (!empty($rtype)) {
            if ($rtype < 3) {
                $contracts = $contracts->where('brand_eztpv_contracts.rate_type_id', $rtype);
            } else {
                if ($rtype == 4) {
                    $extType = 'fixed-tiered';
                } else {
                    $extType = 'tiered-variable';
                }
                $contracts = $contracts->where('brand_eztpv_contracts.rate_type_id', 3)->where('brand_eztpv_contracts.contract_pdf', 'LIKE', '%' . $extType . '%');
            }
        }

        if ($brand) {
            $contracts = $contracts->where('brand_eztpv_contracts.brand_id', $brand->id);
        }

        if ($state) {
            $contracts = $contracts->whereIn('brand_eztpv_contracts.state_id', $state);
        }

        if ($channel) {
            $contracts = $contracts->whereIn('brand_eztpv_contracts.channel_id', $channel);
        }

        if ($market) {
            $contracts = $contracts->whereIn('brand_eztpv_contracts.market_id', $market);
        }

        if ($language) {
            $contracts = $contracts->whereIn('brand_eztpv_contracts.language_id', $language);
        }

        if ($commodity) {
            $map_conmmodities = collect([
                ['id' => 1, 'event_type' => 'electric'],
                ['id' => 2, 'event_type' => 'gas'],
                ['id' => 4, 'event_type' => 'dual'],
            ])->whereIn('id', $commodity)->pluck('event_type');

            $contracts = $contracts->whereIn('brand_eztpv_contracts.commodity', $map_conmmodities);
        }

        if ($request->product_id) {
            $contracts = $contracts->where(
                'brand_eztpv_contracts.product_id',
                $request->product_id
            );
        }

        if ($request->rate_id) {
            $contracts = $contracts->where('brand_eztpv_contracts.rate_id', $request->rate_id);
        }

        if ($request->csv) {
            return $this->csv_response(
                $contracts->get()->toArray(),
                $brand->name . ' Contracts'
            );
        }

        return $contracts->orderBy($column, $direction)->paginate(30);
    }

    public function services($brand)
    {
        return view('brands.services', [
            'brand' => Brand::find($brand),
            'services' => BrandService::where('brand_id', $brand)->with('brand_service_type')->get(),
            'service_types' => BrandServiceType::orderBy('name')->get(),
        ]);
    }

    public function saveService()
    {
        request()->validate([
            'id' => 'nullable|exists:brand_services',
            'brand' => 'required|exists:brands,id',
            'brand_service_type_id' => 'required|exists:brand_service_types,id',
            // 'rate_card' => 'required',
        ]);
        $id = request()->input('id');
        $brand = request()->input('brand');
        $serviceType = request()->input('brand_service_type_id');
        $rateCard = request()->input('rate_card');

        if ($id !== null) {
            $record = BrandService::find($id);
        } else {
            $record = new BrandService();
        }

        $record->brand_id = $brand;
        $record->brand_service_type_id = $serviceType;
        // $record->rate_card = $rateCard;

        $record->save();

        return redirect('/brands/' . $brand . '/services');
    }

    public function removeService()
    {
        $brand = request()->input('brand');
        $id = request()->input('id');

        $record = BrandService::find($id);
        if ($record == null) {
            session()->flash('message', 'Invalid service id');

            return redirect('/brands/' . $brand . '/services');
        }
        $record->delete();
        session()->flash('message', 'Service ' . $record->brand_service_type->name . ' removed.');

        return redirect('/brands/' . $brand . '/services');
    }

    public function createBrandContract(Request $request)
    {
        $vendor = Vendor::where('brand_id', $request->brand_id)->first();

        $brandContract = new BrandContract();
        $brandContract->brand_id = $request->brand_id;
        $brandContract->client_id = $request->client_id;
        $brandContract->status = $request->status;
        $brandContract->name = $request->name;
        $brandContract->description = $request->description;

        $keyname = $this->uploadPDFToS3($request->file('filename'), $request->brand_id, $vendor->id);
        $brandContract->filename = $keyname;

        $brandContract->save();

        return response()->json($brandContract);
    }

    public function uploadPDFToS3($filename, $brand_id, $vendorId)
    {
        $s3filename = md5($filename);
        $keyname = 'uploads/pdfs/' . $brand_id . '/' . $vendorId . '/' . date('Y-m-d') . '/' . $s3filename . '.pdf';

        try {
            $s3 = Storage::disk('s3')->put(
                $keyname,
                file_get_contents($filename),
                'public'
            );
        } catch (Aws\S3\Exception\S3Exception $e) {
            error('Error storing invoice on S3: ' . $e);

            return false;
        }

        return $keyname;
    }

    public function getBrandContracts($brandId = null)
    {
        if ($brandId) {
            $brandContract = BrandContract::where('brand_id', $brandId)->get();

            return response()->json($brandContract);
        }

        $brandContracts = BrandContract::select('id', 'brand_id', 'client_id', 'status', 'filename')->get();

        return response()->json($brandContracts);
    }

    /**
     * List Brands.
     *
     * @param Request $request - request object
     */
    public function listBrands(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $search = $request->get('search');
        $brands = Brand::select(
            'brands.id',
            'brands.name',
            'clients.name AS client_name',
            'uploads.filename',
            'brands.active'
        )->leftjoin(
            'uploads',
            'uploads.id',
            'brands.logo_path'
        )->join(
            'clients',
            'clients.id',
            'brands.client_id'
        )->whereNotNull(
            'brands.client_id'
        );

        if (null != $search) {
            $brands = $brands->search($search);
        }

        $column = 'status' == $column ? 'active' : $column;

        if ($column && $direction) {
            $brands = $brands->orderBy($column, $direction);
        } else {
            $brands = $brands->orderBy('name', 'asc');
        }

        $user_id = Auth::user()->id;

        if ($request->all) {
            $brands = $brands->get();
        } else {
            $brands = $brands->paginate(20);

            foreach ($brands as $brand) {
                $ufb = UserFavoriteBrand::where(
                    'brand_id',
                    $brand->id
                )->where(
                    'user_id',
                    $user_id
                )->whereNull(
                    'deleted_at'
                )->first();
                if ($ufb) {
                    $brand->favorite = 1;
                }
            }
        }

        return response()->json($brands);
    }

    public function getFavoritesBrands()
    {
        $favoriteBrand = Brand::select(
            'brands.id',
            'brands.name',
            'clients.name AS client_name',
            'uploads.filename',
            'brands.active',
            'user_favorite_brands.id AS favorite'
        )->leftjoin(
            'uploads',
            'uploads.id',
            'brands.logo_path'
        )->leftJoin(
            'clients',
            'clients.id',
            'brands.client_id'
        )->join(
            'user_favorite_brands',
            'user_favorite_brands.brand_id',
            'brands.id'
        )->where(
            'user_favorite_brands.user_id',
            Auth::user()->id
        )->whereNotNull(
            'brands.client_id'
        )->whereNull(
            'user_favorite_brands.deleted_at'
        )->orderBy(
            'brands.name'
        );

        return response()->json($favoriteBrand->paginate(30));
    }

    public function updateFavoritesBrands($brandId, Request $request)
    {
        // dedupe
        $brands = [];
        UserFavoriteBrand::where('user_id', Auth::user()->id)->withTrashed()->orderBy('brand_id')->orderBy('deleted_at', 'ASC')->get()->each(function ($item, $key) use ($brands) {
            if (isset($brands[$item->brand_id])) {
                $item->forceDelete();
            } else {
                $brands[$item->brand_id] = 1;
            }
        });
        // end dedupe

        $brand = UserFavoriteBrand::where(
            'brand_id',
            $brandId
        )->where(
            'user_id',
            Auth::user()->id
        )->withTrashed()->first();
        if ($brand) {
            if ($brand->trashed()) {
                $brand->restore();
            } else {
                $brand->delete();
            }

            return response()->json($brand);
        } else {
            $favoriteBrand = UserFavoriteBrand::create([
                'user_id' => Auth::user()->id,
                'brand_id' => $brandId,
            ]);

            return response()->json($favoriteBrand);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $states = State::select('id', 'name', 'country_id')->orderBy('name')->get();
        $clients = Client::select('id', 'name')->orderBy('name')->get();
        $countries = Country::select('id', 'country AS name')->get();

        return view('brands.create', [
            'states' => $states,
            'clients' => $clients,
            'countries' => $countries,
        ]);
    }

    public function setupBrandDefaults(Brand $brand, bool $returnResponse = true)
    {
        try {
            DB::beginTransaction();
            $dispositionsData  = [
                [
                    'disposition_category_id' => 1,
                    'brand_label' => '000002',
                    'fraud_indicator' => 0,
                    'reason' => 'Could Not Contact Customer - Busy, Answering Machine, Etc.',
                    'description' => 'TPV is unable to make contact with the customer after 3 failed attempts.',
                ],
                [
                    'disposition_category_id' => 1,
                    'brand_label' => '000016',
                    'fraud_indicator' => 0,
                    'reason' => 'Call Disconnected',
                    'description' => 'Call disconnects during verification',
                ],
                [
                    'disposition_category_id' => 2,
                    'brand_label' => '000004',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Needs Clarification',
                    'description' => 'Customer does not understand or is unable to complete the verification without questions being addressed.',
                ],
                [
                    'disposition_category_id' => 2,
                    'brand_label' => '000003',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Changed Their Mind',
                    'description' => 'Customer wants to end the verification and/or does not agree with the terms and conditions.',
                ],
                [
                    'disposition_category_id' => 2,
                    'brand_label' => '000011',
                    'fraud_indicator' => 1,
                    'reason' => 'Not Authorized Decision Maker',
                    'description' => 'Customer is not at least 18 years of age and/or is not authorized to complete the enrollment verificaiton.',
                ],
                [
                    'disposition_category_id' => 3,
                    'brand_label' => '000018',
                    'fraud_indicator' => 0,
                    'reason' => 'Existing Account Holder',
                    'description' => 'Customer has an existing account with the company - system will prompt if the customer is existing or customer states they are existing customer.',
                ],
                [
                    'disposition_category_id' => 3,
                    'brand_label' => '000001',
                    'fraud_indicator' => 1,
                    'reason' => 'Sales Rep Did Not Leave Premises',
                    'description' => 'Sales agent did not leave the premises as required.',
                ],
                [
                    'disposition_category_id' => 3,
                    'brand_label' => '000015',
                    'reason' => 'Sales Rep Interrupted',
                    'fraud_indicator' => 1,
                    'description' => 'Sales Representative participated during the Customer Interaction portion of the verification call.  i.e Sales representative attempts to further educate the customer on the program during the customer interaction portion of the call, or Sales Respresentative acknoweldges/answers a question asked by the customer during the verification of the agreement',
                ],
                [
                    'disposition_category_id' => 2,
                    'brand_label' => '000019',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Receiving Energy Assistance',
                    'description' => 'Custmer receiving Energy Assistance such as HEAP, PIPP or Budget Billing',
                ],
                [
                    'disposition_category_id' => 2,
                    'brand_label' => '000007',
                    'fraud_indicator' => 1,
                    'reason' => 'Language Barrier',
                    'description' => 'Customer has difficulty understanding the language spoken by TPV and is unable to complete the verification call.',
                ],
                [
                    'disposition_category_id' => 4,
                    'brand_label' => '000009',
                    'reason' => 'Misrepresentation of Utility',
                    'fraud_indicator' => 1,
                    'description' => 'Sales Agent has not made it clear to the customer they are representing an alternative supplier and/or did not explain to the customer that service would be switched to another provider.',
                ],
                [
                    'disposition_category_id' => 4,
                    'brand_label' => '000020',
                    'reason' => 'Sales Rep Acted as Customer',
                    'fraud_indicator' => 1,
                    'description' => 'Sales Rep completes the verification as the customer.',
                ],
                [
                    'disposition_category_id' => 3,
                    'brand_label' => '000091',
                    'reason' => 'Agent Abusive to Customer.',
                    'description' => 'Agent was abusive to the customer.',
                    'fraud_indicator' => 0,
                ],
                [
                    'disposition_category_id' => 5,
                    'brand_label' => '000012',
                    'fraud_indicator' => 0,
                    'reason' => 'Test Call',
                    'description' => 'Caller advises that the call is being completed for testing purposes.',
                ],
                [
                    'disposition_category_id' => 6,
                    'brand_label' => '000005',
                    'fraud_indicator' => 0,
                    'reason' => 'Hold Time Expired',
                    'description' => 'Sales Rep or Customer puts TPV rep on hold for more allotted time.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '200001',
                    'fraud_indicator' => 0,
                    'reason' => 'Restricted Zip Code',
                    'description' => 'Sales Rep or customer provides a zip code that is blocked through a restricted list.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '200002',
                    'fraud_indicator' => 0,
                    'reason' => 'Unauthorized Enrollment Type',
                    'description' => 'Sales agent attempts a sale for an unauthorized channel.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '200003',
                    'fraud_indicator' => 0,
                    'reason' => 'Sales Rep Not Permitted',
                    'description' => 'Sales Rep Code is inactive or not permitted.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '200004',
                    'fraud_indicator' => 0,
                    'reason' => 'Unmatched Customer Information/Record/Transaction/Product Code Not Found',
                    'description' => 'Sales Rep attempted to enroll service address located in different state than verification',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '200005',
                    'fraud_indicator' => 0,
                    'reason' => 'BTN Matches Sales Rep Phone Number',
                    'description' => 'Customer\'s phone matches a Sales rep\'s phone number',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '200006',
                    'fraud_indicator' => 0,
                    'reason' => 'BTN Previously Good Saled',
                    'description' => 'BTN has been used in a previously good saled TPV',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '000021',
                    'fraud_indicator' => 0,
                    'reason' => 'Rescission/Cancellation',
                    'description' => 'The customer is unclear or raises any concern or issue regarding the recession or cancellation period during the verification call.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '000010',
                    'fraud_indicator' => 0,
                    'reason' => 'Unclear Response',
                    'description' => '',
                ],
                [
                    'disposition_category_id' => 1,
                    'brand_label' => '000017',
                    'fraud_indicator' => 0,
                    'reason' => 'Connectivity',
                    'description' => 'Related to an issue in connectivity or interruption to our system that prevents our TPV agent from proceeding. This includes and is not limited to call static, a missing rate, rate script/cancel script error.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '60002',
                    'fraud_indicator' => 0,
                    'reason' => 'Abandoned',
                    'description' => 'EzTPV was abandoned before completion',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '60001',
                    'fraud_indicator' => 0,
                    'reason' => 'Pending',
                    'description' => 'Pending completion',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900002',
                    'fraud_indicator' => 0,
                    'reason' => 'BTN used in No Sales with alert dispositions',
                    'description' => 'BTN has been No Saled 3 times with alertable dispositions in the last x days.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900003',
                    'fraud_indicator' => 0,
                    'reason' => 'Account Number enrolled with Good Sales multiple times',
                    'description' => 'Sends an alert if the provided account number has been Good Saled 3 times with in the last x days.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900004',
                    'fraud_indicator' => 0,
                    'reason' => 'BTN Matches Sales Rep Phone Number',
                    'description' => 'Customers phone matches a Sales reps phone number',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900005',
                    'fraud_indicator' => 0,
                    'reason' => 'BTN Previously Used for Multiple Customers',
                    'description' => 'BTN has been used in a previously good saled TPV',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900006',
                    'fraud_indicator' => 0,
                    'reason' => 'Email Address used in a previous Good Sale with different Authorizing Name',
                    'description' => 'Email address found for a previous good sale where the authorizing name is different.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900007',
                    'fraud_indicator' => 0,
                    'reason' => 'Account Previously Enrolled',
                    'description' => 'Account Number matches a previouss Good Sale.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900008',
                    'fraud_indicator' => 0,
                    'reason' => 'BTN and Authorizing Name Previously Good Saled ',
                    'description' => 'BTN is used in a previous Good Sale within x amount of days',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900009',
                    'fraud_indicator' => 0,
                    'reason' => 'BTN Used In Previous No Sales',
                    'description' => 'Customers BTN was used in a No Saled TPV.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900010',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer TPV Multiple Times',
                    'description' => 'Authorizing name and BTN appear in a previous good saled TPV, but with a different account number',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900011',
                    'fraud_indicator' => 0,
                    'reason' => 'Existing Service Address',
                    'description' => 'Sales Agent provided an address that is associated with a Good Sale in our database.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900012',
                    'fraud_indicator' => 0,
                    'reason' => 'Too Many Sales Alert',
                    'description' => 'Sales Agent has too many sales within given short timeframe',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900013',
                    'fraud_indicator' => 0,
                    'reason' => 'Account Number attempted multiple times resulting in No Sales with alert dispositions ',
                    'description' => 'Account number has been No Saleed x times with alertable dispositions today.',
                ],
                [
                    'disposition_category_id' => 7,
                    'brand_label' => '900014',
                    'fraud_indicator' => 0,
                    'reason' => 'Non-Fixed VOIP Phone Used',
                    'description' => 'Sales agent provided a BTN that was identified as a NonFixedVOIP number.',
                ],
            ];

            // Map over each entry to add the shared fields
            $dispositions = array_map(function ($entry) use ($brand) {
                return array_merge($entry, [
                    'id' => Uuid::uuid4(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'brand_id' => $brand->id,
                ]);
            }, $dispositionsData);

            // Array of IVR Review dispositions
            $ivr_review_dispositionsData = [
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500000',
                    'fraud_indicator' => 0,
                    'reason' => 'Unknown',
                    'description' => 'Unknown'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500002',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Changed Mind',
                    'description' => 'Customer Changed Mind'
                ],
                [

                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500003',
                    'fraud_indicator' => 0,
                    'reason' => 'Was Not Authorized',
                    'description' => 'Was Not Authorized'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500004',
                    'fraud_indicator' => 0,
                    'reason' => 'Language Barrier',
                    'description' => 'Language Barrier'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500005',
                    'fraud_indicator' => 0,
                    'reason' => 'Agent Interrupted TPV Process',
                    'description' => 'Agent Interrupted TPV Process'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500006',
                    'fraud_indicator' => 0,
                    'reason' => 'Did Not Agree To Service Address',
                    'description' =>  'Did Not Agree To Service Address'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500007',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Had Questions/Did Not Agree',
                    'description' => 'Customer Had Questions/Did Not Agree'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500008',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Understand ETF Clause',
                    'description' => 'Customer Did Not Understand ETF Clause'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500009',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Understand No Savings',
                    'description' => 'Customer Did Not Understand No Savings'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500010',
                    'fraud_indicator' => 0,
                    'reason' => 'Cust Hungup / Disconnect During Verification',
                    'description' => 'Cust Hungup / Disconnect During Verification'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500011',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Understand Supplier Relation',
                    'description' => 'Customer Did Not Understand Supplier Relation'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500012',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer On Government Assistance',
                    'description' => 'Customer On Government Assistance'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500013',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Agree To Acct Num/Meter Num',
                    'description' => 'Customer Did Not Agree To Acct Num/Meter Num'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500014',
                    'fraud_indicator' => 0,
                    'reason' => 'Connectivity (Bad Transfer/Connection)',
                    'description' => 'Connectivity (Bad Transfer/Connection)'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500015',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Agree To Term/Price',
                    'description' => 'Customer Did Not Agree To Term/Price'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500016',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Understand Rate',
                    'description' => 'Customer Did Not Understand Rate'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500017',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Understand Renewal',
                    'description' => 'Customer Did Not Understand Renewal'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500018',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Understand Rescission',
                    'description' => 'Customer Did Not Understand Rescission'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500019',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Did Not Agree To Consent',
                    'description' => 'Customer Did Not Agree To Consent'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500020',
                    'fraud_indicator' => 0,
                    'reason' => 'Refused Recording',
                    'description' => 'Refused Recording'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500021',
                    'fraud_indicator' => 0,
                    'reason' => 'Agent Acted as Customer',
                    'description' => 'Agent Acted as Customer'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500022',
                    'fraud_indicator' => 0,
                    'reason' => 'Existing Customer',
                    'description' => 'Existing Customer'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500023',
                    'fraud_indicator' => 0,
                    'reason' => 'Did not Reach 3rd Attempt',
                    'description' => 'Did not Reach 3rd Attempt'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500024',
                    'fraud_indicator' => 0,
                    'reason' => 'Test Call',
                    'description' => 'Test Call'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500025',
                    'fraud_indicator' => 0,
                    'reason' => 'Agent Did Not Leave Premises',
                    'description' => 'Agent Did Not Leave Premises'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500026',
                    'fraud_indicator' => 0,
                    'reason' => 'Reversed',
                    'description' => 'Reversed'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500027',
                    'fraud_indicator' => 0,
                    'reason' => 'Did Not Agree to Account Details',
                    'description' => 'Did Not Agree to Account Details'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500028',
                    'fraud_indicator' => 0,
                    'reason' => 'Customer Is Confused',
                    'description' => 'Customer Is Confused'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500029',
                    'fraud_indicator' => 0,
                    'reason' => 'Incomplete Address',
                    'description' => 'Incomplete Address'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500030',
                    'fraud_indicator' => 0,
                    'reason' => 'Incomplete Name',
                    'description' => 'Incomplete Name'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500031',
                    'fraud_indicator' => 0,
                    'reason' => 'No Address',
                    'description' => 'No Address'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500032',
                    'fraud_indicator' => 0,
                    'reason' => 'No Audio',
                    'description' => 'No Audio'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500033',
                    'fraud_indicator' => 0,
                    'reason' => 'No Name',
                    'description' => 'No Name'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500034',
                    'fraud_indicator' => 0,
                    'reason' => 'No Or Insufficient DOB',
                    'description' => 'No Or Insufficient DOB'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500035',
                    'fraud_indicator' => 0,
                    'reason' => 'No Response',
                    'description' => 'No Response'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500036',
                    'fraud_indicator' => 0,
                    'reason' => 'No Title',
                    'description' => 'No Title'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500037',
                    'fraud_indicator' => 0,
                    'reason' => 'Rep Tells Customer To Say Yes',
                    'description' => 'Rep Tells Customer To Say Yes'
                ],
                [
                    'disposition_type_id' => 5,
                    'disposition_category_id' => 12,
                    'brand_label' => '500038',
                    'fraud_indicator' => 0,
                    'reason' => 'Response Not Clear',
                    'description' => 'Response Not Clear'
                ]
            ];

            // Map over each entry to add the shared fields
            $ivr_review_dispositions = array_map(function ($entry) use ($brand) {
                return array_merge($entry, [
                    'id' => Uuid::uuid4(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'brand_id' => $brand->id,
                ]);
            }, $ivr_review_dispositionsData);

            //Moving the last disposition to a different variable because you can't use the same insert when the number of fields
            //on the array are different
            $last_disposition = array(
                'id' => Uuid::uuid4(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'disposition_type_id' => 2,
                'disposition_category_id' => 3,
                'brand_id' => $brand->id,
                'brand_label' => '700001',
                'fraud_indicator' => 0,
                'reason' => 'Agent Needs Clarification',
                'description' => 'Agent does not understand or is unable to complete the verification without questions being addressed.',
            );

            Disposition::where('brand_id', $brand->id)->delete();
            Disposition::insert($dispositions);
            Disposition::insert($ivr_review_dispositions);
            Disposition::insert($last_disposition);

            $brandEnrollmentFile = array(
                array(
                    'id' => Uuid::uuid4(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'brand_id' => $brand->id,
                    'report_fields' => '[{"field":"event_created_at","type":"date","format":"m/d/Y","required":true,"header":"Created Date"},{"field":"eztpv_initiated","header":"EZTPV"},{"field":"language","header":"Language"},{"field":"channel","header":"Channel"},{"field":"confirmation_code","header":"Confirmation Code"},{"field":"result","header":"Result"},{"field":"interaction_type","header":"Interaction Type"},{"field":"disposition_label","header":"Disposition ID"},{"field":"disposition_reason","header":"Disposition"},{"field":"source","header":"Source"},{"field":"brand_name","header":"Brand"},{"field":"vendor_label","header":"Vendor Label"},{"field":"vendor_name","header":"Vendor"},{"field":"office_label","header":"Office Label"},{"field":"office_name","header":"Office Name"},{"field":"market","header":"Market"},{"field":"commodity","header":"Commodity"},{"field":"utility_commodity_ldc_code","header":"Utility Commodity LDC Code"},{"field":"utility_commodity_external_id","header":"Utility Commodity External ID"},{"field":"sales_agent_name","header":"Sales Agent Name"},{"field":"sales_agent_rep_id","header":"Sales Agent Rep ID"},{"field":"tpv_agent_name","header":"TPV Agent Name"},{"field":"dnis","header":"DNIS"},{"field":"structure_type","header":"Structure Type"},{"field":"company_name","header":"Company"},{"field":"bill_first_name","header":"Billing First Name"},{"field":"bill_middle_name","header":"Billing Middle Name"},{"field":"bill_last_name","header":"Billing Last Name"},{"field":"auth_first_name","header":"Auth First Name"},{"field":"auth_middle_name","header":"Auth Middle Name"},{"field":"auth_last_name","header":"Auth Last Name"},{"field":"auth_relationship","header":"Auth Relationship"},{"field":"btn","header":"Billing Telephone Number"},{"field":"email_address","header":"Email Address"},{"field":"billing_address1","header":"Billing Address"},{"field":"billing_address2","header":"Billing Address 2"},{"field":"billing_city","header":"Billing City"},{"field":"billing_state","header":"Billing State"},{"field":"billing_zip","header":"Billing Zip"},{"field":"billing_county","header":"Billing County"},{"field":"billing_country","header":"Billing Country"},{"field":"service_address1","header":"Service Address"},{"field":"service_address2","header":"Service Address 2"},{"field":"service_city","header":"Service City"},{"field":"service_state","header":"Service State"},{"field":"service_zip","header":"Service Zip"},{"field":"service_county","header":"Service County"},{"field":"service_country","header":"Service Country"},{"field":"rate_program_code","header":"Rate Program Code"},{"field":"rate_uom","header":"Rate UOM"},{"field":"rate_source_code","header":"Rate Source Code"},{"field":"rate_promo_code","header":"Rate Promo Code"},{"field":"rate_external_id","header":"Rate External ID"},{"field":"rate_renewal_plan","header":"Rate Renewal Plan"},{"field":"rate_channel_source","header":"Rate Channel Source"},{"field":"product_name","header":"Product Name"},{"field":"product_rate_type","header":"Product Rate Type"},{"field":"external_rate_id","header":"External Rate ID"},{"field":"product_term","header":"Product Term"},{"field":"product_term_type","header":"Product Term Type"},{"field":"product_intro_term","header":"Product Intro Term"},{"field":"product_daily_fee","header":"Product Daily Fee"},{"field":"product_service_fee","header":"Product Service Fee"},{"field":"product_rate_amount","header":"Product Rate Amount"},{"field":"product_rate_amount_currency","header":"Product Rate Amount Currency"},{"field":"product_green_percentage","header":"Product Green Percentage"},{"field":"product_cancellation_fee","header":"Product Cancellation Fee"},{"field":"product_admin_fee","header":"Product Admin Fee"},{"field":"transaction_fee","header":"Product Transaction Fee"},{"field":"product_utility_name","header":"Product Utility Name"},{"field":"product_utility_external_id","header":"Product Utility External ID"},{"field":"account_number1","header":"Account Number 1"},{"field":"account_number2","header":"Account Number 2"},{"field":"name_key","header":"Name Key"},{"field":"pass_fail","header":"Pass/Fail"},{"field":"recording","header":"Recording"},{"field":"contracts","header":"Contracts"},{"field":"passfail_reason","header":"Fail Reason"},{"field":"interaction_time","header":"Interaction Time"},{"field":"product_time","header":"Product Time"},{"field":"product_monthly_fee","header":"Product Monthly Fee"}]',
                    'file_format_id' => 1,
                ),
            );

            BrandEnrollmentFile::where('brand_id', $brand->id)->delete();
            BrandEnrollmentFile::insert($brandEnrollmentFile);

            BrandConfig::where('brand_id', $brand->id)->delete();
            $bc = new BrandConfig();
            $bc->created_at = Carbon::now();
            $bc->updated_at = Carbon::now();
            $bc->brand_id = $brand->id;
            $bc->rules = '{"outbound_call_wait":"30","customer_no_response_wait":"30","sales_agent_no_response_wait":"30","customer_still_onpremise":true,"validate_addresses":false,"customer_off_list":true,"standard_no_response_rebuttal":"I am unable to hear you. Due to no response, I will be ending the call at this time!","sales_agent_fraud":true,"disposition_timeout":"30","customer_history_recent_good_sale":true,"customer_history_three_no_sales":true}';
            $bc->save();

            $this->defaultRateCard($brand->id);

            //Saving default configurations for notifications
            //Returning clientAlert for standard (client_alert_type_i = 1 ) and aditional (client_alert_type_i = 2 )alerts
            $alerts = ClientAlert::select(
                'client_alert_type_id',
                'id',
                'has_threshold',
                'function'
            )->get();

            if (!$alerts->isEmpty()) {
                BrandClientAlert::where('brand_id', $brand->id)->delete();
                foreach ($alerts as $alert) {
                    $bca = new BrandClientAlert();
                    $bca->client_alert_id = $alert->id;
                    $bca->brand_id = $brand->id;
                    $bca->status = 0;
                    $bca->threshold = ($alert->has_threshold && $alert->client_alert_type_id == 1) ? 90 : null;
                    $bca->stop_call = 0;
                    $bca->channels = ($alert->client_alert_type_id == 1) ? 'DTD,Retail,TM' : null;
                    $bca->distribution_email = null;
                    $bca->alert_data = $bca->toJson();
                    $bca->disposition_id = $this->getDefaultDispositionForAlertFunction($alert->function, $dispositions);

                    $bca->save();
                }
            }

            $params = [
                '--brand' => $brand->id,
            ];

            Artisan::call(
                'twilio:brand:setup',
                $params
            );

            DB::commit();
            if ($returnResponse) {
                return response()->json(['error' => false, 'message' => null]);
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            info('Error during brand default setup', [$e]);
            if ($returnResponse) {
                return response()->json(['error' => true, 'message' => $e->getMessage()]);
            }

            return false;
        }
    }

    private function getDefaultDispositionForAlertFunction(string $functionName, array $dispositionList)
    {
        $lbl = null;
        switch ($functionName) {
            case 'checkAccountNumberGoodSale':
                $lbl = '900003';
                break;
            case 'checkAccountNumberNoSaleDispositions':
                $lbl = '900002';
                break;
            case 'checkAccountPreviouslyEnrolled':
                $lbl = '900007';
                break;
            case 'checkBtnAndAuthorizingNamePreviouslyGoodSaled':
                $lbl = '900008';
                break;
            case 'checkBtnMatchesSalesRepPhoneNumber':
                $lbl = '900004';
                break;
            case 'checkBtnNoSaleDispositions':
                $lbl = '900009';
                break;
            case 'checkBtnPreviouslyUsedForMultipleCustomers':
                $lbl = '900005';
                break;
            case 'checkBtnUsedInPreviousNoSales':
                $lbl = '900009';
                break;
            case 'checkCallbackNumberPreviouslyUsed':
                $lbl = '900008';
                break;
            case 'checkCustomerTpvedMultipleTimes':
                $lbl = '900010';
                break;
            case 'checkEmailUsedInPreviousGoodSaleDiffNames':
                $lbl = '900006';
                break;
            case 'checkExistingServiceAddress':
                $lbl = '900011';
                break;
            case 'checkNoSaleAlert':
                $lbl = '900013';
                break;
            case 'checkTemporaryOrVoipPhoneUsedBySalesAgent':
                $lbl = '900014';
                break;
            case 'checkTooManySalesAlert':
                $lbl = '900012';
                break;

            default:
                return null;
        }

        foreach ($dispositionList as $disposition) {
            if ($disposition['brand_label'] === $lbl) {
                return $disposition['id'];
            }
        }
        return null;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = array(
            'client_id' => 'required',
            'name' => 'required|unique:brands',
            'billing_distribution' => 'required_without:accounts_payable_distribution',
            'accounts_payable_distribution' => 'required_without:billing_distribution',
            'billing_frequency' => 'required|in:monthly,bi-weekly,weekly'
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('brands.create')
                ->withErrors($validator)
                ->withInput();
        } else {
            $brand = new Brand();
            $brand->client_id = $request->client_id;
            $brand->name = $request->name;
            $brand->legal_name = $request->legal_name;
            $brand->address = $request->address;
            $brand->city = $request->city;
            $brand->state = $request->state;
            $brand->zip = $request->zip;
            $brand->billing_distribution = trim($request->billing_distribution);
            $brand->accounts_payable_distribution = trim($request->accounts_payable_distribution);

            if ($request->purchase_order_no) {
                $po = trim($request->purchase_order_no);
                if (strlen($po) > 0) {
                    $brand->purchase_order_no = $request->purchase_order_no;
                } else {
                    $brand->purchase_order_no = null;
                }
            } else {
                $brand->purchase_order_no = null;
            }
            if ($request->po_valid_until) {
                $brand->po_valid_until = $request->po_valid_until;
            }

            $brand->notes = $request->notes ? $request->notes : '';

            if ($request->service_number) {
                $brand->service_number = '+1' . preg_replace(
                    '/[^A-Za-z0-9]/',
                    '',
                    $request->service_number
                );
            }

            if ($request->has('billing_enabled')) {
                if ($request->billing_enabled === 'on') {
                    $brand->billing_enabled = true;
                } else {
                    $brand->billing_enabled = false;
                }
            } else {
                $brand->billing_enabled = false;
            }
            $brand->billing_frequency = $request->billing_frequency;

            $brand->active = 1;
            $brand->save();

            if ($request->input('setup_default') === 'on') {
                $this->setupBrandDefaults($brand, false);
            }

            if ($request->file('logo_upload')) {
                $img = $request->file('logo_upload');
                $ext = strtolower($img->getClientOriginalExtension());
                $keyname = 'uploads/brands/' . $brand->id . '/logos/' . date('Y') . '/' . date('m') . '/' . date('d');
                $filename = md5($img->getRealPath()) . '.' . $ext;
                $path = public_path('tmp/' . $filename);
                Image::make($img->getRealPath())->save($path);

                $optimizerChain = OptimizerChainFactory::create();
                $optimizerChain->optimize($path);

                $s3 = Storage::disk('s3')->put($keyname . '/' . $filename, file_get_contents($path), 'public');

                if ($brand->logo_path && $brand->logo_path > 0) {
                    // Disable the previous logo
                    $upload = Upload::find($brand->logo_path);

                    if ($upload) {
                        $upload->delete();
                    }
                }

                $upload = new Upload([
                    'user_id' => Auth::user()->id,
                    'brand_id' => $brand->id,
                    'filename' => $keyname . '/' . $filename,
                    'size' => filesize($path),
                    'upload_type_id' => 2,
                ]);

                $upload->save();
                $brand->logo_path = $upload->id;
                $brand->save();

                unlink($path);
            }

            session()->flash('flash_message', 'Brand was successfully added!');

            return redirect()->route('brands.index');
        }
    }

    public function defaultRateCard($brand_id)
    {
        InvoiceRateCard::where('brand_id', $brand_id)->delete();
        $irc = new InvoiceRateCard();
        $irc->id = Uuid::uuid4();
        $irc->brand_id = $brand_id;
        $irc->bill_frequency_id = 1;
        $irc->bill_methodology_id = 2;
        $irc->term_days = 15;
        $irc->minimum = 1000;

        $levels = [];
        $levels[] = array('level' => 0, 'rate' => 0.75);
        $levels[] = array('level' => 12500, 'rate' => 0.72);
        $levels[] = array('level' => 25000, 'rate' => 0.69);
        $levels[] = array('level' => 50000, 'rate' => 0.66);
        $levels[] = array('level' => 75000, 'rate' => 0.62);

        $irc->levels = $levels;
        $irc->it_billable = 175.00;
        $irc->qa_billable = 65.00;
        $irc->cs_billable = 65.00;
        $irc->eztpv_rate = 1.25;
        $irc->hrtpv_transaction = 0;
        $irc->hrtpv_document = 0;
        $irc->address_verification_rate = 0.12;
        $irc->cell_number_verification = 0.50;
        $irc->ld_billback_intl = 0.079;
        $irc->ld_billback_dom = 0.039;
        $irc->ivr_rate = 0.70;
        $irc->ivr_trans_rate = 0.85;
        $irc->storage_rate_in_gb = 0.13;
        $irc->storage_in_gb_min = 50.00;
        $irc->contract_review = 65.00;
        $irc->late_fee = 1.50;
        $irc->api_submission = 1.00;
        $irc->server_hosting = 0.00;
        $irc->save();
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $brand = Brand::select(
            'brands.id',
            'clients.name',
            'brands.name',
            'brands.legal_name',
            'brands.address',
            'brands.city',
            'brands.state',
            'brands.zip',
            'brands.logo_path',
            'brands.active'
        )->leftJoin(
            'clients',
            'brands.client_id',
            'clients.id'
        )->where(
            'brands.id',
            $id
        )->first();

        return view('brands.show', ['brand' => $brand]);
    }

    public function feescheduleupdate(Request $request, $id)
    {
        $irc = InvoiceRateCard::where('brand_id', $id)->first();
        if (null == $irc) {
            $irc = new InvoiceRateCard();
        }

        $rules = [
            'bill_frequency_id' => 'nullable|exists:bill_frequencies,id',
            'bill_methodology_id' => 'nullable|exists:bill_methodology,id',
            'term_days' => 'nullable|integer',
            'minimum' => 'nullable|numeric',
            'eztpv_rate' => 'nullable|numeric',
            'eztpv_tm_rate' => 'nullable|numeric',
            'eztpv_contract' => 'nullable|numeric',
            'eztpv_photo' => 'nullable|numeric',
            'did_tollfree' => 'nullable|numeric',
            'did_local' => 'nullable|numeric',
            'ivr_rate' => 'nullable|numeric',
            'ivr_trans_rate' => 'nullable|numeric',
            'it_billable' => 'nullable|numeric',
            'qa_billable' => 'nullable|numeric',
            'cs_billable' => 'nullable|numeric',
            'ld_billback_dom' => 'nullable|numeric',
            'ld_billback_intl' => 'nullable|numeric',
            'contract_review' => 'nullable|numeric',
            'levels' => 'nullable|json',
            'address_verification_rate' => 'nullable|numeric',
            'cell_number_verification' => 'nullable|numeric',
            'late_fee' => 'nullable|numeric',
            'custom_report_fee' => 'nullable|numeric',
            'storage_in_gb_min' => 'nullable|numeric',
            'storage_rate_in_gb' => 'nullable|numeric',
            'sales_pitch' => 'nullable|numeric',
            'ivr_voiceprint' => 'nullable|numeric',
            'daily_questionnaire' => 'nullable|numeric',
            'gps_distance_cust_sa' => 'nullable|numeric',
            'server_hosting' => 'nullable|numeric',
        ];

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('brands.feeschedule', $id)
                ->withErrors($validator)
                ->withInput();
        } else {
            $irc->bill_frequency_id = $request->bill_frequency_id;
            $irc->bill_methodology_id = $request->bill_methodology_id;
            $irc->term_days = $request->term_days;
            $irc->minimum = $request->minimum;
            $irc->eztpv_rate = $request->eztpv_rate;
            $irc->eztpv_tm_rate = $request->eztpv_tm_rate;
            $irc->eztpv_tm_monthly = $request->eztpv_tm_monthly;
            $irc->eztpv_contract = $request->eztpv_contract;
            $irc->eztpv_photo = $request->eztpv_photo;
            $irc->eztpv_sms = $request->eztpv_sms;
            $irc->hrtpv_transaction = $request->hrtpv_transaction;
            $irc->hrtpv_document = $request->hrtpv_document;
            $irc->did_tollfree = $request->did_tollfree;
            $irc->did_local = $request->did_local;
            $irc->ivr_rate = $request->ivr_rate;
            $irc->ivr_trans_rate = $request->ivr_trans_rate;
            $irc->it_billable = $request->it_billable;
            $irc->qa_billable = $request->qa_billable;
            $irc->cs_billable = $request->cs_billable;
            $irc->ld_billback_dom = $request->ld_billback_dom;
            $irc->ld_billback_intl = $request->ld_billback_intl;
            $irc->contract_review = $request->contract_review;
            $irc->levels = json_decode($request->levels);
            $irc->address_verification_rate = $request->address_verification_rate;
            $irc->cell_number_verification = $request->cell_number_verification;
            $irc->late_fee = $request->late_fee;
            $irc->custom_report_fee = $request->custom_report_fee;
            $irc->storage_in_gb_min = $request->storage_in_gb_min;
            $irc->storage_rate_in_gb = $request->storage_rate_in_gb;
            $irc->digital_transaction = $request->digital_transaction;
            $irc->api_submission = $request->api_submission;
            $irc->http_post = $request->http_post;
            $irc->web_enroll_submission = $request->web_enroll_submission;
            $irc->pay_submission = $request->pay_submission;
            $irc->live_flat_rate = $request->live_flat_rate;
            $irc->esiid_lookup = $request->esiid_lookup;
            $irc->supplemental_invoice = $request->supplemental_invoice;
            $irc->sales_pitch = $request->sales_pitch;
            $irc->ivr_voiceprint = $request->ivr_voiceprint;
            $irc->daily_questionnaire = $request->daily_questionnaire;
            $irc->gps_distance_cust_sa = $request->gps_distance_cust_sa;
            $irc->server_hosting = $request->server_hosting;
            $irc->save();

            session()->flash('flash_message', 'Fee Schedule was successfully updated!');

            return redirect()->route('brands.feeschedule', $id);
        }
    }

    public function taskqueues($id)
    {
        $brand = Brand::select('id', 'name', 'logo_path', 'active')
            ->whereNotNull('client_id')
            ->where('id', $id)
            ->first();
        $taskqueues = BrandTaskQueue::where('brand_id', $id)->get();

        return view('brands.taskqueues', [
            'brand' => $brand,
            'taskqueues' => $taskqueues,
        ]);
    }

    public function feeschedule($id)
    {
        $bf = BillFrequency::orderBy('frequency')->get();
        $bm = BillMethodology::orderBy('methodology')->get();
        $brand = Brand::select(
            'brands.id',
            'brands.client_id',
            'brands.name',
            'brands.address',
            'brands.city',
            'brands.state',
            'brands.zip',
            'brands.logo_path',
            'brands.service_number',
            'brands.active',
            'brands.allow_bg_checks',
            'uploads.filename'
        )->leftJoin(
            'uploads',
            'brands.logo_path',
            'uploads.id'
        )->where(
            'brands.id',
            $id
        )->first();

        $invoice_rate_card = InvoiceRateCard::where('brand_id', $id)->first();
        if (!$invoice_rate_card) {
            $this->defaultRateCard($id);
            $invoice_rate_card = InvoiceRateCard::where('brand_id', $id)->first();
        }

        return view(
            'brands.feeschedule',
            [
                'bill_frequencies' => $bf,
                'bill_methodologies' => $bm,
                'brand' => $brand,
                'invoice_rate_card' => $invoice_rate_card,
            ]
        );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $states = State::select('id', 'name', 'state_abbrev')->orderBy('name', 'asc')->get();
        $clients = Client::select('id', 'name')->orderBy('name')->get();
        $brand = Brand::select(
            'brands.id',
            'brands.client_id',
            'brands.name',
            'brands.legal_name',
            'brands.address',
            'brands.city',
            'brands.state',
            'brands.zip',
            'brands.logo_path',
            'brands.service_number',
            'brands.active',
            'brands.billing_distribution',
            'brands.accounts_payable_distribution',
            'brands.allow_bg_checks',
            'brands.purchase_order_no',
            'brands.po_valid_until',
            'brands.notes',
            'brands.billing_enabled',
            'brands.billing_frequency',
            'uploads.filename'
        )->leftJoin(
            'uploads',
            'brands.logo_path',
            'uploads.id'
        )->where(
            'brands.id',
            $id
        )->first()
            ->makeVisible([
                'billing_distribution',
                'accounts_payable_distribution',
            ]);
        if ($brand->service_number) {
            $brand->service_number = ltrim(
                preg_replace('/[^A-Za-z0-9]/', '', $brand->service_number),
                '+1'
            );
        }
        return view(
            'brands.edit',
            [
                'brand' => $brand,
                'states' => $states,
                'clients' => $clients,
            ]
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Brand $brand)
    {
        $rules = array(
            'client_id' => 'required',
            'name' => [
                'required',
                Rule::unique('brands')->ignore($brand->name, 'name'),
            ],
            'billing_distribution' => 'required_without:accounts_payable_distribution',
            'accounts_payable_distribution' => 'required_without:billing_distribution',
            'billing_frequency' => 'required|in:monthly,bi-weekly,weekly'
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('brands.edit', $brand->id)
                ->withErrors($validator)
                ->withInput();
        } else {
            $brand->client_id = $request->client_id;
            $brand->name = $request->name;
            $brand->legal_name = $request->legal_name;
            $brand->address = $request->address;
            $brand->city = $request->city;
            $brand->state = $request->state;
            $brand->zip = $request->zip;

            $brand->billing_distribution = trim($request->billing_distribution);

            $brand->accounts_payable_distribution = trim($request->accounts_payable_distribution);

            if ($request->purchase_order_no) {
                $po = trim($request->purchase_order_no);
                if (strlen($po) > 0) {
                    $brand->purchase_order_no = $request->purchase_order_no;
                } else {
                    $brand->purchase_order_no = null;
                }
            } else {
                $brand->purchase_order_no = null;
            }
            if ($request->po_valid_until) {
                $brand->po_valid_until = $request->po_valid_until;
            }

            $brand->notes = $request->notes ? $request->notes : '';

            if ($request->service_number) {
                $brand->service_number = '+1' . preg_replace('/[^A-Za-z0-9]/', '', $request->service_number);
            }

            if ($request->allow_bg_checks) {
                $brand->allow_bg_checks = 1;
            } else {
                $brand->allow_bg_checks = 0;
            }

            $brand->billing_frequency = $request->billing_frequency;

            if ($request->has('billing_enabled')) {
                if ($request->billing_enabled === 'on') {
                    $brand->billing_enabled = true;
                } else {
                    $brand->billing_enabled = false;
                }
            } else {
                $brand->billing_enabled = false;
            }

            $brand->active = 1;
            $brand->save();

            if ($request->file('logo_upload')) {
                $img = $request->file('logo_upload');
                $ext = strtolower($img->getClientOriginalExtension());
                $keyname = 'uploads/brands/' . $brand->id . '/logos/' . date('Y') . '/' . date('m') . '/' . date('d');
                $filename = md5($img->getRealPath()) . '.' . $ext;
                $path = public_path('tmp/' . $filename);
                Image::make($img->getRealPath())->resize(300, 300)->save($path);

                $optimizerChain = OptimizerChainFactory::create();
                $optimizerChain->optimize($path);

                $s3 = Storage::disk('s3')->put($keyname . '/' . $filename, file_get_contents($path), 'public');

                if ($brand->logo_path && $brand->logo_path > 0) {
                    // Disable the previous logo
                    $upload = Upload::find($brand->logo_path);

                    if ($upload) {
                        $upload->delete();
                    }
                }

                $upload = new Upload([
                    'user_id' => Auth::user()->id,
                    'brand_id' => $brand->id,
                    'filename' => $keyname . '/' . $filename,
                    'size' => filesize($path),
                    'upload_type_id' => 2,
                ]);

                $upload->save();
                $brand->logo_path = $upload->id;
                $brand->save();

                unlink($path);
            }

            session()->flash('flash_message', 'Brand was successfully edited!');

            return redirect()->route('brands.edit', $brand->id);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $brand = Brand::find($id)->first();
        $brand->delete();

        session()->flash('flash_message', 'Brand was successfully deleted!');

        return back();
    }

    public function search(Request $request)
    {
        $search = trim($request->search);

        $brands = Brand::select('id', 'name', 'active')
            ->search($search)
            ->orderBy('name', 'asc')
            ->paginate(30);

        return view('brands.brands', ['brands' => $brands, 'search' => $search]);
    }

    public function login(Request $request)
    {
        $user = Auth::user();

        User::disableAuditing();

        info('USER is ' . json_encode($user));

        if (isset($user->client_login) && strlen(trim($user->client_login)) > 0) {
            $client_user = User::where('id', $user->client_login)->first();

            // Add token
            $unique_id = bin2hex(random_bytes(7));
            $client_user->staff_token = $unique_id;
            $client_user->save();

            $url = config('app.urls.clients') . '/staff?login=' .
                $user->client_login . '&token=' .
                $unique_id . '&brand_id=' . $request->brand;

            if ($request->vendor) {
                $url .= '&vendor=true';
            }

            User::enableAuditing();

            return redirect($url);
        } else {
            info('Staff Login... in the else...');
        }
    }

    public function bgchk($id)
    {
        $brand = Brand::find($id);
        $creds = BgchkCredential::select(
            'bgchk_credentials.id',
            'bgchk_credentials.bgchk_provider_id',
            'p.provider',
            'bgchk_credentials.details',
            'bgchk_credentials.package'
        )
            ->where('bgchk_credentials.brand_id', $id)
            ->leftJoin(
                'bgchk_providers as p',
                'bgchk_credentials.bgchk_provider_id',
                'p.id'
            )
            ->first();

        $providers = BgchkProvider::get();

        return view(
            'brands.bgchk',
            [
                'brand' => $brand,
                'providers' => $providers,
                'creds' => $creds,
            ]
        );
    }

    public function storeBgchk(Request $request, $id)
    {
        $rules = array(
            'bgchk_provider_id' => 'required',
            'details' => 'required',
            'package' => 'required',
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('brands.bgchk',$id)
                ->withErrors($validator);
        } else {
            $creds = BgchkCredential::where('brand_id', $id)->first();
            if (is_null($creds)) {
                $cred = new BgchkCredential();
                $cred->brand_id = $id;
                $cred->bgchk_provider_id = $request->bgchk_provider_id;
                $cred->details = $request->details;
                $cred->package = $request->package;
                $cred->save();
            } else {
                $creds->bgchk_provider_id = $request->bgchk_provider_id;
                $creds->details = $request->details;
                $creds->package = $request->package;
                $creds->save();
            }
            session()->flash('flash_message', 'Background check information saved!');

            return redirect()->route('brands.bgchk', $id);
        }
    }

    public function vendors(Brand $brand)
    {
        return view(
            'brands.vendors',
            [
                'brand' => $brand,
            ]
        );
    }

    public function list_vendors(Request $request, Brand $brand)
    {
        $vendors = Vendor::select(
            'vendors.id',
            'vendors.vendor_label',
            'brands.name',
            'vendors.deleted_at',
            'vendors.grp_id'
        )->join(
            'brands',
            'vendors.vendor_id',
            'brands.id'
        )->where(
            'vendors.brand_id',
            $brand->id
        )->whereNull(
            'brands.client_id'
        )->withTrashed();

        if (null != $request->get('search')) {
            $vendors = $vendors->search($request->get('search'));
        }

        $vendors = $vendors->orderBy('brands.name')->paginate(25);

        return response()->json($vendors);
    }

    public function createVendor(Brand $brand)
    {
        $vendors = Brand::select(
            'brands.id',
            'brands.name',
            'brands.address',
            'brands.city',
            'states.state_abbrev',
            'brands.zip',
            'brands.logo_path',
            'brands.active'
        )->leftJoin(
            'states',
            'brands.state',
            'states.id'
        )->whereNull(
            'client_id'
        )->orderBy(
            'name'
        )->get();

        $states = State::select('id', 'name', 'state_abbrev')
            ->orderBy('name', 'asc')
            ->get();

        return view(
            'brands.createVendor',
            [
                'brand' => $brand,
                'states' => $states,
                'vendors' => $vendors,
            ]
        );
    }

    public function storeVendor(Request $request, Brand $brand)
    {
        $exists = Vendor::select('id')
            ->where('brand_id', $brand->id)
            ->where('vendor_id', $request->vendor)
            ->exists();
        if ($exists) {
            session()->flash('flash_message', 'Vendor already exists.');

            return redirect()->route('brands.vendors', $brand->id);
        } else {
            if ($request->vendor) {
                $vendor = new Vendor();
                $vendor->brand_id = $brand->id;
                $vendor->vendor_id = $request->vendor;
                $vendor->vendor_label = $request->vendor_label_add;

                if ($request->vendor_id_add) {
                    $vendor->grp_id = $request->vendor_id_add;
                }

                $vendor->save();

                session()->flash('flash_message', 'Vendor was successfully added!');

                return redirect()->route('brands.vendors', $brand->id);
            } else {
                $rules = array(
                    'name' => 'required',
                    'vendor_label' => 'required',
                );

                $validator = Validator::make(request()->all(), $rules);

                if ($validator->fails()) {
                    return redirect()->route('brands.createVendor', $brand)
                        ->withErrors($validator)
                        ->withInput();
                } else {
                    $newBrand = new Brand();
                    $newBrand->name = $request->name;
                    $newBrand->address = $request->address;
                    $newBrand->city = $request->city;
                    $newBrand->state = $request->state;
                    $newBrand->zip = $request->zip;

                    if ($request->service_number) {
                        $newBrand->service_number = '+1' . preg_replace(
                            '/[^A-Za-z0-9]/',
                            '',
                            $request->service_number
                        );
                    }

                    $newBrand->active = 1;
                    $newBrand->save();

                    $vendor = new Vendor();
                    $vendor->brand_id = $brand->id;
                    $vendor->vendor_id = $newBrand->id;
                    $vendor->vendor_label = $request->vendor_label;
                    $vendor->vendor_code = $request->vendor_code;
                    $vendor->grp_id = $request->vendor_id;
                    $vendor->save();

                    session()->flash('flash_message', 'Vendor successfully added!');

                    return redirect()->route('brands.vendors', $brand->id);
                }
            }
        }
    }

    public function editVendor(Brand $brand, $vendor_id)
    {
        $states = State::select(
            'id',
            'name',
            'state_abbrev'
        )->orderBy(
            'name'
        )->get();

        $this_vendor = Vendor::select(
            'vendors.id',
            'vendors.http_post',
            'vendors.http_post_username',
            'vendors.hrtpv',
            'vendors.live_enroll_enabled',
            'vendors.active_customer_check_enabled',
            'brands.name',
            'vendors.vendor_label',
            'vendors.vendor_code',
            'brands.address',
            'brands.city',
            'brands.state',
            'brands.zip',
            'vendors.grp_id',
            'brands.service_number',
            'vendors.deleted_at'
        )->join(
            'brands',
            'vendors.vendor_id',
            'brands.id'
        )->where(
            'vendors.id',
            $vendor_id
        )->withTrashed()->first();
        if ($this_vendor && $this_vendor->service_number) {
            $this_vendor->service_number = str_replace(
                '+1',
                '',
                $this_vendor->service_number
            );
        }

        return view(
            'brands.editVendor',
            [
                'brand' => $brand,
                'vendor' => $this_vendor,
                'states' => $states,
            ]
        );
    }

    public function updateVendor(Request $request, Brand $brand, Vendor $vendor)
    {
         $rules = array(
            'name' => 'required',
            'vendor_label' => 'required',
        );

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('brands.editVendor', [$brand, $vendor])
                ->withErrors($validator)
                ->withInput();
        } else {
            $this_brand = Brand::find($vendor->vendor_id);
            $this_brand->name = $request->name;
            $this_brand->address = $request->address;
            $this_brand->city = $request->city;
            $this_brand->state = $request->state;
            $this_brand->zip = $request->zip;

            if ($request->service_number) {
                $this_brand->service_number = '+1' . preg_replace(
                    '/[^A-Za-z0-9]/',
                    '',
                    $request->service_number
                );
            }

            $this_brand->save();

            $this_vendor = Vendor::find($vendor->id);
            $this_vendor->brand_id = $brand->id;
            $this_vendor->vendor_id = $this_brand->id;
            $this_vendor->vendor_label = $request->vendor_label;
            $this_vendor->vendor_code = $request->vendor_code;
            $this_vendor->grp_id = $request->vendor_id;
            $this_vendor->hrtpv = ($request->hrtpv && 'on' == $request->hrtpv)
                ? 1 : 0;

            if ($request->http_post && 'on' == $request->http_post && 0 == $this_vendor->http_post) {
                $password = str_random(16);

                if ($this_vendor->http_post_username) {
                    $username = $this_vendor->http_post_username;

                    $user = User::where(
                        'username',
                        $username
                    )->first();
                    $user->password = bcrypt($password);
                    $user->save();
                } else {
                    $username = 'http_post_' . str_random(16);

                    $user = new User();
                    $user->created_at = Carbon::now();
                    $user->updated_at = Carbon::now();
                    $user->first_name = 'HTTP';
                    $user->last_name = 'Post';
                    $user->username = $username;
                    $user->password = bcrypt($password);
                    $user->password_change_required = 0;
                    $user->save();

                    $brand_user = new BrandUser();
                    $brand_user->employee_of_id = $vendor->vendor_id;
                    $brand_user->works_for_id = $vendor->brand_id;
                    $brand_user->user_id = $user->id;
                    $brand_user->role_id = 3;
                    $brand_user->save();
                }

                $this_vendor->http_post_username = $username;

                session()->flash('http_post_password', $password);
            }

            $this_vendor->http_post = ($request->http_post && 'on' == $request->http_post)
                ? 1 : 0;

            $this_vendor->live_enroll_enabled = ($request->live_enroll_enabled && 'on' == $request->live_enroll_enabled)
                ? 1 : 0;

            $this_vendor->active_customer_check_enabled = ($request->active_customer_check_enabled && 'on' == $request->active_customer_check_enabled)
                ? 1 : 0;
                
            $this_vendor->save();

            session()->flash('flash_message', 'Vendor was successfully edited!');

            return redirect()->route(
                'brands.editVendor',
                [
                    $brand->id,
                    $vendor->id,
                ]
            );
        }
    }

    public function permDestroyVendor(Brand $brand, $id)
    {
        $vendor = Vendor::where('id', $id)->withTrashed()->first();
        if ($vendor) {
            $vendor->forceDelete();
            session()->flash('flash_message', 'Vendor was successfully deleted!');
        }

        return redirect()->route('brands.vendors', $brand->id);
    }

    public function destroyVendor(Brand $brand, $id)
    {
        $vendor = Vendor::where('id', $id)->first();
        $vendor->delete();

        session()->flash('flash_message', 'Vendor was successfully disabled!');

        return redirect()->route('brands.vendors', $brand->id);
    }

    public function enableVendor(Brand $brand, $id)
    {
        $vendor = Vendor::where('id', $id)->withTrashed()->first();
        $vendor->restore();

        session()->flash('flash_message', 'Vendor was successfully enabled!');

        return redirect()->route('brands.editVendor', [$brand->id, $vendor->id]);
    }

    public function updateEnrollmentFile(Request $request, $brand_id)
    {
        $bef = BrandEnrollmentFile::where('brand_id', $brand_id)->first();
        if (!$bef) {
            $bef = new BrandEnrollmentFile();
            $bef->brand_id = $brand_id;
            $bef->file_format_id = 1;
        }

        $bef->report_fields = trim($request->report_fields);
        $bef->delivery_data = trim($request->delivery_data);
        $bef->save();

        return redirect()->route('brands.enrollments', $brand_id);
    }

    public function createEnrollment(Request $request, $brand_id)
    {
        $params = [
            '--force' => true,
            '--brand' => $brand_id,
            '--date' => $request->input('date'),
        ];

        if (isset($request->noalert) && 1 == $request->noalert) {
            $params['--noalert'] = true;
        }

        Artisan::queue(
            'create:enrollment',
            $params
        );

        return redirect()->route('brands.enrollments', $brand_id);
    }

    public function enrollments(Request $request, $brand_id)
    {
        $brand = Brand::find($brand_id);

        $bef = BrandEnrollmentFile::leftJoin(
            'file_formats',
            'brand_enrollment_files.file_format_id',
            'file_formats.id'
        )->where(
            'brand_enrollment_files.brand_id',
            $brand_id
        )->first();
        if (!$bef) {
            $brandEnrollmentFile = array(
                array(
                    'id' => Uuid::uuid4(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'brand_id' => $brand_id,
                    'report_fields' => '[{"field":"event_created_at","type":"date","format":"m/d/Y","required":true,"header":"Created Date"},{"field":"eztpv_initiated","header":"EZTPV"},{"field":"language","header":"Language"},{"field":"channel","header":"Channel"},{"field":"confirmation_code","header":"Confirmation Code"},{"field":"result","header":"Result"},{"field":"interaction_type","header":"Interaction Type"},{"field":"disposition_label","header":"Disposition ID"},{"field":"disposition_reason","header":"Disposition"},{"field":"source","header":"Source"},{"field":"brand_name","header":"Brand"},{"field":"vendor_label","header":"Vendor Label"},{"field":"vendor_name","header":"Vendor"},{"field":"office_label","header":"Office Label"},{"field":"office_name","header":"Office Name"},{"field":"market","header":"Market"},{"field":"commodity","header":"Commodity"},{"field":"utility_commodity_ldc_code","header":"Utility Commodity LDC Code"},{"field":"utility_commodity_external_id","header":"Utility Commodity External ID"},{"field":"sales_agent_name","header":"Sales Agent Name"},{"field":"sales_agent_rep_id","header":"Sales Agent Rep ID"},{"field":"tpv_agent_name","header":"TPV Agent Name"},{"field":"dnis","header":"DNIS"},{"field":"structure_type","header":"Structure Type"},{"field":"company_name","header":"Company"},{"field":"bill_first_name","header":"Billing First Name"},{"field":"bill_middle_name","header":"Billing Middle Name"},{"field":"bill_last_name","header":"Billing Last Name"},{"field":"auth_first_name","header":"Auth First Name"},{"field":"auth_middle_name","header":"Auth Middle Name"},{"field":"auth_last_name","header":"Auth Last Name"},{"field":"auth_relationship","header":"Auth Relationship"},{"field":"btn","header":"Billing Telephone Number"},{"field":"email_address","header":"Email Address"},{"field":"billing_address1","header":"Billing Address"},{"field":"billing_address2","header":"Billing Address 2"},{"field":"billing_city","header":"Billing City"},{"field":"billing_state","header":"Billing State"},{"field":"billing_zip","header":"Billing Zip"},{"field":"billing_county","header":"Billing County"},{"field":"billing_country","header":"Billing Country"},{"field":"service_address1","header":"Service Address"},{"field":"service_address2","header":"Service Address 2"},{"field":"service_city","header":"Service City"},{"field":"service_state","header":"Service State"},{"field":"service_zip","header":"Service Zip"},{"field":"service_county","header":"Service County"},{"field":"service_country","header":"Service Country"},{"field":"rate_program_code","header":"Rate Program Code"},{"field":"rate_uom","header":"Rate UOM"},{"field":"rate_source_code","header":"Rate Source Code"},{"field":"rate_promo_code","header":"Rate Promo Code"},{"field":"rate_external_id","header":"Rate External ID"},{"field":"rate_renewal_plan","header":"Rate Renewal Plan"},{"field":"rate_channel_source","header":"Rate Channel Source"},{"field":"product_name","header":"Product Name"},{"field":"product_rate_type","header":"Product Rate Type"},{"field":"external_rate_id","header":"External Rate ID"},{"field":"product_term","header":"Product Term"},{"field":"product_term_type","header":"Product Term Type"},{"field":"product_intro_term","header":"Product Intro Term"},{"field":"product_daily_fee","header":"Product Daily Fee"},{"field":"product_service_fee","header":"Product Service Fee"},{"field":"product_rate_amount","header":"Product Rate Amount"},{"field":"product_rate_amount_currency","header":"Product Rate Amount Currency"},{"field":"product_green_percentage","header":"Product Green Percentage"},{"field":"product_cancellation_fee","header":"Product Cancellation Fee"},{"field":"product_admin_fee","header":"Product Admin Fee"},{"field":"transaction_fee","header":"Product Transaction Fee"},{"field":"product_utility_name","header":"Product Utility Name"},{"field":"product_utility_external_id","header":"Product Utility External ID"},{"field":"account_number1","header":"Account Number 1"},{"field":"account_number2","header":"Account Number 2"},{"field":"name_key","header":"Name Key"},{"field":"pass_fail","header":"Pass/Fail"},{"field":"recording","header":"Recording"},{"field":"contracts","header":"Contracts"},{"field":"passfail_reason","header":"Fail Reason"},{"field":"interaction_time","header":"Interaction Time"},{"field":"product_time","header":"Product Time"},{"field":"product_monthly_fee","header":"Product Monthly Fee"}]',
                    'file_format_id' => 1,
                ),
            );

            BrandEnrollmentFile::insert($brandEnrollmentFile);
        } else {
            $bef->run_history = json_decode($bef->run_history, true);
        }

        $logs = [];
        if (isset($bef->run_history) && is_array($bef->run_history)) {
            foreach ($bef->run_history as $key => $value) {
                if (is_array($value) && count($value) > 0) {
                    $logs[] = $value[0];
                } else {
                    if (is_string($value)) {
                        $logs[] = $value;
                    } else {
                        $logs[] = json_encode($value);
                    }
                }
            }
        }

        $logs = array_reverse($logs);
        $logs = array_splice($logs, 0, 10);

        $bef = BrandEnrollmentFile::leftJoin(
            'file_formats',
            'brand_enrollment_files.file_format_id',
            'file_formats.id'
        )->where(
            'brand_enrollment_files.brand_id',
            $brand_id
        )->first();
        if ($bef && $bef->delivery_data) {
            $bef->delivery_data = json_decode($bef->delivery_data);
        }

        $lefs = LogEnrollmentFile::select(
            'log_enrollment_files.start_date',
            'log_enrollment_files.end_date',
            'log_enrollment_files.products',
            'uploads.filename'
        )->leftJoin(
            'uploads',
            'log_enrollment_files.upload_id',
            'uploads.id'
        )->where(
            'log_enrollment_files.brand_id',
            $brand_id
        )->whereNotNull(
            'uploads.filename'
        )->whereNotNull(
            'log_enrollment_files.start_date'
        )->whereNotNull(
            'log_enrollment_files.end_date'
        )->whereNull(
            'log_enrollment_files.deleted_at'
        )->orderBy(
            'start_date',
            'desc'
        )->paginate(30);

        $fields = [
            'account_number1',
            'account_number2',
            'auth_first_name',
            'auth_last_name',
            'auth_middle_name',
            'auth_relationship',
            'bill_first_name',
            'bill_last_name',
            'bill_middle_name',
            'billing_address1',
            'billing_address2',
            'billing_city',
            'billing_country',
            'billing_county',
            'billing_state',
            'billing_zip',
            'brand_name',
            'btn',
            'channel',
            'commodity',
            'company_name',
            'confirmation_code',
            'contracts',
            'disposition_label',
            'disposition_reason',
            'dnis',
            'email_address',
            'event_created_at',
            'external_rate_id',
            'eztpv_initiated',
            'passfail_reason',
            'interaction_time',
            'interaction_type',
            'language',
            'market',
            'name_key',
            'office_label',
            'office_name',
            'pass_fail',
            'product_admin_fee',
            'product_cancellation_fee',
            'product_daily_fee',
            'product_green_percentage',
            'product_intro_term',
            'product_monthly_fee',
            'product_name',
            'product_rate_amount',
            'product_rate_amount_currency',
            'product_rate_type',
            'product_service_fee',
            'product_term',
            'product_term_type',
            'product_time',
            'product_utility_external_id',
            'product_utility_name',
            'rate_channel_source',
            'rate_external_id',
            'rate_program_code',
            'rate_promo_code',
            'rate_renewal_plan',
            'rate_source_code',
            'rate_uom',
            'recording',
            'result',
            'sales_agent_name',
            'sales_agent_rep_id',
            'service_address1',
            'service_address2',
            'service_city',
            'service_country',
            'service_county',
            'service_state',
            'service_zip',
            'source',
            'structure_type',
            'tpv_agent_name',
            'utility_commodity_external_id',
            'utility_commodity_ldc_code',
            'vendor_label',
            'vendor_name',
        ];

        return view(
            'brands.enrollments',
            [
                'bef' => $bef,
                'brand' => $brand,
                'fields' => $fields,
                'lefs' => $lefs,
                'logs' => $logs,
            ]
        );
    }

    public function recordings($brand_id)
    {
        $brand = Brand::find($brand_id);

        return view(
            'brands.recordings',
            [
                'brand' => $brand,
            ]
        );
    }

    public function updateRecordings(Request $request, $brand_id)
    {
        $brand = Brand::find($brand_id);
        if ($brand) {
            $brand->recording_transfer = ('on' == $request->recording_transfer)
                ? 1 : 0;
            $brand->recording_transfer_config = trim($request->recording_transfer_config);
            $brand->recording_transfer_type = $request->recording_transfer_type;
            $brand->save();
        }

        return redirect()->route('brands.recordings', $brand_id);
    }

    public function show_dispo_shortcuts(Request $request, Brand $brand)
    {
        $dispos = Disposition::where('brand_id', $brand->id)->with('category')->get();
        $shortcuts = $dispos->where('is_shortcut', true)->values();
        $dispos = $dispos->where('is_shortcut', false)->values();

        return view('brands.dispo-shortcuts')->with(
            [
                'dispositions' => $dispos,
                'shortcuts' => $shortcuts,
                'brand' => $brand,
            ]
        );
    }

    public function save_dispo_shortcuts(Request $request, Brand $brand)
    {
        $action = 'remove' !== $request->input('action');

        $disp_raw = $request->input('disposition');
        $disposition = Disposition::find($disp_raw);
        if (null === $disposition) {
            abort(400);
        }
        if ($disposition->brand_id !== $brand->id) {
            abort(401);
        }

        $disposition->is_shortcut = $action;
        $disposition->save();

        if ($request->ajax()) {
            return [
                'message' => 'The shortcut was successfully removed.',
                'disposition' => $disposition,
            ];
        }

        return redirect('/brands/' . $brand->id . '/disposition-shortcuts');
    }

    public function getBrands()
    {
        if (request()->input('active') !== null) {
            $brands = Brand::select(
                'brands.name',
                'brands.id',
                'brands.client_id',
                'brands.billing_enabled',
                'brands.billing_frequency'
            )->where('active', 1)
                ->whereNotNull('client_id')
                ->orderBy(
                    'name',
                    'asc'
                );
        } else {
            $brands = Brand::select(
                'brands.id',
                'brands.name',
                'brands.logo_path',
                'brands.active',
                'brands.billing_enabled',
                'uploads.filename'
            )->leftJoin(
                'uploads',
                'brands.logo_path',
                'uploads.id'
            )->orderBy(
                'name',
                'asc'
            )->whereNotNull('brands.client_id');
        }

        return $brands->get();
    }

    public function getVendors()
    {
        return Cache::remember(
            'brand_users_getVendors',
            3600,
            function () {
                return Vendor::select('brands.id', 'brands.name', 'vendors.brand_id', 'vendors.vendor_id')
                    ->join('brands', 'brands.id', 'vendors.vendor_id')
                    ->whereNull('vendors.deleted_at')
                    ->whereNull('brands.deleted_at')
                    ->orderBy('brands.name')
                    ->get();
            }
        );
    }
}
