<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Aws\ElasticTranscoder\ElasticTranscoderClient;
use Aws\Credentials\Credentials;
use Aws\Credentials\CredentialProvider;
use App\Models\KB\Video;

class StartVideoConversion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $video;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $preset_id = config('services.aws.transcode.preset');
        $pipeline_id = config('services.aws.transcode.pipeline');
        if ($pipeline_id == null) {
            info('No pipleline was provided in config to convert ' . $this->video->slug, $this->config);
            return;
        }

        # All inputs will have this prefix prepened to their input key.
        $input_key = $this->video->path;

        # All outputs will have this prefix prepended to their output key.
        $output_key_prefix = 'videos/';

        # Create the client for Elastic Transcoder.
        $transcoder_client = new ElasticTranscoderClient([
            'credentials' => CredentialProvider::fromCredentials(new Credentials(config('services.aws.key'), config('services.aws.secret'))),
            'region' => config('services.aws.region'),
            'version' => '2012-09-25',
        ]);
        $job = $this->create_elastic_transcoder_job($transcoder_client, $pipeline_id, $input_key, $preset_id, $output_key_prefix);
        $this->video->job_id = $job['Job']['Id'];
        $this->video->status = json_encode($job);
        $this->video->save();
        info('Started Video Convert Job ' . $this->video->job_id);
    }

    private function create_elastic_transcoder_job($transcoder_client, $pipeline_id, $input_key, $preset_id, $output_key_prefix)
    {
        # Setup the job input using the provided input key.
        $input = array('Key' => $input_key);

        # Setup the job output using the provided input key to generate an output key.
        $outputs = array(array('Key' => hash("sha256", utf8_encode($input_key)), 'PresetId' => $preset_id));

        # Create the job.
        $create_job_request = array(
            'PipelineId' => $pipeline_id,
            'Input' => $input,
            'Outputs' => $outputs,
            'OutputKeyPrefix' => $output_key_prefix,
        );
        $create_job_result = $transcoder_client->createJob($create_job_request)->toArray();
        return $create_job_result;
    }
}
