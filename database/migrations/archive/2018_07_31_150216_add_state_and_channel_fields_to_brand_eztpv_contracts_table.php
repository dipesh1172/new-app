<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStateAndChannelFieldsToBrandEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->integer('state_id');
            $table->integer('channel_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->dropColumn('state_id');
            $table->dropColumn('channel_id');
        });
    }
}
