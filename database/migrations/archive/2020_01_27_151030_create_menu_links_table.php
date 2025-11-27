<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMenuLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('menu_links', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->timestamps();
            $table->softDeletes();
            $table->string('name', 100);
            $table->string('icon', 100)->nullable();
            $table->string('url')->nullable();
            $table->tinyInteger('position');
            $table->tinyInteger('parent_id')->nullable();
            $table->string('role_permissions', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('menu_links');
    }
}