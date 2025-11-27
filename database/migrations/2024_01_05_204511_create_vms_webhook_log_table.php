<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVmsWebhookLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vms_webhook_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->softDeletes();

            $table->string('ip_address')->nullable();
            $table->string('request_url')->nullable();
            $table->string('webhook_url')->nullable();
            $table->text('webhook_request')->nullable();
            $table->text('webhook_response')->nullable();

            $table->index('ip_address', 'idx_ip_address');
            $table->index('request_url', 'idx_request_url');
            $table->index('webhook_url', 'idx_webhook_url');
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vms_webhook_log');
    }
}
