<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'leads',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('brand_id', 36);
                $table->string('vendor_id', 36)->nullable();
                $table->integer('state_id')->nullable();
                $table->integer('channel_id')->nullable();
                $table->string('external_lead_id', 36)->nullable();
                $table->string('first_name', 128)->nullable();
                $table->string('last_name', 128)->nullable();
                $table->string('service_address1', 64)->nullable();
                $table->string('service_address2', 64)->nullable();
                $table->string('service_city', 32)->nullable();
                $table->string('service_state', 16)->nullable();
                $table->string('service_zip', 10)->nullable();
                $table->string('lead_campaign', 128)->nullable();
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
        Schema::dropIfExists('leads');
    }
}
