<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCallReviewTypeToEventFlags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_flags', function (Blueprint $table) {
            $table->integer('call_review_type_id')->nullable();
            $table->string('reviewed_by', 36)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_flags', function (Blueprint $table) {
            $table->dropColumn('call_review_type_id');
            $table->dropColumn('reviewed_by');
        });
    }
}
