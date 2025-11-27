<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBrandUsersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('brand_users', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('employee_of_id', 36);
			$table->string('works_for_id', 36);
			$table->string('user_id', 36);
			$table->string('tsr_id', 16)->nullable();
			$table->integer('role_id')->nullable()->index('idx_brand_users_role_id');
			$table->string('office_id', 36)->nullable();
			$table->string('hireflow_id', 36)->nullable();
			$table->integer('status')->nullable()->default(1)->index('idx_brand_users_status');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('brand_users');
	}

}
