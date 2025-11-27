<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvStaffTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_staff', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->dateTime('hire_date')->nullable();
			$table->string('first_name', 64)->nullable();
			$table->string('middle_name', 16)->nullable();
			$table->string('last_name', 64)->nullable();
			$table->string('username', 64)->nullable();
			$table->string('email')->nullable();
			$table->string('password', 64)->nullable();
			$table->string('phone', 36)->nullable();
			$table->string('client_login', 36)->nullable();
			$table->integer('call_center_id')->unsigned()->nullable()->index('idx_tpv_staff_call_center_id');
			$table->integer('language_id')->unsigned()->nullable()->index('idx_tpv_staff_language_id');
			$table->integer('role_id')->unsigned()->nullable();
			$table->string('remember_token', 64)->nullable();
			$table->integer('status')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_staff');
	}

}
