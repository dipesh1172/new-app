<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandUserOfficesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_user_offices', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->string('brand_user_id');
            $table->string('office_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_user_offices');
    }
}
