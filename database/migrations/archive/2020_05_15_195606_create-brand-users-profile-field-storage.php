<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandUsersProfileFieldStorage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_users_profile_field_storage', function (Blueprint $table) {
            $table->string('id', 36);
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_user_id', 36);
            $table->string('field_id', 36);
            $table->text('value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_users_profile_field_storage');
    }
}
