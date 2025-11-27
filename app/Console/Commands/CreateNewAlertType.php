<?php

namespace App\Console\Commands;

use App\Models\Alerts\EventActionType;
use Illuminate\Console\Command;

class CreateNewAlertType extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'make:alert-action
        {--name= : Human Readable name of the action to be performed}
        {--description= : Human readable description of the action that will be performed}
        {--var=* : Variables that will be available for templating for this action}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generates the scaffolding for a new Alert Type';
	protected $name;
	protected $desc;
	protected $vars;
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		$this->name = $this->option('name');
		$this->desc = $this->option('description');
		$this->vars = $this->option('var');
		$existing = EventActionType::where('name', $this->name)->first();
		if ($existing != null) {
			$this->error('The specified Alert Action Type already exists!');
			die;
		}

		$this->info('Creating ' . $this->name . ' :: ' . $this->desc);

		$this->callSilent('make:job', [
			'name' => 'Event' . studly_case($this->name) . 'ActionTypeProcessor',
		]);

		$fname = 'app/Jobs/' . 'Event' . studly_case($this->name) . 'ActionTypeProcessor.php';

		$fc = file_get_contents($fname);
		$fc = str_replace('__construct()', '__construct($user, $template, $variables)', $fc);
		file_put_contents($fname, $fc);
		$this->info('Job created as ' . $fname);

		$et = new EventActionType();
		$et->name = $this->name;
		$et->description = $this->desc;
		$et->variables = implode(',', $this->vars);
		$et->save();
	}
}
