<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EnhanceAgentStatuses extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('agent_statuses', function (Blueprint $table) {
            $table->string('interaction_id', 36)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('agent_statuses', function (Blueprint $table) {
            // there is no down, only Zuul
        });
    }
}
