<?php

namespace App\Jobs;

use App\Models\Events\EventAction;
use App\Models\Events\EventSubscription;
use App\Models\Events\EventType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EventActionProcessor implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $eventType;
	public $vars;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct(EventType $et, $variables) {
		$this->eventType = $et;
		$this->vars = $variables;

	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle() {
		$actions = EventAction::forEvent($this->eventType)->get();
		foreach ($actions as $action) {
			$subscriptions = EventSubscription::where('enabled', true)
				->where('event_type', $this->eventType->id)
				->where('action_type', $action->action_type)
				->get();
			$class_name = 'Event' . studly_case($action->action->name) . 'ActionTypeProcessor';
			foreach ($subscriptions as $sub) {
				try {
					$c = new $class_name($sub->user, $action->template, $this->vars);
					dispatch($c);
				} catch (\Exception $e) {
					info('Unknown Alert Action Type: ' . $action->action->name . ' -> ' . $e->getMessage());
				}

			}
		}
	}
}
