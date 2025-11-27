<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFunctionNameToClientAlerts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('client_alerts', function (Blueprint $table) {
            $table->string('channels')->nullable()->change();
            $table->string('function')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('client_alerts', function (Blueprint $table) {
            $table->dropColumn('function');
        });
    }
}
