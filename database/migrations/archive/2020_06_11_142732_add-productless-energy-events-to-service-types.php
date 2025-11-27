<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandServiceType;

class AddProductlessEnergyEventsToServiceTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bst = new BrandServiceType();
        $bst->slug = 'productless_energy_events';
        $bst->name = 'Productless Energy Events';
        $bst->description = 'Submit Energy Events without Utility or Product information included';
        $bst->pricing_type = 'per-use';
        $bst->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        BrandServiceType::where('slug', 'productless_energy_events')->delete();
    }
}
