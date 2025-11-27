<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHasThresholdToClientAlerts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'client_alerts',
            function (Blueprint $table) {
                $table->boolean('has_threshold')->default(0);
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
            'client_alerts',
            function (Blueprint $table) {
                $table->dropColumn('has_threshold');
            }
        );
    }
}
