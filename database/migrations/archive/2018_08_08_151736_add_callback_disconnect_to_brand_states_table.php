<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCallbackDisconnectToBrandStatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_states', function (Blueprint $table) {
            $table->tinyInteger('callback_disconnect')->default(0);
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
            $table->dropColumn('callback_disconnect');
        });
    }
}
