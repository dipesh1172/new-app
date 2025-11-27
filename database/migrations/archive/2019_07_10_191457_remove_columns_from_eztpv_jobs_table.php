<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveColumnsFromEztpvJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('eztpv_jobs', function (Blueprint $table) {
            $table->dropColumn('record_type');
            $table->dropColumn('parent_id');
            $table->dropColumn('batch_data');
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
            $table->string('record_type');
            $table->integer('parent_id')->nullable();
            $table->text('batch_data')->nullable()->default(null);
        });
    }
}
