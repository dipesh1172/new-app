<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RecreateBrandContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('brand_contacts');
        Schema::create('brand_contacts', function(Blueprint $table)
        {
            $table->string('id', 36)->default('')->primary();
            $table->string('brand_id', 36)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('title', 36)->nullable();
            $table->string('email', 128)->nullable();
            $table->string('phone', 16)->nullable();
            $table->integer('phone_number_label_id')->nullable();
            $table->integer('brand_contact_type_id')->nullable();
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
        Schema::dropIfExists('brand_contacts');
        Schema::create('brand_contacts', function(Blueprint $table)
        {
            $table->string('id', 36)->default('')->primary();
            $table->string('brand_id', 36)->nullable();
            $table->string('user_id', 36)->nullable();
            $table->integer('brand_contact_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
