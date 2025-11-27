<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddSurveyImportToUploadTypes extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('upload_types')->insert(['created_at' => $now, 'updated_at' => $now, 'upload_type' => 'Survey Import']);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::table('upload_types')->where('upload_type', 'Survey Import')->delete();
    }
}
