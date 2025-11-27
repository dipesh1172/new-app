<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddChannelSpecificOfficeEztpvConfig extends Migration
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
                $table->tinyInteger('eztpv_config')->after('grp_id')->default(0);
            }
        );

        Schema::create(
            'office_eztpv',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('office_id', 36)->nullable();
                $table->integer('channel_id')->nullable();
                $table->text('config');
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
        //
    }
}
