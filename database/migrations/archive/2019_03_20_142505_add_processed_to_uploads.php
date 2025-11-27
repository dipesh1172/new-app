<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProcessedToUploads extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->boolean('processing')->default(0);
            $table->dateTime('processed_at')->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn(['processing', 'processed_at']);
        });
    }
}
