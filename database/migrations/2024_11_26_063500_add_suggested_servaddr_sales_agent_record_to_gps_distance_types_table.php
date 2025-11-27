<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSuggestedServaddrSalesAgentRecordToGpsDistanceTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gps_distance_types', function (Blueprint $table) {
            DB::table('gps_distance_types')->insert([
                'distance_type' => 'Suggested ServAddr -> SalesAgent'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gps_distance_types', function (Blueprint $table) {
            DB::table('gps_distance_types')->where('distance_type', '=', 'Suggested ServAddr -> SalesAgent')->delete();
        });
    }
}
