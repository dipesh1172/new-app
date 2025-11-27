<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStopCallFieldToBrandAlerts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brand_client_alerts', function (Blueprint $table) {
                $table->boolean('stop_call')->default(false);
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'brand_client_alerts', function (Blueprint $table) {
                $table->dropColumn('stop_call');
            }
        );
    }
}
