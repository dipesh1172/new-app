<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTextMessageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('text_messages', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->string('message_sid');
            $table->string('brand_id', 36)->nullable();
            $table->string('sender_id', 36);
            $table->string('to_phone_id', 36);
            $table->string('from_dnis_id', 36)->nullable();
            $table->text('content');
            $table->text('media_uri')->nullable();
            $table->enum('status', ['accepted', 'queued', 'sending', 'sent', 'failed', 'delivered', 'undelivered', 'receiving', 'received', 'read'])->default('accepted');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('text_messages');
    }
}
