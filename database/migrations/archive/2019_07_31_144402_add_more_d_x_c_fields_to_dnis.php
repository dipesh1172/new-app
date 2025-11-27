<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMoreDXCFieldsToDnis extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('dnis', function (Blueprint $table) {
            $table->integer('channel_id')->nullable();
            $table->integer('market_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('dnis', function (Blueprint $table) {
        });
    }
}
