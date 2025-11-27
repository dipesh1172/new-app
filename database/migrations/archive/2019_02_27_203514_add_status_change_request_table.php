<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusChangeRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('status_change_request');

        Schema::create('status_change_request', function (Blueprint $table) {
            $table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
            $table->string('tpv_staff_id', 36);
            $table->string('current_status', 128);
            $table->datetime('current_status_at');
            $table->string('requested_status', 128);
            $table->boolean('approved')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->string('approved_by', 36)->nullable();
            $table->text('denial_reason')->nullable();
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('status_change_request');
    }
}
