<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOfficesEztpv2Options extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'offices',
            function (Blueprint $table) {
                $table->tinyInteger('contract')->default(0)->nullable();
                $table->tinyInteger('photo')->default(0)->nullable();
                $table->tinyInteger('live_call')->default(0)->nullable();
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
        Schema::table(
            'offices',
            function (Blueprint $table) {
                $table->dropColumn('contract');
                $table->dropColumn('photo');
                $table->dropColumn('live_call');
            }
        );
    }
}
