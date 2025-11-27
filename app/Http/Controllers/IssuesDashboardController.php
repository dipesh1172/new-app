<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\TextMessage;
use App\Models\Recording;
use App\Models\Interaction;
use App\Models\EztpvDocument;
use App\Models\Eztpv;
use App\Models\BrandEnrollmentFile;

class IssuesDashboardController extends Controller
{
    private $end_date;
    private $start_date;

    public function __construct()
    {
        $this->end_date = Carbon::now()->subMinutes(30)->format('Y-m-d H:i:s');
        $this->start_date = Carbon::today()->setTime(7, 0, 0)->format('Y-m-d H:i:s');
    }

    public function issues()
    {
        return view('dashboard/issues_dashboard');
    }

    public function failed_sms()
    {
        return TextMessage::whereIn('status', ['failed', 'undeliverable'])->with(['brand', 'to_phone'])->orderBy('updated_at', 'desc')->get();
    }

    public function failed_sms_error_check($sid)
    {
        $client = new \Twilio\Rest\Client(config('services.twilio.account'), config('services.twilio.auth_token'));
        $msg = $client->messages($sid)->fetch();

        return response()->json(['error' => $msg->errorMessage]);
    }

    public function calls_without_records()
    {
        return Interaction::select(
            'interactions.created_at',
            'interactions.event_result_id',
            'interactions.interaction_time',
            'interactions.interaction_type_id',
            'interactions.id',
            'events.confirmation_code',
            'interactions.event_id'
        )->leftJoin(
            'events',
            'events.id',
            'interactions.event_id'
        )->where(
            'interactions.interaction_time',
            '>',
            0
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2]
        )
            //->whereNull('events.survey_id')
            ->whereNull('events.deleted_at')
            ->whereBetween(
                'interactions.created_at',
                [$this->start_date, $this->end_date]
            )->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('recordings')
                    ->whereRaw('recordings.interaction_id = interactions.id')
                    ->whereDate('recordings.created_at', Carbon::today());
            })->limit(10)->latest('interactions.created_at')->get();
    }

    public function eztpv_without_contracts()
    {
        return Eztpv::select(
            'eztpvs.ip_addr',
            'eztpvs.id',
            'eztpvs.signature_date',
            'eztpvs.created_at',
            'eztpvs.user_id',
            'eztpv_documents.eztpv_id',
            'brands.name',
            'stats_product.confirmation_code',
            'stats_product.event_id'
        )->leftJoin(
            'eztpv_documents',
            'eztpvs.id',
            'eztpv_documents.eztpv_id'
        )->join(
            'stats_product',
            'eztpvs.id',
            'stats_product.eztpv_id'
        )->leftJoin(
            'brands',
            'eztpvs.brand_id',
            'brands.id'
        )->whereBetween(
            'eztpvs.created_at',
            [$this->start_date, $this->end_date]
        )->whereBetween(
            'stats_product.event_created_at',
            [$this->start_date, $this->end_date]
        )->where(
            'stats_product.result',
            'Sale'
        )->where(
            'eztpvs.contract_type',
            '!=',
            0
        )->orderBy(
            'eztpvs.created_at',
            'desc'
        )->get()->filter(function ($eztpv) {
            return is_null($eztpv->eztpv_id);
        })->values();
    }

    public function last_result_bef_per_brand()
    {
        //Carbon::yesterday() because files are generated at nigth
        $befs = BrandEnrollmentFile::select(
            'brand_enrollment_files.run_history',
            'brand_enrollment_files.next_run',
            'brand_enrollment_files.last_run',
            'brand_enrollment_files.brand_id',
            'brands.name'
        )->leftJoin(
            'brands',
            'brand_enrollment_files.brand_id',
            'brands.id'
        )->whereNotNull(
            'brand_enrollment_files.run_history'
        )->where(
            'brand_enrollment_files.run_history',
            '!=',
            ''
        )
            // ->whereDate(
            //     'brand_enrollment_files.updated_at',
            //     Carbon::yesterday()
            // )
            ->orderBy('brands.name');

        //dd($befs->fullSQL());

        $befs = $befs->get()->map(function ($bef) {
            $final_history = json_decode($bef->run_history, true);
            if (is_array($final_history)) {
                $final_history = array_pop($final_history);
            }
            //It migth be a single array or an array of arrays
            $bef->last_history = is_array($final_history) ? array_pop($final_history) : $final_history;
            unset($bef->run_history);

            return $bef;
        });

        return $befs;
    }

    public function last_recording()
    {
        //recordings is huge (ergo slow) so it needs to be filtered by date. We should get entries every day but just in case
        //I'm using a 3 days range which should be more than enough and faster. Same for eztpv_documents
        $last_recording = Recording::select(
            'recordings.created_at'
        )->whereDate(
            'recordings.created_at',
            '>=',
            Carbon::now()->subDays(3)
        )->whereDate(
            'recordings.created_at',
            '<=',
            Carbon::today()
        )->latest()->first();

        if ($last_recording) {
            return ['created_at' => Carbon::parse($last_recording->created_at)->diffForHumans(Carbon::now())];
        } else {
            return ['created_at' => 'No recording was found.'];
        }
    }

    public function last_contract()
    {
        $last_contract = EztpvDocument::select(
            'eztpv_documents.created_at'
        )->leftJoin(
            'eztpvs',
            'eztpv_documents.eztpv_id',
            'eztpvs.id'
        )->where(
            'eztpvs.contract_type',
            '!=',
            0
        )->whereDate(
            'eztpv_documents.created_at',
            '>=',
            Carbon::now()->subDays(3)
        )->whereDate(
            'eztpv_documents.created_at',
            '<=',
            Carbon::today()
        )->latest()->first();

        if ($last_contract) {
            return ['created_at' => $last_contract->created_at->diffForHumans(Carbon::now())];
        } else {
            return ['created_at' => 'No eztpv document was found.'];
        }
    }
}
