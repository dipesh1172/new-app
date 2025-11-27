<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('users', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('first_name', 64)->nullable();
			$table->string('middle_name', 36)->nullable();
			$table->string('last_name', 64)->nullable();
			$table->string('username', 64)->nullable();
			$table->string('password', 128)->nullable();
			$table->string('email', 128)->nullable();
			$table->string('manager_id', 36)->nullable();
			$table->string('remember_token', 64)->nullable();
			$table->string('staff_token', 32)->nullable();
			$table->string('avatar', 36)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('users');
	}

}
