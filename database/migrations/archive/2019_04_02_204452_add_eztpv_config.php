<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvConfig2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'time_clocks',
            function (Blueprint $table) {
                $table->timestamp('time_punch_actual')->nullable();
            }
        );

        Schema::create(
            'eztpv_config',
            function (Blueprint $table) {
                $table->string('id', 36)->primary()->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->string('office_id', 36)->nullable();
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
