<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAuditsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('audits', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('user_id', 36)->nullable();
			$table->string('event')->nullable();
			$table->string('auditable_id', 36)->nullable();
			$table->string('auditable_type')->nullable();
			$table->text('old_values', 65535)->nullable();
			$table->text('new_values', 65535)->nullable();
			$table->text('url', 65535)->nullable();
			$table->string('ip_address', 45)->nullable();
			$table->string('user_agent')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('audits');
	}

}
