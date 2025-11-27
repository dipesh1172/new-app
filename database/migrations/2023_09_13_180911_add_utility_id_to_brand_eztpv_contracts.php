<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUtilityIdToBrandEztpvContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->string('utility_id', 36)->after('commodity')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->dropColumn('utility_id');
        });
    }
}
