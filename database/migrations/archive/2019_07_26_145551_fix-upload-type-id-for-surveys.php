<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class FixUploadTypeIdForSurveys extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $correctType = DB::table('upload_types')->where('upload_type', 'Survey Import')->first();
        if ($correctType->id !== 7) {
            DB::table('uploads')
                ->where('upload_type_id', 7)
                ->where('filename', 'LIKE', 'uploads/survey-import%')
                ->update(
                    [
                        'upload_type_id' => $correctType->id,
                    ]
                );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // there is no down, only Zuul!
    }
}
