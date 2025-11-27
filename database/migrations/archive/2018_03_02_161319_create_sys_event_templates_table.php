<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSysEventTemplatesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('sys_event_templates', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->string('name', 191);
			$table->text('template_content', 65535);
			$table->boolean('enabled')->default(1);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('sys_event_templates');
	}

}
