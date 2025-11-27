<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogClientAlertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_client_alerts', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->string('brand_id', 36);
            $table->integer('alert_id');
            $table->timestamp('alert_timestamp')->nullable();
            $table->text('users_alerted');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('log_client_alerts');
    }
}
