<?php

namespace App\Events\Amazon;

use Illuminate\Foundation\Events\Dispatchable;

class SnsNotification {
	use Dispatchable;

	public $messageId;
	public $topicArn;
	public $subject;
	public $message;
	public $timestamp;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
	public function __construct($msg) {
		$this->messageId = $msg['MessageId'];
		$this->topicArn = $msg['TopicArn'];
		$this->subject = $msg['Subject'];
		$this->message = json_decode($msg['Message'], true);
		$this->timestamp = $msg['Timestamp'];
	}
}
