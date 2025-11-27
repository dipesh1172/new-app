<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateZipsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('zips', function(Blueprint $table)
		{
			$table->string('zip', 5)->primary();
			$table->string('city', 35)->nullable();
			$table->string('state', 2)->nullable();
			$table->decimal('lat', 10)->nullable();
			$table->decimal('lon', 10)->nullable();
			$table->integer('timezone')->nullable();
			$table->integer('dst')->nullable();
		});

		DB::unprepared(File::get('files/sql/zips.sql'));
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('zips');
	}

}
