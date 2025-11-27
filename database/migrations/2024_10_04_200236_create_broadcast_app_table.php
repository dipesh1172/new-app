<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBroadcastAppTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('broadcast_app', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->softDeletes();

            $table->string('event_id')->nullable();
            $table->string('call_sid')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('called')->default(0);
            $table->dateTime('called_date')->nullable();
            $table->boolean('failed')->default(0);
            $table->string('failed_reason')->nullable();
            $table->string('callStatus')->nullable();
            $table->string('answeredBy')->nullable();
            $table->integer('attempts')->nullable()->default(0);

            $table->index('created_at', 'idx_created_at');
            $table->index('updated_at', 'idx_updated_at');
            $table->index('deleted_at', 'idx_deleted_at');
            $table->index('event_id', 'idx_event_id');
            $table->index('call_sid', 'idx_call_sid');
            $table->index('phone', 'idx_phone');
            $table->index('called_date', 'idx_called_date');
            $table->index('callStatus', 'idx_callStatus');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('broadcast_app');
    }
}
