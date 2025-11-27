<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpParser\ParserFactory;

class CreateAlertableEvent extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'make:alert
        {name : Human readable name of the alert}
        {--event= : The name of the event to listen to}
        {--description= : Explanation of the event}
        {--create : Create the event if not found or not provided}
    ';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create the scaffolding necessary for an alertable event';
	private $parser;
	private $events;

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		$name = $this->argument('name');
		$event = $this->option('event');
		$event_given = true;
		if ($event == null) {
			$event_given = false;
			$event = studly_case($name);
		}
		$desc = $this->option('description');
		$create = $this->option('create');

		$event_exists = file_exists('app/Events/' . $event . '.php');
		/*if (!$event_exists && !$create) {
			$this->error('Pass the --create flag to create a new event.');
			die;
		}*/

		$this->events = $this->getEvents();

		$this->callSilent('make:event', [
			'name' => $event,
		]);
		$ev = file_get_contents('app/Events/' . $event . '.php');
		$ev = str_replace('class ' . $event, 'class ' . $event . ' implements \App\Interfaces\ActionableEvent', $ev);
		$ev = str_replace("ActionableEvent\n{\n", "ActionableEvent\n{\n\tpublic function get_vars() { return []; }\n\tpublic function get_description() { return ''; }\n\n", $ev);
		file_put_contents('app/Events/' . $event . '.php', $ev);

		$this->callSilent('make:listener', [
			'name' => $event . 'Listener',
			'--event' => $event,
		]);

		$ev = file_get_contents('app/Listeners/' . $event . 'Listener.php');
		$ev = str_replace('class ' . $event, 'class ' . $event . 'Listener extends \App\Interfaces\EventActionInterface', $ev);
		file_put_contents('app/Listeners/' . $event . 'Listener.php', $ev);

		$this->info('Event and listener files created successfully.');

		try {
			$this->addEventListener('App\\Events\\' . $event, 'App\\Listeners\\' . $event . 'Listener');
		} catch (\Exception $e) {
			$this->error($e->getMessage());
		}

		$prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
		file_put_contents('app/Providers/EventServiceProvider.php', $prettyPrinter->prettyPrintFile($this->events['esp']));

		$this->info('Listener added to EventServiceProvider.');

		//$this->info(json_encode($this->events));
	}

	private function addEventListener($event, $listener) {
		$exists = false;
		foreach ($this->events['events'] as $key => $value) {
			if ($key == $event) {
				$exists = true;
				foreach ($value as $l) {
					if ($l == $listener) {
						$this->error('Listener and Event already exists');
						die;
						return;
					}
				}
				//exit;
			}
		}
		foreach ($this->events['esp'][0]->stmts as $stmt) {
			if ($stmt instanceof \PhpParser\Node\Stmt\Class_ && $stmt->name == 'EventServiceProvider') {
				foreach ($stmt->stmts as $istmt) {
					if ($istmt instanceof \PhpParser\Node\Stmt\Property) {
						foreach ($istmt->props as $prop) {
							if ($prop instanceof \PhpParser\Node\Stmt\PropertyProperty && $prop->name == 'listen') {
								if (!$exists) {

									$prop->default->items[] = new \PhpParser\Node\Expr\ArrayItem(
										new \PhpParser\Node\Expr\Array_([
											new \PhpParser\Node\Expr\ArrayItem(
												new \PhpParser\Node\Scalar\String_($listener, ['kind' => 1]),
												null
											),
										], ['kind' => 2]),
										new \PhpParser\Node\Scalar\String_($event, ['kind' => 1])
									);
									//exit;
								} else {
									foreach ($prop->default->items as $value) {
										if ($value->key->value == $event) {
											$value->value->items[] = new \PhpParser\Node\Expr\ArrayItem(
												new \PhpParser\Node\Scalar\String_($listener, ['kind' => 1]),
												null
											);
										}
									}
								}
							}
						}
					}
				}
				//exit;
			}
		}
	}

	private function getEvents() {
		$eventServiceProvider = $this->parser->parse(file_get_contents('app/Providers/EventServiceProvider.php'));
		try {
			$listenArray = $this->findListenArray($eventServiceProvider);
		} catch (\Exception $e) {
			$this->error($e->getMessage());
			return ['events' => [], 'esp' => []];
		}
		$events = [];
		foreach ($listenArray->default->items as $item) {
			$events[$item->key->value] = [];
			foreach ($item->value->items as $aitem) {
				$events[$item->key->value][] = $aitem->value->value;
			}
		}
		return ['events' => $events, 'esp' => $eventServiceProvider];
	}

	private function findListenArray($ast) {
		$ret = null;
		foreach ($ast[0]->stmts as $stmt) {
			if ($stmt instanceof \PhpParser\Node\Stmt\Class_ && $stmt->name == 'EventServiceProvider') {
				foreach ($stmt->stmts as $istmt) {
					if ($istmt instanceof \PhpParser\Node\Stmt\Property) {
						foreach ($istmt->props as $prop) {
							if ($prop instanceof \PhpParser\Node\Stmt\PropertyProperty && $prop->name == 'listen') {
								return $prop;
							}
						}
						if ($ret != null) {
							exit;
						}
					}
				}
				exit;
			}
		}
		return $ret;
	}
}
