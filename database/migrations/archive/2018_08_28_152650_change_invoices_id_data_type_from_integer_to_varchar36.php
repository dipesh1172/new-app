<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeInvoicesIdDataTypeFromIntegerToVarchar36 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_statuses', function (Blueprint $table) {
            $table->string('invoices_id', 36)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_statuses', function (Blueprint $table) {
            $table->integer('invoices_id')->change();
        });
    }
}
