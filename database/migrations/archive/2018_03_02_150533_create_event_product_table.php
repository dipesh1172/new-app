<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEventProductTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('event_product', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('event_id', 36);
			$table->integer('event_type_id')->nullable();
			$table->integer('market_id')->nullable();
			$table->integer('home_type_id')->nullable();
			$table->string('company_name', 64)->nullable();
			$table->string('bill_first_name', 36)->nullable();
			$table->string('bill_middle_name', 36)->nullable();
			$table->string('bill_last_name', 36)->nullable();
			$table->string('bill_address_id', 36)->nullable();
			$table->string('auth_first_name', 36)->nullable();
			$table->string('auth_middle_name', 36)->nullable();
			$table->string('auth_last_name', 36)->nullable();
			$table->string('auth_relationship', 36)->nullable();
			$table->string('rate_id', 36)->nullable();
			$table->string('service_address_id', 36)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('event_product');
	}

}
