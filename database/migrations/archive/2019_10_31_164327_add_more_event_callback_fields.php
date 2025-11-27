<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMoreEventCallbackFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_callback', function (Blueprint $table) {
            $table->string('worker_name', 32)->nullable();
            $table->string('worker_activity_name', 48)->nullable();
            $table->string('worker_previous_activity_name', 48)->nullable();
            $table->string('task_sid', 64)->nullable();
            $table->integer('worker_time_in_previous_activity_ms')->nullable();
            $table->string('reservation_sid', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
