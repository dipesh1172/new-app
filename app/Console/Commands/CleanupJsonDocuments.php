<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\JsonDocument;

class CleanupJsonDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:json:documents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup the JSON Documents table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $docTypes = [
            // 30 minutes
            30 => [
                'stats-job-2',
                'live-agent-stats',
                'liveagent:cache',
            ],
            // 90 days
            129600 => [
                'atlantic-live-enroll',
                'site-errors',
                'live-enrollments',
                'live-enrollment',
                'live-enrollment-errors',
                'txu-credit-check REQ',
                'txu-credit-check RES',
                'asa-calculation',
                'create-survey-api',
                'tpv-api',
                'Indra Active API',
                'Genie Eligibility Check',
                'eztpv-preview',
                'upload-errors',
                'clearview-sales-agent-alert',
                'upload-parameters',
                'create-lead-api',
                'vendor-live-enrollment',
                'southstar-http-post',
                'santanna-http-post',
            ],
        ];

        foreach ($docTypes as $key => $docType) {
            JsonDocument::where(
                'created_at',
                '<=',
                Carbon::now()->subMinutes($key)
            )->whereIn(
                'document_type',
                $docType
            )->forceDelete();
        }
    }
}
