<?php

namespace App\Listeners;

use App\Models\JsonDocument;
use App\Events\SurveyReadyToImport;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ImportSurvey implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param SurveyReadyToImport $event
     */
    public function handle(SurveyReadyToImport $event)
    {
        Log::debug('ImportSurvey was executed.');
        
        $event->upload->processing = 1;
        $event->upload->save();

        $params = [
            '--brand' => $event->brand,
            '--upload' => $event->upload->id,
            '--state' => $event->state,
            '--script' => $event->script_id,
        ];

        $exitCode = Artisan::call(
            'survey:import',
            $params
        );

        info('Survey Import Exit', [$exitCode]);

        if ($exitCode !== 0) {
            $j = JsonDocument::where('ref_id', $event->upload->id)->first();
            $event->upload->processing = 3;
            if ($j === null) {
                $j = new JsonDocument();
                $j->document_type = 'upload-errors';
                $j->ref_id = $event->upload->id;
                $j->document = ['errorCode' => $exitCode];
            } else {
                $newDoc = $j->document;
                $newDoc['errorCode'] = $exitCode;
                $j->document = $newDoc;
            }

            $j->save();
        } else {
            $event->upload->processing = 0;
        }
        $event->upload->processed_at = \Carbon\Carbon::now();
        $event->upload->save();
    }
}
