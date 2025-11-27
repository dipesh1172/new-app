<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvSaleTypesChannelId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'eztpv_sale_types',
            function (Blueprint $table) {
                $table->integer('channel_id')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'eztpv_sale_types',
            function (Blueprint $table) {
                $table->dropColumn('channel_id');
            }
        );
    }
}
