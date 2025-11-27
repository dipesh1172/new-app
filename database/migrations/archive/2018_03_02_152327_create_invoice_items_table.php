<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateInvoiceItemsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('invoice_items', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('invoice_id', 36)->nullable();
			$table->decimal('quantity', 10)->nullable();
			$table->integer('invoice_desc_id')->nullable();
			$table->decimal('rate', 10)->nullable();
			$table->text('note', 65535)->nullable();
			$table->decimal('total', 10)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('invoice_items');
	}

}
