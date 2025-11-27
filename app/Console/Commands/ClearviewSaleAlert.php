<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\JsonDocument;
use App\Models\Interaction;
use App\Models\Brand;

class ClearviewSaleAlert extends Command
{
    protected $brand;
    protected $brand_name = 'Clearview Energy';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clearview:sale:alert {--sales=8} {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Clearview an alert if a sales agent has x+ sales in a day.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function getSales($agent_id)
    {
        if (!$this->brand) {
            $this->brand = Brand::where('name', $this->brand_name)->first();
        }

        if ($this->brand) {
            return Interaction::select(
                DB::raw('CONCAT("' . config('app.urls.clients') . '", "/events/", events.id) AS url'),
                'events.created_at',
                'events.confirmation_code'
            )->leftJoin(
                'events',
                'interactions.event_id',
                'events.id'
            )->leftJoin(
                'brand_users',
                'events.sales_agent_id',
                'brand_users.id'
            )->leftJoin(
                'users',
                'brand_users.user_id',
                'users.id'
            )->leftJoin(
                'brands AS v',
                'events.brand_id',
                'v.id'
            )->leftJoin(
                'brand_user_offices',
                'brand_users.id',
                'brand_user_offices.brand_user_id'
            )->leftJoin(
                'offices',
                'brand_user_offices.office_id',
                'offices.id'
            )->where(
                'events.brand_id',
                $this->brand->id
            )->where(
                'interactions.event_result_id',
                1
            )->whereDate(
                'events.created_at',
                ($this->option('date'))
                    ? $this->option('date')
                    : Carbon::now('America/Chicago')->format('Y-m-d')
            )->whereNull(
                'events.deleted_at'
            )->where(
                'events.sales_agent_id',
                $agent_id
            )->orderBy(
                'events.created_at'
            )->get()->toArray();
        }

        return [];
    }

    public function getInteractions($date)
    {
        if (!$this->brand) {
            $this->brand = Brand::where('name', $this->brand_name)->first();
        }

        return Interaction::select(
            'events.sales_agent_id',
            'events.created_at',
            'users.first_name',
            'users.last_name',
            'brand_users.tsr_id',
            'v.name AS vendor_name',
            'offices.name AS office_name',
            DB::raw('COUNT(*) as count')
        )->leftJoin(
            'events',
            'interactions.event_id',
            'events.id'
        )->leftJoin(
            'brand_users',
            'events.sales_agent_id',
            'brand_users.id'
        )->leftJoin(
            'users',
            'brand_users.user_id',
            'users.id'
        )->leftJoin(
            'brands AS v',
            'events.brand_id',
            'v.id'
        )->leftJoin(
            'brand_user_offices',
            'brand_users.id',
            'brand_user_offices.brand_user_id'
        )->leftJoin(
            'offices',
            'brand_user_offices.office_id',
            'offices.id'
        )->where(
            'events.brand_id',
            $this->brand->id
        )->where(
            'interactions.event_result_id',
            1
        )->whereDate(
            'events.created_at',
            $date
        )->whereNull(
            'events.deleted_at'
        )->groupBy(
            'events.sales_agent_id'
        )->having(
            DB::raw('count(*)'),
            '>=',
            $this->option('sales')
        )->orderBy(
            DB::raw('count(*)'),
            'desc'
        )->get();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->brand) {
            $this->brand = Brand::where('name', $this->brand_name)->first();
        }

        if ($this->brand) {
            $date = ($this->option('date'))
                ? $this->option('date')
                : Carbon::now()->format('Y-m-d');
            $interactions = $this->getInteractions($date);
            if ($interactions) {
                foreach ($interactions as $interaction) {
                    $hash = $date . '-' . $interaction->sales_agent_id;
                    $json = JsonDocument::where(
                        'document',
                        '"' . $hash . '"'
                    )->where(
                        'ref_id',
                        $this->brand->id
                    )->where(
                        'document_type',
                        'clearview-sales-agent-alert'
                    )->first();
                    if (!$json) {
                        $subject = 'Sale Alert :: ' . $interaction->first_name . ' '
                            . $interaction->last_name . ' (' . $interaction->tsr_id . ')';
                        $data = [
                            'date' => $interaction->created_at,
                            'first_name' => $interaction->first_name,
                            'last_name' => $interaction->last_name,
                            'tsr_id' => $interaction->tsr_id,
                            'office' => $interaction->office_name,
                            'vendor' => $interaction->vendor_name,
                            'sales' => $this->getSales($interaction->sales_agent_id),
                        ];

                        if (config('app.env', 'production') !== 'production') {
                            $email_address = [
                                'brian@tpv.com',
                                'paul@tpv.com',
                                'lauren@tpv.com'
                            ];
                        } else {
                            $email_address = [
                                'salesteam@clearviewenergy.com'
                            ];
                        }

                        if (!empty($email_address)) {
                            Mail::send(
                                'emails.clearviewSalesAlert',
                                $data,
                                function ($message) use ($subject, $email_address) {
                                    $message->subject($subject);
                                    $message->from('no-reply@tpvhub.com');
                                    $message->to($email_address);
                                }
                            );

                            $json = new JsonDocument();
                            $json->document_type = 'clearview-sales-agent-alert';
                            $json->document = $hash;
                            $json->ref_id = $this->brand->id;
                            $json->save();

                            echo "Clearview Sales Agent alert triggered for " . $interaction->tsr_id . ".\n";
                        }
                    } else {
                        echo "Clearview Sales Agent alert was already triggered for " . $interaction->tsr_id . ".\n";
                    }
                }
            }
        }
    }
}
