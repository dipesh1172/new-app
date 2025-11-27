<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDobCollectionToStateConfig extends Migration
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
                $table->boolean('agent_collects_dob')->default(false);
                $table->boolean('request_dob')->default(false);
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
                $table->dropColumn('agent_collects_dob');
                $table->dropColumn('request_dob');
            }
        );
    }
}
