<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Rate;
use App\Models\Product;

class RemoveExpiredProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:remove-expired {--brand=} {--dryrun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for expired rates/product and disables them';

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
        $brand = $this->option('brand');
        $isDryRun = $this->option('dryrun');
        $now = now();

        if (isset($brand)) {
            if ($isDryRun) {
                $products = Product::whereDate('date_to', '<', $now)->where('brand_id', $brand)->with('brand')->get();
                $rates = Rate::select('rates.*')->join('products', 'id', 'product_id')->where('products.brand_id', $brand)->whereDate('date_to', '<', $now)->with(['product', 'product.brand'])->get();
            } else {
                $products = Product::whereDate('date_to', '<', $now)->where('brand_id', $brand)->get();
                $rates = Rate::select('rates.*')->join('products', 'id', 'product_id')->where('products.brand_id', $brand)->whereDate('date_to', '<', $now)->get();
            }
        } else {
            if ($isDryRun) {
                $products = Product::whereDate('date_to', '<', $now)->with('brand')->get();
                $rates = Rate::whereDate('date_to', '<', $now)->with(['product', 'product.brand'])->get();
            } else {
                $products = Product::whereDate('date_to', '<', $now)->get();
                $rates = Rate::whereDate('date_to', '<', $now)->get();
            }
        }

        $this->info('Disabling ' . $products->count() . ' products.');

        $presults = [];
        $products->each(function ($item) use ($isDryRun, $presults) {
            if (!$isDryRun) {
                $item->delete();
                $item->rates->each(function ($rate) use ($item) {
                    if ($rate->deleted_at === null) {
                        $rate->deleted_at = $item->deleted_at;
                        $rate->save();
                    }
                });
            } else {
                $presults[] = [
                    $item->brand->name,
                    $item->name
                ];
            }
        });
        if (count($presults) > 0) {
            $this->table(['Brand', 'Product'], $presults);
        }

        $this->info('Disabling ' . $rates->count() . ' rates.');
        $rresults = [];
        $rates->each(function ($item) use ($isDryRun, $rresults) {
            if (!$isDryRun) {
                $item->delete();
            } else {
                $d = [
                    $item->product->brand->name,
                    $item->product->name,
                    $item->program_code
                ];
                $rresults[] = $d;
            }
        });

        if (count($rresults) > 0) {
            $this->table(['Brand', 'Product', 'Program Code'], $rresults);
        }
    }
}
