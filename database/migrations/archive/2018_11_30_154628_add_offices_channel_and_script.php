<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOfficesChannelAndScript extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'offices',
            function (Blueprint $table) {
                $table->integer('channel_id')->nullable();
                $table->string('script_id', 36)->nullable();
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
            'offices',
            function (Blueprint $table) {
                $table->dropColumn('channel_id');
                $table->dropColumn('script_id');                
            }
        );
    }
}
