<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHttpPostConfigToVendors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->tinyInteger('hrtpv')->default(0);
            $table->tinyInteger('http_post')->default(0);
            $table->string('http_post_username', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('hrtpv');
            $table->dropColumn('http_post');
            $table->dropColumn('http_post_username');
        });
    }
}
