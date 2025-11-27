<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvsPreProcessing extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'eztpvs',
            function (Blueprint $table) {
                $table->tinyInteger('pre_processing')
                    ->default(0)->after('signature');
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
            'eztpvs',
            function (Blueprint $table) {
                $table->dropColumn('pre_processing');
            }
        );
    }
}
