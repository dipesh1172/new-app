<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEsiidTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('esiid_status', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->string('status');
        });

        DB::table('esiid_status')->insert([
            [
                'id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'status' => 'Active',
            ],
            [
                'id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
                'status' => 'De-Energized',
            ],
        ]);

        Schema::create('esiid_files', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->string('filename');
        });

        Schema::create('esiids', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('utility_supported_fuel_id', 36)->nullable();
            $table->string('esiid', 100)->nullable();
            $table->string('street_number', 32)->nullable();
            $table->string('address', 128)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('state', 4)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->tinyInteger('market_id')->nullable()->default(1);
            $table->tinyInteger('esiid_status_id')->nullable()->default(1);
        });

        Schema::table('esiids', function (Blueprint $table) {
            $table->index('esiid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
