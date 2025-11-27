<?php

namespace App\Console\Commands;

use App\Http\Controllers\TwilioTNInventoryController;
use Illuminate\Console\Command;

class GenerateMonthlyTwilioTNReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:monthly-TwilioTNInventory
                            {--email=* : Email recipients (multiple emails allowed)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Twilio TN Inventory Monthly report';

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
        $emails = $this->option('email');

        if (empty($emails)) {
            $this->error('Please provide at least one email recipient using the --email option.');
            return 1;
        }

        $controller = app(TwilioTNInventoryController::class);
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'emails' => $emails,
        ]);
        $controller->generateReport($request);

        $this->info('Twilio TN Inventory Monthly report generated and sent successfully.');
        return 0;
    }
}
