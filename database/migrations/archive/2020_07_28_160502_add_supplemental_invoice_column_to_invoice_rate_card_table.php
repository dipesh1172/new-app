<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSupplementalInvoiceColumnToInvoiceRateCardTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->tinyInteger('supplemental_invoice')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->dropColumn('supplemental_invoice');
        });
    }
}
