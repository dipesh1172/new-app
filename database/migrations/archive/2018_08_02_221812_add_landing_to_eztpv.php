<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLandingToEztpv extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'eztpvs', 
            function (Blueprint $table) {
                $table->timestamp('landing_accessed')->nullable();
                $table->integer('ip_addr')->nullable();
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
            'eztpvs', 
            function (Blueprint $table) {
                $table->dropColumn('landing_accessed');
                $table->dropColumn('ip_addr');
            }
        );
    }
}
