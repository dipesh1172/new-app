<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Aws\Sdk;
use Aws\DynamoDb\Exception\DynamoDbException;

class DynamoDeleteTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dynamo:table:delete {tableName}';

    private $dynamo;
    private $sdk;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a DynamoDB Table';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $options = [
            'region' => config('services.aws.region'),
            'version' => 'latest',
        ];
        if (config('services.aws.dynamo.endpoint') !== null) {
            $options['endpoint'] = config('services.aws.dynamo.endpoint');
        }
        $this->sdk = new Sdk($options);
        $this->dynamo = $this->sdk->createDynamoDb();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tableName = $this->argument('tableName');

        $params = [
            'TableName' => $tableName,
        ];

        try {
            $result = $this->dynamo->deleteTable($params);
            $this->info('Deleted table: '.$tableName);
        } catch (DynamoDbException $e) {
            $this->error('Unable to delete table: ', $e->getMessage());
        }
    }
}
