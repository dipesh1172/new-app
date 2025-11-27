<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldsToDailyStats extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('daily_stats', function (Blueprint $table) {
            $table->decimal('live_english_min', 10, 2)->nullable()->change();
            $table->decimal('live_spanish_min', 10, 2)->nullable()->change();
            $table->decimal('live_good_sale', 10, 2)->nullable()->change();
            $table->decimal('live_no_sale', 10, 2)->nullable()->change();
            $table->decimal('live_channel_dtd', 10, 2)->nullable()->change();
            $table->decimal('live_channel_tm', 10, 2)->nullable()->change();
            $table->decimal('live_channel_retail', 10, 2)->nullable()->after('live_channel_tm');
            $table->decimal('live_cc_tulsa_min', 10, 2)->nullable()->change();
            $table->decimal('live_cc_tahlequah_min', 10, 2)->nullable()->change();
            $table->decimal('live_cc_lasvegas_min', 10, 2)->nullable()->change();

            $table->integer('voice_imprint')->nullable();
        });

        Schema::table('stats_product', function (Blueprint $table) {
            $table->integer('tpv_agent_call_center_id')->nullable()->after('tpv_agent_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
