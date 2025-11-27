<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateLocalityRestrictionsWithCsv extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->text('raw_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->dropColumn(['raw_data']);
        });
    }
}
