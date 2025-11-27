<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRateHistoryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('rate_history', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('brand_id', 36)->nullable();
			$table->string('replaced_with_id', 36)->nullable();
			$table->string('import_from_id', 36)->nullable();
			$table->string('import_to_id', 36)->nullable();
			$table->string('name', 128)->nullable();
			$table->integer('rate_currency_id')->nullable();
			$table->integer('rate_uom_id')->nullable();
			$table->integer('rate_type_id')->nullable();
			$table->float('service_fee', 10, 0)->nullable();
			$table->float('cancellation_fee', 10, 0)->nullable();
			$table->integer('green_percentage')->nullable();
			$table->integer('term')->nullable();
			$table->integer('term_type_id')->nullable();
			$table->integer('rate_id')->nullable();
			$table->integer('program_code')->nullable();
			$table->float('rate_amount', 10, 0)->nullable();
			$table->integer('active')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('rate_history');
	}

}
