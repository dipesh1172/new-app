<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewFieldsSurvey extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('referral_id')->nullable();
            $table->string('srvc_address')->nullable();
            $table->string('agency')->nullable();
            $table->string('enroll_source')->nullable();
            $table->string('agent_vendor')->nullable();
            $table->string('agent_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('referral_id');
            $table->dropColumn('srvc_address');
            $table->dropColumn('agency');
            $table->dropColumn('enroll_source');
            $table->dropColumn('agent_vendor');
            $table->dropColumn('agent_name');
        });
    }
}
