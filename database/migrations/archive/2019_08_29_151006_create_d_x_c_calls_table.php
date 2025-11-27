<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDXCCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dxc_calls', function (Blueprint $table) {
            $table->string('sid', 50)->unique();
            $table->timestamp('endTime');
            $table->timestamp('startTime');
            $table->timestamp('dateUpdated');
            $table->timestamp('dateCreated');
            $table->string('status', 30);
            $table->integer('duration');
            $table->string('direction', 20);
            $table->string('toFormatted', 64);
            $table->string('fromFormatted', 64);
            $table->string('forwardedFrom', 64)->nullable();
            $table->string('callerName', 64)->nullable();
            $table->string('answeredBy', 64)->nullable();
            $table->string('parentCallSid', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dxc_calls');
    }
}
