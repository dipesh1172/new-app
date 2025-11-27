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
use Carbon\Carbon;

/**
 * Class SolomonRecordingApiController
 * @package App\Http\Controllers\API
 */
class SolomonRecordingApiController extends Controller
{
    protected $mode = '';

    protected $env = 'prod';

    protected $brand = 'SolomonSolar';

    protected $ftpSettings = null;

    protected $brandId = [
        'prod' => 'e156442c-3edd-4a15-9ee8-ca2e97ec2e6f',
        'stage' => ''
    ];

    public function getRecords()
    {
        $outputName = 'account_number';
        $startDate = Carbon::now('America/Chicago')->format('Y-m-d');

        $recordings = DB::select("
            select 
                e.confirmation_code,
                i.created_at as created_at,
                ep.auth_first_name as first_name,
                ep.auth_last_name as last_name,
                cf.name as cf_name,
                cfs.value as cf_value,
                r.recording as link
            from events e
            join interactions i on e.id = i.event_id
            join recordings r on i.id = r.interaction_id
            join event_product ep on e.id = ep.event_id
            join custom_field_storages cfs on e.id = cfs.event_id and cfs.product_id is null
            join custom_fields cf on cfs.custom_field_id = cf.id
            where e.brand_id = ?
            and cf.output_name = ?
            and i.created_at >= ?
        ", [$this->brandId['prod'], $outputName, $startDate]);

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
