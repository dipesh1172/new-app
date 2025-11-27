<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeRunHistoryFieldToNullableInBrandEnrollmentFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_enrollment_files', function (Blueprint $table) {
            $table->text('run_history')->nullable()->change();
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
            $table->text('run_history')->change();
        });
    }
}
