<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUtilIdentifiersToBrandUtilities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brand_utilities', function (Blueprint $table) {
                $table->string('utility_external_id', 64)->nullable();
                $table->string('commodity', 64)->nullable();
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
            'brand_utilities', function (Blueprint $table) {
                $table->dropColumn('utility_external_id');
                $table->dropColumn('commodity');
            }
        );
    }
}
