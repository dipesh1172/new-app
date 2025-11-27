<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUtilitiesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('utilities', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes();
			$table->string('name', 64)->nullable();
			$table->string('ldc_code', 32)->nullable();
			$table->integer('state_id')->nullable();
			$table->string('customer_service', 16)->nullable();
			$table->integer('utility_type_id')->nullable();
			$table->string('disclosure_document', 64)->nullable();
			$table->string('discount_program', 128)->nullable();
			$table->string('duns', 32)->nullable();
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
		Schema::drop('utilities');
	}

}
