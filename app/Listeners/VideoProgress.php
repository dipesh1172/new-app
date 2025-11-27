<?php

namespace App\Listeners;

use App\Jobs\UpdateVideoProgress;

class VideoProgress {
	/**
	 * Handle the event.
	 *
	 * @param  object  $event
	 * @return void
	 */
	public function handle($event) {
		if (starts_with($event->subject, 'Amazon Elastic Transcoder')) {
			dispatch(new UpdateVideoProgress($event));
		}
	}
}
