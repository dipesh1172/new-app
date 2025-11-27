<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToEsiid extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('esiids', function (Blueprint $table) {
            $table->index('address');
            $table->index('zipcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
