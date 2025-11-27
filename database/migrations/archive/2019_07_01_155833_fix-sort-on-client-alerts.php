<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixSortOnClientAlerts extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('client_alerts', function (Blueprint $table) {
            $table->integer('sort')->default(0)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('client_alerts', function (Blueprint $table) {
            // there is no down, only Zuul
        });
    }
}
