<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInvoiceAdditionalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_additional', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('owner', 128);
            $table->string('ticket', 128);
            $table->string('category', 128);
            $table->integer('duration');
            $table->date('date_of_work');
            $table->string('description', 255);
            $table->string('client', 128);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */

    public function down()
    {
        Schema::dropIfExists('invoice_additional', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
