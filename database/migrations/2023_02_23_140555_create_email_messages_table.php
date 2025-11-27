<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmailMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->string('id', 36)->default('')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36)->nullable();
            $table->string('event_id', 36)->nullable();
            $table->string('conversation_id', 255)->nullable();
            $table->string('message_id', 255)->nullable();
            $table->string('to', 1000)->nullable();
            $table->string('from', 255)->nullable();
            $table->string('cc', 1000)->nullable();
            $table->string('bcc', 1000)->nullable();
            $table->string('subject', 1000)->nullable();
            $table->mediumText('body')->nullable();
            $table->string('headers', 1000)->nullable();
            $table->index('created_at');
            $table->index('brand_id');
            $table->index('event_id');
            $table->index('conversation_id');
            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_messages');
    }
}
