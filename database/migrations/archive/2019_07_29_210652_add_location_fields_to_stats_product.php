<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLocationFieldsToStatsProduct extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            $table->string('ip_address', 64)->nullable();
            $table->string('gps_coords', 64)->nullable();
            $table->string('eztpv_contract_delivery', 16)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('stats_product', function (Blueprint $table) {
        });
    }
}
