<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('eztpv_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('brand_id', 36);
            $table->string('sales_agent_id', 36);
            $table->string('uploads_id', 36);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('eztpv_contracts');
    }
}
