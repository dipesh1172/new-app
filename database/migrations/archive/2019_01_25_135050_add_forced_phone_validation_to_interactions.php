<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddForcedPhoneValidationToInteractions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'interactions',
            function (Blueprint $table) {
                $table->boolean('forced_phone_validation')->default(0);
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
            'interactions',
            function (Blueprint $table) {
                $table->dropColumn('forced_phone_validation');
            }
        );
    }
}
