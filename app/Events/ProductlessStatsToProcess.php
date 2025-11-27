<?php

namespace App\Events;

use App\Models\Brand;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ProductlessStatsToProcess
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $brand;
    public $products;

    /**
     * Create a new event instance.
     */
    public function __construct(Brand $brand, $products)
    {
        $this->brand = $brand;
        $this->products = $products->toArray();
    }
}
