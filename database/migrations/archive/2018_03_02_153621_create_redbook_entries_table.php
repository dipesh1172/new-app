<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRedbookEntriesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('redbook_entries', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('keyword', 191)->unique();
			$table->string('url', 191);
			$table->boolean('visibleOnIndex')->default(0);
			$table->timestamps();
			$table->softDeletes();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('redbook_entries');
	}

}
