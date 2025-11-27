<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvVendorConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'vendor_eztpv_config', 
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('vendor_id', 36);
                $table->integer('state_id')->nullable();
                $table->tinyInteger('eztpv')->default(0);
                $table->tinyInteger('contracts')->default(0);
                $table->tinyInteger('photo')->default(0);
                $table->tinyInteger('eztpv_live_call')->default(0);
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
        Schema::dropIfExists('vendor_eztpv_config');
    }
}
