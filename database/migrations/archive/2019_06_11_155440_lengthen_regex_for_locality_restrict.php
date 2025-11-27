<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class LengthenRegexForLocalityRestrict extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->string('restrict', 4096)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            // there is no down, only Zuul
        });
    }
}
