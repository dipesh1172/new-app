<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEnrollmentFile;

class UpdateEnrollmentFilesForPassfailRename extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        BrandEnrollmentFile::all()->each(function ($enrollmentFile) {
            $old = $enrollmentFile->report_fields;
            $new = str_replace('fail_reason', 'passfail_reason', $old);
            $enrollmentFile->report_fields = $new;
            $enrollmentFile->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        BrandEnrollmentFile::all()->each(function ($enrollmentFile) {
            $old = $enrollmentFile->report_fields;
            $new = str_replace('passfail_reason', 'fail_reason', $old);
            $enrollmentFile->report_fields = $new;
            $enrollmentFile->save();
        });
    }
}
