<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVendorsAndUtilitiesToLocalityRestriction extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->string('vendor_id', 36)->nullable();
            $table->string('utility_id', 36)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->dropColumn(['vendor_id', 'utility_id']);
        });
    }
}
