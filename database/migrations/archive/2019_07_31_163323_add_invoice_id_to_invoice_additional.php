<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInvoiceIdToInvoiceAdditional extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('invoice_additional', function (Blueprint $table) {
            $table->string('invoice_id', 36)->nullable();
            $table->string('brand_id', 36)->nullable()->after('client');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('invoice_additional', function (Blueprint $table) {
        });
    }
}
