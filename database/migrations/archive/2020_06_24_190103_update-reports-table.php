<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['report_type', 'report_parameters', 'report_data', 'user_id', 'mail_to', 'data_mime_type']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reports', function (Blueprint $table) {
            // There is no down, only Zuul!
        });
    }
}
