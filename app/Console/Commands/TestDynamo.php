<?php

namespace App\Console\Commands;

use App\Models\EventCallback;
use Aws\DynamoDb\Marshaler;
use Illuminate\Console\Command;

class TestDynamo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:dynamo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests dynamoDB connection';

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
        $marshaler = new Marshaler();
        $ecs = EventCallback::get()->toArray();
        foreach ($ecs as $ec) {
            if ($ec && isset($ec->message)) {
                $ec->message = $marshaler->unmarshalItem($ec->message);
            }
        }

        print_r($ecs);
    }
}
