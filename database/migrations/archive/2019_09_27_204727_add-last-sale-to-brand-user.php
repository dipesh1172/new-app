<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLastSaleToBrandUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_users', function (Blueprint $table) {
            $table->dateTime('last_sale_date')->nullable();
            $table->string('last_sale_id', 36)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_users', function (Blueprint $table) {
            $table->dropColumn(['last_sale_date', 'last_sale_id']);
        });
    }
}
