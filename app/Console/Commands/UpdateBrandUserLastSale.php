<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StatsProduct;
use App\Models\BrandUser;

class UpdateBrandUserLastSale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brand:sales:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the last sales date for the brand users';

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
        $users = BrandUser::all();
        $bar = $this->output->createProgressBar(count($users));
        $bar->start();
        foreach ($users as $user) {
            $lastSale = StatsProduct::select('interaction_created_at', 'interaction_id')->where('sales_agent_id', $user->id)->orderBy('interaction_created_at', 'DESC')->first();
            if ($lastSale && $lastSale->interaction_id !== $user->last_sale_id) {
                $user->last_sale_date = $lastSale->interaction_created_at;
                $user->last_sale_id = $lastSale->interaction_id;
                $user->save();
            }
            $bar->advance();
        }
        $bar->finish();

        //(SELECT interaction_created_at FROM stats_product WHERE stats_product.sales_agent_id = brand_users.id ORDER BY interaction_created_at LIMIT 1) AS created_at
    }
}
