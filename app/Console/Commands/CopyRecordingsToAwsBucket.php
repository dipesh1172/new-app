<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Aws\S3\S3Client;

use App\Models\Event;

class CopyRecordingsToAwsBucket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recordings:copy-to-aws-bucket {--brand=} {--vendor=} {--bucket=} {--root=} {--confirmation-code=} {--start-date=} {--end-date=} {--limit=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $cloudfront;
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
        $scriptTimeStart = microtime(true);

        // Validate args
        if(!$this->option('brand')) {
            $this->error('Missing required arg: --brand');

            exit -1;
        }

        if(!$this->option('bucket')) {
            $this->error('Missing required arg: --bucket');

            exit -1;
        }

        // Start date is required if confirmation code is not provided
        if(!$this->option('start-date') && !$this->option('confirmation-code')) {
            $this->error('Missing required arg: --start-date');

            exit -1;
        }

        // End date is required if confirmation code is not provided
        if(!$this->option('end-date') && !$this->option('confirmation-code')) {
            $this->error('Missing required arg: --end-date');

            exit -1;
        }

        // Confirmation is required if start date or end date is NOT provided
        if(!$this->option('confirmation-code') && (!$this->option('start-date') || !$this->option('end-date'))) {
            $this->error('Missing required arg: --end-date');

            exit -1;
        }

        $brand  = $this->option('brand');
        $vendor = $this->option('vendor');
        $bucket = $this->option('bucket');
        $root   = $this->option('root');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $confirmationCode = $this->option('confirmation-code');
        $limit = $this->option('limit');

        $localDir = public_path('/tmp/recording_uploads/');

        // Get the cloudfront URL
        $this->cloudfront = config('services.aws.cloudfront.domain');

        // Create S3 client
        $s3Client = $this->createS3Client();

        if(!$s3Client) {
            $this->error('Error Creating S3 Client');

            exit -1;
        }
     

        // Build query to get recordings to copy
        $data = Event::select(
            'events.confirmation_code',
            'i.id AS interaction_id',
            'i.created_at',
            'i.interaction_type_id',
            'r.recording'
        )->join('interactions AS i', function($join) {
            $join->on('events.id', 'i.event_id');
            $join->whereIn('i.interaction_type_id', [1, 2]);
        })
            ->join('recordings AS r', 'i.id', 'r.interaction_id')
            ->where('events.brand_id', $brand);

        if($vendor) {
            $data = $data->where('events.vendor_id', $vendor);
        }

        // Search by confirmation code or date range?
        if($confirmationCode) {
            $data = $data->where('events.confirmation_code', $confirmationCode);
        } else {
            $data = $data->where('i.created_at', '>', $startDate)
                ->where('i.created_at', '<', $endDate);
        }

        $data = $data->whereNull('i.deleted_at')
            ->orderBy('confirmation_code')
            ->orderBy('i.created_at');

        if($limit) {
            $data = $data->limit($limit);
        }

        $this->info($data->toSql());
        print_r($data->getBindings());
        
        $data = $data->get();

        if(count($data) == 0) {
            $this->info("No recordings found");
            exit -1;
        }

        $count = count($data);
        $ctr = 0;

        $logFile = fopen($localDir . 'recordings_log.csv', 'w');

        fputcsv($logFile, ['confirmation_code', 'interaction_id', 'recording', 'result']);

        foreach($data as $rec) {
            $recordTimeStart = microtime(true);

            $ctr++;

            $this->info("--------------------------------------------------");
            $this->info("[ $ctr / $count]");

            $this->info("");
            $this->info("Conf#:     " . $rec->confirmation_code);
            $this->info("Recording: " . $rec->recording);

            $log = [
                'confirmation_code' => $rec->confirmation_code,
                'interaction_id' => $rec->interaction_id,
                'recording' => $rec->recording,
                'result' => ''
            ];

            try {
                $awsFile = $this->cloudfront . '/' . $rec->recording;

                // Create filename for local copy
                // <conf_code>-<Ymd>-<His>.mp3
                $localFileName = $rec->confirmation_code . '-' . $rec->created_at->format('Ymd-His') . '.mp3';

                // Download file from S3, using the local file naming convetion.
                $content = @file_put_contents(
                    ($localDir . $localFileName),
                    file_get_contents($awsFile)
                );

                // Open local file. This will be uploaded to the specified S3 bucket.
                $stream = fopen($localDir . $localFileName, 'r');

                // Create S3 key for this file.
                $remoteFilename = ($root ? ($root . '/') : '') . $rec->created_at->format('Y/m/') . $localFileName;

                // Upload file contents to S3
                $s3Client->putObject([
                    'Bucket' => $bucket,
                    'Key' => $remoteFilename,
                    'Body' => $stream
                ]);

                fclose($stream);

                // Delete local file
                unlink($localDir . $localFileName);

                $log['result'] = 'Successfully uploaded.';

            } catch (\Exception $e) {
                $log['result'] = 'Error: ' . $e->getMessage();
            }

            // Write to log
            fputcsv($logFile, $log);

            $recordTimeEnd     = microtime(true);
            $recordTimeDiff    = round($recordTimeEnd - $recordTimeStart, 4);
            $scriptTimeElapsed = round($recordTimeEnd - $scriptTimeStart, 4);

            $this->info("Upload took " . $recordTimeDiff . " seconds");
            $this->info("Elapsed time (seconds): " . $scriptTimeElapsed);
        }

        fclose($logFile);
    }

    /**
     * Create S3 client
     */
    private function createS3Client()
    {
        $s3Client = null;

        try {
                
            $s3Client = new S3Client([
                'version' => '2006-03-01',
                'region'  => config('services.aws.region'),
                'credentials' => [
                    'key'    => config('services.aws.key'),
                    'secret' => config('services.aws.secret')
                ]
            ]);

            return $s3Client;

        } catch (\Exception $e) {
            ; // Do nothing; we'll return null.
        }

        return null;
    }
}
