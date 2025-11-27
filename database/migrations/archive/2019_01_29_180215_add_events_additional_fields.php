<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventsAdditionalFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'events',
            function (Blueprint $table) {
                $table->string('external_id', 64)->after('eztpv_id')->nullable();
                $table->string('lead_id', 64)->after('external_id')->nullable();
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
            'events',
            function (Blueprint $table) {
                $table->dropColumn('external_id');
                $table->dropColumn('lead_id');
            }
        );
    }
}
