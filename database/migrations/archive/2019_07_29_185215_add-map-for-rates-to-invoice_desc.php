<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMapForRatesToInvoiceDesc extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('invoice_desc', function (Blueprint $table) {
            $table->string('map_rate_to')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('invoice_desc', function (Blueprint $table) {
            $table->dropColumn('map_rate_to');
        });
    }
}
