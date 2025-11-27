<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangePassFailDefault extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function ($table) {
            $table->boolean('pass_fail')->default(1)->change();
        });

        Schema::table('stats_product', function ($table) {
            $table->boolean('pass_fail')->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function ($table) {
            $table->boolean('pass_fail')->default(0)->change();
        });

        Schema::table('stats_product', function ($table) {
            $table->boolean('pass_fail')->default(0)->change();
        });
    }
}
