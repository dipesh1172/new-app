<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddApiToken extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'tpv_staff', function (Blueprint $table) {
                $table->string('api_token', 128)->nullable()->default(null);
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
            'tpv_staff', function (Blueprint $table) {
                $table->dropColumn('api_token');
            }
        );
    }
}
