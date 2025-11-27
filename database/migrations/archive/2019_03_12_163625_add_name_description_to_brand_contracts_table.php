<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNameDescriptionToBrandContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_contracts', function (Blueprint $table) {
            $table->string('name', 128);
            $table->string('description', 128);
            $table->integer('status')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_contracts', function (Blueprint $table) {
            $table->string('name', 128);
            $table->string('description', 128);
            $table->integer('status')->change();
        });
    }
}
