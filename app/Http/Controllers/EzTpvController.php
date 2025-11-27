<?php

namespace App\Http\Controllers;

use App\Models\EztpvDocument;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Artisan;
use App\Traits\ContractTrait;

class EzTpvController extends Controller
{
    use ContractTrait;

    public function contracts(Request $request)
    {
        $search = $request->get('search');

        $contracts = EztpvDocument::select(
            'events.id AS event_id',
            'eztpv_documents.created_at',
            'events.confirmation_code',
            'brands.name',
            'uploads.filename'
        )->join(
            'events',
            'eztpv_documents.eztpv_id',
            'events.eztpv_id'
        )->join(
            'brands',
            'events.brand_id',
            'brands.id'
        )->join(
            'uploads',
            'eztpv_documents.uploads_id',
            'uploads.id'
        )->where(
            'eztpv_documents.created_at',
            '>=',
            '2018-11-06 00:00:00'
        );

        if ($search != null) {
            $contracts = $contracts->where(
                'events.confirmation_code',
                $search
            );
        }

        $contracts = $contracts->withTrashed()
            ->orderBy(
                'eztpv_documents.created_at'
            )->with(
                'event',
                'event.interactions',
                'event.interactions.recordings'
            )->paginate(50);

        // echo "<pre>";
        // print_r($contracts->toArray());
        // exit();

        return view(
            'eztpv.contracts',
            [
                'contracts' => $contracts,
            ]
        );
    }

    public function previewSignature(Request $request, $id)
    {
        $debug = ($request->debug == 1) ? true : false;
        $no_sale_allowed = ($request->no_sale_allowed == 1) ? false : true;
        // echo "DEBUG IS " . $debug . "<br />";

        $task = $this->generateContract($id, true, $debug, $no_sale_allowed);
        if ($task['error']) {
            echo ' -- Error for ' . $id .
                ' : ' . $task['message'] . "\n";
        } else {
            // echo "<pre>";
            // print_r($task);
            // exit();
            if ($task) {
                $contents = file_get_contents($task['file']);

                @unlink($task['file']);
                header('Content-type: application/pdf');
                header('Content-Disposition: inline; filename="preview.pdf"');
                header('Content-Transfer-Encoding: binary');
                header('Accept-Ranges: bytes');
                echo $contents;
            } else {
                echo "Unable to generate a document with this confirmation code.  It may not be good saled.  Try adding ?no_sale_allowed=1 to the end of the url.\n";
            }
        }
    }
}
