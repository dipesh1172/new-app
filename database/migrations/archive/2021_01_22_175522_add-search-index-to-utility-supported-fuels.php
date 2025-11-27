<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSearchIndexToUtilitySupportedFuels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('utility_supported_fuels', function (Blueprint $table) {
            $table->index([
                'utility_id',
                'utility_fuel_type_id',
            ], 'usf_idx_utility_search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('utility_supported_fuels', function (Blueprint $table) {
            $table->dropIndex('usf_idx_utility_search');
        });
    }
}
