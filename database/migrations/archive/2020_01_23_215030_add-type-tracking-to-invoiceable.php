<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypeTrackingToInvoiceable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoiceables', function (Blueprint $table) {
            $table->string('invoiceable_item_id', 36)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoiceables', function (Blueprint $table) {
            $table->dropColumn(['invoiceable_item_id']);
        });
    }
}
