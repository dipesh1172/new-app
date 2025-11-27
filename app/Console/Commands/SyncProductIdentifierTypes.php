<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventProductIdentifier;
use App\Models\StatsProduct;

use Illuminate\Console\Command;

class SyncProductIdentifierTypes extends Command
{
    protected $identifiers = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:product-identifier-types {--utility_id=} {--brand_id=} {--start=} {--end=}';
    // sync:product-identifier-types --utility_id=308e1de0-5271-4529-90f0-94f6767c77a4 --brand_id=eb35e952-04fc-42a9-a47d-715a328125c0 --start=2022-01-08 --end=2022-01-08
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronizes the utility_account_number_type_id field in the event_product_identifiers table with their configured UAN type';

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
     * Load data from the database
     *
     * @param  int    $utility_id
     * @param  int    $brand_id
     * @param  string $start
     * @param  string $end
     * @return void
     */
    protected function loadData($utility_id, $brand_id, $start, $end) {
        /*
            SELECT
                event_product_identifiers.id, utility_account_identifiers.utility_account_number_type_id
            FROM
                events
                    LEFT JOIN
                event_product ON event_product.event_id = events.id
                    LEFT JOIN
                utility_supported_fuels ON utility_supported_fuels.id = event_product.utility_id
                    LEFT JOIN
                event_product_identifiers ON event_product_identifiers.event_product_id = event_product.id
                    LEFT JOIN
                utility_account_identifiers ON utility_account_identifiers.utility_id = utility_supported_fuels.id and utility_account_identifiers.utility_account_type_id = event_product_identifiers.utility_account_type_id
            WHERE
                events.brand_id = 'eb35e952-04fc-42a9-a47d-715a328125c0'
                    AND utility_supported_fuels.utility_id = '308e1de0-5271-4529-90f0-94f6767c77a4'
                    AND events.created_at >= '2022-01-08 00:00:00'
                    AND events.created_at <= '2022-01-08 23:59:59'
                    AND events.deleted_at IS NULL
            ORDER BY events.created_at DESC
        */
        $query = Event::select(
                'event_product_identifiers.id',
                'event_product_identifiers.identifier as value',
                'event_product_identifiers.event_product_id',
                'utility_account_identifiers.utility_account_number_type_id as actual_uan_type_id'
            )
            ->leftJoin('event_product', 'event_product.event_id', '=', 'events.id')
            ->leftJoin('utility_supported_fuels', 'utility_supported_fuels.id', '=', 'event_product.utility_id')
            ->leftJoin('event_product_identifiers', 'event_product_identifiers.event_product_id', '=', 'event_product.id')
            ->leftJoin('utility_account_identifiers', function($join) {
                $join->on('utility_account_identifiers.utility_id', '=', 'utility_supported_fuels.id')
                    ->on('utility_account_identifiers.utility_account_type_id', '=', 'event_product_identifiers.utility_account_type_id');
            })
            ->where('events.brand_id', $brand_id)
            ->where('utility_supported_fuels.utility_id', $utility_id)
            ->where('events.created_at', '>=', $start)
            ->where('events.created_at', '<=', $end)
            ->orderBy('events.created_at', 'desc');

        $this->identifiers = $query->get();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $utility_id = $this->option('utility_id');
        $brand_id = $this->option('brand_id');
        $start = $this->option('start').' 00:00:00';
        $end = $this->option('end').' 23:59:59';

        if (empty($utility_id) || empty($brand_id) || empty($start) || empty($end)) {
            $this->error('Missing required arguments');
            return;
        }

        $this->loadData($utility_id, $brand_id, $start, $end);
        $this->sync();
        // $this->info(print_r($this->identifiers->toArray(), true));
    }

    /**
     * Sync the utility_account_number_type_id field in the event_product_identifiers table with their configured UAN type
     */
    public function sync() {
        $event_products = [];

        foreach($this->identifiers as $identifier) {
            $this->info('Syncing confirmation code: '.$identifier->id.'...');

            if (empty($event_products[$identifier->event_product_id])) {
                $event_products[$identifier->event_product_id] = [];
            }

            $statsColumnName = $this->typeIdToColumnName($identifier['actual_uan_type_id']);
            $event_products[$identifier->event_product_id][$statsColumnName] = $identifier->value;

            $epi = EventProductIdentifier::find($identifier->id);
            $epi['utility_account_number_type_id'] = $identifier['actual_uan_type_id'];
            $epi->save();
        }

        foreach ($event_products as $event_product_id => $event_product) {
            $this->info('Saving stats product: '.$event_product_id.'...');
            $sp = StatsProduct::where('event_product_id', '=', $event_product_id)->first();
            $sp->fill($event_product);
            $sp->save();
        }
    }

    public function typeIdToColumnName($type_id) {
        switch ($type_id) {
            case 2:
                return 'account_number2';
            case 3:
                return 'name_key';
            case 1:
            default:
                return 'account_number1';
        }
    }
}
