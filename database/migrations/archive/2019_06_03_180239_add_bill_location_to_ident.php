<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBillLocationToIdent extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('utility_account_identifiers', function (Blueprint $table) {
            $table->text('bill_location')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('utility_account_identifiers', function (Blueprint $table) {
            // there is no down, only Zuul
        });
    }
}
