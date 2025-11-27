<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeEventIdNullable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'event_alerts',
            function (Blueprint $table) {
                $table->string('event_id', 36)->nullable()->change();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
