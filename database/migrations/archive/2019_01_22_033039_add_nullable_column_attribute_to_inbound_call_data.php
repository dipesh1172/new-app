<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNullableColumnAttributeToInboundCallData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('inbound_call_data', function (Blueprint $table) {
            $table->string('brand_id')->nullable()->change();
            $table->string('channel_id')->nullable()->change();
            $table->string('language_id')->nullable()->change();
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
            $table->string('brand_id')->nullable(false)->change();
            $table->string('channel_id')->nullable(false)->change();
            $table->string('language_id')->nullable(false)->change();
        });
    }
}
