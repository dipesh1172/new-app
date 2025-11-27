<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTextMessagesIncomingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('text_messages_incoming', function (Blueprint $table) {
            $table->string('id', 36)->default('')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('message_sid', 191)->nullable();
            $table->string('brand_id', 36)->nullable();
            $table->string('from_phone_id', 36)->nullable();
            $table->string('to_phone_id', 36)->nullable();
            $table->text('content')->nullable();
            $table->index('created_at');
            $table->index('brand_id');
            $table->index('from_phone_id');
            $table->index('to_phone_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('text_messages_incoming');
    }
}
