<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLegacyFieldsToDnis extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('dnis', function (Blueprint $table) {
            $table->string('platform', 16)->nullable()->default('focus');
            $table->string('skill_name', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Nothing.
    }
}
