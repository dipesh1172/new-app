<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateInvoicesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('invoices', function(Blueprint $table)
		{
			$table->string('id', 36);
			$table->timestamps();
			$table->softDeletes();
			$table->string('brand_id', 36);
			$table->dateTime('invoice_start_date')->nullable();
			$table->dateTime('invoice_end_date')->nullable();
			$table->dateTime('invoice_due_date')->nullable();
			$table->string('account_number', 32)->nullable();
			$table->string('invoice_number', 32)->nullable();
			$table->integer('status')->nullable();
			$table->primary(['id','brand_id']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('invoices');
	}

}
