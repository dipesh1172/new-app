<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddApiTypeToGpsCoordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gps_coords', function (Blueprint $table) {
            // Nullable, will start with: ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE, append with more info
            $table->string('api_type', 255)->nullable();
            // JSON
            $table->longtext('api_response')->nullable();

            $table->index('api_type', 'idx_api_type');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gps_coords', function (Blueprint $table) {
            $table->dropColumn('api_type');
            $table->dropColumn('api_response');

            $table->dropIndex('idx_api_type');
        });
    }
}
