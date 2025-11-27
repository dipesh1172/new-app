<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHasoutboundToBrandStates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brand_states', function (Blueprint $table) {
                $table->tinyInteger('hasoutbound')->default(0);
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
            'brand_states', function (Blueprint $table) {
                $table->dropColumn('hasoutbound');
            }
        );
    }
}
