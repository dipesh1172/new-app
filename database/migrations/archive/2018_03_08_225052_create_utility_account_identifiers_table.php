<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUtilityAccountIdentifiersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('utility_account_identifiers', function(Blueprint $table)
		{
			$table->string('id', 36)->default('')->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('utility_id', 36)->nullable();
			$table->integer('utility_account_type_id')->nullable();
			$table->string('validation_regex', 64)->nullable();
			$table->string('mask', 64)->nullable();
			$table->text('description', 65535)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('utility_account_identifiers');
	}

}
