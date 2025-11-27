<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexToAgentStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('agent_statuses', function (Blueprint $table) {
            $table->index('tpv_staff_id');
            $table->index('created_at');
        });

        Schema::table('interactions', function (Blueprint $table) {
            $table->index(['created_at', 'tpv_staff_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('agent_statuses', function (Blueprint $table) {
            $table->dropIndex('tpv_staff_id');
            $table->dropIndex('created_at');
        });
    }
}