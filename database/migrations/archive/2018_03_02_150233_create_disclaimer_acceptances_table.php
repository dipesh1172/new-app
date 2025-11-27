<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDisclaimerAcceptancesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('disclaimer_acceptances', function(Blueprint $table)
		{
			$table->string('id', 36)->default('')->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('user_id', 36)->nullable();
			$table->string('disclaimer_source', 20)->nullable();
			$table->string('disclaimer_source_id', 36)->nullable();
			$table->string('disclaimer_id', 36)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('disclaimer_acceptances');
	}

}
