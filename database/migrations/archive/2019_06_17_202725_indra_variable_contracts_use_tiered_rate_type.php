<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraVariableContractsUseTieredRateType extends Migration
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
            'LIKE',
            'Indra%'
            )
        ->first();

        $bec = BrandEztpvContract::where(
            'brand_id',
            $brand->id
        )
        ->where(
            'rate_type_id',
            2
        )
        ->update([
            'rate_type_id' => 3
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
