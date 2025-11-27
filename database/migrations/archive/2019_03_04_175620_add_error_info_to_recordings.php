<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddErrorInfoToRecordings extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'recordings',
            function (Blueprint $table) {
                $table->string('remote_status')->nullable();
                $table->string('remote_error_code')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'recordings',
            function (Blueprint $table) {
                $table->dropColumn('remote_status');
                $table->dropColumn('remote_error_code');
            }
        );
    }
}
