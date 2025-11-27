<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWebenrollToEztpv extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('eztpvs', function (Blueprint $table) {
            $table->tinyInteger('webenroll')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Only zuul?
    }
}
