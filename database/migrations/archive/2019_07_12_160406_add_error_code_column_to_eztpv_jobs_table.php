<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddErrorCodeColumnToEztpvJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('eztpv_jobs', function (Blueprint $table) {
            $table->text('error_code')->nullable()->default(null);
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
            $table->dropColumn('error_code');
        });
    }
}
