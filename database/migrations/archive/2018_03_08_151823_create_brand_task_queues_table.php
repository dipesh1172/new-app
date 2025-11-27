<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandTaskQueuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_task_queues', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36);
            $table->string('task_queue', 64);
            $table->string('task_queue_sid', 36);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_task_queues');
    }
}
