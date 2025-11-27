<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTerms extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('terms_and_conditions', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36)->nullable();
            $table->string('channels', 128)->nullable();
            $table->string('states', 128)->nullable();
            $table->string('markets', 128)->nullable();
            $table->integer('language_id')->nullable();
            $table->integer('rate_type_id')->nullable();
            $table->longText('toc')->nullable();
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
