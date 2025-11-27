<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBrandStatesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('brand_states', function(Blueprint $table)
		{
			$table->string('id', 36)->default('')->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('brand_id', 36)->nullable();
			$table->integer('state_id')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('brand_states');
	}

}
