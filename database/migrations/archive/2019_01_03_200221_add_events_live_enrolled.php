<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventsLiveEnrolled extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'event_product',
            function (Blueprint $table) {
                $table->tinyInteger('live_enroll')->nullable();
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
            'event_product',
            function (Blueprint $table) {
                $table->dropColumn('live_enroll');
            }
        );
    }
}
