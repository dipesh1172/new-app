<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameBrandEztpvSaleTypeIdColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_eztpv_sale_types', function (Blueprint $table) {
            $table->renameColumn('brand_eztpv_sale_type_id', 'eztpv_sale_type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_eztpv_sale_types', function (Blueprint $table) {
            $table->renameColumn('eztpv_sale_type_id', 'brand_eztpv_sale_type_id');
        });
    }
}
