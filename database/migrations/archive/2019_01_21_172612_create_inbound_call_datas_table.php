<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInboundCallDatasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inbound_call_data', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->dateTime('start_date');
            $table->integer('calls');
            $table->float('call_time', 8, 2);
            $table->float('avg_call_time', 8, 2);
            $table->float('asa', 8, 2);
            $table->integer('calls_abandoned');
            $table->float('avg_abandon_time', 8, 2);
            $table->float('service_level', 8, 2);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inbound_call_data');
    }
}
