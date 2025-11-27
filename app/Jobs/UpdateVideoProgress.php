<?php

namespace App\Jobs;

use App\Models\Redbook\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class UpdateVideoProgress implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable;
	protected $event;
	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($event) {
		$this->event = $event;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle() {
		$jobId = $this->event->message['jobId'];
		$state = $this->event->message['state'];
		$video = Video::where('job_id', $jobId)->first();
		switch ($state) {
		case 'COMPLETED':
			//clean up the original
			Storage::disk('s3-vid-upload')->delete($video->path);
			$video->path = $this->event->message['outputKeyPrefix'] . $this->event->message['outputs'][0]['key'];
			$video->status = 'Conversion Complete';
			$video->job_id = null;
			$video->save();
			break;

		case 'PROGRESSING':
			$video->status = 'Conversion in Progress';
			$video->save();
			break;

		default:
			$video->status = json_encode($this->event->message);
			info('Unknown event type for VideoConversion', $this->event);
			break;
		}
	}
}
