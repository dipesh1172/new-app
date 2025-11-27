<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventCallback extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_callback', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->string('event_type', 32)->nullable();
            $table->string('task_created_date', 32)->nullable();
            $table->string('task_queue_name', 128)->nullable();
            $table->string('task_age', 16)->nullable();
            $table->string('sid', 64)->nullable();
            $table->string('task_assignment_status', 16)->nullable();
            $table->string('task_queue_sid', 64)->nullable();
            $table->text('task_attributes')->nullable();
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
