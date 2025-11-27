<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPassFailColumnsToEventProduct extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_product', function (Blueprint $table) {
            $table->boolean('pass_fail')->default(1);
            $table->string('fail_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_product', function (Blueprint $table) {
            $table->dropColumn('pass_fail');
            $table->dropColumn('fail_reason');
        });
    }
}
