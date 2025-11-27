<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyEztpvJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('eztpv_jobs', function (Blueprint $table) {
            $table->text('batch_data')->default(null)->change();
            $table->dateTime('central_end_time')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('eztpv_jobs', function (Blueprint $table) {
            $table->text('batch_data')->default(false)->change();
            $table->dateTime('central_end_time')->nullable(false)->default(false)->change();
        });
    }
}
