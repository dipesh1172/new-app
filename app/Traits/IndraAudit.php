<?php 

namespace App\Traits;

use App\Models\Brand;
use DB;
use Log;

trait IndraAudit
{
    public function getIndraRealTimeReport($request)
    {
        $column = $request['column'] ?? '';
        $direction = $request['direction'] ?? '';
        $search = strtolower($request['search'] ?? '');
        $searchField = $request['searchField'] ?? '';
        $start_date = $request['startDate'] ?? '';
        $end_date = $request['endDate'] ?? '';
        $brandIds = $request['brandId'] ?? [];

        // $start_date = '2022-05-16 12:00:00';
        // $end_date = '2022-05-16 13:00:00';

        $check_filesize = $request['checkFileSize'] ?? true;
        $check_filesize = false;

        $eventsReviewed = [];
        $eventsToExclude = [];
        $eventsToInclude = [];
        
        $sql = "SELECT event_id from indra_audits where reviewed=1";
        foreach(DB::select($sql) as $row)
            $eventsReviewed[] = "'" . $row->event_id . "'";

        if($searchField == 'reviewed' && $search !== "") {

            if($search === 'y') {
                $eventsToInclude = $eventsReviewed;
            } else if($search === 'n') {
                $eventsToExclude = $eventsReviewed;
            }

        }

        // $brand = Brand::where('name', 'Indra Energy')->first();
            
        $sql = "SELECT events.created_at as event_created_at
                       , b.name as brand_name
                       , events.id as event_id
                       , events.confirmation_code
                       , group_concat(u.filename) as contracts
                       , group_concat(es.source) as source
                       , group_concat(et.event_type) as commodity_type
                       , '' as commodity
                       , '' as filesize
                       , 1 as contracts_valid
                       , if(isnull(cq.created_at), 0, 1) as sent_to_queue
                       , ia.good_or_bad
                       , ia.comment
                FROM events 
                LEFT JOIN indra_audits ia ON events.id=ia.event_id
                LEFT JOIN contractQueue cq ON events.confirmation_code=cq.confirmation_code
                JOIN brands b on events.brand_id = b.id
                LEFT JOIN eztpv_documents ed on events.id = ed.event_id
                LEFT JOIN uploads u on ed.uploads_id = u.id
                JOIN interactions i on events.id = i.event_id
                JOIN event_sources es on i.event_source_id = es.id
                JOIN event_product ep on events.id = ep.event_id
                JOIN event_types et on ep.event_type_id = et.id
                WHERE   
                        events.channel_id != 2 and
                        i.event_result_id = 1 and
                        i.deleted_at is null and es.deleted_at is null and
                        et.deleted_at is null and 
                        u.deleted_at is null and ed.deleted_at is null";

        if($brandIds && count($brandIds) > 0) {
            $strBrands = "'" . implode("','", $brandIds) . "'";
            $sql .= " and events.brand_id in ($strBrands)";
        }

        if(count($eventsToExclude) > 0) {
            $sql .= " and events.id not in (" . implode(",", $eventsToExclude) . ")";
        }

        if(count($eventsToInclude) > 0) {
            $sql .= " and events.id in (" . implode(",", $eventsToInclude) . ")";
        }

        if($searchField == 'good_or_bad' && $search !== "") {
            if($search === 'g') {
                $sql .= " and ia.good_or_bad=1";
            } else if($search === 'b') {
                $sql .= " and ia.good_or_bad=0";
            }
        }

        if($searchField === "event_id" && !empty($search)) {
            $sql .= " and events.id like '%$search%'";
        }

        if($searchField === "confirmation_code" && !empty($search)) {
            $sql .= " and events.confirmation_code like '%$search%'";
        }

        if(!empty($start_date) && !empty($end_date)) {
            $sql .= " and events.created_at >= '$start_date 00:00:00' and events.created_at <= '$end_date 23:59:59'";
        }

        $sql .= " GROUP BY events.confirmation_code";
        $sql .=" order by events.created_at desc";

        $results = DB::select($sql);
        $comm = [
            'Natural Gas' => 'gas',
            'Electric' => 'electric'
        ];

        // $urls = [];

        foreach($results as &$row) {
            // list($cntValid, $size) =
            //     $check_filesize 
            //         ? $this->isContractsPDFValid($row->contracts ?? '')
            //         : [!empty($row->contracts) ? 1 : 0, ''];

            // if(!empty($row->contracts)) {
            //     $urls = array_merge($urls, explode(",", $row->contracts));
            // }

            // $row->contracts_valid = $cntValid >= 1;
            // $row->filesize = $size;

            $row->reviewed = in_array("'$row->event_id'", $eventsReviewed);
            
            $row->commodity = (strpos($row->commodity_type, 'Electric') !== false && strpos($row->commodity_type, 'Natural Gas') !== false) ? 'dual' : ($comm[$row->commodity_type] ?? $row->commodity_type);
            $row->commodity_type = (strpos($row->commodity_type, 'Electric') !== false && strpos($row->commodity_type, 'Natural Gas') !== false) ? 'Dual Fuel' : $row->commodity_type;
        }

        if(!is_null($search) && $search !== "") {
            switch($searchField) {
                case "contracts_valid":
                    $results = array_filter($results, function($row) use ($search) {
                        $yCondition = $search === 'y';
                        $nCondition = $search === 'n';
                        return ($yCondition === (bool)$row->contracts_valid) || ($nCondition !== (bool)$row->contracts_valid);
                    });
                    break;
                case "sent_to_queue":
                    $results = array_filter($results, function($row) use ($search) {
                        $yCondition = $search === 'y';
                        $nCondition = $search === 'n';
                        return ($yCondition === (bool)$row->sent_to_queue) || ($nCondition !== (bool)$row->sent_to_queue);
                    });
                    break;
            }
        }

        return $results;
    }

    private function isContractsPDFValid($contracts) {
        $cntValid = 0;
        $size = "";
    
        $contracts = explode(",", $contracts);
        foreach($contracts as $contract) {
            $url = "https://tpv-live.s3.amazonaws.com/" . $contract;
            // $url = "https://tpv-live.s3.amazonaws.com/uploads/pdfs/eb35e952-04fc-42a9-a47d-715a328125c0/09f71ad9-dcac-49a2-9784-5c8c6cefbc22/2022-04-21/103e91b1a79fc44405498b91081c02f5.pdf";
            $headers = get_headers($url, true);
            if($headers && !empty($headers['Content-Type']) && $headers['Content-Type'] === 'application/pdf' && $headers['Content-Length'] > 50*1000)
                $cntValid ++;
    
            if($headers && !empty($headers['Content-Type']) && $headers['Content-Type'] === 'application/pdf')
                $size .= round($headers['Content-Length']/1000) . "KB";
            else
                $size .= "?";
    
            $size .= ",";
        }
    
        return [$cntValid, trim($size, ",")];
    }

}
