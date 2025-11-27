<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCommodityUtilityLdcCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->string('utility_commodity_ldc_code', 64)
                    ->after('commodity')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->dropColumn('utility_commodity_ldc_code');
            }
        );
    }
}
