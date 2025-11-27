<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEztpvJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('eztpv_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('record_type');
            $table->integer('parent_id')->nullable();
            $table->string('eztpv_id', 36)->nullable();
            $table->dateTime('central_start_time');
            $table->dateTime('central_end_time');
            $table->text('batch_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('eztpv_jobs');
    }
}
