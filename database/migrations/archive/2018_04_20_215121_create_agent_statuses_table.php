<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAgentStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agent_statuses', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->string('tpv_staff_id', 36)->foreign()->references('id')->on('tpv_staff')->nullable();
            $table->string('station_number', 15);
            $table->string('client_name')->nullable();
            $table->string('event');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agent_statuses');
    }
}
