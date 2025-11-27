<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateKbAlertsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('kb_alerts', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes();
			$table->string('applies_to', 191)->nullable();
			$table->integer('icon')->default(1);
			$table->text('message', 65535);
			$table->dateTime('expires_at')->nullable();
			$table->integer('priority')->default(5);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('kb_alerts');
	}

}
