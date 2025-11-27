<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventProductEnrollmentDate extends Migration
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
                $table->timestamp('enroll_date')->after('auth_relationship')->nullable();
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
                $table->dropColumn('enroll_date');
            }
        );
    }
}
