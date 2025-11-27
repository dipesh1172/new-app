<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDispositionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('dispositions', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->integer('disposition_category_id')->nullable();
			$table->string('brand_id', 36)->nullable();
			$table->string('brand_label', 24)->nullable();
			$table->string('reason', 253)->nullable();
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
		Schema::drop('dispositions');
	}

}
