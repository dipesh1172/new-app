<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFielsToBrandUtilitySupportedFuels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brand_utility_supported_fuels', function (Blueprint $table) {
                $table->string('ldc_code', 64)->nullable();
                $table->string('external_id', 64)->nullable();
                $table->string('commodity', 64)->nullable();
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
            'brand_utility_supported_fuels', function (Blueprint $table) {
                $table->dropColumn('ldc_code');
                $table->dropColumn('external_id');
                $table->dropColumn('commodity');
            }
        );
    }
}
