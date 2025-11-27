<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Traits\CSVResponseTrait;
use App\Models\InvoiceableType;
use App\Models\Invoiceable;
use App\Models\InvoiceTracking;
use App\Models\InvoiceRateCard;
use App\Models\InvoiceItem;
use App\Models\InvoiceDesc;
use App\Models\InvoiceAdditional;
use App\Models\Invoice;
use App\Models\Brand;

class BillingController extends Controller
{
    use CSVResponseTrait;

    public function invoice_create()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'create-invoices-index',
                'title' => 'Create Invoices',
            ]
        );
    }

    public function index()
    {
        $invoicableBrands = Brand::select('id', 'name')->whereNotNull('client_id')->orderBy('name', 'ASC')->get();

        return view('generic-vue')->with(
            [
                'componentName' => 'invoices-index',
                'title' => 'Invoices',
                'parameters' => [
                    'invoiceable-brands' => json_encode($invoicableBrands),
                    'create-url' => json_encode(route('billing.invoice_create')),
                ],
            ]
        );
    }

    public function billingRun(Request $request)
    {
        if (!$request->invoice_start_date || !$request->invoice_end_date || !isset($request->brands)) {
            session()->flash(
                'flash_message',
                'All fields are required to run invoicing.'
            );

            return redirect()->back();
        } else {
            foreach ($request->brands as $brand) {
                Artisan::call(
                    'generate:invoices',
                    [
                        '--brand' => [$brand],
                        '--dateRangeStart' => $request->invoice_start_date,
                        '--dateRangeEnd' => $request->invoice_end_date,
                    ]
                );
            }
        }

        return redirect()->route('billing.index');
    }

    public static function CreateInvoiceable(string $brand_id, string $resource, int $qty = 1, string $itemId = '', $eventId = null, $notes = null): bool
    {
        $itype = InvoiceableType::where('resource', $resource)->first();
        if (null === $itype) {
            Log::info('Invoiceable Resource Type (' . $resource . ') does not exist.');

            return false;
        }
        $innerItemId = null;
        if ($itemId !== '') {
            $innerItemId = $itemId;
        }
        if (!empty($notes)) {
            $callingFuncOrNotes = $notes;
        } else {
            $backtrace = debug_backtrace(0, 3); // limit the number of stack frames returned to minimize memory/time
            if (count($backtrace) > 1 && !empty($backtrace[1]['function'])) {
                if (!empty($backtrace[1]['class'])) {
                    $callingFuncOrNotes = $backtrace[1]['class'] . '@' . $backtrace[1]['function'];
                } else {
                    $callingFuncOrNotes = $backtrace[1]['function'];
                }
                if (count($backtrace) > 2 && !empty($backtrace[2]['function'])) {
                    if (!empty($backtrace[2]['class'])) {
                        $callingFuncOrNotes = $backtrace[2]['class'] . '@' . $backtrace[2]['function'] . ' -> ' . $callingFuncOrNotes;
                    } else {
                        $callingFuncOrNotes = $backtrace[2]['function'] . ' -> ' . $callingFuncOrNotes;
                    }
                }
            } else {
                $callingFuncOrNotes = 'unable to determine calling function and no notes specified';
            }
        }

        try {
            $inv = Invoiceable::where('brand_id', $brand_id)
                ->where('invoiceable_type_id', $itype->id)
                ->where('quantity', $qty)
                ->where('event_id', $eventId)
                ->where('invoiceable_item_id', $innerItemId)
                ->first();

            if (empty($inv)) {
                $inv = new Invoiceable(
                    [
                        'brand_id' => $brand_id,
                        'invoiceable_type_id' => $itype->id,
                        'quantity' => $qty,
                        'event_id' => $eventId,
                    ]
                );
                $inv->notes = $callingFuncOrNotes;
                $inv->invoiceable_item_id = $innerItemId;

                $inv->save();
                Log::info('Created invoiceable for ' . $innerItemId);
            } else {
                Log::info('Did not create duplicate invoiceable for ' . $innerItemId);
            }
        } catch (Exception $e) {
            Log::info('Could not create Invoiceable for Brand ' . $brand_id . ' #' . $qty . ' resource: (' . $resource . ')');
            Log::error($e);

            return false;
        }

        return true;
    }

    public function invoiceRegenerate(Request $request)
    {
        if ($request->invoice_id) {
            $inv = Invoice::find($request->invoice_id);
            if ($inv) {
                Artisan::call(
                    'generate:invoices',
                    [
                        '--brand' => [$inv->brand_id],
                        '--dateRangeStart' => $inv->invoice_start_date->format('Y-m-d'),
                        '--dateRangeEnd' => $inv->invoice_end_date->format('Y-m-d'),
                        '--force' => true,
                    ]
                );
            }

            return redirect()->route('billing.invoice', $request->invoice_id);
        }

        return redirect()->back();
    }

    public function getInvoices(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $search = $request->get('search');
        $limit = $request->get('daterange');

        // 2023-03-18-13538
        $timePeriods = [
            '1' => ['method' => 'subDays', 'value' => 7],
            '2' => ['method' => 'subDays', 'value' => 31],
            '3' => ['method' => 'subMonths', 'value' => 6],
            '4' => ['method' => 'subYears', 'value' => 1],
            '5' => ['method' => 'subYears', 'value' => 100],
        ];

        $period = $timePeriods[$limit] ?? $timePeriods['2']; // default to 31 days

        try {
            $invoices = Invoice::with([
                    'statuses',
                    'brand' => function ($query) {
                        $query->withTrashed()->select('id', 'name AS brand_name', 'client_id');
                    },
                    'brand.client' => function ($query) {
                        $query->withTrashed()->select('id', 'name AS client_name');
                    },
                ])
                ->withCount(['items as total' => function ($query) {
                    $query->select(DB::raw('sum(total)'));
                }])
                ->when($search, function ($query, $search) {
                    return $query->where('brand_id', $search);
                })
                ->when($column, function ($query, $column) use ($direction) {
                    if ($column === 'invoice_statuses.status' || $column === 'brands.name' || $column === 'clients.name') {
                        $query->join('invoice_statuses', 'invoice_statuses.invoices_id', '=', 'invoices.id')
                            ->join('brands', 'brands.id', '=', 'invoices.brand_id')
                            ->join('clients', 'clients.id', '=', 'brands.client_id');
                    }
                    return $query->orderBy($column, $direction ?? 'desc');
                })
                ->when($direction, function ($query, $direction) use ($column) {
                    return $query->orderBy($column ?? 'invoices.created_at', $direction);
                })
                ->when(!$column && !$direction, function ($query) {
                    return $query->orderBy('invoices.created_at', 'desc');
                })
                ->where('invoices.created_at', '>=', Carbon::now()->{$period['method']}($period['value']))
                ->paginate(30);
        } catch (Exception $e) {
            Log::error('An error occurred while fetching invoices:' .
            'Error message: ' . $e->getMessage() .
            'At file: ' . $e->getFile() . ' on line ' . $e->getLine() .
            'At: ' . Carbon::now() .
            'Stack trace: ' . $e->getTraceAsString());
        }

        $invoices->getCollection()->map(function ($i) {
            if ($i->read) {
                $obj_arr = json_decode($i->read, true);
                foreach ($obj_arr as &$oa) {
                    //dd(date('Y-m-d H:i:s', $oa['opened']));
                    $oa = [
                        'opened' => (Carbon::createFromTimestamp($oa['opened']))->format('Y-m-d H:i:s'),
                        'ip' => long2ip(intval($oa['ip'])),
                    ];
                }
                $i->read = $obj_arr;
            }
            return $i;
        });

        return $invoices;
    }

    public function invoice_add_item(Request $request, $id)
    {
        $rules = array(
            'item_desc' => 'required|exists:invoice_desc,id',
            'item_price' => 'required|numeric',
            'item_quantity' => 'required|numeric|min:0',
            'item_desc_note' => 'nullable|string',
        );

        $validator = Validator::make($request->all(), $rules);
        $invoice = Invoice::where('id', $id)->first();
        if ($validator->fails() || null == $invoice) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        } else {
            $ii = new InvoiceItem();
            $ii->invoice_id = $id;
            $ii->invoice_desc_id = $request->item_desc;
            $ii->quantity = $request->item_quantity;
            $ii->rate = $request->item_price;
            $ii->total = $request->item_quantity * $request->item_price;

            if ($request->item_desc_note) {
                $ii->note = $request->item_desc_note;
            }

            $ii->save();
        }

        return redirect()->route('billing.invoice', $id);
    }

    public function get_invoice_data($id)
    {
        $inv = Invoice::select(
            'invoices.id',
            'brands.id AS brand_id',
            'brands.name',
            'brands.legal_name',
            'brands.address',
            'brands.city',
            'brands.notes as brand_notes',
            'states.state_abbrev AS state',
            'brands.zip',
            'brands.purchase_order_no',
            'invoices.invoice_number',
            'invoices.account_number',
            'invoices.invoice_bill_date',
            'invoices.invoice_start_date',
            'invoices.invoice_end_date',
            'invoices.invoice_due_date',
            'invoice_statuses.status',
            'invoices.pdf'
        )->join(
            'brands',
            'invoices.brand_id',
            'brands.id'
        )->leftJoin(
            'states',
            'brands.state',
            'states.id'
        )->leftJoin(
            'invoice_statuses',
            'invoices.id',
            'invoice_statuses.invoices_id'
        )->where(
            'invoices.id',
            $id
        )->first();
        if (null === $inv) {
            return redirect()->route('billing.index');
        } else {
            $inv->pdf = json_decode($inv->pdf);

            $items = InvoiceItem::withTrashed()
                ->select('invoice_items.*', 'invoice_desc.*', 'invoice_items.id as invoice_item_id', 'invoice_items.deleted_at as deleted_at')
                ->join(
                    'invoice_desc',
                    'invoice_items.invoice_desc_id',
                    'invoice_desc.id'
                )->where(
                    'invoice_id',
                    $inv->id
                )->where(
                    'invoice_desc_id',
                    '!=',
                    1
                )->orderBy(
                    'invoice_desc.invoice_sort',
                    'asc'
                )

                ->get();

            $live = InvoiceItem::withTrashed()->select(
                '*'
            )->join(
                'invoice_desc',
                'invoice_items.invoice_desc_id',
                'invoice_desc.id'
            )
                ->where('invoice_id', $inv->id)
                ->where('invoice_desc_id', 1)
                ->orderBy('invoice_desc_id', 'asc')
                ->select('invoice_items.*', 'invoice_desc.*', 'invoice_items.id as invoice_item_id', 'invoice_items.deleted_at as deleted_at')
                ->get();

            $total_minutes = 0;
            $total = 0;
            $live_avg_rate = 0;
            $calc = $live->toArray();

            if (count($calc) > 0) {
                for ($i = 0; $i < count($calc); ++$i) {
                    if ($calc[$i]['deleted_at'] == null) {
                        $total_minutes += $calc[$i]['quantity'];
                        $total += $calc[$i]['total'];
                    }
                }
            }

            if ($total > 0 && $total_minutes > 0) {
                $live_avg_rate = $total / $total_minutes;
            }

            return [
                'invoice' => $inv,
                'items' => $items,
                'live' => $live,
                'live_avg_rate' => $live_avg_rate,
                'live_minutes' => $total_minutes,
                'live_total' => $total,
            ];
        }
    }

    public function invoice(Request $request, $id)
    {
        if ($request->pdf) {
            $i_data = $this->get_invoice_data($id);

            return view(
                'billing.invoice_pdf',
                [
                    'invoice' => $i_data['invoice'],
                    'invoice_desc' => null,
                    'items' => $i_data['items'],
                    'live' => $i_data['live'],
                    'live_avg_rate' => $i_data['live_avg_rate'],
                    'live_minutes' => $i_data['live_minutes'],
                    'live_total' => $i_data['live_total'],
                ]
            );
        } else {
            return view('billing.invoice');
        }
    }

    public function invoice_get_vars($id)
    {
        $i_data = $this->get_invoice_data($id);

        $ircCheck = InvoiceRateCard::where(
            'brand_id',
            $i_data['invoice']['brand_id']
        )->first();

        if ($ircCheck) {
            $supplemental_invoice = $ircCheck->supplemental_invoice;
        } else {
            $supplemental_invoice = 0;
        }

        return response()->json(
            [
                'invoice' => $i_data['invoice'],
                'invoice_tracking' => InvoiceTracking::where(
                    'invoice_id',
                    $i_data['invoice']->id
                )->get(),
                'invoice_desc' => InvoiceDesc::orderBy('item_desc')->get(),
                'items' => $i_data['items'],
                'live' => $i_data['live'],
                'live_avg_rate' => $i_data['live_avg_rate'],
                'live_minutes' => $i_data['live_minutes'],
                'live_total' => $i_data['live_total'],
                'supplemental_invoice' => $supplemental_invoice,
            ]
        );
    }

    public function invoiceSoftDelete(Request $request)
    {
        $request->validate([
            'invoiceItemId' => 'required',
        ]);

        //soft delete
        $invoiceItem = InvoiceItem::findOrFail($request->invoiceItemId);
        $invoiceItem->delete();

        return $this->invoice_get_vars($invoiceItem->invoice_id);
    }

    public function invoiceRestore(Request $request)
    {
        $request->validate([
            'invoiceItemId' => 'required',
        ]);

        //soft delete
        $invoiceItem = InvoiceItem::withTrashed()->findOrFail($request->invoiceItemId);
        $invoiceItem->restore();

        return $this->invoice_get_vars($invoiceItem->invoice_id);
    }

    public function invoiceUpdate(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:invoice_items,id',
            'price' => 'required|numeric',
            'quantity' => 'required|numeric|min:0',
        ]);

        $invoiceItem = InvoiceItem::withTrashed()->findOrFail($request->id);
        $invoiceItem->rate = $request->price;
        $invoiceItem->quantity = $request->quantity;
        $invoiceItem->total = $request->price * $request->quantity;

        $invoiceItem->save();

        return $this->invoice_get_vars($invoiceItem->invoice_id);
    }

    public function rateCardForBrand($brand)
    {
        $rc = InvoiceRateCard::where('brand_id', $brand)->first();

        return response()->json([
            'card' => $rc,
        ]);
    }

    public function rateCardForClient($client_id)
    {
        $brand = Brand::where('client_id', $client_id)->first();

        if ($brand) {
            $rc = InvoiceRateCard::where('brand_id', $brand->id)->first();

            return response()->json([
                'card' => $rc,
            ]);
        } else {
            abort(404);
        }
    }

    public function charges()
    {
        return view('generic-vue')->with(
            [
                'componentName' => 'billing-charges',
                'title' => 'Charges and Credits',
            ]
        );
    }

    public function getBillingCharges()
    {
        $query = request()->input('search');
        if ($query !== null) {
            $query = '%' . $query . '%';
        }
        $sort = request()->input('sort');
        switch ($sort) {
            case 'short_desc':
                $sort = 'description';
                break;
            case 'owner':
            case 'brand':
            case 'ticket':
            case 'category':
            case 'duration':
            case 'date_of_work':
            case 'invoice_bill_date':
                break;

            case 'updated_at':
            default:
                $sort = 'invoice_additional.updated_at';
        }
        $dir = request()->input('dir');
        if ($dir !== 'desc') {
            $dir = 'asc';
        }
        $tab = request()->input('tab');
        if ($tab !== 'invoiced') {
            $tab = 'uninvoiced';
        }
        $billingCharges = InvoiceAdditional::select(
            'invoice_additional.updated_at',
            'invoice_additional.id',
            'rate',
            'owner',
            'ticket',
            'category',
            'invoice_desc.item_desc as category_name',
            'duration',
            'date_of_work',
            'description',
            'brands.name as brand',
            'invoice_additional.brand_id',
            'invoice_bill_date',
            'invoice_id'
        )->leftJoin(
            'invoice_desc',
            'invoice_desc.id',
            'invoice_additional.category'
        )->leftJoin(
            'brands',
            'brands.id',
            'invoice_additional.brand_id'
        )->leftJoin(
            'invoices',
            'invoices.id',
            'invoice_additional.invoice_id'
        );

        if ($tab == 'uninvoiced') {
            if ($query == null) {
                $billingCharges = $billingCharges->whereNull('invoice_additional.invoice_id');
            } else {
                $billingCharges = $billingCharges->where(function ($q) use ($query) {
                    $q->whereNull('invoice_additional.invoice_id')
                        ->where('description', 'like', $query);
                })
                    ->orWhere(function ($q) use ($query) {
                        $q->whereNull('invoice_additional.invoice_id')
                            ->where('brands.name', 'like', $query);
                    })
                    ->orWhere(function ($q) use ($query) {
                        $q->whereNull('invoice_additional.invoice_id')
                            ->where('owner', 'like', $query);
                    })
                    ->orWhere(function ($q) use ($query) {
                        $q->whereNull('invoice_additional.invoice_id')
                            ->where('ticket', 'like', $query);
                    })
                    ->orWhere(function ($q) use ($query) {
                        $q->whereNull('invoice_additional.invoice_id')
                            ->where('invoice_desc.item_desc', 'like', $query);
                    });
            }
        } else {
            if ($query == null) {
                $billingCharges = $billingCharges->whereNotNull('invoice_additional.invoice_id');
            } else {
                $billingCharges = $billingCharges->where(function ($q) use ($query) {
                    $q->whereNotNull('invoice_additional.invoice_id')
                        ->where('description', 'like', $query);
                })
                    ->orWhere(function ($q) use ($query) {
                        $q->whereNotNull('invoice_additional.invoice_id')
                            ->where('brands.name', 'like', $query);
                    })
                    ->orWhere(function ($q) use ($query) {
                        $q->whereNotNull('invoice_additional.invoice_id')
                            ->where('owner', 'like', $query);
                    })
                    ->orWhere(function ($q) use ($query) {
                        $q->whereNotNull('invoice_additional.invoice_id')
                            ->where('ticket', 'like', $query);
                    })
                    ->orWhere(function ($q) use ($query) {
                        $q->whereNotNull('invoice_additional.invoice_id')
                            ->where('invoice_desc.item_desc', 'like', $query);
                    });
            }
        }

        if ($sort !== null) {
            $billingCharges = $billingCharges->orderBy(
                $sort,
                $dir
            );
        }

        switch (request()->input('export')) {
            case 'csv':

                $billingCharges = $billingCharges->get();

                $returnData = $this->csv_response(
                    array_values(
                        $billingCharges->toArray()
                    ),
                    'charges-' . $tab
                );
                return $returnData;

                break;
            default:
                $billingCharges = $billingCharges->paginate(30);

                return response()->json($billingCharges);
        }
    }

    public function saveBillingCharge(Request $request)
    {
        $validatedData = $request->validate([
            'duration' => 'required|numeric|min:0',
            'rate' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date_of_work' => 'required|date',
            'category' => 'required|exists:invoice_desc,id',
            'brand' => 'required|exists:brands,id',
            'id' => 'nullable|exists:invoice_additional,id',
            'owner' => 'required|string',
            'ticket' => 'required|string',
        ]);

        $isCredit = $request->input('rate_is_credit');

        $billingChargeId = $validatedData['id'];

        if ($billingChargeId) {
            $billingCharge = InvoiceAdditional::find($billingChargeId);
        } else {
            $billingCharge = new InvoiceAdditional();
        }
        $billingCharge->owner = $validatedData['owner'];
        $billingCharge->ticket = $validatedData['ticket'];
        $billingCharge->category = $validatedData['category'];
        $billingCharge->duration = $validatedData['duration'];
        $billingCharge->rate = $isCredit ? - ($validatedData['rate']) : $validatedData['rate'];
        $billingCharge->date_of_work = $validatedData['date_of_work'];
        $billingCharge->description = $validatedData['description'];
        $billingCharge->brand_id = $validatedData['brand'];
        $billingCharge->save();

        return response()->json($billingCharge);
    }

    public function deleteBillingCharge(Request $request)
    {
        $billingChargeId = $request->get('id');
        $billingCharge = InvoiceAdditional::find($billingChargeId);
        $billingCharge->delete();

        return response()->json($request);
    }
}
