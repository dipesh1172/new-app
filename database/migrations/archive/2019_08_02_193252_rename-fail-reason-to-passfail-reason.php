<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameFailReasonToPassfailReason extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            $table->renameColumn('fail_reason', 'passfail_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            $table->renameColumn('passfail_reason', 'fail_reason');
        });
    }
}
