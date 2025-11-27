<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddChannelAndMarketToLocalityRestrictions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->integer('market_id')->nullable();
            $table->integer('channel_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->dropColumn(['market_id', 'channel_id']);
        });
    }
}
