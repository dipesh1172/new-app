<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;
use App\Models\Brand;

class AddIndraPaRetailContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $brand = Brand::where(
            'name',
            'Indra Energy'
        )
        ->first();

        $becs = BrandEztpvContract::where(
            'brand_id',
            $brand->id
        )
        ->where(
            'state_id',
            39
        )
        ->where(
            'channel_id',
            1
        )
        ->get();

        foreach ($becs as $bec) {
            $newRow = $bec->replicate();
            $newRow->channel_id = 3;
            $newRow->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // unnecessary
    }
}
