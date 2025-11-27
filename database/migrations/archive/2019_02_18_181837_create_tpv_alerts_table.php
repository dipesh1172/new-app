<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTpvAlertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tpv_alerts', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('message', 255);
            $table->date('start_date');
            $table->date('end_date');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tpv_alerts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}