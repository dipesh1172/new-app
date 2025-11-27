<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixRpaOhSpanishContractRateTypes extends Migration
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
            'RPA Energy'
        )
            ->first();

        $becs = BrandEztpvContract::where(
            'brand_id',
            $brand->id
        )
            ->where(
                'state_id',
                36
            )
            ->where(
                'language_id',
                2
            )
            ->update([
                'rate_type_id' => 2
            ]);
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
