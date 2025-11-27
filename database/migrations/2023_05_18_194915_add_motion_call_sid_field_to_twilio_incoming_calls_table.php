<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMotionCallSidFieldToTwilioIncomingCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('twilio_incoming_calls', function (Blueprint $table) {
            $table->string('motion_call_sid', 64)->nullable();
            $table->index('motion_call_sid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('twilio_incoming_calls', function (Blueprint $table) {
            $table->dropColumn('motion_call_sid');
            $table->dropIndex('motion_call_sid');
        });
    }
}
