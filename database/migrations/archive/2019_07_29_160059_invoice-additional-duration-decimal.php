<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InvoiceAdditionalDurationDecimal extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('invoice_additional', function (Blueprint $table) {
            $table->decimal('duration')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
