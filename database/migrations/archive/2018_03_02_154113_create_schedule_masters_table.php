<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateScheduleMastersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('schedule_masters', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->softDeletes();
			$table->dateTime('published_at')->nullable();
			$table->integer('published_by')->nullable();
			$table->integer('created_by');
			$table->integer('year')->default(2017);
			$table->integer('month')->default(1);
			$table->integer('day')->default(1);
			$table->text('comments', 65535)->nullable();
			$table->integer('role_id')->nullable()->default(1);
			$table->integer('dept_id')->nullable()->default(1);
			$table->boolean('invert_role')->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('schedule_masters');
	}

}
