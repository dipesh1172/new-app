<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDistanceTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gps_distance_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('distance_type', 30);

            $table->index(['id', 'distance_type']);
        });

        DB::table('gps_distance_types')->insert([
            ['id' => 1, 'distance_type' => 'ServAddr -> SalesAgent'],
            ['id' => 2, 'distance_type' => 'ServAddr -> CustSig'],
            ['id' => 3, 'distance_type' => 'ServAddr -> CustDigital'],
            ['id' => 4, 'distance_type' => 'SalesAgent -> Customer'],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gps_distance_types');
    }
}
