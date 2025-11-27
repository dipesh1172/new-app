<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateKbEntryVersionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('kb_entry_versions', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->integer('kb_id');
			$table->integer('version');
			$table->string('title', 191);
			$table->text('content', 65535);
			$table->string('author', 36);
			$table->integer('category')->nullable();
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
		Schema::drop('kb_entry_versions');
	}

}
