<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInvoiceablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'invoiceables', function (Blueprint $table) {
                $table->string('id', 36);
                $table->timestamps();
                $table->softDeletes();
                $table->string('brand_id', 36);
                $table->integer('invoiceable_type_id');
                $table->integer('quantity')->default(1);

                $table->primary('id'); 
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
        Schema::dropIfExists('invoiceables');
    }
}
