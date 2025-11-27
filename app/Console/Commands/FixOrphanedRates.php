<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Rate;

class FixOrphanedRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate:orphans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disables rates that are active but the associated product is not';

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
        $rates = Rate::select('rates.id')->join('products', 'products.id', 'rates.product_id')->whereNull('rates.deleted_at')->whereNotNull('products.deleted_at')->get();
        $bar = $this->output->createProgressBar(count($rates));
        $bar->start();
        $rates->each(function ($rate) use ($bar) {
            Rate::find($rate->id)->delete();
            $bar->advance();
        });
        $bar->finish();
    }
}
