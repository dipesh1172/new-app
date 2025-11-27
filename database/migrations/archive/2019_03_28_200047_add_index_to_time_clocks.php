<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexToTimeClocks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'time_clocks',
            function (Blueprint $table) {
                $table->index(['tpv_staff_id', 'created_at']);
                $table->index(['tpv_staff_id', 'time_punch']);
                $table->index(['tpv_staff_id', 'created_at', 'agent_status_type_id']);
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
    }
}
