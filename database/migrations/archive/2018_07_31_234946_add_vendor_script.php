<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVendorScript extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'vendor_scripts',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('vendor_id', 36);
                $table->string('script_id', 36)->nullable();
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
        Schema::dropIfExists('vendor_scripts');
    }
}
