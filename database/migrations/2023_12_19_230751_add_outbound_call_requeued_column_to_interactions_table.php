<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOutboundCallRequeuedColumnToInteractionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->tinyInteger('outbound_call_requeued')->nullable()->default(0);

            $table->index('outbound_call_requeued', 'idx_outbound_call_requeued');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropColumn('outbound_call_requeued');

            $table->dropIndex('idx_outbound_call_requeued');
        });
    }
}
