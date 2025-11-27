<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRatesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('rates', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('product_id', 36)->nullable();
			$table->string('utility_id', 36)->nullable();
			$table->integer('rate_currency_id')->nullable();
			$table->integer('rate_uom_id')->nullable();
			$table->float('cancellation_fee', 10, 0)->nullable();
			$table->integer('external_rate_id')->nullable();
			$table->integer('program_code')->nullable();
			$table->float('rate_amount', 10, 0)->nullable();
			$table->string('dxc_rec_id', 11)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('rates');
	}

}
