<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldToLeadsAndIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('external_lead_id2', 36)->after('external_lead_id')->nullable();
            $table->index('external_lead_id');
            $table->index('external_lead_id2');
        });

        Schema::table('brand_user_offices', function (Blueprint $table) {
            $table->index('brand_user_id');
            $table->index('office_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    { }
}
