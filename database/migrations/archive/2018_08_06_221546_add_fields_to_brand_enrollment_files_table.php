<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldsToBrandEnrollmentFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_enrollment_files', function (Blueprint $table) {
            $table->dateTime('next_run');
            $table->dateTime('last_run');
            $table->text('run_history')->nullable();
            $table->text('delivery_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_enrollment_files', function (Blueprint $table) {
            $table->dropColumn('next_run');
            $table->dropColumn('last_run');
            $table->dropColumn('run_history');
            $rate->dropColumn('delivery_data');
        });
    }
}
