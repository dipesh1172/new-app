<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatsLanguageId extends Migration
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
                $table->integer('language_id')
                    ->nullable()->after('eztpv_initiated');
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
                $table->dropColumn('language_id');
            }
        );
    }
}
