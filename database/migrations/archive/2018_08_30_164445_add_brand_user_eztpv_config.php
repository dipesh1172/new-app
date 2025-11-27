<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandUserEztpvConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'brand_user_eztpv_config', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('brand_user_id', 36)->nullable();
                $table->text('config')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_user_eztpv_config');
    }
}
