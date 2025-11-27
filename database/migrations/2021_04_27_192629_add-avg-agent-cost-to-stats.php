<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAvgAgentCostToStats extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stats_tpv_agent', function (Blueprint $table) {
            $table->double('agent_cost', 10, 2)->default(0.0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stats_tpv_agent', function (Blueprint $table) {
            $table->dropColumn('agent_cost');
        });
    }
}
