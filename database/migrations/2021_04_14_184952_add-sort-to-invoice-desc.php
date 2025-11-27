<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\InvoiceDesc;

class AddSortToInvoiceDesc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_desc', function (Blueprint $table) {
            $table->integer('invoice_sort')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_desc', function (Blueprint $table) {
            $table->dropColumn('invoice_sort');
        });
    }
}
