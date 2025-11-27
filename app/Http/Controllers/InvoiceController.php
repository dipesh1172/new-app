<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\InvoiceTracking;
use App\Models\InvoiceStatus;
use App\Models\InvoiceRateCard;
use App\Models\InvoiceItem;
use App\Models\InvoiceDesc;
use App\Models\Invoice;
use App\Models\Brand;

class InvoiceController extends Controller
{
    // Invoice Status notes:
    // billing status   status          note

    // unbilled	        generated	    NULL
    // unbilled	        approved		NULL
    // unbilled         delivery_error	email error code
    // billed           delivered       NULL
    // billed           unpaid          NULL
    // billed           disputed        NULL
    // billed           written_off     NULL ?
    // billed           adjusted		adjustment notes?
    // billed           paid			payment reference code

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
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
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
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
    }

    /**
     * Approves specified resource (invoice) and sends link email.
     */
    public function approveInvoice($invoice_id)
    {
        $status = InvoiceStatus::select(
            'invoice_statuses.id',
            'invoices.brand_id'
        )->leftJoin(
            'invoices',
            'invoice_statuses.invoices_id',
            'invoices.id'
        )->where('invoices_id', $invoice_id)->first();
        if (!$status) {
            $status = new InvoiceStatus();
            $status->invoices_id = $invoice_id;
            $status->users_id = 'system';
            $status->billing_status = 'unbilled';
            $status->status = 'generated';
            $status->save();

            $status = InvoiceStatus::select(
                'invoice_statuses.id',
                'invoices.brand_id'
            )->leftJoin(
                'invoices',
                'invoice_statuses.invoices_id',
                'invoices.id'
            )->where('invoices_id', $invoice_id)->first();
        }

        if ($status) {
            $status->users_id = Auth::user()->id;
            $status->status = 'approved';
            $status->save();

            $uploads = [];

            $invoice_pdf = $this->printInvoiceToPDF($invoice_id);
            $invoice_url = $this->uploadInvoiceToS3($invoice_pdf, $status->brand_id);
            $uploads[] = [
                'doc' => 'invoice',
                'filename' => $invoice_url,
            ];

            $ircCheck = InvoiceRateCard::where(
                'brand_id',
                $status->brand_id
            )->first();

            if ($ircCheck&& $ircCheck->supplemental_invoice === 1) {

                $supplemental_invoice_pdf = $this->supplemental_report($invoice_id, 'true');
                $supplemental_invoice_url = $this->uploadInvoiceToS3($supplemental_invoice_pdf, $status->brand_id);
                $uploads[] = [
                    'doc' => 'supplemental invoice',
                    'filename' => $supplemental_invoice_url,
                ];
                $live_minutes_xls = $this->live_minutes($invoice_id);
                if ($live_minutes_xls != false) {
                    $live_minutes_url = $this->uploadXlsToS3($live_minutes_xls, $status->brand_id);
                    $uploads[] = [
                        'doc' => 'live minutes report',
                        'filename' => $live_minutes_url,
                    ];
                }
            }

            $delete = $this->deleteLocalFile($invoice_pdf);
            if (isset($supplemental_invoice_pdf)) {
                $delete = $this->deleteLocalFile($supplemental_invoice_pdf);
            }
            if (isset($live_minutes_xls) && $live_minutes_xls !== false) {
                $delete = $this->deleteLocalFile($live_minutes_xls);
            }

            if (isset($uploads)) {
                $invoice = Invoice::find($invoice_id);
                $invoice->pdf = json_encode($uploads);
                $invoice->save();
            }

            // Billing Distribution
            $email = $this->emailInvoiceLinkBilling(
                $invoice_id,
                $uploads,
                $status->brand_id
            );
            if ($email) {
                $in = Invoice::find($invoice_id);
                if ($in) {
                    $in->sent = Carbon::now();
                    $in->save();
                }
            }

            // Accounts Payable Distribution
            $email2 = $this->emailInvoiceFileAccountsPayable(
                $invoice_id,
                $status->brand_id
            );
            if ($email2) {
                $in = Invoice::find($invoice_id);
                if ($in) {
                    $in->sent = Carbon::now();
                    $in->save();
                }
            }
        }

        return redirect()->route('billing.invoice', $invoice_id);
    }

    public function send($invoice_id)
    {
        $invoice = Invoice::find($invoice_id);
        $invoice->pdf = json_decode($invoice->pdf, true);
        if ($invoice && $invoice->pdf !== null) {
            // Billing Distribution
            $email = $this->emailInvoiceLinkBilling(
                $invoice_id,
                $invoice->pdf,
                $invoice->brand_id
            );
            // Accounts Payable Distribution
            $email2 = $this->emailInvoiceFileAccountsPayable(
                $invoice_id,
                $invoice->brand_id
            );
            if (!$email) {
                session()->flash(
                    'flash_message',
                    'There was an error sending the invoice to the Billing Distributuion list.'
                );
            } elseif (($email && $email2) || ($email && !$email2)) {
                $in = Invoice::find($invoice_id);
                if ($in) {
                    $in->sent = Carbon::now();
                    $in->save();
                }

                if (!$email2) {
                    session()->flash(
                        'flash_message',
                        'Invoice was successfully resent. There was an error sending the invoice to the Accounts Payable Distribution list, or the list was empty.'
                    );
                } else {
                    session()->flash(
                        'flash_message',
                        'Invoice was successfully resent.'
                    );
                }
            } else {
                session()->flash(
                    'flash_message',
                    'There was a non-specific error sending the invoice.'
                );
            }
        }

        return redirect()->route('billing.invoice', $invoice_id);
    }

    public function generate($invoice_id)
    {
        $invoice = Invoice::find($invoice_id);
        if ($invoice) {
            $uploads = [];

            $invoice_pdf = $this->printInvoiceToPDF($invoice_id);
            $invoice_url = $this->uploadInvoiceToS3($invoice_pdf, $invoice->brand_id);
            $uploads[] = [
                'doc' => 'invoice',
                'filename' => $invoice_url,
            ];

            $ircCheck = InvoiceRateCard::where(
                'brand_id',
                $invoice->brand_id
            )
                ->first();
            if (
                $ircCheck
                && $ircCheck->supplemental_invoice === 1
            ) {
                $supplemental_invoice_pdf = $this->supplemental_report($invoice_id, 'true');
                $supplemental_invoice_url = $this->uploadInvoiceToS3($supplemental_invoice_pdf, $invoice->brand_id);
                $uploads[] = [
                    'doc' => 'supplemental invoice',
                    'filename' => $supplemental_invoice_url,
                ];
                $live_minutes_xls = $this->live_minutes($invoice_id);
                if ($live_minutes_xls != false) {
                    $live_minutes_url = $this->uploadXlsToS3($live_minutes_xls, $invoice->brand_id);
                    $uploads[] = [
                        'doc' => 'live minutes report',
                        'filename' => $live_minutes_url,
                    ];
                }
            }

            $delete = $this->deleteLocalFile($invoice_pdf);
            if (isset($supplemental_invoice_pdf)) {
                $delete = $this->deleteLocalFile($supplemental_invoice_pdf);
            }
            if (
                isset($live_minutes_xls)
                && $live_minutes_xls !== false
            ) {
                $delete = $this->deleteLocalFile($live_minutes_xls);
            }

            // $pdf = $this->printInvoiceToPDF($invoice_id);
            // $upload = $this->uploadInvoiceToS3($pdf, $invoice->brand_id);
            // $delete = $this->deleteLocalFile($pdf);
            if (isset($uploads)) {
                $invoice->pdf = json_encode($uploads);
                $invoice->save();
            }
            // $email = $this->emailInvoiceLink(
            //     $invoice_id,
            //     $upload,
            //     $status->brand_id
            // );
        }

        return redirect()->route('billing.invoice', $invoice_id);
    }

    /**
     * Marks specified resource (invoice) as paid.
     *
     * @param string $id
     */
    public function markInvoiceAsPaid($id)
    {
        $invoice = Invoice::find($id);

        $check = InvoiceStatus::where(
            'invoices_id',
            $invoice->id
        )->where(
            'billing_status',
            'billed'
        )->get();

        if (!$check->isEmpty()) {
            $status = new InvoiceStatus();
            $status->invoices_id = $invoice->id;
            $status->users_id = Auth::user()->id;
            $status->billing_status = 'billed';
            $status->status = 'paid';
            $status->save();

            return; //unknown where to return to at this time, remove this comment when someone figures this out
        }

        // report error?
        return; //unknown where to return to at this time, remove this comment when someone figures this out
    }

    /**
     * Adds line item associated with specified resource (invoice).
     *
     * @param string $id
     */
    public function addLineItemToInvoice(Request $request, $id)
    {
        $invoice = Invoice::find($id);
        $validatedData = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'invoice_desc_id' => 'required|exists:invoice_desc,id',
            'rate' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $total = $request->quantity * $request->rate;

        $item = new InvoiceItem();
        $item->invoice_id = $invoice->id;
        $item->quantity = $request->quantity;
        $item->invoice_desc_id = $request->invoice_desc_id;
        $item->rate = $request->rate;
        $item->note = $request->note;
        $item->total = $total;
        $item->save();

        return; //unknow where to return to at this time, remove this comment when someone figures this out
    }

    public function printInvoiceToPDF($id)
    {
        $inv = Invoice::select(
            'invoices.id',
            'brands.id AS brand_id',
            'brands.name',
            'brands.legal_name',
            'brands.address',
            'brands.city',
            'brands.purchase_order_no',
            'states.state_abbrev AS state',
            'brands.zip',
            'invoices.invoice_number',
            'invoices.account_number',
            'invoices.invoice_bill_date',
            'invoices.invoice_start_date',
            'invoices.invoice_end_date',
            'invoices.invoice_due_date'
        )->join(
            'brands',
            'invoices.brand_id',
            'brands.id'
        )->leftJoin(
            'states',
            'brands.state',
            'states.id'
        )->where(
            'invoices.id',
            $id
        )->first();

        $items = InvoiceItem::join(
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
            'invoice_desc_id',
            'asc'
        )->get();

        $live = InvoiceItem::select(
            '*'
        )->join(
            'invoice_desc',
            'invoice_items.invoice_desc_id',
            'invoice_desc.id'
        )->where(
            'invoice_id',
            $inv->id
        )->where(
            'invoice_desc_id',
            1
        )->orderBy(
            'invoice_desc_id',
            'asc'
        )->get();

        $total_minutes = 0;
        $total = 0;
        $live_avg_rate = 0;
        $calc = $live->toArray();

        if (count($calc) > 0) {
            for ($i = 0; $i < count($calc); ++$i) {
                $total_minutes += $calc[$i]['quantity'];
                $total += $calc[$i]['total'];
            }
        }

        if ($total_minutes > 0) {
            $live_avg_rate = $total / $total_minutes;
        }

        $pdf = PDF::loadView(
            'billing.invoice_pdf',
            [
                'invoice' => $inv,
                'invoice_desc' => null,
                'items' => $items,
                'live' => $live,
                'live_avg_rate' => $live_avg_rate,
                'live_minutes' => $total_minutes,
                'live_total' => $total,
            ]
        );

        $filename = public_path() . '/tmp/invoice_' . $inv->brand_id
            . '_' . $inv->id . '_' . mt_rand(0, 99999);
        $pdf->setOptions(['dpi' => 150, 'isRemoteEnabled' => true]);
        $pdf->save($filename);

        return $filename;
    }

    public function uploadInvoiceToS3($filename, $brand_id)
    {
        $s3filename = md5($filename) . '.pdf';
        $keyname = 'uploads/invoices/' . $brand_id . '/' . $s3filename;

        try {
            Storage::disk('s3')->put(
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

    public function uploadXlsToS3($filename, $brand_id)
    {
        $s3filename = md5($filename) . '.xls';
        $keyname = 'uploads/invoices/' . $brand_id . '/' . $s3filename;

        try {
            Storage::disk('s3')->put(
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

    public function deleteLocalFile($filename)
    {
        unlink($filename);

        return;
    }

    public function invoiceTracking(Request $request, $id)
    {
        if ($request->url) {
            $it = new InvoiceTracking();
            $ip_addr = (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
                ? $_SERVER['HTTP_X_FORWARDED_FOR']
                : $_SERVER['REMOTE_ADDR'];
            $it->ip_addr = ip2long($ip_addr);
            $it->invoice_id = $id;
            $it->invoice_tracking_type_id = 2;
            $it->save();

            $url = $request->url;
            if ($request->add_cdn) {
                $url = config('services.aws.cloudfront.domain') . '/' . $request->url;
            }

            $i = Invoice::find($id);
            if ($i) {
                $obj_arr = [];
                if ($i->read) {
                    $obj_arr = json_decode($i->read, true);
                }

                $obj_arr[] = [
                    'opened' => time(),
                    'ip' => $it->ip_addr,
                ];
                $i->read = json_encode($obj_arr);
                $i->save();
            }

            return redirect($url);
        }
    }

    public function emailInvoiceLinkBilling($invoice, $filenames, $brand_id)
    {
        $inv = Invoice::find($invoice);
        $brand = Brand::find($brand_id);

        if ($brand && strlen(trim($brand->billing_distribution)) > 0) {
            $emails_final = [];
            $emails = explode(',', trim($brand->billing_distribution));
            for ($i = 0; $i < count($emails); ++$i) {
                if (filter_var(trim($emails[$i]), FILTER_VALIDATE_EMAIL)) {
                    $emails_final[] = trim($emails[$i]);
                }
            }
            $brand_name = ($brand->legal_name !== null && trim($brand->legal_name) !== '' ? $brand->legal_name : $brand->name);

            $email_file = 'emails.billingSendInvoiceToBillingDistribution';
            $start_date = date('m/d/Y', strtotime($inv->invoice_start_date));
            $end_date = date('m/d/Y', strtotime($inv->invoice_end_date));
            $email_data = [
                'id' => $inv->id,
                'brand_name' => $brand_name,
                'urls' => $filenames,
                'start_date' => $start_date,
                'end_date' => $end_date,
            ];

            if (config('app.env') !== 'production') {
                $subject = '(TESTING) TPV.com (' . $brand_name . ') Invoice: '
                    . $start_date . '-' . $end_date;
            } else {
                $subject = 'TPV.com (' . $brand_name . ') Invoice: '
                    . $start_date . '-' . $end_date;
            }

            try {
                Mail::send(
                    $email_file,
                    $email_data,
                    function ($message) use ($subject, $emails_final) {
                        $message->subject($subject);
                        $message->from('no-reply@tpvhub.com');

                        if (config('app.env') == 'production') {
                            $message->bcc(['accountingmanagers@answernet.com']);
                        }

                        $message->to($emails_final);
                    }
                );

                return true;
            } catch (Exception $e) {
                info('Error sending invoice email ' . $e);

                return false;
            }
        }

        return false;
    }

    public function emailInvoiceFileAccountsPayable($invoice, $brand_id)
    {
        $inv = Invoice::find($invoice);
        $brand = Brand::find($brand_id);

        if ($brand && strlen(trim($brand->accounts_payable_distribution)) > 0) {
            $emails_final = [];
            $emails = explode(',', trim($brand->accounts_payable_distribution));
            for ($i = 0; $i < count($emails); ++$i) {
                if (filter_var(trim($emails[$i]), FILTER_VALIDATE_EMAIL)) {
                    $emails_final[] = trim($emails[$i]);
                }
            }
            $brand_name = ($brand->legal_name !== null && trim($brand->legal_name) !== '' ? $brand->legal_name : $brand->name);

            $urls = json_decode($inv->pdf, true);

            $filenames = [];
            foreach ($urls as $url) {
                $fileParts = explode('.', $url['filename']);
                $s3File = $this->s3Download($url['filename']);

                if (!is_array($s3File)) {
                    $fileString = $brand->name . ' ' . date('Y-m-d', strtotime($inv->invoice_start_date)) . ' - ' . date('Y-m-d', strtotime($inv->invoice_end_date)) . ' ' . ucwords($url['doc']) . '.' . $fileParts[1];

                    $tmpFile = public_path('tmp/' . $fileString);

                    file_put_contents(
                        $tmpFile,
                        $s3File
                    );

                    $filenames[] = $tmpFile;
                }
            }

            $email_file = 'emails.billingSendInvoiceToAccountsPayableDistribution';
            $start_date = date('m/d/Y', strtotime($inv->invoice_start_date));
            $end_date = date('m/d/Y', strtotime($inv->invoice_end_date));
            $email_data = [
                'id' => $inv->id,
                'brand_name' => $brand_name,
                'start_date' => $start_date,
                'end_date' => $end_date,
            ];

            if (config('app.env') !== 'production') {
                $subject = '(TESTING) TPV.com (' . $brand_name . ') Invoice: '
                    . $start_date . '-' . $end_date;
            } else {
                $subject = 'TPV.com (' . $brand_name . ') Invoice: '
                    . $start_date . '-' . $end_date;
            }

            try {
                Mail::send(
                    $email_file,
                    $email_data,
                    function ($message) use ($subject, $emails_final, $filenames) {
                        $message->subject($subject);
                        $message->from('no-reply@tpvhub.com');

                        if (config('app.env') == 'production') {
                            $message->bcc(['accountingmanagers@answernet.com']);
                        }

                        $message->to($emails_final);

                        foreach ($filenames as $file) {
                            $message->attach($file);
                        }
                    }
                );

                foreach ($filenames as $file) {
                    unlink($file);
                }

                return true;
            } catch (Exception $e) {
                info('Error sending invoice email ' . $e);

                return false;
            }
        }

        return false;
    }

    /**
     * Get Invoice Categories.
     */
    public function getInvoiceCategories()
    {
        $invoiceCategories = InvoiceDesc::select(
            'id',
            'item_desc',
            'map_rate_to'
        )->orderBy(
            'item_desc',
            'asc'
        )->paginate(50);

        return response()->json($invoiceCategories);
    }

    public function supplemental_report($id, $pdfView = null)
    {
        // $brand_id = session('current_brand')->id;
        $invoice = Invoice::select(
            'invoices.id',
            'brands.id AS brand_id',
            'brands.name',
            'brands.legal_name',
            'brands.address',
            'brands.city',
            'brands.purchase_order_no',
            'states.state_abbrev AS state',
            'brands.zip',
            'invoices.invoice_number',
            'invoices.account_number',
            'invoices.invoice_bill_date',
            'invoices.invoice_start_date',
            'invoices.invoice_end_date',
            'invoices.invoice_due_date',
            'invoice_statuses.status'
        )
            ->join('brands', 'invoices.brand_id', 'brands.id')
            ->leftJoin('states', 'brands.state', 'states.id')
            ->leftJoin(
                'invoice_statuses',
                'invoices.id',
                'invoice_statuses.invoices_id'
            )
            ->where('invoices.id', $id)
            ->first();
        $brand_id = $invoice->brand_id;

        //Initializing values
        $result = [];
        $init_values = [
            'total_good_sales' => 0,
            'total_no_sales' => 0,
            'total_transaction_time' => 0,
            'tpv_good_sales_transaction_time' => 0,
            'tpv_no_sales_transaction_time' => 0,
            'total_costs' => 0.00,
            'cost_per_minute' => 0.00,
        ];

        $commodities = ['electric', 'gas', 'total', 'Live Minutes (Call Abandoned)', 'Total Live Minute Invoice Charges'];
        //DTD = 1, TM = 2, Retail = 3, Customer Care = 4
        $channels = [1, 2, 3, 4];

        foreach ($commodities as $c) {
            $result[$c] = [
                1 => $init_values,
                2 => $init_values,
                3 => $init_values,
                4 => $init_values,
            ];
        }

        //2 is gas 1 is electric
        //Calculating the t_cost and more
        $stats_product = StatsProduct::select(
            'interaction_time',
            'stats_product.channel_id',
            'stats_product.commodity_id',
            'stats_product.result'
        )->where(
            'stats_product.brand_id',
            $brand_id
        );

        if (config('app.env') !== 'production') {
            $stats_product = $stats_product->where(
                'stats_product.dev',
                0
            );
        }

        $stats_product = $stats_product->whereNotNull(
            'stats_product.commodity_id'
        )->whereNotNull(
            'stats_product.channel_id'
        )->whereBetween(
            'stats_product.event_created_at',
            [$invoice->invoice_start_date, $invoice->invoice_end_date]
        )->groupBy(
            ['stats_product.commodity_id', 'stats_product.channel_id', 'stats_product.confirmation_code']
        )->get();

        $amount = [
            'gas' => [
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
            ],
            'electric' => [
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
            ],
        ];

        foreach ($stats_product as $st) {
            $process = false;
            switch ($st->commodity_id) {
                case 1:
                    $c = 'electric';
                    $process = true;
                    break;

                case 2:
                    $c = 'gas';
                    $process = true;
                    break;
            }
            if ($process) {
                $result[$c][$st->channel_id]['tpv_good_sales_transaction_time'] = (isset($result[$c][$st->channel_id]['tpv_good_sales_transaction_time'])) ? $result[$c][$st->channel_id]['tpv_good_sales_transaction_time'] : 0;
                $result[$c][$st->channel_id]['tpv_no_sales_transaction_time'] = (isset($result[$c][$st->channel_id]['tpv_no_sales_transaction_time'])) ? $result[$c][$st->channel_id]['tpv_no_sales_transaction_time'] : 0;
                $result[$c][$st->channel_id]['total_transaction_time'] = (isset($result[$c][$st->channel_id]['total_transaction_time'])) ? $result[$c][$st->channel_id]['total_transaction_time'] : 0;

                if ($st->result === 'Sale') {
                    $result[$c][$st->channel_id]['tpv_good_sales_transaction_time'] = round($result[$c][$st->channel_id]['tpv_good_sales_transaction_time'] + $st->interaction_time, 2);
                    //Setting the amount of records per channel/commodity to calculate the cost later
                    ++$amount[$c][$st->channel_id];
                    ++$result[$c][$st->channel_id]['total_good_sales'];
                }
                if ($st->result === 'No Sale') {
                    $result[$c][$st->channel_id]['tpv_no_sales_transaction_time'] = round($result[$c][$st->channel_id]['tpv_no_sales_transaction_time'] + $st->interaction_time, 2);
                    //Setting the amount of records per channel/commodity to calculate the cost later
                    ++$amount[$c][$st->channel_id];
                    ++$result[$c][$st->channel_id]['total_no_sales'];
                }
                $result[$c][$st->channel_id]['total_transaction_time'] = round($result[$c][$st->channel_id]['total_transaction_time'] + $st->interaction_time, 2);
            }
        }

        //Calculating the total_costs
        $live_min = InvoiceItem::select(
            'total',
            'rate'
        )->where(
            'invoice_desc_id',
            1
        )->where(
            'invoice_id',
            $id
        )->first();

        if ($live_min) {
            if ($live_min->total > 0) {
                foreach ($commodities as $c) {
                    if (
                        $c === 'electric'
                        || $c === 'gas'
                    ) {
                        foreach ($channels as $ch) {
                            if ($amount[$c][$ch] > 0) {
                                $result[$c][$ch]['cost_per_minute'] = round($live_min->rate, 2);
                                $result[$c][$ch]['total_costs'] = round($live_min->rate * $result[$c][$ch]['total_transaction_time'], 2);
                            }
                        }
                    }
                }
            }
        }

        //Calculating Totals
        $result['total'] = [
            1 => $init_values,
            2 => $init_values,
            3 => $init_values,
            4 => $init_values,
        ];

        foreach ($init_values as $key => $value) {
            $result['total'][1][$key] = round($result['electric'][1][$key] + $result['gas'][1][$key], 2);
            $result['total'][2][$key] = round($result['electric'][2][$key] + $result['gas'][2][$key], 2);
            $result['total'][3][$key] = round($result['electric'][3][$key] + $result['gas'][3][$key], 2);
            $result['total'][4][$key] = round($result['electric'][4][$key] + $result['gas'][4][$key], 2);
        }

        // Live Minutes (Call Abandoned)
        $lmca = StatsProduct::select(
            'stats_product.interaction_time'
        )->where(
            'stats_product.brand_id',
            $brand_id
        )->whereNull(
            'stats_product.commodity_id'
        )->whereBetween(
            'stats_product.event_created_at',
            [$invoice->invoice_start_date, $invoice->invoice_end_date]
        )->groupBy(
            ['stats_product.confirmation_code']
        );

        if (config('app.env') !== 'production') {
            $lmca = $lmca->where(
                'stats_product.dev',
                0
            );
        }

        $lmca = $lmca->get();

        $result['Live Minutes (Call Abandoned)'][1] = 0;
        if (
            isset($lmca)
            && count($lmca) > 0
        ) {
            foreach ($lmca as $lmca_row) {
                $result['Live Minutes (Call Abandoned)'][1] = $result['Live Minutes (Call Abandoned)'][1] + round($live_min->rate * $lmca_row->interaction_time, 2);
            }
        }

        // calculate total invoice charges
        $result['Total Live Minute Invoice Charges'][1] = round(
            $result['total'][1]['total_costs']
                + $result['total'][2]['total_costs']
                + $result['total'][3]['total_costs']
                + $result['total'][4]['total_costs']
                + $result['Live Minutes (Call Abandoned)'][1],
            2
        );

        if ($pdfView) {
            $pdf = PDF::loadView(
                'invoice.supplemental_report',
                [
                    'result' => $result,
                    'invoice' => $invoice,
                    'view' => 'pdf',
                ]
            );

            $filename = public_path() . '/tmp/supplemental_invoice_' . $brand_id
                . '_' . $invoice->id . '_' . mt_rand(0, 99999) . '.pdf';
            $pdf->setOptions(['dpi' => 150, 'isRemoteEnabled' => true]);
            $pdf->save($filename);

            return $filename;
        } else {
            return view(
                'invoice.supplemental_report',
                [
                    'result' => $result,
                    'invoice' => $invoice,
                    'view' => 'html',
                ]
            );
        }
    }

    public function live_minutes($id)
    {
        $invoice = Invoice::find($id);

        $data = StatsProduct::select(
            'event_id',
            'event_created_at',
            'confirmation_code',
            'interaction_created_at',
            'product_time',
            'interaction_time',
            'commodity',
            'vendor_name',
            'result',
            'interaction_type',
            'sales_agent_name',
            'sales_agent_rep_id',
            'channel'
        )->where(
            'brand_id',
            $invoice->brand_id
        )->where(
            'interaction_time',
            '>',
            0
        )->whereRaw(
            'DATE(event_created_at) >= ?',
            $invoice->invoice_start_date->format('Y-m-d')
        )->whereRaw(
            'DATE(event_created_at) <= ?',
            $invoice->invoice_end_date->format('Y-m-d')
        )->orderBy('event_created_at')
            ->get()
            ->toArray();

        return $this->xls_response(
            array_values(
                $data
            ),
            $invoice->brand_id,
            $invoice->id
        );
    }

    private function xls_response($list, $brand_id, $invoice_id)
    {
        $xls_filename = public_path('tmp/invoice_live_minutes_' . $brand_id . '_' . $invoice_id . '.xls');

        if (isset($list) && isset($list[0])) {
            array_unshift($list, array_keys($list[0]));
            // $callback = function () use ($list) {
            $tmp_csv_path = public_path('tmp/' . md5(time() . '_' . $brand_id) . '.csv');
            $FH = fopen($tmp_csv_path, 'w');
            foreach ($list as $row) {
                fputcsv($FH, $row);
            }
            fclose($FH);

            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();

            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);

            $spreadsheet = $reader->load($tmp_csv_path);
            $writer = new Xls($spreadsheet);
            $writer->save($xls_filename);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if (file_exists(public_path($tmp_csv_path))) {
                unlink(public_path($tmp_csv_path));
            }
            // };

            return $xls_filename;
        } else {
            return false;
        }
    }

    private function csv_response($list, $filename)
    {
        $headers = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $filename . '.csv',
            'Expires' => '0',
            'Pragma' => 'public',
        ];

        if (isset($list) && isset($list[0])) {
            array_unshift($list, array_keys($list[0]));
            $callback = function () use ($list) {
                $FH = fopen('php://output', 'w');
                foreach ($list as $row) {
                    try {
                        fputcsv($FH, $row);
                    } catch (\Exception $e) {
                        info('could not write row due to ' . $e->getMessage(), $row);
                    }
                }
                fclose($FH);
            };

            return response()->stream($callback, 200, $headers);
        } else {
            return redirect()->back()->with('message', 'No results to return.');
        }
    }

    public function live_minutes_export(Request $request, $id)
    {
        $data = $this->live_minutes_data($request, $id, false);

        return $this->csv_response(
            array_values(
                $data['data']
            ),
            'events'
        );

        //header(spintf('Location: /invoices/%s/live_minutes_view', $id));
    }

    public function live_minutes_view(Request $request, $id)
    {
        $data = $this->live_minutes_data($request, $id);

        return view(
            'invoice.live_minutes',
            $data
        );
    }

    public function live_minutes_data(Request $request, $id, $paginated = true)
    {
        $sortBy = 'interaction_created_at';
        $sortDir = 'desc';

        $sortColumn = $request->column;
        if (!empty($sortColumn)) {
            $sortBy = $sortColumn;
        }

        $sortDirection = $request->direction;
        if (!empty($sortDirection)) {
            $sortDir = $sortDirection;
        }

        $columns = [
            'event_id',
            'event_created_at',
            'confirmation_code',
            'interaction_created_at',
            'product_time',
            'interaction_time',
            'commodity',
            'vendor_name',
            'result',
            'interaction_type',
            'sales_agent_name',
            'sales_agent_rep_id',
            'channel',
        ];

        if (!in_array($sortColumn, $columns)) {
            $sortBy = 'interaction_created_at';
        }

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $invoice = Invoice::find($id);

        $data = StatsProduct::select(
            $columns
        )->where(
            'brand_id',
            $invoice->brand_id
        )->where(
            'interaction_time',
            '>',
            0
        )->whereBetween(
            'interaction_created_at',
            [
                $invoice->invoice_start_date->format('Y-m-d'),
                $invoice->invoice_end_date->format('Y-m-d')
            ]
        )->orderBy($sortBy, $sortDir);

        $pages = $paginated ? (clone $data)->paginate(25) : (clone $data)->get();

        return [
            'invoice' => $invoice,
            'data' => $pages->toArray(),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ];
    }

    private function s3Download($keyname)
    {
        if (Storage::disk('s3')->exists($keyname)) {
            $file = Storage::disk('s3')->get($keyname);

            return $file;
        } else {
            return [
                'error',
                $keyname . ' not found.',
            ];
        }
    }
}
