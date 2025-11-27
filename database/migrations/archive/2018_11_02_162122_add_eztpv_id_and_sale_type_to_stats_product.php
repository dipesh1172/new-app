<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvIdAndSaleTypeToStatsProduct extends Migration
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
            function ($table) {
                $table->string('eztpv_id', 36)->nullable()->after('eztpv_initiated');
                $table->string('eztpv_sale_type', 24)->nullable()->after('eztpv_id');
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
            function ($table) {
                $table->dropColumn('eztpv_id');
                $table->dropColumn('eztpv_sale_type');
            }
        );
    }
}
