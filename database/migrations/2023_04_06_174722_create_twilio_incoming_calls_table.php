<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTwilioIncomingCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('twilio_incoming_calls', function (Blueprint $table) {
            $table->string('id', 30)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->string('call_sid')->nullable();
            $table->text('call_info');
            $table->text('ivr_info');
            $table->index('call_sid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('twilio_incoming_calls');
    }
}
