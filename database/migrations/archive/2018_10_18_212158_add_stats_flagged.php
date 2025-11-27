<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatsFlagged extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->string('flagged_reason', 255)
                    ->nullable()->after('result');
                $table->string('flagged_by', 128)
                    ->nullable()->after('flagged_reason');
                $table->string('flagged_by_label', 64)
                    ->nullable()->after('flagged_by');
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->dropColumn('flagged_reason');
                $table->dropColumn('flagged_by');
                $table->dropColumn('flagged_by_label');
            }
        );
    }
}
