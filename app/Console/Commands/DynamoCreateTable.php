<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Aws\Sdk;
use Aws\DynamoDb\Exception\DynamoDbException;

class DynamoCreateTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dynamo:table:create {tableName} {partitionKey} {sortKey}';

    private $dynamo;
    private $sdk;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a DynamoDB Table';

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
        $partitionKey = $this->argument('partitionKey');
        $sortKey = $this->argument('sortKey');

        $params = [
            'TableName' => $tableName,
            'KeySchema' => [
                [
                    'AttributeName' => $partitionKey,
                    'KeyType' => 'HASH',  //Partition key
                ],
                [
                    'AttributeName' => $sortKey,
                    'KeyType' => 'RANGE',  //Sort key
                ],
            ],
            'AttributeDefinitions' => [
                [
                    'AttributeName' => $partitionKey,
                    'AttributeType' => 'N',
                ],
                [
                    'AttributeName' => $sortKey,
                    'AttributeType' => 'S',
                ],
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => 10,
                'WriteCapacityUnits' => 10,
            ],
        ];

        try {
            $result = $this->dynamo->createTable($params);
            $this->info('Created table: '.$tableName);
        } catch (DynamoDbException $e) {
            $this->error('Unable to create table: ', $e->getMessage());
        }
    }
}
