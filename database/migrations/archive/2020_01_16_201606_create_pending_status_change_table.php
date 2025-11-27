<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePendingStatusChangeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pending_status_change', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->string('tpv_id');
            $table->string('new_status_id');
            $table->boolean('completed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pending_status_change');
    }
}
