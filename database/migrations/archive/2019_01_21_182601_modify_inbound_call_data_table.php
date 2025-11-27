<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyInboundCallDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('inbound_call_data', function (Blueprint $table) {
            $table->string('brand_id');
            $table->string('channel_id');
            $table->string('language_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inbound_call_data', function (Blueprint $table) {
            $table->dropColumn('brand_id');
            $table->dropColumn('channel_id');
            $table->dropColumn('language_id');
        });
    }
}
