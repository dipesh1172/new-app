<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\State;
use App\Models\Rate;
use App\Models\Product;
use App\Models\BrandEztpvContract;

class TestContractSelection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contract:select 
                            {--brand=} 
                            {--show-products}
                            {--english} 
                            {--spanish} 
                            {--state=} 
                            {--channel-tm} 
                            {--channel-dtd} 
                            {--channel-retail} 
                            {--residential} 
                            {--commercial}
                            {--gas}
                            {--electric}
                            {--dual-fuel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Returns if the specified inputs have contracts available';

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
        $langID = 1;
        if ($this->option('english')) {
            $langID = 1;
        }
        if ($this->option('spanish')) {
            $langID = 2;
        }
        $brandID = $this->option('brand');
        $stateAbbr = $this->option('state');
        $state = State::where('state_abbrev', $stateAbbr)->first();
        $channel = 1;
        if ($this->option('channel-dtd')) {
            $channel = 1;
        }
        if ($this->option('channel-tm')) {
            $channel = 2;
        }
        if ($this->option('channel-retail')) {
            $channel = 3;
        }
        $market = 1;
        if ($this->option('residential')) {
            $market = 1;
        }
        if ($this->option('commercial')) {
            $market = 2;
        }
        $commodity = 'dual';
        if ($this->option('electric')) {
            $commodity = 'electric';
        }
        if ($this->option('gas')) {
            $commodity = 'gas';
        }
        if ($this->option('dual-fuel')) {
            $commodity = 'dual';
        }

        $contracts = BrandEztpvContract::where('language_id', $langID)
            ->where('channel_id', $channel)
            ->where('state_id', $state->id)
            ->where('market_id', $market)
            ->where('brand_id', $brandID)
            ->where('commodity', $commodity)
            ->get();

        $contract_count = $contracts->count();

        if ($contract_count == 0) {
            $this->error('No Contracts were selected with the specified parameters');
        } else {
            $this->info('There are ' . $contract_count . ' possible contracts for selection');
        }
        $this->line('-----------');
        $c = 0;
        foreach ($contracts as $contract) {
            $this->line('contract id ' . $contract->id);
            $product = null;
            $rate = null;
            if ($contract->product_id !== null) {
                $product = Product::find($contract->product_id)->withTrashed();
            }
            if ($contract->rate_id !== null) {
                $rate = Rate::find($contract->rate_id)->withTrashed();
            }
            if ($product) {
                $this->info('Product: ' . $product->name . ($product->isTrashed() ? '(deleted)' : ''));
            }
            if ($rate) {
                $this->info('Rate: ' . $rate->program_code . ($rate->isTrashed() ? '(deleted)' : ''));
            }
            $this->line('-----------');
            if ($product || $rate) {

                $c += 1;
            }
        }

        if ($c == 0) {
            $this->info('Contract Selection is not dependent on product/rate selection');
        }
    }
}
