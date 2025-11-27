<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWorkerSidFieldToTwilioIncomingCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('twilio_incoming_calls', function (Blueprint $table) {
            $table->string('worker_sid', 64)->nullable();
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
            $table->dropColumn('worker_sid');
        });
    }
}
