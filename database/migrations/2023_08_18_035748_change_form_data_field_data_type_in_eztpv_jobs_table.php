<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeFormDataFieldDataTypeInEztpvJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('eztpv_jobs', function (Blueprint $table) {
            $table->mediumText('form_data')->nullable()->change();
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
            $table->text('form_data')->nullable()->change();
        });
    }
}
