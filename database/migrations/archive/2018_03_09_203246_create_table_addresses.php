<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableAddresses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addresses', function(Blueprint $table)
        {
            $table->string('id', 36)->default('')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('line_1', 255)->nullable();
            $table->string('line_2', 255)->nullable();
            $table->string('line_3', 255)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('state_province', 16)->nullable();
            $table->string('zip', 16)->nullable();
            $table->integer('country_id');
            $table->string('other_details', 255)->nullable();
            $table->boolean('validated')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
