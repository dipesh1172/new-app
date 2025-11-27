<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDigitalTracking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('digital_tracking', function (Blueprint $table) {
            $table->string('id', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('interaction_id', 36);
            $table->string('ip_addr', 32);
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
