<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;

use App\Traits\SearchFormTrait;
use App\Models\StatsProduct;

use App\Models\State;
use App\Models\ScriptAnswer;
use App\Models\QaTracking;
use App\Models\PhoneNumberLookup;
use App\Models\PhoneNumber;
use App\Models\Language;
use App\Models\InteractionType;
use App\Models\Interaction;
use App\Models\EventProductIdentifier;
use App\Models\EventProduct;
use App\Models\EventNote;
use App\Models\EventFlagReason;
use App\Models\EventFlag;
use App\Models\EventAlert;
use App\Models\Event;
use App\Models\EmailAddressLookup;
use App\Models\EmailAddress;
use App\Models\Disposition;
use App\Models\CustomFieldStorage;
use App\Models\CallReviewTypeCategory;
use App\Models\CallReviewType;
use App\Models\Brand;
use App\Models\AddressLookup;
use App\Models\Address;
use App\Mail\GenericEmail;
use LDAP\Result;

class EventController extends Controller
{
    use SearchFormTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('events/events', [
            'brands' => $this->get_brands(),
            'languages' => $this->get_languages(),
            'commodities' => $this->get_commodities(),
            'vendors' => $this->get_vendors(),
        ]);
    }

    public function listEvents(Request $request)
    {
        $now = Carbon::now();
        $column = $request->get('column');
        $direction = $request->get('direction');
        $search = $request->get('search');
        $searchField = $request->get('searchField');
        $start_date = $request->has('startDate') ? $request->get('startDate') : Carbon::yesterday()->format('Y-m-d');
        $start_date = Carbon::parse($start_date);
        $end_date = $request->has('endDate') ? $request->get('endDate') : $now->format('Y-m-d');
        $end_date = Carbon::parse($end_date);
        $vendor = $request->get('vendor');
        $brand = $request->get('brandId');
        $language = $request->get('language');
        $saleType = $request->get('saleType');
        $channel = $request->get('channel');
        $market = $request->get('market');

        $data = StatsProduct::select(
            'stats_product.event_id',
            'stats_product.event_created_at',
            'stats_product.interaction_created_at',
            'stats_product.eztpv_initiated',
            'stats_product.channel',
            'stats_product.confirmation_code',
            'stats_product.result',
            'stats_product.brand_name',
            'stats_product.vendor_name',
            'stats_product.sales_agent_name',
            'stats_product.sales_agent_rep_id',
            'stats_product.tpv_agent_name',
            'stats_product.btn',
            DB::raw("case when qa_tracking.event_id is null then 'N' else 'Y' end as monitored")
        )->leftJoin(
            'qa_tracking',
            'qa_tracking.event_id',
            'stats_product.event_id'
        );

        if ($searchField && $search) {
            switch ($searchField) {
                case 'monitored':
                    if (strtoupper($search) === 'Y' || strtoupper($search === 'N')) {
                        if (strtoupper($search) === 'Y') {
                            $data = $data->whereNotNull('qa_tracking.event_id');
                        } else {
                            $data = $data->whereNull('qa_tracking.event_id');
                        }
                    }
                    break;

                case 'phone_number':
                    $data = $data->where('btn', 'LIKE', '%' . $search . '%');
                    break;

                case 'account_number':
                    $data = $data->where(
                        'account_number1',
                        'LIKE',
                        '%' . $search . '%'
                    )->orWhere(
                        'account_number2',
                        'LIKE',
                        '%' . $search . '%'
                    );
                    break;

                case 'confirmation_code':
                    $data = $data->where(
                        'confirmation_code',
                        'LIKE',
                        '%' . $search . '%'
                    );
                    break;

                case 'tpv_name':
                    $data = $data->where(
                        'tpv_agent_name',
                        'LIKE',
                        "%{$search}%"
                    );
                    break;

                case 'sales_agent':
                    $data = $data->where(
                        'sales_agent_name',
                        'LIKE',
                        "%{$search}%"
                    );
                    break;

                case 'lead_record_id':
                    $data = $data->where(
                        'leads.external_lead_id',
                        'LIKE',
                        "%{$search}%"
                    )->leftJoin(
                        'events',
                        'stats_product.event_id',
                        'events.id'
                    )->leftJoin(
                        'leads',
                        'events.lead_id',
                        'leads.id'
                    );
                    break;
            }
        }

        $data = $data->where(
            'event_created_at',
            '>=',
            $start_date->startOfDay()
        )->where(
            'event_created_at',
            '<=',
            $end_date->endOfDay()
        );

        if ($channel) {
            $data = $data->whereIn('channel_id', $this->listToArray($channel));
        }

        if ($market) {
            $data = $data->whereIn('market_id', $this->listToArray($market));
        }

        if ($vendor) {
            $data = $data->whereIn('vendor_id', $this->listToArray($vendor));
        }

        if ($brand) {
            $data = $data->whereIn('brand_id', $this->listToArray($brand));
        }

        if ($language) {
            $data = $data->whereIn('language_id', $this->listToArray($language));
        }

        if ($saleType) {
            $saleTypes = $this->listToArray($saleType);
            $data = $data->whereIn('result', $saleTypes);
        }

        $data = $data->groupBy('stats_product.confirmation_code');

        if ($column && $direction) {
            $data = $data->orderBy($column, $direction);
        } else {
            $data = $data->orderBy('stats_product.event_created_at', 'desc');
        }

        switch ($request->get('export')) {
            case 'csv':
                $data = $data->get();
                return $this->csv_response(
                    array_values(
                        $data->toArray()
                    ),
                    'events'
                );
                break;

            default:
                $ourData = $data->paginate(20)->toArray();
                $ourData['data'] = collect($ourData['data'])->map(function ($item) {
                    $item['event_created_at'] = (Carbon::parse($item['event_created_at']))->format('n-j-y g:i:s a');
                    return $item;
                });

                return $ourData;
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    //end create()

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }

    //end store()

    private function do_new_qa_update(Request $request, $interaction)
    {
        $currentResult = $interaction->event_result_id;
        if ($currentResult != 3 && $request->input('result') !== $currentResult) {
            session()->flash('flash_message_error', 'Only Closed Calls may have their result changed.');

            return redirect()->back();
        }

        if ($currentResult == 3 && $request->input('result') !== $currentResult) {
            //updating interaction status
            $newResult = $request->input('result');
            if ($newResult == 1) {
                // change to good sale
                DB::transaction(function () use (
                    $request,
                    $interaction
                ) {
                    $interaction->event_result_id = 1;
                    $interaction->disposition_id = null;
                    $interaction->save();

                    $now = Carbon::now();
                    $days = $now->diffInDays($interaction->created_at);
                    if ($days > 0) {
                        //send research email
                        $content = "The status of {$interaction->event->confirmation_code}
                            was updated to Good Sale by the QA Department\n
                            Date of Call: {$interaction->created_at->format('m-d-Y')}\n
                            Date of Status Update: {$now->format('m-d-Y')}\n
                            QA Notes: {$request->input('notes')}";
                        Mail::to('research@tpv.com')->send(
                            new GenericEmail('Status Update to Good Sale ' . $interaction->event->confirmation_code, $content)
                        );
                    }

                    StatsProduct::where('event_id', $request->event_id)
                        ->where('interaction_id', $interaction->id)
                        ->get()
                        ->each(function ($item) {
                            $item->result = 'Sale';
                            $item->disposition_id = null;
                            $item->disposition_label = null;
                            $item->disposition_reason = null;
                            $item->save();
                        });

                    if ($request->input('notes') !== null) {
                        $notes = new EventNote();
                        $notes->tpv_staff_id = Auth::user()->id;
                        $notes->event_id = $request->event_id;
                        $notes->notes = $request->input('notes');
                        $notes->save();
                    }
                });
            }

            if ($newResult == 2) {
                //change to no sale
                $dispo_raw = trim($request->input('disposition'));
                if ($dispo_raw === null || $dispo_raw === '') {
                    session()->flash(
                        'flash_message_error',
                        'The disposition field is required when selected result is No Sale'
                    );

                    return redirect()->back();
                }
                $dispo = Disposition::find($dispo_raw);
                if ($dispo === null) {
                    session()->flash('flash_message_error', 'Invalid disposition selected');

                    return redirect()->back();
                }

                DB::transaction(function () use ($request, $dispo, $interaction) {
                    // Update Event Flags
                    $efs = EventFlag::where(
                        'event_id',
                        $request->event_id
                    )->whereNull('reviewed_by')->get();
                    if ($efs) {
                        foreach ($efs as $ef) {
                            $ef->call_review_type_id = $request->call_review_type;
                            $ef->reviewed_by = Auth::user()->id;
                            $ef->save();
                        }
                    }
                    // End Update Event Flags

                    $interaction->event_result_id = 2;
                    $interaction->disposition_id = $dispo->id;
                    $interaction->save();

                    StatsProduct::where('event_id', $request->event_id)
                        ->where('interaction_id', $interaction->id)
                        ->get()
                        ->each(function ($item) use ($dispo) {
                            $item->result = 'No Sale';
                            $item->disposition_id = $dispo->id;
                            $item->disposition_label = $dispo->brand_label;
                            $item->disposition_reason = $dispo->reason;
                            $item->save();
                        });

                    if ($request->input('notes') !== null) {
                        $notes = new EventNote();
                        $notes->tpv_staff_id = Auth::user()->id;
                        $notes->event_id = $request->event_id;
                        $notes->notes = $request->input('notes');
                        $notes->save();
                    }
                });
            }
        }

        // Update Event Flags
        $efs = EventFlag::where(
            'interaction_id',
            $interaction->id
        )->whereNull('reviewed_by')->get();
        if ($efs !== null && $efs->isNotEmpty()) {
            $efs->each(function ($ef) use ($request) {
                $ef->call_review_type_id = $request->call_review_type;
                $ef->reviewed_by = Auth::user()->id;
                $ef->save();
            });
        }
        // End Update Event Flags

        $track = new QaTracking();
        $track->tpv_staff_id = Auth::user()->id;
        $track->event_id = $request->event_id;
        $track->completed_at = Carbon::now();
        $track->qa_task_id = 4;
        $track->save();

        session()->flash('flash_message', 'Interaction was successfully updated!');

        return redirect()->route(
            'events.show',
            [
                'id' => $request->event_id,
                'qa_review' => 'true',
                'interaction' => $interaction->id
            ]
        );
    }

    public function qaupdate(Request $request)
    {
        $dispo = null;
        $interaction = null;
        $currentResult = null;
        $command = $request->input('command');

        if ($request->input('interaction_id')) {
            $interaction = Interaction::find($request->input('interaction_id'));
            $currentResult = $interaction->event_result_id;
        }
        if ($command == 'update' && $interaction !== null) {
            // this is used by the "qa toolbar"
            return $this->do_new_qa_update($request, $interaction);
            // the rest of the code in this function is the old way that is used
            // by the new qa tools in the QA Section of the Event page
        }

        if ($request->result == null || $request->result == 0) {
            session()->flash('flash_message_error', 'You must select a result.');

            return redirect()->back();
        }
        if ($request->result == 2) {
            if ($request->disposition == '') {
                session()->flash('flash_message_error', 'The disposition field is required when selected result is No Sale');

                return redirect()->back();
            }
            $dispo = Disposition::find($request->disposition);
            if ($dispo == null) {
                info('Invalid disposition: ' . $request->disposition);
                session()->flash('flash_message_error', 'Invalid disposition selected');

                return redirect()->back();
            }
        }

        $success = false;
        DB::transaction(function () use ($request, $dispo, $success) {
            // Update Event Flags
            $efs = EventFlag::where(
                'event_id',
                $request->event_id
            )->whereNull('reviewed_by')->get();
            if ($efs) {
                foreach ($efs as $ef) {
                    $ef->call_review_type_id = $request->call_review_type;
                    $ef->reviewed_by = Auth::user()->id;
                    $ef->save();
                }
            }
            // End Update Event Flags

            $track = new QaTracking();
            $track->tpv_staff_id = Auth::user()->id;
            $track->event_id = $request->event_id;
            $track->completed_at = Carbon::now();
            $track->qa_task_id = 4;
            $track->save();

            // Update Interaction
            $existing = Interaction::find($request->interaction_id);
            $i = new Interaction();
            $i->created_at = Carbon::now("America/Chicago");
            $i->updated_at = Carbon::now("America/Chicago");

            $i->tpv_staff_id = Auth::id();

            $itype = InteractionType::where('name', 'qa_update')->first();
            $i->interaction_type_id = $itype->id;

            $i->event_id = $request->event_id;

            if ($request->result !== null) {
                $i->event_result_id = $request->result;
            }

            if ($request->disposition) {
                $i->disposition_id = $request->disposition;
            }

            if ($request->result == 1) { // Good Sale
                $i->disposition_id = null;
            }

            if ($existing->event_result_id == 1 && $request->result == 2) {
                $now = Carbon::now();
                $days = $now->diffInDays($existing->created_at);
                if ($days > 0) {
                    //send research email
                    //$dispo->reason
                    $content = "The status of {$existing->event->confirmation_code} was updated to No Sale by the QA Department\n
                    Date of Call: {$existing->created_at->format('m-d-Y')}\n
                    Date of Status Update: {$now->format('m-d-Y')}\n
                    No Sale Disposition: {$dispo->reason}\n
                    QA Notes: {$request->input('notes')}";
                    Mail::to('research@tpv.com')->send(
                        new GenericEmail('Status Update to No Sale ' . $existing->event->confirmation_code, $content)
                    );
                }
            }

            $i->save();
            // End Update Interaction

            // Update Stats Product
            StatsProduct::where('event_id', $request->event_id)
                ->where('interaction_id', $existing->id)
                ->get()
                ->each(function ($item) use ($request, $dispo) {
                    switch ($request->result) {
                        case 1: // Good Sale
                            $item->result = 'Sale';
                            $item->disposition_id = null;
                            $item->disposition_label = null;
                            $item->disposition_reason = null;
                            break;

                        case 2: //No Sale
                            if ($dispo !== null) {
                                $item->result = 'No Sale';
                                $item->disposition_id = $dispo->id;
                                $item->disposition_label = $dispo->brand_label;
                                $item->disposition_reason = $dispo->reason;
                            }
                            break;

                        default: //Closed etc
                            $item->result = 'Closed';
                            $item->disposition_id = null;
                            $item->disposition_label = null;
                            $item->disposition_reason = null;
                    }
                    $item->save();
                });

            // End Update Stats Product

            if ($request->input('notes') !== null) {
                $notes = new EventNote();
                $notes->tpv_staff_id = Auth::user()->id;
                $notes->event_id = $request->event_id;
                $notes->notes = $request->input('notes');
                $notes->save();
            }

            session()->flash('flash_message', 'Result was successfully updated!');

            $success = true;
        });

        if ($success) {
            $event = Event::find($request->event_id);
            if ($event) {
                \App\Http\Controllers\QaController::event_reprocess($event);
            }
        }

        return redirect()->route(
            'events.show',
            [
                'id' => $request->event_id,
                'qa_review' => 'true',
                'interaction' => $request->interaction_id
            ]
        );
    }

    public function qa_update_event(Request $request)
    {
        $serviceAddress = $request->input('serviceAddress');
        $billingAddress = $request->input('billingAddress');
        $phone = $request->input('phone');
        $email = $request->input('email');
        $billName = $request->input('billName');
        $authName = $request->input('authName');
        $idents = $request->input('identifiers');
        $ep_id = $request->input('ep_id');
        $event_id = $request->input('event_id');
        $sync = $request->input('sync');
        if ($sync === null) {
            $sync = false;
        } else {
            if ($sync !== false) {
                $sync = true;
            }
        }
        $market = $request->input('market');

        $edata = [
            'serviceAddress' => $serviceAddress,
            'billingAddress' => $billingAddress,
            'phone' => $phone,
            'email' => $email,
            'billName' => $market !== 1 ? $billName : explode(' ', $billName), // residential is split into parts, comm is the company name
            'authName' => explode(' ', $authName),
            'identifiers' => $idents,
            'do-idents' => true,
        ];

        $event = Event::find($event_id);

        if ($request->input('notes') !== null) {
            $notes = new EventNote();
            $notes->tpv_staff_id = Auth::user()->id;
            $notes->event_id = $event_id;
            $notes->notes = $request->input('notes');
            $notes->save();
        }

        try {
            if ($sync !== true) {
                $ep = EventProduct::find($ep_id);

                $this->__do_qa_event_update($ep, $event, $edata);
            } else {
                $products = EventProduct::where('event_id', $event_id)->get();
                foreach ($products as $product) {
                    if ($product->id === $ep_id) {
                        $edata['do-idents'] = true;
                    } else {
                        $edata['do-idents'] = false;
                    }
                    $this->__do_qa_event_update($product, $event, $edata);
                }
            }
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        return response()->json(['error' => false]);
    }

    private function __do_qa_event_update($product, $event, $data)
    {
        $sp = StatsProduct::where('event_product_id', $product->id)->where('event_id', $event->id)->first();

        // Billing Name
        if ($product->market_id == 1) {
            $product->bill_first_name = $data['billName'][0];
            $sp->bill_first_name = $data['billName'][0];
            switch (count($data['billName'])) {
                case 2:
                    $product->bill_last_name = $data['billName'][1];
                    $sp->bill_last_name = $data['billName'][1];
                    break;

                case 3:
                    $product->bill_middle_name = $data['billName'][1];
                    $product->bill_last_name = $data['billName'][2];
                    $sp->bill_middle_name = $data['billName'][1];
                    $sp->bill_last_name = $data['billName'][2];
                    break;

                default:
                    throw new Exception('Invalid Billing Name Format: ' . implode(' ', $data['billName']));
            }
        } else {
            $product->company_name = $data['billName'];
            $sp->company_name = $data['billName'];
        }

        // Authorizing Name
        $product->auth_first_name = $data['authName'][0];
        $sp->auth_first_name = $data['authName'][0];
        switch (count($data['authName'])) {
            case 2:
                $product->auth_last_name = $data['authName'][1];
                $sp->auth_last_name = $data['authName'][1];
                break;

            case 3:
                $product->auth_middle_name = $data['authName'][1];
                $product->auth_last_name = $data['authName'][2];
                $sp->auth_middle_name = $data['authName'][1];
                $sp->auth_last_name = $data['authName'][2];
                break;

            default:
                throw new Exception('Invalid Authorized Name Format: ' . implode(' ', $data['authName']));
        }

        //PHone
        if ($sp->btn !== $data['phone']) {
            $p = PhoneNumber::where('phone_number', $data['phone'])->first();
            if ($p === null) {
                $p = new PhoneNumber();
                $p->phone_number = $data['phone'];
                $p->save();
            }
            $plookup = PhoneNumberLookup::where('type_id', $event->id)->first();
            if ($plookup) {
                $plookup->phone_number_id = $p->id;
                $plookup->save();
                $sp->btn = $data['phone'];
            } else {
                $plookup = new PhoneNumberLookup();
                $plookup->type_id = $event->id;
                $plookup->phone_number_id = $p->id;
                $plookup->phone_number_type_id = 3;
                $plookup->save();
            }
        }

        // Email
        if ($sp->email_address !== $data['email']) {
            $e = EmailAddress::where('email_address', $data['email'])->first();
            if ($e === null) {
                $e = new EmailAddress();
                $e->email_address = $data['email'];
                $e->save();
            }
            $elookup = EmailAddressLookup::where('type_id', $event->id)->first();
            if ($elookup) {
                $elookup->email_address_id = $e->id;
                $elookup->save();
            } else {
                $elookup = new EmailAddressLookup();
                $elookup->type_id = $event->id;
                $elookup->email_address_id = $e->id;
                $elookup->email_address_type_id = 3;
                $elookup->save();
            }

            $sp->email_address = $data['email'];
        }

        // Service Address
        $al = AddressLookup::where('id_type', 'e_p:service')->where('type_id', $product->id)->first();
        if ($data['serviceAddress'] === null) {
            if ($al !== null) {
                $al->delete();
            }
        } else {
            $existingAddress = Address::where('line_1', $data['serviceAddress']['line_1'])
                ->where('line_2', $data['serviceAddress']['line_2'])
                ->where('city', $data['serviceAddress']['city'])
                ->where('state_province', $data['serviceAddress']['state_province'])
                ->where('zip', $data['serviceAddress']['zip'])
                ->first();

            if ($existingAddress === null) {
                $ad = new Address();
                $ad->line_1 = $data['serviceAddress']['line_1'];
                $ad->line_2 = $data['serviceAddress']['line_2'];
                $ad->city = $data['serviceAddress']['city'];
                $ad->state_province = $data['serviceAddress']['state_province'];
                $ad->zip = $data['serviceAddress']['zip'];
                if (strlen($data['serviceAddress']['zip']) == 5) {
                    $ad->country_id = 1;
                } else {
                    $ad->country_id = 2;
                }
            } else {
                $ad = $existingAddress;
            }

            $sp->service_address1 = $ad->line_1;
            $sp->service_address2 = $ad->line_2;
            $sp->service_city = $ad->city;
            $sp->service_state = $ad->state_province;
            $sp->service_zip = $ad->zip;

            if (strlen($data['serviceAddress']['zip']) == 5) {
                $sp->service_country = 'United States';
            } else {
                $sp->service_country = 'Canada';
            }

            $ad->save();

            if ($al === null) {
                $al = new AddressLookup();
                $al->type_id = $product->id;
                $al->id_type = 'e_p:service';
            }
            $al->address_id = $ad->id;
            $al->save();
        }

        // Billing Address
        $al = AddressLookup::where('id_type', 'e_p:billing')->where('type_id', $product->id)->first();
        if ($data['billingAddress'] === null) {
            if ($al !== null) {
                $al->delete();
            }
            if ($ad !== null) { // reuses service address to fill in billing address
                $sp->billing_address1 = $ad->line_1;
                $sp->billing_address2 = $ad->line_2;
                $sp->billing_city = $ad->city;
                $sp->billing_state = $ad->state_province;
                $sp->billing_zip = $ad->zip;
                if (strlen($data['serviceAddress']['zip']) == 5) {
                    $sp->billing_country = 'United States';
                } else {
                    $sp->billing_country = 'Canada';
                }
            }
        } else {
            $existingAddress = Address::where('line_1', $data['billingAddress']['line_1'])
                ->where('line_2', $data['billingAddress']['line_2'])
                ->where('city', $data['billingAddress']['city'])
                ->where('state_province', $data['billingAddress']['state_province'])
                ->where('zip', $data['billingAddress']['zip'])
                ->first();

            if ($existingAddress === null) {
                $ad = new Address();
                $ad->line_1 = $data['billingAddress']['line_1'];
                $ad->line_2 = $data['billingAddress']['line_2'];
                $ad->city = $data['billingAddress']['city'];
                $ad->state_province = $data['billingAddress']['state_province'];
                $ad->zip = $data['billingAddress']['zip'];
                if (strlen($data['billingAddress']['zip']) == 5) {
                    $ad->country_id = 1;
                } else {
                    $ad->country_id = 2;
                }
            } else {
                $ad = $existingAddress;
            }

            $sp->billing_address1 = $ad->line_1;
            $sp->billing_address2 = $ad->line_2;
            $sp->billing_city = $ad->city;
            $sp->billing_state = $ad->state_province;
            $sp->billing_zip = $ad->zip;

            if (strlen($data['billingAddress']['zip']) == 5) {
                $sp->billing_country = 'United States';
            } else {
                $sp->billing_country = 'Canada';
            }

            $ad->save();
            if ($al === null) {
                $al = new AddressLookup();
                $al->type_id = $product->id;
                $al->id_type = 'e_p:billing';
            }
            $al->address_id = $ad->id;
            $al->save();
        }

        // Identifiers
        if ($data['do-idents']) {
            $i = 1;
            foreach ($data['identifiers'] as $ident) {
                $epi = EventProductIdentifier::find($ident['id']);
                if ($epi) {
                    if ($epi->identifier !== $ident['identifier']) {
                        $epi->identifier = $ident['identifier'];
                        $epi->save();
                        if ($i == 1) {
                            $sp->account_number1 = $ident['identifier'];
                        }
                        if ($i == 2) {
                            $sp->account_number2 = $ident['identifier'];
                        }
                    }
                }
                ++$i;
            }
        }

        $product->save();
        $sp->save();
    }

    public function zip_code_lookup(Request $request)
    {
        $zip = $request->input('zip');
        if (null == $zip || '' == trim($zip)) {
            return response()->json(['error_code' => 'INVALID_ZIP', 'error' => 'Zip code is invalid']);
        }

        $zips = Cache::remember(
            'zipcode_' . $zip,
            900,
            function () use ($zip) {
                return DB::table('zips')
                    ->leftJoin('states', 'states.state_abbrev', '=', 'zips.state')
                    ->select(['zips.zip', 'zips.city', 'zips.state', 'states.id as state_id'])
                    ->where('zips.zip', 'like', $zip . '%')
                    ->limit(5)
                    ->get();
            }
        );
        if (0 == count($zips)) {
            return response()->json(['error_code' => 'ZIP_NOT_FOUND', 'error' => 'Zip code not found']);
        }

        return response()->json(['error' => null, 'cities' => $zips]);
    }

    /**
     * Display the specified resource.

     *
     * @param \App\Event $event
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Event $event)
    {
        $alerts = EventAlert::where(
            'event_id',
            $event->id
        )->with('client_alert')->get();

        $event = Event::select(
            'events.id',
            'events.event_category_id',
            'events.brand_id',
            'events.created_at',
            'events.confirmation_code',
            'events.channel_id',
            'events.sales_agent_id',
            'events.vendor_id',
            'events.eztpv_id',
            'events.gps_coords',
            'events.agent_confirmation',
            'events.script_id',
            'events.digital_script_id',
            'events.lead_id',
            'events.synced'
        )->with(
            [
                'lead',
                'digital_submissions',
                'notes',
                'notes.tpvStaff',
                'brand',
                'channel',
                'customFieldStorage',
                'customFieldStorage.customField',
                'eztpv',
                'eztpv.eztpv_docs',
                'eztpv.eztpv_docs.uploads',
                'eztpv.eztpv_docs.uploads.type',
                'eztpv.eztpv_sale_type',
                'eztpv.signature_customer',
                'eztpv.signature_agent',
                'phone',
                'phone.phone_number',
                'email.email_address',
                'products.event_type',
                'products.identifiers',
                'products.identifiers.utility_account_type',
                'products.rate' => function ($query) {
                    $query->withTrashed();
                },
                'products.utility_supported_fuel',
                'products.utility_supported_fuel.utility',
                'products.utility_supported_fuel.identifiers',
                'products.rate.rate_type',
                'products.rate.rate_uom',
                'products.rate.term_type',
                'products.rate.rate_currency',
                'products.rate.product' => function ($query) {
                    $query->withTrashed();
                },
                'products.rate.product.intro_term_type',
                'products.rate.product.term_type',
                'products.rate.product.rate_type',
                'products.addresses',
                'products.customFields',
                'products.customFields.customField',
                'interactions',
                'interactions.result',
                'interactions.source',
                'interactions.disposition',
                'interactions.interaction_type',
                'interactions.recordings',
                'interactions.service_types',
                'interactions.tpv_agent',
                'interactions.event_flags',
                'interactions.event_flags.flag_reason',
                'interactions.event_flags.reviewer',
                'interactions.agent_statuses',
                'sales_agent' => function ($query) {
                    $query->withTrashed();
                },
                'sales_agent.user' => function ($query) {
                    $query->withTrashed();
                },
                'vendor' => function ($query) {
                    $query->withTrashed();
                },
                'script' => function ($query) {
                    $query->withTrashed();
                },
                'digital_script' => function ($query) {
                    $query->withTrashed();
                },
            ]
        )->where(
            'events.id',
            $event->id
        )->first();

        if (isset($event) && isset($event->eztpv) && isset($event->eztpv->eztpv_docs)) {
            foreach ($event->eztpv->eztpv_docs as $key => $doc) {
                if ($doc->preview_only === 1) {
                    unset($event->eztpv->eztpv_docs[$key]);
                }
            }
        }

        $tracking = QaTracking::select(
            'tpv_staff.first_name',
            'tpv_staff.last_name',
            'qa_tracking.completed_at'
        )->join(
            'tpv_staff',
            'tpv_staff.id',
            'qa_tracking.tpv_staff_id'
        )->where(
            'event_id',
            $event->id
        )->where(
            'qa_task_id',
            4
        )->whereNotNull(
            'completed_at'
        )->first();

        $dispositions = Disposition::where(
            'brand_id',
            $event->brand_id
        )->orderBy('fraud_indicator', 'DESC')->orderBy('reason')->get();

        $call_review_types = Cache::remember(
            'call_review_types',
            3600,
            function () {
                $types = [];
                $categories = CallReviewTypeCategory::select(
                    'id',
                    'call_review_type_category'
                )->get();

                foreach ($categories as $category) {
                    $type = CallReviewType::select('id', 'call_review_type')
                        ->where('call_review_type_category_id', $category->id)
                        ->orderBy('call_review_type')
                        ->get()
                        ->toArray();
                    $types[$category->call_review_type_category] = $type;
                }

                return $types;
            }
        );

        if ($event) {
            $event->brand->does_recording_transfer = $event->brand->recording_transfer;
            $address = null;
            if (isset($event->products) && count($event->products) > 0) {
                for ($i = 0; $i < count($event->products); ++$i) {
                    if (isset($event->products[$i]->addresses)) {
                        for ($x = 0; $x < count($event->products[$i]->addresses); ++$x) {
                            if ($event->products[$i]->addresses[$x]['id_type'] == 'e_p:service') {
                                if ($address == null) {
                                    $address = $event->products[$i]->addresses[$x]->address->line_1
                                        . ' ' . $event->products[$i]->addresses[$x]->address->line_2
                                        . ' ' . $event->products[$i]->addresses[$x]->address->city
                                        . ' ' . $event->products[$i]->addresses[$x]->address->state_province
                                        . ' ' . $event->products[$i]->addresses[$x]->address->zip;
                                }
                            }
                        }
                    }
                }
            }

            $lat = null;
            $lng = null;
            if ($event->gps_coords) {
                $split = explode(',', $event->gps_coords);
                $lat = $split[0];
                $lng = $split[1];
            }

            $states = Cache::remember(
                'all-states-by-abbrev2',
                3600,
                function () {
                    return State::select('id', 'state_abbrev as abbreviation', 'name')->orderBy('name', 'ASC')->get();
                }
            );

            // hide ets if no answers present
            if (isset($event->eztpv->has_digital)) {
                foreach ($event->interactions as $interaction) {
                    if ($interaction->interaction_type_id === 6) {
                        $answers = ScriptAnswer::where(
                            'script_answers.interaction_id',
                            $interaction->id
                        )->get();
                        if (count($answers) < 1) {
                            $event->eztpv->has_digital = 0;
                        }
                    }
                }
            }

            $qa_review = $request->input('qa_review');
            $interaction = $request->input('interaction');

            return view(
                'events.show',
                [
                    'alerts' => $alerts,
                    'call_review_types' => $call_review_types,
                    'dispositions' => $dispositions,
                    'event' => $event,
                    'service_address' => $address,
                    'tracking' => $tracking,
                    'lat' => $lat,
                    'lng' => $lng,
                    'states' => $states,
                    'fromQaReview' => $qa_review !== null,
                    'focusOn' => $interaction,
                ]
            );
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Event $event
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Event $event)
    {
    }

    //end edit()

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Event               $event
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Event $event)
    {
    }

    //end update()

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Event $event
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Event $event)
    {
    }

    //end destroy()

    public function add_notes(Request $request, $id)
    {
        if ($request->input('notes') !== null) {
            $notes = new EventNote();
            $notes->tpv_staff_id = Auth::user()->id;
            $notes->event_id = $id;
            $notes->notes = $request->input('notes');
            $notes->save();
        }

        return redirect()->back();
    }

    public function remove_from_call_review(Request $request, $id)
    {
        $ef = EventFlag::where('event_id', $id)->latest()->first();
        if ($ef) {
            $ef->reviewed_by = Auth::user()->id;
            $ef->delete();
            session()->flash('flash_message', 'The QA has been successfully removed from Call Review.');
        }
        if ($request->input('notes') !== null) {
            $notes = new EventNote();
            $notes->tpv_staff_id = Auth::user()->id;
            $notes->event_id = $id;
            $notes->notes = $request->input('notes');
            $notes->save();
        }

        return redirect()->route('qa_review.index');
    }

    public function export()
    {
        $headers = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=events.csv',
            'Expires' => '0',
            'Pragma' => 'public',
        ];

        $list = Event::get()
            ->map(
                function ($item) {
                    return collect($item)->except(
                        [
                            'id',
                            'created_at',
                            'updated_at',
                            'deleted_at',
                            'brand_id',
                        ]
                    );
                }
            )->toArray();

        // add headers for each column in the CSV download
        array_unshift($list, array_keys($list[0]));

        $callback = function () use ($list) {
            $FH = fopen('php://output', 'w');
            foreach ($list as $row) {
                fputcsv($FH, $row);
            }

            fclose($FH);
        };

        return response()->stream($callback, 200, $headers);
    }

    //end export()

    public function markAsReviewed(Request $request, $id)
    {
        $flag = $request->input('flag');

        if ($flag == null) {
            $track = new QaTracking();
            $track->tpv_staff_id = Auth::user()->id;
            $track->event_id = $id;
            $track->completed_at = Carbon::now();
            $track->qa_task_id = 4;
            $track->save();
        }

        // Update Event Flags
        if ($flag != null) {
            $efs = EventFlag::where('id', $flag)->get();
        } else {
            $internalFlagReasons = EventFlagReason::where('show_to_agents', false)->get()->pluck('id')->toArray();

            $efs = EventFlag::where(
                'event_id',
                $id
            )->whereIn('flag_reason_id', $internalFlagReasons)
                ->whereNull('reviewed_by')
                ->get();
        }
        if ($efs) {
            foreach ($efs as $ef) {
                $ef->reviewed_by = Auth::user()->id;
                $ef->save();
            }
        }
        // End Update Event Flags

        if ($request->input('notes') !== null) {
            $notes = new EventNote();
            $notes->tpv_staff_id = Auth::user()->id;
            $notes->event_id = $id;
            $notes->notes = $request->input('notes');
            $notes->save();
        }

        //if (request('qa_review') === true && in_array(Auth::user()->role_id, array('1', '3', '4'))) {
        return redirect()->route('events.show', ['id' => $id]);
        //} else {
        //    return redirect()->route('qa_review.index');
        //}
    }

    //end markAsReviewed()

    public function lookupConfirmationCode($code)
    {
        $event = Event::select(
            'events.id',
            'events.brand_id',
            'events.created_at',
            'events.confirmation_code',
            'events.channel_id',
            'events.sales_agent_id',
            'events.vendor_id',
            'events.eztpv_id'
        )->with(
            'brand',
            'channel',
            'eztpv',
            'eztpv.eztpv_docs',
            'eztpv.eztpv_docs.uploads',
            'eztpv.eztpv_docs.uploads.type',
            'phone',
            'phone.phone_number',
            'products',
            'products.event_type',
            'products.identifiers',
            'products.identifiers.utility_account_type',
            'products.rate',
            'products.rate.utility',
            'products.rate.utility.utility',
            'products.rate.rate_type',
            'products.rate.rate_uom',
            'products.rate.term_type',
            'products.rate.rate_currency',
            'products.rate.product',
            'products.rate.product.intro_term_type',
            'products.rate.product.term_type',
            'products.rate.product.rate_type',
            'products.addresses',
            'interactions',
            'interactions.result',
            'interactions.source',
            'interactions.disposition',
            'interactions.interaction_type',
            'interactions.recordings',
            'interactions.service_types',
            'interactions.tpv_agent',
            'interactions.event_flags',
            'interactions.event_flags.flag_reason',
            'sales_agent',
            'sales_agent.user',
            'vendor'
        )->where(
            'events.confirmation_code',
            $code
        )->first();

        return response()->json($event);
    }

    public function listAudits(Request $request)
    {
        $event = Event::where('id', $request->id)->first();

        $array = [];
        $interactions = Interaction::where('event_id', $request->id)->get();
        foreach ($interactions as $interaction) {
            $array[] = $interaction->audits;
        }

        return [
            'event' => $event->audits,
            'interactions' => $array,
        ];
    }

    private function brands()
    {
        return Brand::select('id', 'name')
            ->whereNotNull('client_id')
            ->orderBy('name')
            ->get();
    }

    private function languages()
    {
        return Language::select('languages.id', 'languages.language AS name')
            ->join('brand_state_languages', 'brand_state_languages.language_id', 'languages.id')
            ->join('brand_states', 'brand_state_languages.brand_state_id', 'brand_states.id')
            ->groupBy('languages.id', 'name')
            ->get();
    }

    private function listToArray($items)
    {
        if (is_array($items)) {
            return $items;
        }

        if (is_string($items) && strlen(trim($items)) > 0) {
            return explode(',', $items);
        }

        return [];
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
}