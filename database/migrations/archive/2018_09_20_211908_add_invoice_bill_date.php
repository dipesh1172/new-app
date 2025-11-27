<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInvoiceBillDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'invoices',
            function (Blueprint $table) {
                $table->dateTime('invoice_bill_date')->after('brand_id')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'invoices',
            function (Blueprint $table) {
                $table->removeColumn('invoice_bill_date');
            }
        );
    }
}
