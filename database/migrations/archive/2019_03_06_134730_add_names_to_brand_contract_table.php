<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNamesToBrandContractTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // these changes have been entered manuall
        /* Schema::table('brand_contracts', function (Blueprint $table) {
            $table->string('name', 128);
            $table->string('description', 128);
            $table->integer('status', 11)->change();
            $table->string('signed_filename')->nullable();
        }); */
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // there is no down, only Zuul
    }
}
