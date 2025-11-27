<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFileFormatIdColumnToBrandEnrollmentFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_enrollment_files', function (Blueprint $table) {
            $table->integer('file_format_id')
                ->default(1)
                ->after('report_fields');
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
            $table->dropColumn('file_format_id');
        });
    }
}
