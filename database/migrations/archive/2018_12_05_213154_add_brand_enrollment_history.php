<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddBrandEnrollmentHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('upload_types')->insert(
            [
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'upload_type' => 'Enrollment File',
                ]
            ]
        );

        Schema::table(
            'log_enrollment_files',
            function (Blueprint $table) {
                $table->softDeletes();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('upload_id', 36)->nullable();
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
        //
    }
}
