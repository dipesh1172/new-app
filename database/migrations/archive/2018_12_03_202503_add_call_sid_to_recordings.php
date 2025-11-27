<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCallSidToRecordings extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('call_id', 48)->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn('call_id');
        });
    }
}
