<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateServiceLoginsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('service_logins', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('tpv_staff_id', 36)->nullable();
			$table->integer('service_type_id')->nullable();
			$table->string('username', 128)->nullable();
			$table->string('password', 128)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('service_logins');
	}

}
