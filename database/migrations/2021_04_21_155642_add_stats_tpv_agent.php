<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatsTpvAgent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'stats_tpv_agent',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->date('stats_date');
                $table->string('tpv_staff_id', 36);
                $table->integer('total_calls')->default(0);
                $table->double('total_hours', 10, 2)->default(0.00);
                $table->double('billable_time', 10, 2)->default(0.00);
                $table->double('calls_per_hour', 10, 2)->default(0.00);
                $table->double('productive_occupancy', 10, 2)->default(0.00);
                $table->double('avg_revenue_per_payroll_hour', 10, 2)->default(0.00);
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
        //
    }
}
