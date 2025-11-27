<?php 

namespace App\Traits;

use App\Models\Eztpv;
use App\Models\EztpvDocument;
use App\Models\Interaction;


trait InvoicingTrait
{
    public function getMinutesByBrand($brand_id, $start_date, $end_date)
    {
        $minutes = Interaction::sum('interactions.interaction_time')
            ->whereBetween('interactions.created_at', [$start_date, $end_date])
            ->leftJoin('events', 'interactions.event_id', 'events.id')
            ->where('events.brand_id', $brand_id)
            ->get();

        return $minutes;
    }

    public function getMinutesByBrandWithLanguageBreakdown($brand_id, $start_date, $end_date)
    {
        $minutes = Interaction::whereBetween('interactions.created_at', [$start_date, $end_date])
            ->leftJoin('events', 'interactions.event_id', 'events.id')
            ->where('events.brand_id', $brand_id)
            ->leftJoin('languages', 'events.language_id', 'languages.id')
            ->get();

        $total = $minutes->sum('interaction_time');
        $spanish = $minutes->where('language', 'Spanish')->sum('interaction_time');
        $english = $minutes->where('language', 'English')->sum('interaction_time');

        return $breakdown = ['total' => $total, 'spanish' => $spanish, 'english' => $english];
    }

    public function getEztpvByBrand($brand_id, $date)
    {
        return Eztpv::where(
            'brand_id', 
            $brand_id
        )
        ->whereDate('created_at', date('Y-m-d', strtotime($date)))
        ->get();
    }

    public function getDocServiceContractsByBrand($brand_id, $date)
    {
        return EztpvDocument::leftJoin(
            'events',
            'eztpv_documents.event_id',
            'events.id'
        )
        ->where('events.brand_id', $brand_id)
        ->whereDate('eztpv_documents.created_at', date('Y-m-d', strtotime($date)))
        ->leftJoin('uploads', 'eztpv_documents.uploads_id', 'uploads.id')
        ->where('uploads.upload_type_id', 3)
        ->get();
    }

    public function getDocServicePhotosByBrand($brand_id, $date)
    {
        return EztpvDocument::leftJoin(
            'events',
            'eztpv_documents.event_id',
            'events.id'
        )
        ->where('events.brand_id', $brand_id)
        ->whereDate('eztpv_documents.created_at', date('Y-m-d', strtotime($date)))
        ->leftJoin('uploads', 'eztpv_documents.uploads_id', 'uploads.id')
        ->where('uploads.upload_type_id', 4)
        ->get();
    }

    public function getInteractionsByBrandAndDate($brand_id, $date)
    {
        // Grab all minutes except IVR (event_source_id = 2)
        $interactions = Interaction::leftJoin(
            'events', 
            'interactions.event_id',
            'events.id'
        )
        ->where('events.brand_id', $brand_id)
        ->where('interactions.event_source_id', '!=', 2)
        ->whereDate('interactions.created_at', date('Y-m-d', strtotime($date)))
        ->with(
            'source',
            'interaction_type',
            'event.language',
            'result',
            'event.channel',
            'event.documents.uploads.type',
            'event.brand.dnis'
        )
        ->get();

        return $interactions;
    }
}