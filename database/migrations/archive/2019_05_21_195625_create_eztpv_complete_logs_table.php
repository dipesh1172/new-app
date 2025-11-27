<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEztpvCompleteLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('eztpv_complete_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->string('brand_id');
            $table->string('user_id');
            $table->string('eztpv_id');
            $table->text('params');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('eztpv_complete_logs');
    }
}
