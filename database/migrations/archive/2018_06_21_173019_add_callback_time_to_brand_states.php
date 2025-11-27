<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCallbackTimeToBrandStates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_states', function (Blueprint $table) {
            $table->integer('customer_callback_time')->default(0);
            $table->tinyInteger('eztpv')->default(0);
            $table->tinyInteger('eztpv_contracts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_states', function (Blueprint $table) {
            $table->dropColumn('customer_callback_time');
            $table->dropColumn('eztpv');
            $table->dropColumn('eztpv_contracts');
        });
    }
}
