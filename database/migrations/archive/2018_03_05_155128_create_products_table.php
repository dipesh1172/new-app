<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProductsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('products', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('brand_id', 36)->nullable();
			$table->string('name', 128)->nullable();
			$table->string('channel', 64)->nullable();
			$table->string('market', 64)->nullable();
			$table->string('home_type', 64)->nullable();
			$table->integer('rate_type_id')->nullable();
			$table->integer('green_percentage')->nullable();
			$table->integer('term')->nullable();
			$table->integer('term_type_id')->nullable();
			$table->float('service_fee', 10, 0)->nullable();
			$table->float('daily_fee', 10, 0)->nullable();
			$table->integer('prepaid')->nullable()->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('products');
	}

}
