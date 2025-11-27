<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandEnrollmentFilesLiveEnroll extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brand_enrollment_files',
            function (Blueprint $table) {
                $table->tinyInteger('live_enroll')->default(0);
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
            'brand_enrollment_files',
            function (Blueprint $table) {
                $table->dropColumn('live_enroll');
            }
        );
    }
}
