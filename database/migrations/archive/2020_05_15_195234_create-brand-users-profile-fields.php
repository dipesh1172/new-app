<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandUsersProfileFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_users_profile_fields', function (Blueprint $table) {
            $table->string('id', 36);
            $table->timestamps();
            $table->softDeletes();
            $table->string('section_id', 36);
            $table->integer('sort');
            $table->string('name');
            $table->string('desc');
            $table->string('type');
            $table->boolean('required');
            $table->text('properties')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_users_profile_fields');
    }
}
