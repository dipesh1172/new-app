<?php

namespace App\Http\Controllers\API;

use App\Helpers\FtpHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

/**
 * Class NrgRecordingApiController
 * @package App\Http\Controllers\API
 */
class NrgRecordingApiController extends Controller
{
    protected $mode = '';

    protected $env = 'prod';

    protected $brand = 'Nrg';

    protected $ftpSettings = null;

    protected $brandId = [
        'xoom'          => '99e84b55-26ed-4469-b9be-66e94e73e416',
        'directEnergy'  => '94d29d20-0bcf-49a3-a261-7b0c883cbd1d',
        'reliantEnergy' => 'a56d5655-1de7-4aa5-9a76-bd2a2cda9e17',
        'greenMountain' => '7b0a45c2-a459-4810-9a51-b2c4c78c127e',
        'nrg'           => '7c8552f9-a40f-4952-8fcb-46ae5b0e9b1d',
    ];

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getRecords(Request $request): JsonResponse
    {
        $data       = $request->all();
        $brandName  = $data['brandName'];
        $dateFrom   = $data['dateFrom'];
        $dateTo     = $data['dateTo'];

        $recordings = DB::select("
            select distinct
                e.confirmation_code,
                i.created_at as created_at,
                r.recording as link
            from events e
            join interactions i on e.id = i.event_id
            join recordings r on i.id = r.interaction_id
            join event_product ep on e.id = ep.event_id
            join custom_field_storages cfs on e.id = cfs.event_id and cfs.product_id is null
            join custom_fields cf on cfs.custom_field_id = cf.id
            where e.brand_id = ?
            AND i.created_at >= ?
            AND i.created_at <= ?    
        ",[$this->brandId[$brandName], $dateFrom, $dateTo]);

        // return json_encode($recordings);
        return response()->json($recordings, 200);
    }

    /**
     * Test function
     */
    public function ping(Request $request)
    {
        return "Echoing loud and clear";
    }
}
