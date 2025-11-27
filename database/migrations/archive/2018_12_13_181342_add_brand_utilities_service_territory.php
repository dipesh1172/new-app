<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandUtilitiesServiceTerritory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brand_utilities',
            function (Blueprint $table) {
                $table->string('service_territory', 64)->nullable();
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
            'brand_utilities',
            function (Blueprint $table) {
                $table->dropColumn('service_territory');
            }
        );
    }
}
