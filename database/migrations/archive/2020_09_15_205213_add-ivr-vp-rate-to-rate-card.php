<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIvrVpRateToRateCard extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->decimal('ivr_voiceprint')->default(0.25);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->dropColumn('ivr_voiceprint');
        });
    }
}
